<?php

namespace App\Controllers;

use Core\Config;
use Core\Database;
use Utils\Helper;
use Utils\PromptTemplateService;
use DateTimeImmutable;

class ChatController
{
    private string $knowledgeServiceUrl;
    private string $llmApiUrl;
    private string $llmApiKey;
    private string $llmModel;
    private int $timeout = 120;
    private ?array $resolvedChildProfile = null;
    private ?PromptTemplateService $promptTemplateService = null;

    public function __construct()
    {
        $host = Config::get('CHROMA_SERVICE_HOST', '127.0.0.1');
        $port = Config::get('CHROMA_SERVICE_PORT', '4001');

        $this->knowledgeServiceUrl = "http://{$host}:{$port}";
        $this->llmApiUrl = trim((string) Config::get('LLM_API_URL', 'https://api.deepseek.com/v1/chat/completions'));
        $this->llmApiKey = trim((string) Config::get('LLM_API_KEY', ''));
        $this->llmModel = trim((string) Config::get('LLM_MODEL', 'deepseek-chat'));
        if ($this->llmApiUrl === '') {
            $this->llmApiUrl = 'https://api.deepseek.com/v1/chat/completions';
        }
        if ($this->llmModel === '') {
            $this->llmModel = 'deepseek-chat';
        }
    }

    public function reply(): void
    {
        $this->startStream();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendStreamError('Method not allowed', 405);
            return;
        }

        if (!$this->verifyCsrfToken()) {
            $this->sendStreamError('Invalid CSRF token', 403);
            return;
        }

        $input = $this->getJsonInput();
        $messages = $this->sanitizeMessages($input['messages'] ?? []);

        if (empty($messages)) {
            $this->sendStreamError('Messages are required', 400);
            return;
        }

        $userMessage = trim((string)($input['user_message'] ?? $this->getLatestUserMessage($messages)));
        $childProfile = $this->resolveChildProfile($input);
        $childAgeBand = $childProfile['age_band'] ?? null;
        $knowledgeCommand = $this->parseKnowledgeCommand($userMessage);

        if ($knowledgeCommand !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $replyLanguage = $this->detectReplyLanguage($userMessage);

            if ($knowledgeCommand['query'] === '') {
                $this->streamTextResponse(
                    $replyLanguage === 'zh'
                        ? "知识库命令格式：/search [limit] 你的问题\n示例：/search 5 儿童睡眠建议"
                        : "Knowledge base command format: /search [limit] your question\nExample: /search 5 children sleep advice"
                );
                return;
            }

            $searchResult = $this->searchKnowledge(
                $knowledgeCommand['query'],
                $knowledgeCommand['limit'],
                'child',
                $childAgeBand
            );
            $this->streamTextResponse(
                $this->formatKnowledgeSearchResponse(
                    (string)($searchResult['query'] ?? $knowledgeCommand['query']),
                    $searchResult,
                    $replyLanguage
                )
            );
            return;
        }

        if ($this->llmApiKey === '') {
            $this->sendStreamError('LLM API key is not configured', 500);
            return;
        }

        $knowledge = $userMessage !== ''
            ? $this->fetchKnowledgeContext($userMessage, 3, 'child', $childAgeBand)
            : ['context' => '', 'sources' => []];
        $messagesForModel = $this->buildModelMessages($messages, $knowledge, $userMessage, $childProfile);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->streamModelResponse($messagesForModel);
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function verifyCsrfToken(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return Helper::verifyCsrfToken($token);
    }

    private function sanitizeMessages(array $messages): array
    {
        $allowedRoles = ['system', 'user', 'assistant'];
        $sanitized = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $role = $message['role'] ?? null;
            $content = isset($message['content']) ? trim((string)$message['content']) : '';

            if (!in_array($role, $allowedRoles, true) || $content === '') {
                continue;
            }

            $sanitized[] = [
                'role' => $role,
                'content' => $content
            ];
        }

