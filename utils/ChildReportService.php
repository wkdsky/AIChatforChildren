<?php

namespace Utils;

use App\Models\ChildReport;
use Core\Config;
use DateInterval;
use DateTimeImmutable;

class ChildReportService
{
    private const ALLOWED_DAYS = [7, 14, 30];
    private const CONTENT_MIN_MESSAGES = 12;
    private const CONTENT_MIN_CHARACTERS = 240;

    private ChildReport $reportModel;

    public function __construct(?ChildReport $reportModel = null)
    {
        $this->reportModel = $reportModel ?? new ChildReport();
    }

    public function normalizeDays($value, int $default = 14): int
    {
        $days = (int) $value;
        if (!in_array($days, self::ALLOWED_DAYS, true)) {
            return $default;
        }

        return $days;
    }

    public function getOverview(int $childId, int $parentId, int $days): array
    {
        $child = $this->getChildOrFail($childId, $parentId);
        $scope = $this->buildManualScope($childId, $parentId, $days);
        return $this->buildSnapshotFromScope($child, $scope, 'manual_preview', false);
    }

    public function getHistoryBundle(int $childId, int $parentId): array
    {
        $this->getChildOrFail($childId, $parentId);
        $this->ensureAutoReportsForChild($childId, $parentId);

        return [
            'settings' => $this->formatSettings($this->getSettings($childId, $parentId)),
            'reports' => array_map(
                fn(array $row) => $this->formatStoredReportRow($row),
                $this->reportModel->listStoredReports($childId, $parentId)
            ),
        ];
    }

    public function getStoredReport(int $reportId, int $childId, int $parentId): array
    {
        $this->getChildOrFail($childId, $parentId);
        $row = $this->reportModel->getStoredReport($reportId, $childId, $parentId);
        if (!$row) {
            throw new \RuntimeException('Report not found.');
        }

        return [
            'report' => $this->decodeStoredReportRow($row),
            'report_record' => $this->formatStoredReportRow($row),
        ];
    }

    public function updateSettings(int $childId, int $parentId, array $input): array
    {
        $this->getChildOrFail($childId, $parentId);

        $enabled = !empty($input['auto_generate_enabled']);
        $frequencyDays = $this->normalizeDays($input['auto_generate_frequency_days'] ?? 7, 7);
        $windowDays = $this->normalizeDays($input['auto_generate_window_days'] ?? 14, 14);
        $now = AppTime::now();

        $existing = $this->getSettings($childId, $parentId);
        $nextDueAt = $enabled
            ? ($existing['next_report_due_at'] ?? $now->format('Y-m-d H:i:s'))
            : null;

        $saved = $this->reportModel->upsertReportSettings($childId, $parentId, [
            'auto_generate_enabled' => $enabled,
            'auto_generate_frequency_days' => $frequencyDays,
            'auto_generate_window_days' => $windowDays,
            'next_report_due_at' => $nextDueAt,
            'last_report_generated_at' => $existing['last_report_generated_at'] ?? null,
        ]);

        if (!$saved) {
            throw new \RuntimeException('Unable to save report settings right now.');
        }

        return $this->formatSettings($this->getSettings($childId, $parentId));
    }

    public function generateAndStore(int $childId, int $parentId, int $days, string $mode = 'manual'): array
    {
        $child = $this->getChildOrFail($childId, $parentId);

        return $mode === 'auto'
            ? $this->generateAutoReportBundle($child, $parentId, $days)
            : $this->generateManualReportBundle($child, $parentId, $days);
    }

    public function getCumulativeAnalysis(int $childId, int $parentId, array $reportIds): array
    {
        $this->getChildOrFail($childId, $parentId);
        $rows = $this->reportModel->getStoredReportsByIds($childId, $parentId, $reportIds);
        if ($rows === []) {
            throw new \RuntimeException('Select at least one saved report.');
        }

        $reports = array_map(fn(array $row) => $this->decodeStoredReportRow($row), $rows);
        $records = array_map(fn(array $row) => $this->formatStoredReportRow($row), $rows);
        $messageSummary = $this->reportModel->getRetainedMessageSummaryForReports(
            $childId,
            $parentId,
            array_column($rows, 'id')
        );

        $analysis = $this->buildTrendAnalysis($reports, $records, $messageSummary);

        return [
            'analysis' => $analysis,
            'reports' => $records,
        ];
    }

    public function runDueAutoReports(?int $parentId = null, int $limit = 20): array
    {
        $results = [];
        foreach ($this->reportModel->getDueAutoReportChildren($parentId, $limit) as $row) {
            $child = [
                'id' => (int) $row['child_id'],
                'name' => $row['name'],
                'gender' => $row['gender'],
                'birth_date' => $row['birth_date'],
                'last_login_at' => $row['last_login_at'],
            ];

            $bundle = $this->generateAndStore(
                (int) $row['child_id'],
                (int) $row['parent_id'],
                $this->normalizeDays($row['auto_generate_window_days'] ?? 14),
                'auto'
            );

            $results[] = [
                'child_id' => $child['id'],
                'child_name' => $child['name'],
                'report_id' => $bundle['report_record']['id'] ?? null,
                'status' => $bundle['report']['status'] ?? 'ready',
                'risk_level' => $bundle['report']['risk_level'] ?? 'low',
            ];
        }

        return $results;
    }

    public function ensureAutoReportsForChild(int $childId, int $parentId): void
    {
        $settings = $this->getSettings($childId, $parentId);
        if (empty($settings['auto_generate_enabled'])) {
            return;
        }

        $nextDueAt = $settings['next_report_due_at'] ?? null;
        $now = AppTime::now();

        if ($nextDueAt !== null) {
            $nextDue = new DateTimeImmutable($nextDueAt, AppTime::timezone());
            if ($nextDue > $now) {
                return;
            }
        }

        $this->generateAndStore(
            $childId,
            $parentId,
            $this->normalizeDays($settings['auto_generate_window_days'] ?? 14),
            'auto'
        );
    }

    private function generateManualReportBundle(array $child, int $parentId, int $fallbackDays): array
    {
        $childId = (int) $child['id'];
        $now = AppTime::now();
        $reportDay = $now->format('Y-m-d');
        $existing = $this->reportModel->findManualReportForDay($childId, $parentId, $reportDay);
        $scope = $this->buildManualScope($childId, $parentId, $fallbackDays, $existing);
        $messages = $this->reportModel->getConversationMessagesBetween($childId, $scope['start_at'], $scope['end_at']);
        $snapshot = $this->buildSnapshotFromScope($child, $scope, 'manual', true);

        $row = $this->storeSnapshot($childId, $parentId, $snapshot, $scope, $messages, $existing);

        return [
            'report' => $this->decodeStoredReportRow($row),
            'report_record' => $this->formatStoredReportRow($row),
            'settings' => $this->formatSettings($this->getSettings($childId, $parentId)),
            'reports' => array_map(
                fn(array $item) => $this->formatStoredReportRow($item),
                $this->reportModel->listStoredReports($childId, $parentId)
            ),
            'updated_existing' => $existing !== null,
        ];
    }

