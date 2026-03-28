<?php

namespace App\Controllers;

use Core\Config;
use Utils\Helper;

class ChatController
{
    private string $knowledgeServiceUrl;
    private string $llmApiUrl;
    private string $llmApiKey;
    private string $llmModel;
    private int $timeout = 120;

    public function __construct()
    {
        $host = Config::get('CHROMA_SERVICE_HOST', '127.0.0.1');
        $port = Config::get('CHROMA_SERVICE_PORT', '4001');

        $this->knowledgeServiceUrl = "http://{$host}:{$port}";
        $this->llmApiUrl = Config::get('LLM_API_URL', 'https://api.deepseek.com/v1/chat/completions');
        $this->llmApiKey = Config::get('LLM_API_KEY', '');
        $this->llmModel = Config::get('LLM_MODEL', 'deepseek-chat');
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
        $childAgeBand = $this->resolveChildAgeBand($input);
        $knowledgeCommand = $this->parseKnowledgeCommand($userMessage);

        if ($knowledgeCommand !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if ($knowledgeCommand['query'] === '') {
                $this->streamTextResponse("知识库命令格式：/search [limit] 你的问题\n示例：/search 5 儿童睡眠建议");
                return;
            }

            $searchResult = $this->searchKnowledge(
                $knowledgeCommand['query'],
                $knowledgeCommand['limit'],
                'child',
                $childAgeBand
            );
            $this->streamTextResponse(
                $this->formatKnowledgeSearchResponse($knowledgeCommand['query'], $searchResult)
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
        $messagesForModel = $this->buildModelMessages($messages, $knowledge);

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
        $params = [
            'query' => $query,
            'limit' => max(1, min($limit, 10)),
            'session_type' => $sessionType,
            'include_filtered' => 'false'
        ];

        if ($ageBand) {
            $params['age_band'] = $ageBand;
        }

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
            return [
                'success' => false,
                'message' => '知识库服务暂时不可用',
                'results' => []
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => '知识库返回格式异常',
                'results' => []
            ];
        }

        return $decoded;
    }

    private function formatKnowledgeSearchResponse(string $query, array $searchResult): string
    {
        $results = is_array($searchResult['results'] ?? null) ? $searchResult['results'] : [];
        $header = "【知识库直查】\nQuery: {$query}";

        if (empty($results)) {
            $message = trim((string)($searchResult['message'] ?? '当前知识库没有找到可靠匹配结果'));
            return $header . "\n" . $message;
        }

        $lines = [$header];
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
            $lines[] = "片段：{$snippet}";
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

    private function buildModelMessages(array $messages, array $knowledge): array
    {
        $knowledgeContext = trim((string)($knowledge['context'] ?? ''));
        if ($knowledgeContext === '') {
            return $messages;
        }

        $sources = $knowledge['sources'] ?? [];
        $sourceText = !empty($sources) ? implode(', ', $sources) : 'Unknown';

        $knowledgePrompt = implode("\n\n", [
            'Use the following knowledge base context when it is relevant to the user request.',
            'If the context is not relevant, ignore it and answer normally.',
            "Knowledge base context:\n{$knowledgeContext}",
            "Knowledge base sources: {$sourceText}"
        ]);

        array_unshift($messages, [
            'role' => 'system',
            'content' => $knowledgePrompt
        ]);

        return $messages;
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