        return $sanitized;
    }

    private function getLatestUserMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? null) === 'user') {
                return $messages[$i]['content'] ?? '';
            }
        }

        return '';
    }

    private function parseKnowledgeCommand(string $message): ?array
    {
        $trimmed = trim($message);
        if ($trimmed === '' || !preg_match('/^\/search\b/i', $trimmed)) {
            return null;
        }

        $args = trim(substr($trimmed, 7));
        if (str_starts_with($args, ':')) {
            $args = trim(substr($args, 1));
        }

        $limit = 5;
        if (preg_match('/^(\d{1,2})\s+(.+)$/u', $args, $matches)) {
            $limit = max(1, min((int)$matches[1], 10));
            $args = trim($matches[2]);
        }

        return [
            'query' => $args,
            'limit' => $limit
        ];
    }

    private function searchKnowledge(
        string $query,
        int $limit = 5,
        string $sessionType = 'child',
        ?string $ageBand = null
    ): array {
        $safeLimit = max(1, min($limit, 10));
        $params = [
            'query' => $query,
            'limit' => $safeLimit,
            'session_type' => $sessionType,
            'include_filtered' => 'true',
        ];

        if ($ageBand) {
            $params['age_band'] = $ageBand;
        }

        $decoded = $this->executeKnowledgeSearch($params);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Knowledge base service is temporarily unavailable.',
                'results' => []
            ];
        }

        // Retry with a corrected query for common misspellings.
        $correctedQuery = $this->normalizeKnowledgeQuery($query);
        if (empty($decoded['results']) && $correctedQuery !== $query) {
            $correctedParams = $params;
            $correctedParams['query'] = $correctedQuery;
            $corrected = $this->executeKnowledgeSearch($correctedParams);
            if (is_array($corrected) && !empty($corrected['results'])) {
                $corrected['query_corrected_from'] = $query;
                return $corrected;
            }
            if (is_array($corrected) && empty($decoded['filtered_out']) && !empty($corrected['filtered_out'])) {
                $decoded = $corrected;
            }
        }

        // Relaxed fallback: keep session/visibility safety filters, but allow closest low-confidence items.
        if (empty($decoded['results'])) {
            $fallbackResults = $this->buildRelaxedFallbackResults($decoded, $safeLimit);
            if (!empty($fallbackResults)) {
                $decoded['results'] = $fallbackResults;
                $decoded['reliable'] = false;
                $decoded['message'] = 'No strict match found. Showing the closest available results.';
                $decoded['no_result_reason'] = 'closest_match_fallback';
            }
        }

        return $decoded;
    }

    private function executeKnowledgeSearch(array $params): ?array
    {
        $url = $this->knowledgeServiceUrl . '/api/search?' . http_build_query($params);
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || !$response) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function normalizeKnowledgeQuery(string $query): string
    {
        $fixed = preg_replace('/\bchilden\b/i', 'children', $query);
        $fixed = preg_replace('/\bchildern\b/i', 'children', $fixed ?? $query);
        $fixed = preg_replace('/\bteh\b/i', 'the', $fixed ?? $query);
        return trim((string)($fixed ?? $query));
    }

    private function buildRelaxedFallbackResults(array $searchResult, int $limit): array
    {
        $filtered = is_array($searchResult['filtered_out'] ?? null) ? $searchResult['filtered_out'] : [];
        if (empty($filtered)) {
            return [];
        }

        $blockedReasons = [
            'document_disabled',
            'chunk_retrieval_disabled',
            'visibility_not_child_safe',
            'visibility_not_parent_allowed',
            'visibility_not_system_allowed',
            'age_band_mismatch',
            'invalid_session_type',
        ];

        $candidates = [];
        foreach ($filtered as $item) {
            if (!is_array($item)) {
                continue;
            }

            $reasons = is_array($item['reasons'] ?? null) ? $item['reasons'] : [];
            $hasBlockedReason = false;
            foreach ($reasons as $reason) {
                if (in_array((string)$reason, $blockedReasons, true)) {
                    $hasBlockedReason = true;
                    break;
                }
            }
            if ($hasBlockedReason) {
                continue;
            }

            $document = trim((string)($item['document'] ?? ''));
            if ($document === '') {
                continue;
            }

            $score = (float)($item['score'] ?? 0.0);
            $distance = (float)($item['distance'] ?? 1.5);
            if ($score < 0.20 && $distance > 0.95) {
                continue;
            }

            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $matchSignals = is_array($item['match_signals'] ?? null) ? $item['match_signals'] : [];

            $candidates[] = [
                'document' => $document,
                'metadata' => $metadata,
                'distance' => $distance,
                'score' => $score,
                'reliable' => false,
                'passed_relevance_threshold' => false,
                'reason' => 'closest semantic match',
                'match_signals' => $matchSignals,
            ];
        }

        if (empty($candidates)) {
            return [];
        }

        usort($candidates, static function (array $a, array $b): int {
            $scoreDiff = (float)$b['score'] <=> (float)$a['score'];
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            return (float)$a['distance'] <=> (float)$b['distance'];
        });

        return array_slice($candidates, 0, max(1, min($limit, 10)));
    }

    private function formatKnowledgeSearchResponse(string $query, array $searchResult, string $replyLanguage = 'auto'): string
    {
        $results = is_array($searchResult['results'] ?? null) ? $searchResult['results'] : [];
        $replyLanguage = in_array($replyLanguage, ['zh', 'en'], true) ? $replyLanguage : $this->detectReplyLanguage($query);
        $isChinese = $replyLanguage === 'zh';
        $header = $isChinese
            ? "【知识库直查】\n查询：{$query}"
            : "[Knowledge Base Direct Search]\nQuery: {$query}";

        if (empty($results)) {
            $message = trim((string)($searchResult['message'] ?? ($isChinese
                ? '当前知识库没有找到可靠匹配结果。'
                : 'No reliable matches were found in the knowledge base.')));
            return $header . "\n" . $message;
        }

        $lines = [$header];
        $isReliable = (bool)($searchResult['reliable'] ?? false);
        if (!$isReliable) {
            $lines[] = $isChinese
                ? '注：以下为最接近结果（低置信度），请结合实际判断。'
                : 'Note: the following are closest matches (low confidence).';
        }

        if (!empty($searchResult['query_corrected_from'])) {
            $fromQuery = trim((string)$searchResult['query_corrected_from']);
            if ($fromQuery !== '') {
                $lines[] = $isChinese
                    ? "已按拼写纠正继续检索：{$fromQuery} -> {$query}"
                    : "Searched with spelling correction: {$fromQuery} -> {$query}";
            }
        }

        foreach ($results as $index => $item) {
            $rank = $index + 1;
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $title = trim((string)($metadata['title'] ?? $metadata['original_filename'] ?? 'Unknown'));
            $document = trim((string)($item['document'] ?? ''));
            $snippet = preg_replace('/\s+/u', ' ', $document) ?: '';
            if (function_exists('mb_substr') && mb_strlen($snippet, 'UTF-8') > 180) {
                $snippet = mb_substr($snippet, 0, 180, 'UTF-8') . '...';
            } elseif (strlen($snippet) > 180) {
                $snippet = substr($snippet, 0, 180) . '...';
            }

            $lines[] = '';
            $lines[] = "{$rank}. {$title}";
            $lines[] = $isChinese ? "片段：{$snippet}" : "Snippet: {$snippet}";
        }

        return implode("\n", $lines);
    }

    private function fetchKnowledgeContext(
        string $query,
        int $limit = 3,
        string $sessionType = 'child',
        ?string $ageBand = null
    ): array
    {
        $url = $this->knowledgeServiceUrl
            . '/api/context?query=' . urlencode($query)
            . '&limit=' . $limit
            . '&session_type=' . urlencode($sessionType);

        if ($ageBand) {
            $url .= '&age_band=' . urlencode($ageBand);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || !$response) {
            return ['context' => '', 'sources' => []];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['context' => '', 'sources' => []];
        }

        $sources = [];
        foreach (($decoded['sources'] ?? []) as $source) {
            if (is_string($source) && $source !== '') {
                $sources[] = $source;
            }
        }

        return [
            'context' => trim((string)($decoded['context'] ?? '')),
            'sources' => array_values(array_unique($sources))
        ];
    }

    private function resolveChildAgeBand(array $input): ?string
    {
        $ageBand = $input['age_band'] ?? null;
        $allowed = ['0_3', '3_6', '6_12', '12_18'];

        if (is_string($ageBand) && in_array($ageBand, $allowed, true)) {
            return $ageBand;
        }

        return null;
    }

    private function resolveChildProfile(array $input): array
    {
        if ($this->resolvedChildProfile !== null) {
            return $this->resolvedChildProfile;
        }

        $profile = [
            'id' => (int) ($_SESSION['user']['id'] ?? 0),
            'name' => trim((string) ($_SESSION['user']['name'] ?? '')),
            'role' => trim((string) ($_SESSION['user']['role'] ?? '')),
            'birth_date' => null,
            'age_years' => null,
            'age_band' => null,
        ];

        if ($profile['role'] === 'child' && $profile['id'] > 0) {
            $stmt = Database::getInstance()->prepare(
                "SELECT name, birth_date
                FROM users
                WHERE id = :id AND role = 'child'
                LIMIT 1"
            );
            $stmt->execute(['id' => $profile['id']]);
            $row = $stmt->fetch();

            if (is_array($row)) {
                $profile['name'] = trim((string) ($row['name'] ?? $profile['name']));
                $profile['birth_date'] = $row['birth_date'] ?? null;
                $profile['age_years'] = $this->calculateAgeYears($profile['birth_date']);
                $profile['age_band'] = $this->mapAgeYearsToBand($profile['age_years']);
            }
        }

        if ($profile['age_band'] === null) {
            $profile['age_band'] = $this->resolveChildAgeBand($input);
        }

        if ($profile['age_band'] === null && $profile['role'] === 'child') {
            $profile['age_band'] = '6_12';
        }

        $this->resolvedChildProfile = $profile;
        return $profile;
    }

    private function calculateAgeYears(?string $birthDate): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        try {
            $birth = new DateTimeImmutable($birthDate);
            $today = new DateTimeImmutable('today');
        } catch (\Exception $e) {
            return null;
        }

        return max(0, $birth->diff($today)->y);
    }

    private function mapAgeYearsToBand(?int $ageYears): ?string
    {
        if ($ageYears === null) {
            return null;
        }

        if ($ageYears < 3) {
            return '0_3';
        }

        if ($ageYears < 6) {
            return '3_6';
        }

        if ($ageYears < 12) {
            return '6_12';
        }

        return '12_18';
    }

    private function buildModelMessages(
        array $messages,
        array $knowledge,
        string $latestUserMessage = '',
        array $childProfile = []
    ): array
    {
        $systemMessages = [
            [
                'role' => 'system',
                'content' => $this->buildReplyLanguagePrompt($latestUserMessage)
            ],
            [
                'role' => 'system',
                'content' => $this->buildChildResponseSystemPrompt($childProfile)
            ],
        ];

        $knowledgeContext = trim((string)($knowledge['context'] ?? ''));
        if ($knowledgeContext === '') {
            return array_merge($systemMessages, $messages);
        }

        $sources = $knowledge['sources'] ?? [];
        $sourceText = !empty($sources) ? implode(', ', $sources) : 'Unknown';

        $knowledgePrompt = implode("\n\n", [
            'Use the following knowledge base context when it is relevant to the user request.',
            'If the context is not relevant, ignore it and answer normally.',
            "Knowledge base context:\n{$knowledgeContext}",
            "Knowledge base sources: {$sourceText}"
        ]);

        $systemMessages[] = [
            'role' => 'system',
            'content' => $knowledgePrompt
        ];

        return array_merge($systemMessages, $messages);
    }

    private function buildChildResponseSystemPrompt(array $childProfile): string
    {
        $ageBand = (string) ($childProfile['age_band'] ?? '6_12');
        $promptVariables = $this->buildChildPromptVariables($childProfile);

        $basePrompt = $this->getPromptTemplateContent('child_chat_core_safety', $promptVariables);
        $agePrompt = $this->buildAgeSpecificPrompt($ageBand, $promptVariables);

        return implode("\n\n", array_filter([
            trim($basePrompt),
            trim($agePrompt),
        ]));
    }

    private function buildAgeSpecificPrompt(string $ageBand, array $variables = []): string
    {
        $templateKey = match ($ageBand) {
            '0_3' => 'child_chat_age_0_3',
            '3_6' => 'child_chat_age_3_6',
            '12_18' => 'child_chat_age_12_18',
            default => 'child_chat_age_6_12',
        };

        return $this->getPromptTemplateContent($templateKey, $variables);
    }

    private function buildChildPromptVariables(array $childProfile): array
    {
        $ageBand = (string) ($childProfile['age_band'] ?? '6_12');
        $ageYears = $childProfile['age_years'] ?? null;
        $childName = trim((string) ($childProfile['name'] ?? 'child'));

        return [
            '{child_profile}' => $this->buildChildProfileSummary($childProfile),
            '{child_name}' => $childName !== '' ? $childName : 'child',
            '{age_band}' => $ageBand,
            '{age_years}' => $ageYears !== null ? (string) $ageYears : 'unknown',
        ];
    }

    private function buildChildProfileSummary(array $childProfile): string
    {
        $ageBand = (string) ($childProfile['age_band'] ?? '6_12');
        $ageYears = $childProfile['age_years'] ?? null;

        if ($ageYears !== null) {
            return sprintf('role=child, age_band=%s, about %d years old', $ageBand, $ageYears);
        }

        return sprintf('role=child, age_band=%s, age not known exactly', $ageBand);
    }

    private function getPromptTemplateContent(string $templateKey, array $variables = []): string
    {
        $fallback = PromptTemplateService::getDefaultContent($templateKey);

        try {
            $content = $this->getPromptTemplateService()->getTemplateContent($templateKey, $fallback);
        } catch (\Throwable $e) {
            $content = $fallback;
        }

        return $this->renderPromptTemplate($content, $variables);
    }

    private function renderPromptTemplate(string $content, array $variables = []): string
    {
        if ($variables === []) {
            return trim($content);
        }

        return trim(strtr($content, $variables));
    }

    private function getPromptTemplateService(): PromptTemplateService
    {
        if ($this->promptTemplateService === null) {
            $this->promptTemplateService = new PromptTemplateService(Database::getInstance());
        }

        return $this->promptTemplateService;
    }

    private function buildReplyLanguagePrompt(string $latestUserMessage): string
    {
        $languageHint = $this->buildReplyLanguageHint($latestUserMessage);

        return implode("\n", array_filter([
            'Always answer in the same language as the user\'s latest message.',
            'If the user explicitly asks for a different language, follow that request.',
            'If the latest message mixes languages, reply in the dominant language of the actual question.',
            'Keep quoted titles, filenames, and source text in their original language when useful, but explain them in the user\'s language.',
            'Do not mention these language rules unless the user asks.',
            $languageHint,
        ]));
    }

    private function buildReplyLanguageHint(string $latestUserMessage): string
    {
        $detected = $this->detectReplyLanguage($latestUserMessage);

        if ($detected === 'zh') {
            return 'The latest user message is primarily in Chinese, so reply in Simplified Chinese unless the user requests another language.';
        }

        if ($detected === 'en') {
            return 'The latest user message is primarily in English, so reply in English unless the user requests another language.';
        }

        return 'Infer the reply language from the user\'s latest message and match it closely.';
    }

    private function detectReplyLanguage(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'auto';
        }

        $hanCount = preg_match_all('/\p{Han}/u', $text, $matches);
        $latinCount = preg_match_all('/[A-Za-z]/', $text, $matches);

        if ($hanCount > 0 && $hanCount >= max(2, $latinCount / 2)) {
            return 'zh';
        }

        if ($latinCount > 0 && $hanCount === 0) {
            return 'en';
        }

        if ($latinCount > $hanCount) {
            return 'en';
        }

        if ($hanCount > 0) {
            return 'zh';
        }

        return 'auto';
    }

    private function streamModelResponse(array $messages): void
    {
        $payload = [
            'model' => $this->llmModel,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'stream' => true
        ];

        $upstreamStatus = 0;
        $errorBuffer = '';

        $ch = curl_init($this->llmApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'Authorization: Bearer ' . $this->llmApiKey,
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$upstreamStatus) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', trim($headerLine), $matches)) {
                    $upstreamStatus = (int)$matches[1];
                }
                return strlen($headerLine);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, $data) use (&$upstreamStatus, &$errorBuffer) {
                if (connection_aborted()) {
                    return 0;
                }

                if ($upstreamStatus >= 400) {
                    $errorBuffer .= $data;
                    return strlen($data);
                }

                echo $data;
                @ob_flush();
                flush();

                return strlen($data);
            },
        ]);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);

        if (connection_aborted()) {
            curl_close($ch);
            return;
        }

        if ($result === false && $curlError !== '') {
            curl_close($ch);
            $this->sendStreamError('Failed to connect to the LLM service: ' . $curlError, 502);
            return;
        }

        if ($upstreamStatus >= 400) {
            curl_close($ch);
            $decodedError = json_decode($errorBuffer, true);
            $message = $decodedError['error']['message']
                ?? $decodedError['message']
                ?? 'LLM service returned an error';
            $this->sendStreamError($message, $upstreamStatus);
            return;
        }

        curl_close($ch);
    }

    private function startStream(): void
    {
        http_response_code(200);
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        ob_implicit_flush(true);
        ignore_user_abort(false);
        set_time_limit(0);
    }

    private function sendStreamError(string $message, int $status = 500): void
    {
        http_response_code($status);
        $payload = json_encode([
            'error' => $message,
            'status' => $status
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        echo "data: {$payload}\n\n";
        echo "data: [DONE]\n\n";
        @ob_flush();
        flush();
    }

    private function streamTextResponse(string $text): void
    {
        $parts = preg_split('/(\n)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            $parts = [$text];
        }

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $payload = json_encode([
                'choices' => [[
                    'delta' => ['content' => $part]
                ]]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            echo "data: {$payload}\n\n";
            @ob_flush();
            flush();
        }

        echo "data: [DONE]\n\n";
        @ob_flush();
        flush();
    }
}