    private function generateAutoReportBundle(array $child, int $parentId, int $days): array
    {
        $childId = (int) $child['id'];
        $settings = $this->getSettings($childId, $parentId);
        $days = $this->normalizeDays($days ?: ($settings['auto_generate_window_days'] ?? 14));
        $scope = $this->buildRollingScope($days);
        $messages = $this->reportModel->getConversationMessagesBetween($childId, $scope['start_at'], $scope['end_at']);
        $snapshot = $this->buildSnapshotFromScope($child, $scope, 'auto', true);
        $row = $this->storeSnapshot($childId, $parentId, $snapshot, $scope, $messages);

        $frequencyDays = (int) ($settings['auto_generate_frequency_days'] ?? 7);
        $nextDueAt = AppTime::now()->add(new DateInterval('P' . $frequencyDays . 'D'))->format('Y-m-d H:i:s');
        $this->reportModel->upsertReportSettings($childId, $parentId, [
            'auto_generate_enabled' => !empty($settings['auto_generate_enabled']),
            'auto_generate_frequency_days' => $frequencyDays,
            'auto_generate_window_days' => (int) ($settings['auto_generate_window_days'] ?? $days),
            'next_report_due_at' => $nextDueAt,
            'last_report_generated_at' => AppTime::now()->format('Y-m-d H:i:s'),
        ]);

        return [
            'report' => $this->decodeStoredReportRow($row),
            'report_record' => $this->formatStoredReportRow($row),
            'settings' => $this->formatSettings($this->getSettings($childId, $parentId)),
            'reports' => array_map(
                fn(array $item) => $this->formatStoredReportRow($item),
                $this->reportModel->listStoredReports($childId, $parentId)
            ),
            'updated_existing' => false,
        ];
    }

    private function storeSnapshot(
        int $childId,
        int $parentId,
        array $snapshot,
        array $scope,
        array $messages,
        ?array $existing = null
    ): array {
        $payload = [
            'generation_mode' => $snapshot['generation_mode'] === 'auto' ? 'auto' : 'manual',
            'status' => $snapshot['status'],
            'window_days' => $snapshot['days'],
            'window_start_date' => $snapshot['window']['start'],
            'window_end_date' => $snapshot['window']['end'],
            'scope_started_at' => $scope['start_at'],
            'scope_ended_at' => $scope['end_at'],
            'report_day' => $scope['report_day'],
            'sample_message_count' => $snapshot['content_readiness']['message_count'] ?? 0,
            'sample_character_count' => $snapshot['content_readiness']['character_count'] ?? 0,
            'sample_active_days' => $snapshot['content_readiness']['active_days'] ?? 0,
            'message_record_count' => count($messages),
            'confidence' => $snapshot['content_readiness']['confidence'] ?? 'none',
            'risk_level' => $snapshot['risk_level'],
            'headline' => $snapshot['analysis']['headline'] ?? '',
            'report_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $reportId = $this->reportModel->transaction(function () use ($childId, $parentId, $payload, $messages, $existing) {
            if ($existing) {
                $this->reportModel->updateStoredReport((int) $existing['id'], $childId, $parentId, $payload);
                $reportId = (int) $existing['id'];
            } else {
                $reportId = $this->reportModel->createStoredReport($childId, $parentId, $payload);
            }

            $this->reportModel->replaceStoredReportMessages($reportId, $messages);
            return $reportId;
        });

        $row = $this->reportModel->getStoredReport((int) $reportId, $childId, $parentId);
        if (!$row) {
            throw new \RuntimeException('Report was generated but could not be loaded.');
        }

