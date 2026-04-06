<?php

namespace App\Controllers;

use App\Models\ChildReport;
use Core\Config;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Utils\Helper;

class ParentChildReportController
{
    private const CONTENT_MIN_MESSAGES = 12;
    private const CONTENT_MIN_CHARACTERS = 240;

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? null;
        if (!Helper::verifyCsrfToken($token)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'CSRF validation failed.',
            ], 403);
        }
    }

    private function getParentId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    private function normalizeDays($value): int
    {
        $days = (int) $value;
        if (!in_array($days, [7, 14, 30], true)) {
            return 14;
        }

        return $days;
    }

    private function getWindowDates(int $days): array
    {
        $timezone = new DateTimeZone(Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai')));
        $end = new DateTimeImmutable('today', $timezone);
        $start = $end->sub(new DateInterval('P' . ($days - 1) . 'D'));

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    private function buildSeriesMap(int $days): array
    {
        $dates = $this->getWindowDates($days);
        $timezone = new DateTimeZone(Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai')));
        $cursor = new DateTimeImmutable($dates['start'], $timezone);
        $end = new DateTimeImmutable($dates['end'], $timezone);
        $series = [];

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $series[$key] = [
                'date' => $key,
                'label' => $cursor->format('m-d'),
                'login_count' => 0,
                'conversation_count' => 0,
                'message_count' => 0,
                'child_message_count' => 0,
                'assistant_message_count' => 0,
                'child_character_count' => 0,
                'used_minutes' => 0,
            ];
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $series;
    }

    private function mergeRows(array &$series, array $rows, array $fieldMap): void
    {
        foreach ($rows as $row) {
            $date = $row['activity_date'] ?? null;
            if (!$date || !isset($series[$date])) {
                continue;
            }

            foreach ($fieldMap as $source => $target) {
                $series[$date][$target] = (int) ($row[$source] ?? 0);
            }
        }
    }

    private function buildSummary(array $series, array $child): array
    {
        $summary = [
            'total_logins' => 0,
            'total_conversations' => 0,
            'total_messages' => 0,
            'total_child_messages' => 0,
            'total_assistant_messages' => 0,
            'total_minutes' => 0,
            'active_days' => 0,
            'average_minutes_per_active_day' => 0,
            'average_messages_per_active_day' => 0,
            'last_login_at' => $child['last_login_at'] ?? null,
            'peak_day' => null,
            'peak_score' => 0,
        ];

        foreach ($series as $day) {
            $summary['total_logins'] += $day['login_count'];
            $summary['total_conversations'] += $day['conversation_count'];
            $summary['total_messages'] += $day['message_count'];
            $summary['total_child_messages'] += $day['child_message_count'];
            $summary['total_assistant_messages'] += $day['assistant_message_count'];
            $summary['total_minutes'] += $day['used_minutes'];

            $activityScore = ($day['login_count'] * 2) + $day['child_message_count'] + intdiv($day['used_minutes'], 10);
            if ($activityScore > 0) {
                $summary['active_days']++;
            }

            if ($activityScore >= $summary['peak_score']) {
                $summary['peak_score'] = $activityScore;
                $summary['peak_day'] = $day['date'];
            }
        }

        if ($summary['active_days'] > 0) {
            $summary['average_minutes_per_active_day'] = (int) round($summary['total_minutes'] / $summary['active_days']);
            $summary['average_messages_per_active_day'] = (int) round($summary['total_child_messages'] / $summary['active_days']);
        }

        return $summary;
    }

    private function computeContentReadiness(array $messages, int $days): array
    {
        $messageCount = count($messages);
        $characterCount = 0;
        $uniqueDays = [];

        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            $characterCount += mb_strlen($content, 'UTF-8');

            $createdAt = (string) ($message['created_at'] ?? '');
            if ($createdAt !== '') {
                $uniqueDays[substr($createdAt, 0, 10)] = true;
            }
        }

        $eligible = $messageCount >= self::CONTENT_MIN_MESSAGES
            && $characterCount >= self::CONTENT_MIN_CHARACTERS;

        $confidence = 'low';
        if ($messageCount >= 35 && $characterCount >= 900) {
            $confidence = 'high';
        } elseif ($messageCount >= 20 && $characterCount >= 450) {
            $confidence = 'medium';
        }

        return [
            'eligible' => $eligible,
            'message_count' => $messageCount,
            'character_count' => $characterCount,
            'active_days' => count($uniqueDays),
            'window_days' => $days,
            'minimum_messages' => self::CONTENT_MIN_MESSAGES,
            'minimum_characters' => self::CONTENT_MIN_CHARACTERS,
            'confidence' => $confidence,
            'reason' => $eligible
                ? 'Enough recent messages are available for a content report.'
                : 'More recent child-authored chat messages are needed before a stable content report can be generated.',
        ];
    }

    private function buildOverviewInsights(array $summary, int $days): array
    {
        $insights = [];

        if ($summary['active_days'] === 0) {
            $insights[] = "No tracked login or chat activity was found in the last {$days} days.";
            return $insights;
        }

        $insights[] = "{$summary['active_days']} active day(s) were recorded in the last {$days} days.";

        if ($summary['peak_day']) {
            $insights[] = "The busiest day was {$summary['peak_day']}.";
        }

        if ($summary['total_child_messages'] > 0) {
            $insights[] = "The child sent {$summary['total_child_messages']} message(s), averaging {$summary['average_messages_per_active_day']} on active days.";
        }

        if ($summary['total_minutes'] > 0) {
            $insights[] = "Tracked online time totaled {$summary['total_minutes']} minute(s), averaging {$summary['average_minutes_per_active_day']} on active days.";
        }

        if ($summary['total_logins'] === 0 && ($summary['total_child_messages'] > 0 || $summary['total_minutes'] > 0)) {
            $insights[] = 'Login counts start from the new reporting feature onward, while chat and usage history can still reflect earlier activity.';
        }

        return $insights;
    }

    private function extractKeywords(array $messages, int $limit = 6): array
    {
        $stopwords = [
            'the', 'and', 'that', 'this', 'with', 'have', 'just', 'what', 'when', 'your', 'about',
            'there', 'would', 'could', 'should', 'because', 'really', 'maybe', 'then', 'them', 'they',
            '你们', '我们', '这个', '那个', '然后', '因为', '可以', '就是', '觉得', '真的', '今天', '一个',
            '一下', '还是', '不是', '没有', '怎么', '为什么', '什么', '现在', '最近', '已经', '孩子'
        ];
        $stopwordMap = array_fill_keys($stopwords, true);
        $counts = [];

        foreach ($messages as $message) {
            $content = mb_strtolower(trim((string) ($message['content'] ?? '')), 'UTF-8');
            if ($content === '') {
                continue;
            }

            preg_match_all('/[\p{Han}]{2,}|[a-z]{3,}/u', $content, $matches);
            foreach ($matches[0] ?? [] as $token) {
                if (isset($stopwordMap[$token])) {
                    continue;
                }
                $counts[$token] = ($counts[$token] ?? 0) + 1;
            }
        }

        arsort($counts);
        $keywords = [];
        foreach ($counts as $token => $count) {
            $keywords[] = [
                'name' => $token,
                'count' => $count,
            ];
            if (count($keywords) >= $limit) {
                break;
            }
        }

        return $keywords;
    }

    private function detectUrgentSignals(array $messages): array
    {
        $patterns = [
            [
                'level' => 'high',
                'title' => 'Possible self-harm language',
                'phrases' => ['想死', '不想活', '自杀', '伤害自己', 'kill myself', 'want to die', 'suicide', 'hurt myself', 'self harm'],
                'detail' => 'Recent messages include language that may refer to self-harm or suicide. Review context immediately and seek urgent help if there is any current risk.',
            ],
            [
                'level' => 'medium',
                'title' => 'Possible aggressive language',
                'phrases' => ['报复', '打死', '伤害别人', 'revenge', 'hurt someone', 'kill him', 'kill her'],
                'detail' => 'Recent messages include language that may refer to harming others or aggressive intent. Review context closely and talk with the child soon.',
            ],
        ];

        $alerts = [];
        foreach ($patterns as $pattern) {
            foreach ($messages as $message) {
                $content = mb_strtolower((string) ($message['content'] ?? ''), 'UTF-8');
                foreach ($pattern['phrases'] as $phrase) {
                    if ($phrase !== '' && str_contains($content, mb_strtolower($phrase, 'UTF-8'))) {
                        $alerts[] = [
                            'level' => $pattern['level'],
                            'title' => $pattern['title'],
                            'detail' => $pattern['detail'],
                        ];
                        continue 3;
                    }
                }
            }
        }

        return $alerts;
    }

    private function scoreEmotionSignals(array $messages): array
    {
        $positiveWords = ['开心', '高兴', '喜欢', '期待', '兴奋', '有趣', '快乐', '好玩', 'love', 'happy', 'excited', 'enjoy', 'fun', 'great', 'good'];
        $stressWords = ['难过', '伤心', '生气', '害怕', '担心', '焦虑', '紧张', '烦', '孤独', '累', 'sad', 'angry', 'afraid', 'worried', 'anxious', 'lonely', 'tired', 'upset', 'hate'];

        $positive = 0;
        $stress = 0;

        foreach ($messages as $message) {
            $content = mb_strtolower((string) ($message['content'] ?? ''), 'UTF-8');
            foreach ($positiveWords as $word) {
                if (str_contains($content, mb_strtolower($word, 'UTF-8'))) {
                    $positive++;
                }
            }
            foreach ($stressWords as $word) {
                if (str_contains($content, mb_strtolower($word, 'UTF-8'))) {
                    $stress++;
                }
            }
        }

        return [
            'positive_hits' => $positive,
            'stress_hits' => $stress,
            'balance' => $stress > $positive + 2
                ? 'mostly_stressed'
                : ($positive > $stress + 2 ? 'mostly_positive' : 'mixed'),
        ];
    }

    private function createFallbackAnalysis(array $child, array $messages, array $readiness, int $days): array
    {
        $keywords = $this->extractKeywords($messages);
        $urgentSignals = $this->detectUrgentSignals($messages);
        $emotionSignals = $this->scoreEmotionSignals($messages);
        $topicNames = array_map(fn(array $item) => $item['name'], $keywords);

        $guidance = [
            'Ask open questions about the topics the child brings up most often this week.',
            'Look for changes that last for weeks, affect school/home/friend life, or make the child unsafe.',
            'Use this report as a conversation starter rather than a diagnosis.',
        ];

        if (!empty($urgentSignals)) {
            $guidance[0] = 'Review the flagged messages directly and seek immediate human support if there is any current safety concern.';
        }

        $emotionSummary = match ($emotionSignals['balance']) {
            'mostly_positive' => 'Recent language leans more positive or enthusiastic than stressed.',
            'mostly_stressed' => 'Recent language includes more stress-related or upset wording than positive wording.',
            default => 'Recent language shows a mixed emotional picture with both positive and stressed wording.',
        };

        return [
            'source' => 'fallback',
            'headline' => !empty($topicNames)
                ? 'Recent chats are centered on ' . implode(' / ', array_slice($topicNames, 0, 3)) . '.'
                : 'A basic recent chat summary is available.',
            'sample_confidence' => $readiness['confidence'],
            'disclaimer' => 'This is a supportive summary of recent chat patterns, not a clinical diagnosis.',
            'topic_overview' => !empty($topicNames)
                ? 'Most repeated keywords in recent child-authored messages suggest ongoing interest in ' . implode(', ', array_slice($topicNames, 0, 5)) . '.'
                : 'There were enough recent messages to summarize, but repeated topic keywords were limited.',
            'topics' => array_map(fn(array $item) => [
                'name' => $item['name'],
                'summary' => "Mentioned {$item['count']} time(s) in recent child messages.",
                'evidence_count' => $item['count'],
            ], $keywords),
            'interests' => array_map(fn(array $item) => [
                'name' => $item['name'],
                'why_it_matters' => 'This topic appears repeatedly in recent child-authored chats.',
            ], array_slice($keywords, 0, 3)),
            'emotional_overview' => [
                'summary' => $emotionSummary,
                'mood_balance' => $emotionSignals['balance'],
                'supporting_signals' => [
                    "Positive-word hits: {$emotionSignals['positive_hits']}",
                    "Stress-word hits: {$emotionSignals['stress_hits']}",
                    "Sample window: {$days} days",
                ],
            ],
            'wellbeing' => [
                'summary' => 'Focus on sustained changes over time, not a single conversation.',
                'strengths' => [
                    'The child is actively expressing thoughts in chat.',
                    'Recent topics provide concrete entry points for parent-child conversation.',
                ],
                'watch_points' => [
                    'If stress-related wording continues for weeks or interferes with daily life, consider professional evaluation.',
                    'Review any rapid change in tone, withdrawal, or unsafe language together with offline behavior.',
                ],
            ],
            'parent_guidance' => $guidance,
            'alerts' => $urgentSignals,
        ];
    }

    private function requestLlmAnalysis(array $child, array $messages, array $readiness, int $days): ?array
    {
        $apiKey = Config::get('LLM_API_KEY', '');
        $apiUrl = Config::get('LLM_API_URL', 'https://api.deepseek.com/v1/chat/completions');
        $model = Config::get('LLM_MODEL', 'deepseek-chat');

        if ($apiKey === '') {
            return null;
        }

        $keywords = $this->extractKeywords($messages);
        $urgentSignals = $this->detectUrgentSignals($messages);
        $messageLines = [];
        foreach (array_slice($messages, -60) as $message) {
            $messageLines[] = sprintf(
                "[%s] %s",
                $message['created_at'] ?? '',
                preg_replace('/\s+/u', ' ', trim((string) ($message['content'] ?? '')))
            );
        }

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 1400,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You create parent-facing child chat reports. Use only the provided child-authored messages. Do not diagnose mental illness. State uncertainty clearly. If there are any possible self-harm or violence signals, keep the alert level high and recommend immediate human follow-up. Return JSON only with keys: headline, sample_confidence, disclaimer, topic_overview, topics, interests, emotional_overview, wellbeing, parent_guidance, alerts. Keep it concise, concrete, and supportive."
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'child' => [
                            'name' => $child['name'] ?? 'Child',
                            'gender' => $child['gender'] ?? null,
                            'birth_date' => $child['birth_date'] ?? null,
                        ],
                        'window_days' => $days,
                        'sample' => $readiness,
                        'keyword_hints' => $keywords,
                        'rule_based_alerts' => $urgentSignals,
                        'messages' => $messageLines,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ],
            ],
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '' || $httpCode >= 400) {
            return null;
        }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        return $this->extractJsonPayload($content);
    }

    private function extractJsonPayload(string $content): ?array
    {
        $trimmed = trim($content);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```json\s*(\{.*\})\s*```/su', $trimmed, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeAnalysisPayload(array $payload, array $fallback): array
    {
        $analysis = $fallback;
        foreach ($payload as $key => $value) {
            $analysis[$key] = $value;
        }

        if (!isset($analysis['alerts']) || !is_array($analysis['alerts'])) {
            $analysis['alerts'] = [];
        }

        foreach ($fallback['alerts'] as $fallbackAlert) {
            $exists = false;
            foreach ($analysis['alerts'] as $alert) {
                if (($alert['title'] ?? '') === ($fallbackAlert['title'] ?? '')
                    && ($alert['detail'] ?? '') === ($fallbackAlert['detail'] ?? '')) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $analysis['alerts'][] = $fallbackAlert;
            }
        }

        if (!isset($analysis['parent_guidance']) || !is_array($analysis['parent_guidance'])) {
            $analysis['parent_guidance'] = $fallback['parent_guidance'];
        }

        $analysis['source'] = $payload ? 'llm' : $fallback['source'];
        $analysis['disclaimer'] = 'This report highlights recent language patterns only and is not a clinical diagnosis.';
        return $analysis;
    }

    public function overview(): void
    {
        $childId = (int) ($_GET['child_id'] ?? 0);
        $days = $this->normalizeDays($_GET['days'] ?? 14);

        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        $reportModel = new ChildReport();
        $child = $reportModel->getChildForParent($childId, $this->getParentId());
        if (!$child) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $dates = $this->getWindowDates($days);
        $series = $this->buildSeriesMap($days);
        $this->mergeRows($series, $reportModel->getDailyLogins($childId, $dates['start'], $dates['end']), [
            'login_count' => 'login_count',
        ]);
        $this->mergeRows($series, $reportModel->getDailyConversations($childId, $dates['start'], $dates['end']), [
            'conversation_count' => 'conversation_count',
        ]);
        $this->mergeRows($series, $reportModel->getDailyMessages($childId, $dates['start'], $dates['end']), [
            'message_count' => 'message_count',
            'child_message_count' => 'child_message_count',
            'assistant_message_count' => 'assistant_message_count',
            'child_character_count' => 'child_character_count',
        ]);
        $this->mergeRows($series, $reportModel->getDailyUsage($childId, $dates['start'], $dates['end']), [
            'used_minutes' => 'used_minutes',
        ]);

        $recentMessages = $reportModel->getRecentChildMessages($childId, $dates['start'], $dates['end'], 120);
        $contentReadiness = $this->computeContentReadiness($recentMessages, $days);
        $summary = $this->buildSummary($series, $child);

        $this->jsonResponse([
            'success' => true,
            'report' => [
                'child' => $child,
                'days' => $days,
                'summary' => $summary,
                'insights' => $this->buildOverviewInsights($summary, $days),
                'series' => array_values($series),
                'content_readiness' => $contentReadiness,
            ],
        ]);
    }

    public function content(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $this->verifyCsrf();

        $childId = (int) ($_POST['child_id'] ?? 0);
        $days = $this->normalizeDays($_POST['days'] ?? 14);

        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        $reportModel = new ChildReport();
        $child = $reportModel->getChildForParent($childId, $this->getParentId());
        if (!$child) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $dates = $this->getWindowDates($days);
        $messages = $reportModel->getRecentChildMessages($childId, $dates['start'], $dates['end'], 120);
        $readiness = $this->computeContentReadiness($messages, $days);

        if (!$readiness['eligible']) {
            $this->jsonResponse([
                'success' => false,
                'message' => $readiness['reason'],
                'content_readiness' => $readiness,
            ], 422);
        }

        $fallback = $this->createFallbackAnalysis($child, $messages, $readiness, $days);
        $llmAnalysis = $this->requestLlmAnalysis($child, $messages, $readiness, $days);
        $analysis = $this->normalizeAnalysisPayload($llmAnalysis ?? [], $fallback);

        $this->jsonResponse([
            'success' => true,
            'analysis' => $analysis,
            'content_readiness' => $readiness,
        ]);
    }
}