        return $row;
    }

    private function buildManualScope(
        int $childId,
        int $parentId,
        int $fallbackDays,
        ?array $existingManualReport = null
    ): array {
        $now = AppTime::now();
        $settings = $this->getSettings($childId, $parentId);
        $fallbackWindowDays = $this->normalizeDays(
            $fallbackDays ?: ($settings['auto_generate_window_days'] ?? 14)
        );

        $scopeStartAt = $existingManualReport['scope_started_at'] ?? null;
        if ($scopeStartAt === null) {
            $previous = $existingManualReport
                ? $this->reportModel->getLatestStoredReportBefore(
                    $childId,
                    $parentId,
                    $existingManualReport['created_at'] ?? $now->format('Y-m-d H:i:s'),
                    (int) $existingManualReport['id']
                )
                : $this->reportModel->getLatestStoredReport($childId, $parentId);

            if ($previous) {
                $scopeStartAt = $previous['scope_ended_at']
                    ?? $previous['updated_at']
                    ?? $previous['created_at']
                    ?? null;
            }
        }

        if ($scopeStartAt === null) {
            $scopeStartAt = $this->buildRollingScope($fallbackWindowDays)['start_at'];
        }

        $scopeEndAt = $now->format('Y-m-d H:i:s');
        if ($scopeStartAt >= $scopeEndAt) {
            $scopeStartAt = $now->sub(new DateInterval('PT1M'))->format('Y-m-d H:i:s');
        }

        return $this->buildScopeDescriptor(
            $scopeStartAt,
            $scopeEndAt,
            'manual_incremental',
            $now->format('Y-m-d')
        );
    }

    private function buildRollingScope(int $days): array
    {
        $days = $this->normalizeDays($days);
        $end = AppTime::now();
        $start = AppTime::today()->sub(new DateInterval('P' . ($days - 1) . 'D'));

        return $this->buildScopeDescriptor(
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            'rolling_window',
            $end->format('Y-m-d')
        );
    }

    private function buildScopeDescriptor(string $startAt, string $endAt, string $type, string $reportDay): array
    {
        $startDate = substr($startAt, 0, 10);
        $endDate = substr($endAt, 0, 10);
        $days = max(
            1,
            (int) ((new DateTimeImmutable($startDate, AppTime::timezone()))
                ->diff(new DateTimeImmutable($endDate, AppTime::timezone()))
                ->days) + 1
        );

        return [
            'type' => $type,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => $days,
            'report_day' => $reportDay,
        ];
    }

    private function getChildOrFail(int $childId, int $parentId): array
    {
        $child = $this->reportModel->getChildForParent($childId, $parentId);
        if (!$child) {
            throw new \RuntimeException('Child account not found.');
        }

        return $child;
    }

    private function getSettings(int $childId, int $parentId): array
    {
        $settings = $this->reportModel->getReportSettings($childId, $parentId);
        if ($settings) {
            return $settings;
        }

        return [
            'child_id' => $childId,
            'parent_id' => $parentId,
            'auto_generate_enabled' => 0,
            'auto_generate_frequency_days' => 7,
            'auto_generate_window_days' => 14,
            'next_report_due_at' => null,
            'last_report_generated_at' => null,
        ];
    }

    private function buildSnapshot(array $child, int $days, string $mode, bool $allowLlm): array
    {
        return $this->buildSnapshotFromScope($child, $this->buildRollingScope($days), $mode, $allowLlm);
    }

    private function buildSnapshotFromScope(array $child, array $scope, string $mode, bool $allowLlm): array
    {
        $series = $this->buildSeriesMap($scope['start_date'], $scope['end_date']);
        $childId = (int) $child['id'];

        $this->mergeRows($series, $this->reportModel->getDailyLogins($childId, $scope['start_date'], $scope['end_date']), [
            'login_count' => 'login_count',
        ]);
        $this->mergeRows($series, $this->reportModel->getDailyConversations($childId, $scope['start_date'], $scope['end_date']), [
            'conversation_count' => 'conversation_count',
        ]);
        $this->mergeRows($series, $this->reportModel->getDailyMessages($childId, $scope['start_date'], $scope['end_date']), [
            'message_count' => 'message_count',
            'child_message_count' => 'child_message_count',
            'assistant_message_count' => 'assistant_message_count',
            'child_character_count' => 'child_character_count',
        ]);
        $this->mergeRows($series, $this->reportModel->getDailyUsage($childId, $scope['start_date'], $scope['end_date']), [
            'used_minutes' => 'used_minutes',
        ]);

        $messages = $this->reportModel->getRecentChildMessagesBetween($childId, $scope['start_at'], $scope['end_at'], 120);
        $summary = $this->buildSummary($series, $child);
        $readiness = $this->computeContentReadiness($messages, $scope['days']);
        $signalPacket = $this->analyzeSignals($messages, $summary, $scope['days']);
        $analysis = $this->buildAnalysis($child, $readiness, $signalPacket, $scope['days'], $allowLlm);

        return [
            'version' => 2,
            'status' => $readiness['message_count'] > 0 ? 'ready' : 'insufficient_data',
            'generated_at' => AppTime::now()->format(DATE_ATOM),
            'generation_mode' => $mode,
            'risk_level' => $this->riskLevelFromAnalysis($analysis),
            'child' => [
                'id' => $childId,
                'gender' => $child['gender'] ?? null,
                'age_years' => $this->calculateAgeYears($child['birth_date'] ?? null),
                'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
            ],
            'days' => $scope['days'],
            'window' => [
                'start' => $scope['start_date'],
                'end' => $scope['end_date'],
            ],
            'scope' => [
                'type' => $scope['type'],
                'start_at' => AppTime::toIso8601($scope['start_at']),
                'end_at' => AppTime::toIso8601($scope['end_at']),
                'report_day' => $scope['report_day'],
            ],
            'summary' => $summary,
            'insights' => $this->buildOverviewInsights($summary, $scope['days']),
            'series' => array_values($series),
            'content_readiness' => $readiness,
            'analysis' => $analysis,
        ];
    }

    private function buildAnalysis(array $child, array $readiness, array $signalPacket, int $days, bool $allowLlm): array
    {
        if (($readiness['message_count'] ?? 0) === 0) {
            return $this->buildNoDataAnalysis($readiness, $days);
        }

        $fallback = $this->createFallbackAnalysis($child, $readiness, $signalPacket, $days);
        if (!$allowLlm || empty($readiness['recommended_sample_met'])) {
            return $fallback;
        }

        $payload = $this->requestLlmAnalysis($child, $readiness, $signalPacket, $days);
        return $this->normalizeAnalysisPayload($payload ?? [], $fallback);
    }

    private function buildSeriesMap(string $startDate, string $endDate): array
    {
        $cursor = new DateTimeImmutable($startDate, AppTime::timezone());
        $end = new DateTimeImmutable($endDate, AppTime::timezone());
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
            'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
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

        $recommendedSampleMet = $messageCount >= self::CONTENT_MIN_MESSAGES
            && $characterCount >= self::CONTENT_MIN_CHARACTERS;

        $confidence = 'none';
        if ($messageCount > 0 && $characterCount > 0) {
            $confidence = 'low';
            if ($messageCount >= 35 && $characterCount >= 900) {
                $confidence = 'high';
            } elseif ($messageCount >= 20 && $characterCount >= 450) {
                $confidence = 'medium';
            }
        }

        return [
            'eligible' => $messageCount > 0,
            'can_generate' => $messageCount > 0,
            'recommended_sample_met' => $recommendedSampleMet,
            'message_count' => $messageCount,
            'character_count' => $characterCount,
            'active_days' => count($uniqueDays),
            'window_days' => $days,
            'minimum_messages' => self::CONTENT_MIN_MESSAGES,
            'minimum_characters' => self::CONTENT_MIN_CHARACTERS,
            'confidence' => $confidence,
            'reason' => $messageCount === 0
                ? 'No recent child-authored chat messages were found in the selected date range.'
                : ($recommendedSampleMet
                    ? 'Enough recent messages are available for a fuller report.'
                    : 'A limited recent sample is available. Interpret the report cautiously and watch for patterns over time.'),
        ];
    }

    private function buildOverviewInsights(array $summary, int $days): array
    {
        $insights = [];

        if ($summary['active_days'] === 0) {
            return ["No tracked login or chat activity was found in the last {$days} days."];
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

        return $insights;
    }

    private function analyzeSignals(array $messages, array $summary, int $days): array
    {
        $riskMatches = $this->collectCatalogMatches($messages, $this->riskCatalog());
        $thinkingMatches = $this->collectCatalogMatches($messages, $this->thinkingPatternCatalog());
        $protectiveMatches = $this->collectCatalogMatches($messages, $this->protectiveCatalog());
        $topics = $this->buildTopics($messages);
        $emotionSignals = $this->scoreEmotionSignals($messages);

        $riskDimensions = $this->buildRiskDimensions($riskMatches);
        $thinkingPatterns = $this->buildThinkingPatterns($thinkingMatches);
        $protectiveFactors = $this->buildProtectiveFactors($protectiveMatches, $summary);
        $alerts = $this->buildAlerts($riskDimensions);

        return [
            'topics' => $topics,
            'emotion' => $this->buildEmotionalOverview($emotionSignals, $riskDimensions),
            'risk_dimensions' => $riskDimensions,
            'thinking_patterns' => $thinkingPatterns,
            'protective_factors' => $protectiveFactors,
            'alerts' => $alerts,
            'wellbeing' => $this->buildWellbeingOverview($riskDimensions, $thinkingPatterns, $protectiveFactors, $days),
            'parent_guidance' => $this->buildParentGuidance($riskDimensions, $thinkingPatterns, $protectiveFactors),
        ];
    }

    private function buildTopics(array $messages): array
    {
        $catalog = [
            'school' => ['label' => 'School and performance', 'patterns' => ['学校', '老师', '同学', '作业', '考试', 'school', 'teacher', 'class', 'homework', 'exam', 'grade']],
            'friendship' => ['label' => 'Friendship and belonging', 'patterns' => ['朋友', '同学关系', '被欺负', '孤独', 'friend', 'friends', 'left out', 'bully', 'peer']],
            'family' => ['label' => 'Family and home life', 'patterns' => ['妈妈', '爸爸', '家里', 'family', 'mom', 'dad', 'home']],
            'sleep_body' => ['label' => 'Sleep, body, and energy', 'patterns' => ['睡', '累', '胖', '吃', 'sleep', 'tired', 'body', 'food', 'weight']],
            'gaming_media' => ['label' => 'Games and media', 'patterns' => ['游戏', '手机', '视频', '动画', 'game', 'gaming', 'youtube', 'video', 'screen']],
            'identity' => ['label' => 'Identity and self-image', 'patterns' => ['我是不是', '讨厌自己', '好丑', 'who am i', 'i hate myself', 'ugly', 'worthless']],
        ];

        $counts = [];
        foreach ($messages as $message) {
            $content = mb_strtolower((string) ($message['content'] ?? ''), 'UTF-8');
            foreach ($catalog as $id => $spec) {
                foreach ($spec['patterns'] as $pattern) {
                    if ($pattern !== '' && str_contains($content, mb_strtolower($pattern, 'UTF-8'))) {
                        $counts[$id] = ($counts[$id] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        arsort($counts);
        $topics = [];
        foreach ($counts as $id => $count) {
            $topics[] = [
                'name' => $catalog[$id]['label'],
                'summary' => "This theme appeared across {$count} recent message(s).",
                'evidence_count' => $count,
            ];
            if (count($topics) >= 5) {
                break;
            }
        }

        if ($topics !== []) {
            return $topics;
        }

        return $this->buildGenericTopics($messages);
    }

    private function buildGenericTopics(array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $questionCount = 0;
        $sharingCount = 0;
        $activeDays = [];

        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if (str_contains($content, '?') || str_contains($content, '？')) {
                $questionCount++;
            }
            if (mb_strlen($content, 'UTF-8') >= 28) {
                $sharingCount++;
            }

            $createdAt = (string) ($message['created_at'] ?? '');
            if ($createdAt !== '') {
                $activeDays[substr($createdAt, 0, 10)] = true;
            }
        }

        $topics = [];

        if ($questionCount >= 2) {
            $topics[] = [
                'name' => 'Questions and advice-seeking',
                'summary' => 'Recent chat includes repeated requests for explanations, reassurance, or problem-solving help.',
                'evidence_count' => $questionCount,
            ];
        }

        if ($sharingCount >= 2) {
            $topics[] = [
                'name' => 'Personal sharing and storytelling',
                'summary' => 'Recent chat includes longer descriptions of experiences, feelings, or situations rather than only short prompts.',
                'evidence_count' => $sharingCount,
            ];
        }

        if ($topics === []) {
            $topics[] = [
                'name' => 'Mixed everyday topics',
                'summary' => count($activeDays) >= 2
                    ? 'Recent chat spans multiple days and several ordinary topics without one dominant repeated theme.'
                    : 'Recent chat contains a small sample of everyday topics without one dominant repeated theme.',
                'evidence_count' => count($messages),
            ];
        }

        return array_slice($topics, 0, 3);
    }

    private function collectCatalogMatches(array $messages, array $catalog): array
    {
        $matches = [];

        foreach ($catalog as $spec) {
            $matches[$spec['id']] = [
                'spec' => $spec,
                'count' => 0,
                'days' => [],
            ];
        }

        foreach ($messages as $message) {
            $content = mb_strtolower((string) ($message['content'] ?? ''), 'UTF-8');
            $date = substr((string) ($message['created_at'] ?? ''), 0, 10) ?: AppTime::today()->format('Y-m-d');

            foreach ($catalog as $spec) {
                foreach ($spec['patterns'] as $pattern) {
                    if ($pattern !== '' && str_contains($content, mb_strtolower($pattern, 'UTF-8'))) {
                        $matches[$spec['id']]['count']++;
                        $matches[$spec['id']]['days'][$date] = true;
                        break;
                    }
                }
            }
        }

        return $matches;
    }

    private function buildRiskDimensions(array $matches): array
    {
        $dimensions = [];

        foreach ($matches as $item) {
            $spec = $item['spec'];
            $signalCount = (int) $item['count'];
            $dayCount = count($item['days']);
            $level = $this->determineLevel($spec['base_level'], $signalCount, $dayCount);

            if ($level === null) {
                continue;
            }

            $dimensions[] = [
                'id' => $spec['id'],
                'name' => $spec['title'],
                'level' => $level,
                'summary' => $spec['summary'],
                'why_it_matters' => $spec['why_it_matters'],
                'evidence' => $this->appendPersistenceNote($spec['evidence'], $signalCount, $dayCount),
                'parent_action' => $spec['parent_action'],
                'signal_count' => $signalCount,
                'active_days' => $dayCount,
            ];
        }

        usort($dimensions, fn(array $left, array $right) => $this->levelWeight($right['level']) <=> $this->levelWeight($left['level']));
        return $dimensions;
    }

    private function buildThinkingPatterns(array $matches): array
    {
        $patterns = [];

        foreach ($matches as $item) {
            $spec = $item['spec'];
            $signalCount = (int) $item['count'];
            $dayCount = count($item['days']);
            $level = $this->determineLevel($spec['base_level'], $signalCount, $dayCount);
            if ($level === null) {
                continue;
            }

            $patterns[] = [
                'name' => $spec['title'],
                'level' => $level,
                'summary' => $spec['summary'],
                'evidence' => $this->appendPersistenceNote($spec['evidence'], $signalCount, $dayCount),
            ];
        }

        usort($patterns, fn(array $left, array $right) => $this->levelWeight($right['level']) <=> $this->levelWeight($left['level']));
        return $patterns;
    }

    private function buildProtectiveFactors(array $matches, array $summary): array
    {
        $factors = [];

        foreach ($matches as $item) {
            $signalCount = (int) $item['count'];
            if ($signalCount <= 0) {
                continue;
            }

            $spec = $item['spec'];
            $factors[] = [
                'name' => $spec['title'],
                'summary' => $spec['summary'],
            ];
        }

        if ($summary['total_child_messages'] >= 8) {
            $factors[] = [
                'name' => 'Willingness to express thoughts',
                'summary' => 'The child is using chat to express feelings or questions rather than staying completely silent.',
            ];
        }

        return array_slice($factors, 0, 5);
    }

    private function scoreEmotionSignals(array $messages): array
    {
        $positiveWords = ['开心', '高兴', '喜欢', '期待', '兴奋', '有趣', '快乐', 'love', 'happy', 'excited', 'enjoy', 'fun', 'great'];
        $stressWords = ['难过', '伤心', '害怕', '担心', '焦虑', '紧张', '烦', 'sad', 'afraid', 'worried', 'anxious', 'upset'];
        $angerWords = ['生气', '讨厌', '报复', 'angry', 'hate', 'revenge', 'mad'];
        $hopelessWords = ['没用', '没有意义', '不想活', '没人喜欢我', 'worthless', 'nothing matters', 'no one cares', 'useless'];

        $scores = [
            'positive' => 0,
            'stress' => 0,
            'anger' => 0,
            'hopeless' => 0,
        ];

        foreach ($messages as $message) {
            $content = mb_strtolower((string) ($message['content'] ?? ''), 'UTF-8');
            foreach ($positiveWords as $word) {
                if (str_contains($content, mb_strtolower($word, 'UTF-8'))) {
                    $scores['positive']++;
                }
            }
            foreach ($stressWords as $word) {
                if (str_contains($content, mb_strtolower($word, 'UTF-8'))) {
                    $scores['stress']++;
                }
            }
            foreach ($angerWords as $word) {
                if (str_contains($content, mb_strtolower($word, 'UTF-8'))) {
                    $scores['anger']++;
                }
            }
            foreach ($hopelessWords as $word) {
                if (str_contains($content, mb_strtolower($word, 'UTF-8'))) {
                    $scores['hopeless']++;
                }
            }
        }

        return $scores;
    }

    private function buildEmotionalOverview(array $emotionSignals, array $riskDimensions): array
    {
        $summary = 'Recent chat language shows a mixed emotional picture.';
        if ($emotionSignals['hopeless'] > 0) {
            $summary = 'Recent chat includes discouraged or self-critical wording that deserves gentle follow-up.';
        } elseif ($emotionSignals['anger'] > max(1, $emotionSignals['positive'])) {
            $summary = 'Recent chat leans more irritable or conflict-focused than calm or positive.';
        } elseif ($emotionSignals['stress'] > $emotionSignals['positive'] + 2) {
            $summary = 'Recent chat leans more worried or overwhelmed than positive.';
        } elseif ($emotionSignals['positive'] > 0 && $riskDimensions === []) {
            $summary = 'Recent chat includes more everyday curiosity or positive engagement than distress signals.';
        }

        return [
            'summary' => $summary,
            'supporting_signals' => [
                "Positive-language hits: {$emotionSignals['positive']}",
                "Stress-language hits: {$emotionSignals['stress']}",
                "Anger-language hits: {$emotionSignals['anger']}",
                "Hopeless/self-worth hits: {$emotionSignals['hopeless']}",
            ],
        ];
    }

    private function buildWellbeingOverview(array $riskDimensions, array $thinkingPatterns, array $protectiveFactors, int $days): array
    {
        $watchPoints = array_map(
            fn(array $dimension) => $dimension['summary'],
            array_slice($riskDimensions, 0, 4)
        );
        $strengths = array_map(
            fn(array $factor) => $factor['summary'],
            array_slice($protectiveFactors, 0, 4)
        );

        if ($watchPoints === []) {
            $watchPoints[] = 'No urgent safety-themed language was detected in this sample, but chat signals should always be interpreted together with offline behavior.';
        }

        if ($strengths === []) {
            $strengths[] = 'There are not many clear protective signals in chat alone, so offline routines and trusted relationships remain important context.';
        }

        if ($thinkingPatterns !== []) {
            $watchPoints[] = 'A few thinking patterns in chat look rigid, self-critical, avoidant, or conflict-focused. Watch whether those patterns are becoming more frequent across the last ' . $days . ' days.';
        }

        $summary = $riskDimensions === []
            ? 'No acute risk signal stands out in this sample, but the report should still be read as one partial view of the child’s recent wellbeing.'
            : 'Several patterns in recent chat deserve follow-up, especially if similar behavior is also showing up at school, home, or with peers.';

        return [
            'summary' => $summary,
            'strengths' => $strengths,
            'watch_points' => $watchPoints,
        ];
    }

    private function buildParentGuidance(array $riskDimensions, array $thinkingPatterns, array $protectiveFactors): array
    {
        $guidance = [
            'Use this report as a conversation starter, not a diagnosis. Look for patterns that repeat across chat, home, school, and friendships.',
            'Protect privacy by discussing themes and feelings first instead of reading back exact chat lines unless there is a safety concern.',
        ];

        foreach ($riskDimensions as $dimension) {
            $guidance[] = $dimension['parent_action'];
        }

        if ($thinkingPatterns !== []) {
            $guidance[] = 'When the child uses all-or-nothing, revenge-focused, or harsh self-talk, respond with calm curiosity and help them name other possible explanations or next steps.';
        }

        if ($protectiveFactors !== []) {
            $guidance[] = 'Reinforce the supportive adults, routines, and strengths that already appear in the child’s recent communication.';
        }

        return array_values(array_unique(array_slice($guidance, 0, 6)));
    }

    private function buildAlerts(array $riskDimensions): array
    {
        $alerts = [];
        foreach ($riskDimensions as $dimension) {
            if (!in_array($dimension['level'], ['high', 'medium'], true)) {
                continue;
            }

            $alerts[] = [
                'level' => $dimension['level'],
                'title' => $dimension['name'],
                'detail' => $dimension['evidence'],
            ];
        }

        return array_slice($alerts, 0, 4);
    }

    private function createFallbackAnalysis(array $child, array $readiness, array $signalPacket, int $days): array
    {
        $topics = $signalPacket['topics'];
        $riskDimensions = $signalPacket['risk_dimensions'];
        $thinkingPatterns = $signalPacket['thinking_patterns'];
        $protectiveFactors = $signalPacket['protective_factors'];
        $topicNames = array_map(fn(array $item) => $item['name'], array_slice($topics, 0, 3));

        $headline = 'Recent chat mainly shows everyday interests and a low-risk emotional picture.';
        if ($riskDimensions !== []) {
            $highestLevel = $this->riskLevelFromAnalysis(['risk_dimensions' => $riskDimensions, 'alerts' => $signalPacket['alerts']]);
            if ($highestLevel === 'high') {
                $headline = 'Recent chat includes one or more safety-sensitive themes that need prompt adult follow-up.';
            } elseif ($highestLevel === 'medium') {
                $headline = 'Recent chat shows recurring stress themes and a few patterns worth checking in on soon.';
            } else {
                $headline = 'Recent chat shows mild stress signals alongside ordinary interests.';
            }
        } elseif ($topicNames !== []) {
            $headline = 'Recent chat mainly centers on ' . implode(' / ', $topicNames) . '.';
        }

        $topicOverview = $topicNames !== []
            ? 'The most visible recent themes are ' . implode(', ', array_slice($topicNames, 0, 4)) . '.'
            : 'No single topic dominated the recent sample.';

        return [
            'source' => 'rule_based',
            'headline' => $headline,
            'sample_confidence' => $readiness['confidence'],
            'disclaimer' => 'This report summarizes recent chat patterns only. It is not a clinical diagnosis and should be read alongside offline behavior.',
            'topic_overview' => $topicOverview,
            'topics' => $topics,
            'interests' => array_map(
                fn(array $topic) => [
                    'name' => $topic['name'],
                    'why_it_matters' => $topic['summary'],
                ],
                array_slice($topics, 0, 3)
            ),
            'emotional_overview' => $signalPacket['emotion'],
            'wellbeing' => $signalPacket['wellbeing'],
            'risk_dimensions' => $riskDimensions,
            'thinking_patterns' => $thinkingPatterns,
            'protective_factors' => $protectiveFactors,
            'parent_guidance' => $signalPacket['parent_guidance'],
            'alerts' => $signalPacket['alerts'],
        ];
    }

    private function buildNoDataAnalysis(array $readiness, int $days): array
    {
        return [
            'source' => 'rule_based',
            'headline' => 'No recent child-authored chat sample is available yet.',
            'sample_confidence' => $readiness['confidence'] ?? 'none',
            'disclaimer' => 'This report is not a diagnosis. It simply reflects that there was not enough recent child-authored chat in the selected window to analyze content patterns.',
            'topic_overview' => 'There were no recent child-authored messages in the selected time window.',
            'topics' => [],
            'interests' => [],
            'emotional_overview' => [
                'summary' => 'No content sample is available for emotional patterning.',
                'supporting_signals' => [
                    "Window length: {$days} days",
                    'Child-authored messages: 0',
                ],
            ],
            'wellbeing' => [
                'summary' => 'Use activity history and offline observation until a larger recent chat sample is available.',
                'strengths' => [
                    'You can still use login and activity history to understand routine and consistency.',
                ],
                'watch_points' => [
                    'A missing chat sample does not mean there is no risk. Offline behavior and trusted adult check-ins remain essential.',
                ],
            ],
            'risk_dimensions' => [],
            'thinking_patterns' => [],
            'protective_factors' => [],
            'parent_guidance' => [
                'Wait for more recent child-authored chat before drawing content-based conclusions.',
                'If you already have concerns, use direct conversation and offline observation rather than relying on report data alone.',
            ],
            'alerts' => [],
        ];
    }

    private function requestLlmAnalysis(array $child, array $readiness, array $signalPacket, int $days): ?array
    {
        $apiKey = Config::get('LLM_API_KEY', '');
        $apiUrl = Config::get('LLM_API_URL', 'https://api.deepseek.com/v1/chat/completions');
        $model = Config::get('LLM_MODEL', 'deepseek-chat');

        if ($apiKey === '') {
            return null;
        }

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 1800,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You write parent-facing child wellbeing reports from structured digests only. Never quote, reconstruct, or infer exact child wording. Never expose names of peers, schools, or identifiable details. Do not diagnose. Use cautious, concrete language. Return JSON only with keys: headline, sample_confidence, disclaimer, topic_overview, topics, interests, emotional_overview, wellbeing, risk_dimensions, thinking_patterns, protective_factors, parent_guidance, alerts.'
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'child' => [
                            'age_years' => $this->calculateAgeYears($child['birth_date'] ?? null),
                            'gender' => $child['gender'] ?? null,
                        ],
                        'window_days' => $days,
                        'sample' => $readiness,
                        'structured_signals' => $signalPacket,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

        foreach (['topics', 'interests', 'risk_dimensions', 'thinking_patterns', 'protective_factors', 'parent_guidance', 'alerts'] as $listKey) {
            if (!isset($analysis[$listKey]) || !is_array($analysis[$listKey])) {
                $analysis[$listKey] = $fallback[$listKey];
            }
        }

        if (!isset($analysis['wellbeing']) || !is_array($analysis['wellbeing'])) {
            $analysis['wellbeing'] = $fallback['wellbeing'];
        }

        if (!isset($analysis['emotional_overview']) || !is_array($analysis['emotional_overview'])) {
            $analysis['emotional_overview'] = $fallback['emotional_overview'];
        }

        $analysis['source'] = $payload !== [] ? 'llm_structured' : $fallback['source'];
        $analysis['disclaimer'] = 'This report summarizes recent chat patterns only. It is not a clinical diagnosis and should be read alongside offline behavior.';

        return $analysis;
    }

    private function calculateAgeYears(?string $birthDate): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        try {
            $birth = new DateTimeImmutable($birthDate, AppTime::timezone());
        } catch (\Exception $e) {
            return null;
        }

        return max(0, $birth->diff(AppTime::today())->y);
    }

    private function formatSettings(array $settings): array
    {
        return [
            'child_id' => (int) $settings['child_id'],
            'parent_id' => (int) $settings['parent_id'],
            'auto_generate_enabled' => !empty($settings['auto_generate_enabled']),
            'auto_generate_frequency_days' => (int) ($settings['auto_generate_frequency_days'] ?? 7),
            'auto_generate_window_days' => (int) ($settings['auto_generate_window_days'] ?? 14),
            'next_report_due_at' => AppTime::toIso8601($settings['next_report_due_at'] ?? null),
            'last_report_generated_at' => AppTime::toIso8601($settings['last_report_generated_at'] ?? null),
        ];
    }

    private function formatStoredReportRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'generation_mode' => $row['generation_mode'],
            'status' => $row['status'],
            'window_days' => (int) $row['window_days'],
            'window_start_date' => $row['window_start_date'],
            'window_end_date' => $row['window_end_date'],
            'scope_started_at' => AppTime::toIso8601($row['scope_started_at'] ?? null),
            'scope_ended_at' => AppTime::toIso8601($row['scope_ended_at'] ?? null),
            'report_day' => $row['report_day'] ?? null,
            'sample_message_count' => (int) ($row['sample_message_count'] ?? 0),
            'sample_character_count' => (int) ($row['sample_character_count'] ?? 0),
            'sample_active_days' => (int) ($row['sample_active_days'] ?? 0),
            'message_record_count' => (int) ($row['message_record_count'] ?? 0),
            'confidence' => $row['confidence'] ?? 'none',
            'risk_level' => $row['risk_level'] ?? 'low',
            'headline' => $row['headline'] ?? '',
            'created_at' => AppTime::toIso8601($row['created_at'] ?? null),
            'updated_at' => AppTime::toIso8601($row['updated_at'] ?? null),
        ];
    }

    private function decodeStoredReportRow(array $row): array
    {
        $decoded = json_decode((string) ($row['report_json'] ?? ''), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Stored report payload is invalid.');
        }

        if (!isset($decoded['scope']) || !is_array($decoded['scope'])) {
            $decoded['scope'] = [
                'type' => ($row['generation_mode'] ?? 'manual') === 'auto' ? 'rolling_window' : 'manual_incremental',
                'start_at' => AppTime::toIso8601(
                    $row['scope_started_at']
                    ?? (($row['window_start_date'] ?? null) ? $row['window_start_date'] . ' 00:00:00' : null)
                ),
                'end_at' => AppTime::toIso8601(
                    $row['scope_ended_at']
                    ?? $row['updated_at']
                    ?? $row['created_at']
                    ?? (($row['window_end_date'] ?? null) ? $row['window_end_date'] . ' 23:59:59' : null)
                ),
                'report_day' => $row['report_day'] ?? substr((string) ($row['created_at'] ?? ''), 0, 10),
            ];
        }

        if (!isset($decoded['generation_mode'])) {
            $decoded['generation_mode'] = $row['generation_mode'] ?? 'manual';
        }

        if (!isset($decoded['generated_at'])) {
            $decoded['generated_at'] = AppTime::toIso8601($row['updated_at'] ?? $row['created_at'] ?? null);
        }

        return $decoded;
    }

    private function buildTrendAnalysis(array $reports, array $records, array $messageSummary): array
    {
        $reportCount = count($reports);
        $dateSpan = $this->buildTrendDateSpan($reports, $records, $messageSummary);
        $riskTrajectory = $this->buildRiskTrajectory($reports);
        $recurringRisks = $this->buildTrendList($reports, 'risk_dimensions', true);
        $thinkingTrends = $this->buildTrendList($reports, 'thinking_patterns', true);
        $protectiveTrends = $this->buildTrendList($reports, 'protective_factors', false);
        $topicTrends = $this->buildTrendList($reports, 'topics', false);
        $guidance = $this->mergeTrendGuidance($reports);
        $engagement = $this->buildTrendEngagementSummary($records, $messageSummary);

        $headline = 'Recent saved reports stay mostly low-risk with everyday ups and downs.';
        if ($riskTrajectory['level'] === 'high') {
            $headline = 'Across the selected reports, one or more higher-priority risk themes stay present and need close adult follow-up.';
        } elseif ($riskTrajectory['direction'] === 'rising') {
            $headline = 'Across the selected reports, stress-related signals look stronger more recently than earlier in the period.';
        } elseif ($riskTrajectory['direction'] === 'easing') {
            $headline = 'Across the selected reports, recent patterns look somewhat calmer than earlier in the selected period.';
        } elseif ($riskTrajectory['level'] === 'medium') {
            $headline = 'Across the selected reports, recurring stress themes remain visible and worth watching.';
        }

        $summary = $riskTrajectory['summary'];
        if ($summary === '') {
            $summary = $reportCount <= 1
                ? 'This view summarizes a single saved report rather than a longer trend.'
                : 'This view combines the selected saved reports and highlights repeated themes, shifts in risk, and steady protective factors.';
        }

        return [
            'source' => 'rule_based',
            'generated_at' => AppTime::now()->format(DATE_ATOM),
            'selected_report_count' => $reportCount,
            'date_span' => $dateSpan,
            'headline' => $headline,
            'summary' => $summary,
            'risk_trajectory' => $riskTrajectory,
            'engagement' => $engagement,
            'recurring_risks' => $recurringRisks,
            'thinking_trends' => $thinkingTrends,
            'protective_trends' => $protectiveTrends,
            'topic_trends' => $topicTrends,
            'parent_guidance' => $guidance,
        ];
    }

    private function buildTrendDateSpan(array $reports, array $records, array $messageSummary): array
    {
        $starts = [];
        $ends = [];

        foreach ($reports as $index => $report) {
            $scope = $report['scope'] ?? [];
            $record = $records[$index] ?? [];
            $starts[] = $scope['start_at'] ?? $record['scope_started_at'] ?? $record['created_at'] ?? null;
            $ends[] = $scope['end_at'] ?? $record['scope_ended_at'] ?? $record['updated_at'] ?? $record['created_at'] ?? null;
        }

        if (!empty($messageSummary['first_message_at'])) {
            $starts[] = AppTime::toIso8601($messageSummary['first_message_at']);
        }
        if (!empty($messageSummary['last_message_at'])) {
            $ends[] = AppTime::toIso8601($messageSummary['last_message_at']);
        }

        $starts = array_values(array_filter($starts));
        $ends = array_values(array_filter($ends));

        sort($starts);
        sort($ends);

        return [
            'start' => $starts[0] ?? null,
            'end' => $ends !== [] ? $ends[count($ends) - 1] : null,
        ];
    }

    private function buildRiskTrajectory(array $reports): array
    {
        $weights = [];
        foreach ($reports as $report) {
            $weights[] = $this->levelWeight(
                (string) ($report['risk_level'] ?? $this->riskLevelFromAnalysis($report['analysis'] ?? []))
            );
        }

        $latestWeight = $weights !== [] ? $weights[count($weights) - 1] : 0;
        $latestLevel = $this->weightToLevel($latestWeight);
        if (count($weights) <= 1) {
            return [
                'direction' => 'single_snapshot',
                'level' => $latestLevel,
                'summary' => 'This selection contains one saved report, so it should be read as a snapshot rather than a multi-report trend.',
            ];
        }

        $split = max(1, intdiv(count($weights), 2));
        $early = array_slice($weights, 0, $split);
        $recent = array_slice($weights, $split);
        $earlyAverage = array_sum($early) / max(1, count($early));
        $recentAverage = array_sum($recent) / max(1, count($recent));
        $delta = $recentAverage - $earlyAverage;

        $direction = 'stable';
        if ($delta >= 0.35) {
            $direction = 'rising';
        } elseif ($delta <= -0.35) {
            $direction = 'easing';
        }

        $summary = match ($direction) {
            'rising' => 'Compared with the earlier selected reports, the more recent reports carry somewhat stronger risk signals.',
            'easing' => 'Compared with the earlier selected reports, the more recent reports look somewhat calmer overall.',
            default => 'Across the selected reports, overall risk level looks fairly steady rather than clearly rising or easing.',
        };

        return [
            'direction' => $direction,
            'level' => $latestLevel,
            'summary' => $summary,
        ];
    }

    private function buildTrendList(array $reports, string $listKey, bool $withLevel): array
    {
        $counts = [];

        foreach ($reports as $report) {
            $items = $report['analysis'][$listKey] ?? [];
            if (!is_array($items)) {
                continue;
            }

            $seen = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $name = trim((string) ($item['name'] ?? $item['title'] ?? ''));
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;

                if (!isset($counts[$name])) {
                    $counts[$name] = [
                        'name' => $name,
                        'reports' => 0,
                        'summary' => trim((string) ($item['summary'] ?? $item['why_it_matters'] ?? $item['evidence'] ?? '')),
                        'level' => 'low',
                        'weight' => 0,
                    ];
                }

                $counts[$name]['reports']++;
                if ($counts[$name]['summary'] === '' && !empty($item['summary'])) {
                    $counts[$name]['summary'] = trim((string) $item['summary']);
                }

                if ($withLevel) {
                    $level = (string) ($item['level'] ?? 'low');
                    $weight = $this->levelWeight($level);
                    if ($weight > $counts[$name]['weight']) {
                        $counts[$name]['weight'] = $weight;
                        $counts[$name]['level'] = $level;
                    }
                }
            }
        }

        usort($counts, function (array $left, array $right) {
            if ($left['reports'] === $right['reports']) {
                return $right['weight'] <=> $left['weight'];
            }

            return $right['reports'] <=> $left['reports'];
        });

        $items = array_slice(array_values($counts), 0, 6);
        return array_map(function (array $item) use ($withLevel, $reports) {
            $summary = $item['summary'] !== ''
                ? $item['summary']
                : "Appeared in {$item['reports']} selected report(s).";

            $summary = rtrim($summary, '.');
            $summary .= " Seen in {$item['reports']} of " . count($reports) . ' selected report(s).';

            $formatted = [
                'name' => $item['name'],
                'summary' => $summary,
                'reports' => $item['reports'],
            ];

            if ($withLevel) {
                $formatted['level'] = $item['level'];
            }

            return $formatted;
        }, $items);
    }

    private function buildTrendEngagementSummary(array $records, array $messageSummary): array
    {
        $manualCount = 0;
        $autoCount = 0;

        foreach ($records as $record) {
            if (($record['generation_mode'] ?? 'manual') === 'auto') {
                $autoCount++;
            } else {
                $manualCount++;
            }
        }

        $summary = 'The selected reports span ' . count($records) . ' saved snapshot(s).';
        if (($messageSummary['child_message_count'] ?? 0) > 0) {
            $summary .= ' Under those reports, there are '
                . (int) $messageSummary['child_message_count']
                . ' distinct child-authored message(s) retained for internal analysis.';
        }

        return [
            'summary' => $summary,
            'manual_reports' => $manualCount,
            'auto_reports' => $autoCount,
            'active_days' => (int) ($messageSummary['active_days'] ?? 0),
            'child_message_count' => (int) ($messageSummary['child_message_count'] ?? 0),
            'assistant_message_count' => (int) ($messageSummary['assistant_message_count'] ?? 0),
            'total_message_count' => (int) ($messageSummary['total_message_count'] ?? 0),
        ];
    }

    private function mergeTrendGuidance(array $reports): array
    {
        $counts = [];
        foreach ($reports as $report) {
            $guidanceItems = $report['analysis']['parent_guidance'] ?? [];
            if (!is_array($guidanceItems)) {
                continue;
            }

            foreach ($guidanceItems as $item) {
                $text = trim((string) $item);
                if ($text === '') {
                    continue;
                }

                $counts[$text] = ($counts[$text] ?? 0) + 1;
            }
        }

        arsort($counts);
        return array_slice(array_keys($counts), 0, 6);
    }

    private function weightToLevel(int $weight): string
    {
        return match (true) {
            $weight >= 3 => 'high',
            $weight >= 2 => 'medium',
            default => 'low',
        };
    }

    private function determineLevel(string $baseLevel, int $signalCount, int $dayCount): ?string
    {
        if ($signalCount <= 0) {
            return null;
        }

        if ($baseLevel === 'high') {
            return 'high';
        }

        if ($baseLevel === 'medium') {
            if ($signalCount >= 3 || $dayCount >= 2) {
                return 'medium';
            }

            return 'low';
        }

        if ($signalCount >= 2 || $dayCount >= 2) {
            return 'low';
        }

        return null;
    }

    private function appendPersistenceNote(string $text, int $signalCount, int $dayCount): string
    {
        if ($signalCount <= 0) {
            return $text;
        }

        return $text . " Seen in {$signalCount} matched message(s) across {$dayCount} day(s).";
    }

    private function levelWeight(string $level): int
    {
        return match ($level) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    private function riskLevelFromAnalysis(array $analysis): string
    {
        $levels = [];
        foreach (($analysis['alerts'] ?? []) as $alert) {
            $levels[] = (string) ($alert['level'] ?? 'low');
        }
        foreach (($analysis['risk_dimensions'] ?? []) as $dimension) {
            $levels[] = (string) ($dimension['level'] ?? 'low');
        }

        if (in_array('high', $levels, true)) {
            return 'high';
        }
        if (in_array('medium', $levels, true)) {
            return 'medium';
        }

        return 'low';
    }

    private function riskCatalog(): array
    {
        return [
            [
                'id' => 'self_harm',
                'title' => 'Self-harm or suicidal language',
                'base_level' => 'high',
                'patterns' => ['想死', '不想活', '自杀', '伤害自己', '结束生命', 'kill myself', 'want to die', 'suicide', 'hurt myself', 'self harm'],
                'summary' => 'Recent chat includes language related to not wanting to live or hurting the self.',
                'evidence' => 'This sample includes self-directed harm language and should be treated as an immediate safety check, not a wait-and-see issue.',
                'why_it_matters' => 'Self-harm or suicidal language can reflect acute distress and needs prompt adult attention.',
                'parent_action' => 'Check on current safety immediately. If there is any current danger or uncertainty, seek urgent local crisis or emergency support right away.',
            ],
            [
                'id' => 'harm_to_others',
                'title' => 'Aggression or harm-to-others language',
                'base_level' => 'high',
                'patterns' => ['报复', '打死', '伤害别人', 'kill him', 'kill her', 'hurt someone', 'revenge', 'weapon', '刀', '枪'],
                'summary' => 'Recent chat includes revenge-focused or harm-to-others language.',
                'evidence' => 'The wording suggests aggressive intent, fantasies of retaliation, or focus on harming someone else.',
                'why_it_matters' => 'Threat or revenge language needs adult follow-up, especially if it appears alongside offline conflict or access to weapons.',
                'parent_action' => 'Ask about the conflict calmly, reduce access to dangerous items if relevant, and escalate quickly if there is any real-world threat or planning.',
            ],
            [
                'id' => 'unsafe_contact',
                'title' => 'Secrecy, unsafe contact, or boundary risk',
                'base_level' => 'high',
                'patterns' => ['不要告诉爸妈', '保密', '见网友', '陌生人', '发照片', '裸照', 'don\'t tell your parents', 'secret chat', 'met online', 'send pics', 'nudes'],
                'summary' => 'Recent chat includes secrecy around adults, online contact, or requests to hide interactions.',
                'evidence' => 'The child may be describing or rehearsing boundary-crossing or hidden contact that needs adult review.',
                'why_it_matters' => 'Secrecy and online-contact risk can signal grooming, coercion, or unsafe peer pressure.',
                'parent_action' => 'Review recent online activity and safety rules promptly, and move quickly if there is any sign of coercion, secrecy with adults, or pressure to share private content.',
            ],
            [
                'id' => 'bullying_conflict',
                'title' => 'Bullying, exclusion, or peer conflict',
                'base_level' => 'medium',
                'patterns' => ['被欺负', '没人和我玩', '他们都笑我', '排挤', 'bully', 'picked on', 'left out', 'they hate me', 'mean to me'],
                'summary' => 'Recent chat points to peer conflict, teasing, or feeling excluded.',
                'evidence' => 'The sample includes signs of social stress, rejection, teasing, or tension with peers.',
                'why_it_matters' => 'Peer stress can affect mood, school functioning, and safety, especially when it becomes persistent.',
                'parent_action' => 'Ask about school, friendships, and who feels safe to talk to. If the pattern continues, check in with school adults or other trusted caregivers.',
            ],
            [
                'id' => 'hopelessness',
                'title' => 'Hopeless or low-self-worth language',
                'base_level' => 'medium',
                'patterns' => ['没用', '没人喜欢我', '没有意义', '讨厌自己', 'worthless', 'useless', 'nothing matters', 'no one cares', 'i hate myself'],
                'summary' => 'Recent chat includes discouraged, rejected, or harsh self-worth language.',
                'evidence' => 'The sample suggests the child may be framing themselves or their future in a strongly negative way.',
                'why_it_matters' => 'Persistent hopelessness or harsh self-judgment can be an early warning sign of emotional distress.',
                'parent_action' => 'Check how long these feelings have been present, whether they are affecting sleep or school, and whether the child still finds comfort, interest, or connection in daily life.',
            ],
            [
                'id' => 'anxiety_fear',
                'title' => 'Fear, anxiety, or overwhelm',
                'base_level' => 'medium',
                'patterns' => ['害怕', '担心', '焦虑', '紧张', 'panic', 'anxious', 'afraid', 'worried', 'terrified'],
                'summary' => 'Recent chat repeatedly expresses fear, worry, or feeling overwhelmed.',
                'evidence' => 'The sample includes repeated worry-based or fear-based wording.',
                'why_it_matters' => 'Recurring anxious language may track with school pressure, social fear, or broader emotional overload.',
                'parent_action' => 'Ask what situations feel biggest right now, and watch for avoidance, sleep disruption, or physical complaints that rise with stress.',
            ],
            [
                'id' => 'sleep_body',
                'title' => 'Sleep, exhaustion, or body/food strain',
                'base_level' => 'low',
                'patterns' => ['睡不着', '失眠', '好累', '不想起床', '太胖', '不想吃', 'can\'t sleep', 'insomnia', 'so tired', 'skip meals', 'fat'],
                'summary' => 'Recent chat mentions sleep disruption, exhaustion, or body/food concerns.',
                'evidence' => 'The sample suggests strain around sleep, energy, or body-related stress.',
                'why_it_matters' => 'Sleep and body-image stress often connect to broader mood, anxiety, or self-esteem issues.',
                'parent_action' => 'Check bedtime patterns, appetite, morning energy, and whether body-related worries are becoming more frequent or more rigid.',
            ],
            [
                'id' => 'risky_behavior',
                'title' => 'Substances, dangerous dares, or risky behavior',
                'base_level' => 'medium',
                'patterns' => ['抽烟', '喝酒', '毒品', '挑战', 'sneak out', 'vape', 'weed', 'drunk', 'drug', 'dangerous challenge'],
                'summary' => 'Recent chat includes experimentation, dangerous dares, or risky behavior themes.',
                'evidence' => 'The sample mentions substances, sneaking behavior, or unsafe challenge-type behavior.',
                'why_it_matters' => 'Risk-taking can rise during periods of peer pressure, impulsivity, or emotional strain.',
                'parent_action' => 'Ask who the child is with, what pressure exists, and whether there is offline access to substances, dares, or unsafe environments.',
            ],
        ];
    }

    private function thinkingPatternCatalog(): array
    {
        return [
            [
                'id' => 'all_or_nothing',
                'title' => 'All-or-nothing thinking',
                'base_level' => 'low',
                'patterns' => ['永远', '总是', 'everyone hates me', 'never works', 'all my fault', 'nothing ever'],
                'summary' => 'Recent chat sometimes frames problems in very absolute or total terms.',
                'evidence' => 'The child may be collapsing a complex situation into rigid “always / never / everyone” language.',
            ],
            [
                'id' => 'harsh_self_talk',
                'title' => 'Harsh self-talk',
                'base_level' => 'medium',
                'patterns' => ['我好差', '我真笨', '讨厌自己', 'i am stupid', 'i hate myself', 'i am bad', 'i am useless'],
                'summary' => 'Recent chat includes self-critical or shaming ways of describing the self.',
                'evidence' => 'The child may be using unusually harsh language about their own worth, competence, or likeability.',
            ],
            [
                'id' => 'revenge_framing',
                'title' => 'Revenge or payback framing',
                'base_level' => 'medium',
                'patterns' => ['报复', '让他付出代价', 'revenge', 'make them pay', 'get back at'],
                'summary' => 'Recent chat sometimes frames conflict in terms of payback or retaliation.',
                'evidence' => 'The child may be rehearsing revenge-based responses instead of repair or support-seeking.',
            ],
            [
                'id' => 'avoidance',
                'title' => 'Avoidance or withdrawal framing',
                'base_level' => 'low',
                'patterns' => ['不想见人', '不想上学', '躲起来', 'stay away', 'hide', 'don\'t want to go to school'],
                'summary' => 'Recent chat sometimes leans toward hiding, avoiding, or pulling away from people or situations.',
                'evidence' => 'The child may be describing avoidance as the main way to cope with stress.',
            ],
        ];
    }

    private function protectiveCatalog(): array
    {
        return [
            [
                'id' => 'help_seeking',
                'title' => 'Help-seeking behavior',
                'patterns' => ['告诉妈妈', '告诉老师', '找人帮忙', 'ask for help', 'talk to my mom', 'talk to teacher', 'need help'],
                'summary' => 'The child mentions asking trusted adults for help or being willing to talk.',
            ],
            [
                'id' => 'connection',
                'title' => 'Connection with supportive people',
                'patterns' => ['朋友陪我', '妈妈安慰我', '老师帮我', 'my friend helped', 'my mom helped', 'teacher supported'],
                'summary' => 'The child refers to at least some supportive connection with trusted people.',
            ],
            [
                'id' => 'curiosity',
                'title' => 'Curiosity and learning',
                'patterns' => ['为什么', '怎么做', '想学', 'how do i', 'learn', 'build', 'make', 'science'],
                'summary' => 'The child still shows curiosity, learning energy, or constructive interest in topics.',
            ],
            [
                'id' => 'empathy',
                'title' => 'Empathy or repair',
                'patterns' => ['谢谢', '对不起', '我想帮助', 'thank you', 'sorry', 'help them', 'care about'],
                'summary' => 'The child shows moments of empathy, apology, or interest in repairing relationships.',
            ],
        ];
    }
}
