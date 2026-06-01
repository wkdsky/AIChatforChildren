<?php

namespace Utils;

use App\Models\ChildReport;
use Core\Config;
use DateInterval;
use DateTimeImmutable;

class ChildReportService
{
    private const MIN_REPORT_DAYS = 1;
    private const MAX_REPORT_DAYS = 90;
    private const REPORT_REQUIRED_KEYS = [
        'headline',
        'sample_confidence',
        'disclaimer',
        'topic_overview',
        'event_blocks',
        'topics',
        'interests',
        'emotional_overview',
        'wellbeing',
        'risk_dimensions',
        'thinking_patterns',
        'protective_factors',
        'parent_guidance',
        'alerts',
    ];
    private const TREND_REQUIRED_KEYS = [
        'headline',
        'summary',
        'risk_trajectory',
        'recurring_risks',
        'thinking_trends',
        'protective_trends',
        'topic_trends',
        'parent_guidance',
    ];
    private const CONTENT_MIN_MESSAGES = 10;
    private const CONTENT_MIN_CHARACTERS = 120;
    private const CONTENT_MIN_ACTIVE_DAYS = 0;
    private const INCREMENT_MIN_MESSAGES = 5;
    private const INCREMENT_MIN_CHARACTERS = 60;
    private const INCREMENT_MIN_ACTIVE_DAYS = 0;
    private const TRANSCRIPT_MAX_MESSAGES = 220;
    private const TRANSCRIPT_MAX_MESSAGE_CHARS = 420;
    private const TRANSCRIPT_MAX_TOTAL_CHARS = 22000;
    private const CHILD_MESSAGE_WEIGHT = 1.0;
    private const ASSISTANT_MESSAGE_WEIGHT = 0.2;
    private const REPORT_PROMPT_VERSION = 'ai-report-v6';
    private const TREND_PROMPT_VERSION = 'ai-trend-v1';

    private ChildReport $reportModel;
    private ?string $lastAnalysisFailureReason = null;

    public function __construct(?ChildReport $reportModel = null)
    {
        $this->reportModel = $reportModel ?? new ChildReport();
    }

    public function normalizeDays($value, int $default = 14): int
    {
        $days = (int) $value;
        if ($days < self::MIN_REPORT_DAYS || $days > self::MAX_REPORT_DAYS) {
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
        $child = $this->getChildOrFail($childId, $parentId);
        $this->reportModel->deleteOrphanedReportMessages();
        $this->ensureAutoReportsForChild($childId, $parentId);

        return [
            'settings' => $this->formatSettings($this->getSettings($childId, $parentId)),
            'usage_report' => $this->buildUsageHabitReport($child),
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
        $periodDays = $this->normalizeDays(
            $input['auto_generate_frequency_days']
                ?? $input['auto_generate_period_days']
                ?? $input['auto_generate_window_days']
                ?? 7,
            7
        );
        $now = AppTime::now();

        $existing = $this->getSettings($childId, $parentId);
        $existingPeriodDays = $this->resolveAutoReportPeriodDays($existing, 7);
        $shouldResetSchedule = $enabled && (
            empty($existing['auto_generate_enabled'])
            || empty($existing['next_report_due_at'])
            || $existingPeriodDays !== $periodDays
        );
        $nextDueAt = null;

        if ($enabled) {
            $nextDueAt = $shouldResetSchedule
                ? $now->add(new DateInterval('P' . $periodDays . 'D'))->format('Y-m-d H:i:s')
                : ($existing['next_report_due_at'] ?? null);
        }

        $saved = $this->reportModel->upsertReportSettings($childId, $parentId, [
            'auto_generate_enabled' => $enabled,
            'auto_generate_frequency_days' => $periodDays,
            'auto_generate_window_days' => $periodDays,
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
        $this->reportModel->deleteOrphanedReportMessages();

        return $mode === 'auto'
            ? $this->generateAutoReportBundle($child, $parentId, $days)
            : $this->generateManualReportBundle($child, $parentId, $days);
    }

    public function getCumulativeAnalysis(int $childId, int $parentId, array $reportIds): array
    {
        $child = $this->getChildOrFail($childId, $parentId);
        $this->reportModel->deleteOrphanedReportMessages();
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
        $retainedMessages = $this->dedupeRetainedMessages(
            $this->reportModel->getRetainedMessagesForReports(
                $childId,
                $parentId,
                array_column($rows, 'id')
            )
        );

        $analysis = $this->buildTrendAnalysis($child, $reports, $records, $messageSummary, $retainedMessages);

        return [
            'analysis' => $analysis,
            'reports' => $records,
        ];
    }

    public function runPromptSmokeTest(array $fixture, bool $callModel = true): array
    {
        $child = $fixture['child'] ?? [
            'birth_date' => '2016-01-01',
            'gender' => 'unknown',
            'last_login_at' => null,
        ];

        $scope = $fixture['scope'] ?? $this->buildScopeDescriptor(
            AppTime::now()->sub(new DateInterval('P7D'))->format('Y-m-d H:i:s'),
            AppTime::now()->format('Y-m-d H:i:s'),
            'manual_incremental',
            AppTime::today()->format('Y-m-d')
        );

        $messages = $this->filterReportMessages($fixture['messages'] ?? []);
        $incrementMessages = $this->filterReportMessages($fixture['increment_messages'] ?? $messages);
        $childMessages = $this->filterMessagesByRole($messages, 'user');
        $incrementChildMessages = $this->filterMessagesByRole($incrementMessages, 'user');
        $summary = $fixture['summary'] ?? $this->buildSyntheticSummaryFromMessages($messages, $child);
        $readiness = $this->computeContentReadiness($childMessages, (int) ($scope['days'] ?? 7), $incrementChildMessages);
        $packet = $this->buildAiReportPacket($child, $summary, $scope, $readiness, $messages);
        $previewAnalysis = $this->buildPreviewAnalysis($child, $summary, $scope, $readiness);

        $result = [
            'prompt_version' => self::REPORT_PROMPT_VERSION,
            'system_prompt' => $this->buildReportSystemPrompt(),
            'user_packet' => $packet,
            'readiness' => $readiness,
            'preview_analysis' => $previewAnalysis,
        ];

        if ($callModel) {
            $analysis = $this->requestLlmAnalysis($packet);
            $result['raw_analysis'] = $analysis;
            $result['normalized_analysis'] = $analysis !== null
                ? $this->normalizeAnalysisPayload($analysis, $previewAnalysis)
                : null;
            $result['validation'] = $this->validateAnalysisPayload($analysis ?? []);
            $result['failure_reason'] = $analysis === null ? $this->lastAnalysisFailureReason : null;
        }

        return $result;
    }

    private function buildUsageHabitReport(array $child): array
    {
        $childId = (int) ($child['id'] ?? 0);
        $today = AppTime::today();
        $dailyStart = $today->sub(new DateInterval('P14D'));
        $currentWeekStart = $today->sub(new DateInterval('P' . (((int) $today->format('N')) - 1) . 'D'));
        $weeklyStart = $currentWeekStart->sub(new DateInterval('P21D'));
        $currentMonthStart = $today->modify('first day of this month');
        $monthlyStart = $currentMonthStart->sub(new DateInterval('P2M'));

        $globalStart = $dailyStart;
        foreach ([$weeklyStart, $monthlyStart] as $candidate) {
            if ($candidate < $globalStart) {
                $globalStart = $candidate;
            }
        }

        $dailyMap = $this->buildUsageDayMap(
            $childId,
            $globalStart->format('Y-m-d'),
            $today->format('Y-m-d')
        );

        return [
            'source' => 'usage_habit',
            'source_detail' => 'This updates automatically once a day from recorded login and chat activity. Per-login duration figures are estimated from daily totals, and days with usage but missing login counts are treated as one inferred session.',
            'updated_at' => AppTime::now()->format(DATE_ATOM),
            'default_period' => 'day',
            'child' => [
                'id' => $childId,
                'age_years' => $this->calculateAgeYears($child['birth_date'] ?? null),
                'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
            ],
            'ranges' => [
                'day' => $this->buildUsageDailyRange($dailyMap, $dailyStart, $today),
                'week' => $this->buildUsageWeeklyRange($dailyMap, $currentWeekStart),
                'month' => $this->buildUsageMonthlyRange($dailyMap, $currentMonthStart, $today),
            ],
        ];
    }

    private function buildUsageDayMap(int $childId, string $startDate, string $endDate): array
    {
        $map = [];
        $cursor = new DateTimeImmutable($startDate, AppTime::timezone());
        $end = new DateTimeImmutable($endDate, AppTime::timezone());

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $map[$key] = [
                'date' => $key,
                'login_count' => 0,
                'used_minutes' => 0,
                'child_message_count' => 0,
                'assistant_message_count' => 0,
                'conversation_count' => 0,
                'first_login_at' => null,
                'last_login_at' => null,
            ];
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        foreach ($this->reportModel->getDailyLogins($childId, $startDate, $endDate) as $row) {
            $date = $row['activity_date'] ?? null;
            if (!$date || !isset($map[$date])) {
                continue;
            }

            $map[$date]['login_count'] = (int) ($row['login_count'] ?? 0);
            $map[$date]['first_login_at'] = AppTime::toIso8601($row['first_login_at'] ?? null);
            $map[$date]['last_login_at'] = AppTime::toIso8601($row['last_login_at'] ?? null);
        }

        foreach ($this->reportModel->getDailyUsage($childId, $startDate, $endDate) as $row) {
            $date = $row['activity_date'] ?? null;
            if (!$date || !isset($map[$date])) {
                continue;
            }

            $map[$date]['used_minutes'] = (int) ($row['used_minutes'] ?? 0);
        }

        foreach ($this->reportModel->getDailyMessages($childId, $startDate, $endDate) as $row) {
            $date = $row['activity_date'] ?? null;
            if (!$date || !isset($map[$date])) {
                continue;
            }

            $map[$date]['child_message_count'] = (int) ($row['child_message_count'] ?? 0);
            $map[$date]['assistant_message_count'] = (int) ($row['assistant_message_count'] ?? 0);
        }

        foreach ($this->reportModel->getDailyConversations($childId, $startDate, $endDate) as $row) {
            $date = $row['activity_date'] ?? null;
            if (!$date || !isset($map[$date])) {
                continue;
            }

            $map[$date]['conversation_count'] = (int) ($row['conversation_count'] ?? 0);
        }

        return $map;
    }

    private function buildUsageDailyRange(array $dailyMap, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $buckets = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $label = $cursor->format('m-d');
            $buckets[] = $this->buildUsageBucket(
                $dailyMap,
                $cursor,
                $cursor,
                $label,
                'day-' . $cursor->format('Y-m-d')
            );
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $this->buildUsageRangePayload('day', 'Last 15 Days', $buckets, $start, $end, $dailyMap);
    }

    private function buildUsageWeeklyRange(array $dailyMap, DateTimeImmutable $currentWeekStart): array
    {
        $buckets = [];
        $rangeStart = $currentWeekStart->sub(new DateInterval('P21D'));
        for ($index = 3; $index >= 0; $index--) {
            $start = $currentWeekStart->sub(new DateInterval('P' . ($index * 7) . 'D'));
            $end = $start->add(new DateInterval('P6D'));
            $buckets[] = $this->buildUsageBucket(
                $dailyMap,
                $start,
                $end,
                $start->format('m-d') . ' to ' . $end->format('m-d'),
                'week-' . $start->format('Y-m-d')
            );
        }

        return $this->buildUsageRangePayload(
            'week',
            'Last 4 Weeks',
            $buckets,
            $rangeStart,
            $currentWeekStart->add(new DateInterval('P6D')),
            $dailyMap
        );
    }

    private function buildUsageMonthlyRange(array $dailyMap, DateTimeImmutable $currentMonthStart, DateTimeImmutable $today): array
    {
        $buckets = [];
        $rangeStart = $currentMonthStart->sub(new DateInterval('P2M'));
        for ($index = 2; $index >= 0; $index--) {
            $start = $currentMonthStart->sub(new DateInterval('P' . $index . 'M'));
            $end = $start->add(new DateInterval('P1M'))->sub(new DateInterval('P1D'));
            if ($end > $today) {
                $end = $today;
            }

            $buckets[] = $this->buildUsageBucket(
                $dailyMap,
                $start,
                $end,
                $start->format('Y-m'),
                'month-' . $start->format('Y-m')
            );
        }

        return $this->buildUsageRangePayload('month', 'Last 3 Months', $buckets, $rangeStart, $today, $dailyMap);
    }

    private function buildUsageRangePayload(
        string $period,
        string $title,
        array $buckets,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $dailyMap
    ): array {
        return [
            'period' => $period,
            'title' => $title,
            'bucket_count' => count($buckets),
            'summary' => $this->buildUsageBucket($dailyMap, $start, $end, $title, $period . '-summary'),
            'buckets' => $buckets,
        ];
    }

    private function buildUsageBucket(
        array $dailyMap,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $label,
        string $key
    ): array {
        $totals = [
            'login_count' => 0,
            'estimated_session_count' => 0,
            'used_minutes' => 0,
            'child_message_count' => 0,
            'assistant_message_count' => 0,
            'conversation_count' => 0,
            'active_days' => 0,
        ];
        $earliestLoginAt = null;
        $latestLoginAt = null;
        $distribution = [];
        $maxEstimatedMinutes = 0.0;

        $cursor = $start;
        while ($cursor <= $end) {
            $dateKey = $cursor->format('Y-m-d');
            $row = $dailyMap[$dateKey] ?? [
                'login_count' => 0,
                'used_minutes' => 0,
                'child_message_count' => 0,
                'assistant_message_count' => 0,
                'conversation_count' => 0,
                'first_login_at' => null,
                'last_login_at' => null,
            ];

            $loginCount = (int) ($row['login_count'] ?? 0);
            $usedMinutes = (int) ($row['used_minutes'] ?? 0);
            $childMessages = (int) ($row['child_message_count'] ?? 0);
            $assistantMessages = (int) ($row['assistant_message_count'] ?? 0);
            $conversationCount = (int) ($row['conversation_count'] ?? 0);
            $estimatedSessionCount = max(
                $loginCount,
                ($usedMinutes > 0 || $childMessages > 0 || $assistantMessages > 0 || $conversationCount > 0) ? 1 : 0
            );

            $totals['login_count'] += $loginCount;
            $totals['estimated_session_count'] += $estimatedSessionCount;
            $totals['used_minutes'] += $usedMinutes;
            $totals['child_message_count'] += $childMessages;
            $totals['assistant_message_count'] += $assistantMessages;
            $totals['conversation_count'] += $conversationCount;

            if ($loginCount > 0 || $usedMinutes > 0 || $childMessages > 0) {
                $totals['active_days']++;
            }

            if ($estimatedSessionCount > 0) {
                $estimatedMinutes = round($usedMinutes / max(1, $estimatedSessionCount), 1);
                $distribution[] = [
                    'value' => $estimatedMinutes,
                    'weight' => $estimatedSessionCount,
                ];
                if ($estimatedMinutes > $maxEstimatedMinutes) {
                    $maxEstimatedMinutes = $estimatedMinutes;
                }
            }

            $firstLoginAt = $row['first_login_at'] ?? null;
            if ($firstLoginAt !== null && ($earliestLoginAt === null || $firstLoginAt < $earliestLoginAt)) {
                $earliestLoginAt = $firstLoginAt;
            }

            $lastLoginAt = $row['last_login_at'] ?? null;
            if ($lastLoginAt !== null && ($latestLoginAt === null || $lastLoginAt > $latestLoginAt)) {
                $latestLoginAt = $lastLoginAt;
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        $avgMinutesPerLogin = $totals['estimated_session_count'] > 0
            ? round($totals['used_minutes'] / $totals['estimated_session_count'], 1)
            : 0.0;
        $avgChildMessagesPerLogin = $totals['estimated_session_count'] > 0
            ? round($totals['child_message_count'] / $totals['estimated_session_count'], 1)
            : 0.0;

        return [
            'key' => $key,
            'label' => $label,
            'start' => AppTime::toIso8601($start->format('Y-m-d 00:00:00')),
            'end' => AppTime::toIso8601($end->format('Y-m-d 23:59:59')),
            'login_count' => $totals['login_count'],
            'estimated_session_count' => $totals['estimated_session_count'],
            'used_minutes' => $totals['used_minutes'],
            'child_message_count' => $totals['child_message_count'],
            'assistant_message_count' => $totals['assistant_message_count'],
            'conversation_count' => $totals['conversation_count'],
            'active_days' => $totals['active_days'],
            'avg_estimated_minutes_per_login' => $avgMinutesPerLogin,
            'median_estimated_minutes_per_login' => $this->weightedMedian($distribution),
            'max_estimated_minutes_per_login' => round($maxEstimatedMinutes, 1),
            'avg_child_messages_per_login' => $avgChildMessagesPerLogin,
            'earliest_login_at' => $earliestLoginAt,
            'latest_login_at' => $latestLoginAt,
        ];
    }

    private function weightedMedian(array $distribution): float
    {
        if ($distribution === []) {
            return 0.0;
        }

        usort($distribution, static function (array $left, array $right): int {
            return ($left['value'] <=> $right['value']);
        });

        $totalWeight = 0;
        foreach ($distribution as $item) {
            $totalWeight += (int) ($item['weight'] ?? 0);
        }

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $threshold = (int) ceil($totalWeight / 2);
        $runningWeight = 0;
        foreach ($distribution as $item) {
            $runningWeight += (int) ($item['weight'] ?? 0);
            if ($runningWeight >= $threshold) {
                return round((float) ($item['value'] ?? 0), 1);
            }
        }

        return round((float) ($distribution[count($distribution) - 1]['value'] ?? 0), 1);
    }

    public function runTrendPromptSmokeTest(array $fixture, bool $callModel = true): array
    {
        $child = $fixture['child'] ?? [
            'birth_date' => '2016-01-01',
            'gender' => 'unknown',
            'last_login_at' => null,
        ];
        $reports = $fixture['reports'] ?? [];
        $records = $fixture['records'] ?? [];
        $retainedMessages = $this->dedupeRetainedMessages(
            $this->filterReportMessages($fixture['retained_messages'] ?? [])
        );
        $messageSummary = $fixture['message_summary'] ?? $this->buildTrendMessageSummary($retainedMessages);
        $packet = $this->buildAiTrendPacket($child, $reports, $records, $messageSummary, $retainedMessages);
        $fallback = $this->buildRuleBasedTrendAnalysis($reports, $records, $messageSummary);

        $result = [
            'prompt_version' => self::TREND_PROMPT_VERSION,
            'system_prompt' => $this->buildTrendSystemPrompt(),
            'user_packet' => $packet,
            'fallback_analysis' => $fallback,
        ];

        if ($callModel) {
            $analysis = $this->requestTrendLlmAnalysis($packet);
            $result['raw_analysis'] = $analysis;
            $result['normalized_analysis'] = $analysis !== null
                ? $this->normalizeTrendAnalysisPayload($analysis, $fallback)
                : null;
            $result['validation'] = $this->validateTrendAnalysisPayload($analysis ?? []);
            $result['failure_reason'] = $analysis === null ? $this->lastAnalysisFailureReason : null;
        }

        return $result;
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
                $this->resolveAutoReportPeriodDays($row),
                'auto'
            );

            $results[] = [
                'child_id' => $child['id'],
                'child_name' => $child['name'],
                'report_id' => $bundle['report_record']['id'] ?? null,
                'status' => !empty($bundle['generated'])
                    ? ($bundle['report']['status'] ?? 'ready')
                    : 'skipped',
                'risk_level' => !empty($bundle['generated'])
                    ? ($bundle['report']['risk_level'] ?? 'low')
                    : 'none',
                'message' => $bundle['message'] ?? null,
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
            $this->resolveAutoReportPeriodDays($settings),
            'auto'
        );
    }

    private function generateManualReportBundle(array $child, int $parentId, int $fallbackDays): array
    {
        $childId = (int) $child['id'];
        $scope = $this->buildManualScope($childId, $parentId, $fallbackDays);
        $messages = $this->reportModel->getConversationMessagesBetween($childId, $scope['start_at'], $scope['end_at']);
        $snapshot = $this->buildSnapshotFromScope($child, $scope, 'manual', true, $messages, $messages);

        if (empty($snapshot['content_readiness']['can_generate'])) {
            return $this->buildSkippedGenerationBundle(
                $childId,
                $parentId,
                $snapshot,
                $snapshot['content_readiness']['reason'] ?? 'Not enough new child-authored chat has accumulated yet.'
            );
        }

        $row = $this->storeSnapshot($childId, $parentId, $snapshot, $scope, $messages);

        return [
            'generated' => true,
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

    private function generateAutoReportBundle(array $child, int $parentId, int $days): array
    {
        $childId = (int) $child['id'];
        $settings = $this->getSettings($childId, $parentId);
        $days = $this->normalizeDays($days ?: $this->resolveAutoReportPeriodDays($settings), 7);
        $scope = $this->buildAutoScope($childId, $parentId, $days);
        $messages = $this->reportModel->getConversationMessagesBetween($childId, $scope['start_at'], $scope['end_at']);
        $snapshot = $this->buildSnapshotFromScope($child, $scope, 'auto', true, $messages, $messages);

        if (empty($snapshot['content_readiness']['can_generate'])) {
            $this->advanceAutoSchedule($childId, $parentId, $settings, $days, false);

            return $this->buildSkippedGenerationBundle(
                $childId,
                $parentId,
                $snapshot,
                $snapshot['content_readiness']['reason'] ?? 'Not enough new child-authored chat has accumulated yet.'
            );
        }

        $row = $this->storeSnapshot($childId, $parentId, $snapshot, $scope, $messages);

        $this->advanceAutoSchedule($childId, $parentId, $settings, $days, true);

        return [
            'generated' => true,
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
        ?int $excludeReportId = null
    ): array {
        $now = AppTime::now();
        return $this->buildIncrementalScope(
            $childId,
            $parentId,
            $fallbackDays,
            'manual_incremental',
            $now->format('Y-m-d'),
            $excludeReportId
        );
    }

    private function buildAutoScope(int $childId, int $parentId, int $days): array
    {
        $now = AppTime::now();
        return $this->buildIncrementalScope(
            $childId,
            $parentId,
            $days,
            'auto_incremental',
            $now->format('Y-m-d')
        );
    }

    private function buildIncrementalScope(
        int $childId,
        int $parentId,
        int $fallbackDays,
        string $type,
        string $reportDay,
        ?int $excludeReportId = null
    ): array {
        $now = AppTime::now();
        $settings = $this->getSettings($childId, $parentId);
        $fallbackWindowDays = $this->normalizeDays(
            $fallbackDays ?: $this->resolveAutoReportPeriodDays($settings),
            14
        );

        $scopeStartAt = $this->resolveLatestReportCutoffAt($childId, $parentId, $excludeReportId);
        if ($scopeStartAt === null) {
            $scopeStartAt = $this->buildRollingScope($fallbackWindowDays)['start_at'];
        }

        $scopeEndAt = $now->format('Y-m-d H:i:s');
        if ($scopeStartAt >= $scopeEndAt) {
            $scopeStartAt = $now->sub(new DateInterval('PT1M'))->format('Y-m-d H:i:s');
        }

        return $this->buildScopeDescriptor($scopeStartAt, $scopeEndAt, $type, $reportDay);
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

    private function resolveLatestReportCutoffAt(int $childId, int $parentId, ?int $excludeReportId = null): ?string
    {
        $latest = $excludeReportId !== null
            ? $this->reportModel->getLatestStoredReportExcluding($childId, $parentId, $excludeReportId)
            : $this->reportModel->getLatestStoredReport($childId, $parentId);

        return $this->resolveReportCutoffAt($latest);
    }

    private function resolveReportCutoffAt(?array $report): ?string
    {
        if (!$report) {
            return null;
        }

        return $report['scope_ended_at']
            ?? $report['updated_at']
            ?? $report['created_at']
            ?? null;
    }

    private function buildSkippedGenerationBundle(
        int $childId,
        int $parentId,
        array $snapshot,
        string $message,
        bool $updatedExisting = false
    ): array {
        return [
            'generated' => false,
            'message' => $message,
            'updated_existing' => $updatedExisting,
            'report' => $snapshot,
            'report_record' => null,
            'settings' => $this->formatSettings($this->getSettings($childId, $parentId)),
            'reports' => array_map(
                fn(array $item) => $this->formatStoredReportRow($item),
                $this->reportModel->listStoredReports($childId, $parentId)
            ),
        ];
    }

    private function advanceAutoSchedule(
        int $childId,
        int $parentId,
        array $settings,
        int $days,
        bool $markGenerated
    ): void {
        $frequencyDays = $this->resolveAutoReportPeriodDays($settings, $days ?: 7);
        $nextDueAt = AppTime::now()->add(new DateInterval('P' . $frequencyDays . 'D'))->format('Y-m-d H:i:s');
        $this->reportModel->upsertReportSettings($childId, $parentId, [
            'auto_generate_enabled' => !empty($settings['auto_generate_enabled']),
            'auto_generate_frequency_days' => $frequencyDays,
            'auto_generate_window_days' => $frequencyDays,
            'next_report_due_at' => $nextDueAt,
            'last_report_generated_at' => $markGenerated
                ? AppTime::now()->format('Y-m-d H:i:s')
                : ($settings['last_report_generated_at'] ?? null),
        ]);
    }

    private function filterReportMessages(array $messages): array
    {
        return array_values(array_filter($messages, function (array $message) {
            return in_array((string) ($message['role'] ?? ''), ['user', 'assistant'], true);
        }));
    }

    private function dedupeRetainedMessages(array $messages): array
    {
        $deduped = [];
        $seen = [];

        foreach ($messages as $message) {
            $messageId = (int) ($message['message_id'] ?? 0);
            $key = $messageId > 0
                ? 'message_' . $messageId
                : sha1(
                    implode('|', [
                        (string) ($message['conversation_id'] ?? ''),
                        (string) ($message['role'] ?? ''),
                        (string) ($message['created_at'] ?? ''),
                        (string) ($message['content'] ?? ''),
                    ])
                );

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $message;
        }

        return $deduped;
    }

    private function filterMessagesByRole(array $messages, string $role): array
    {
        return array_values(array_filter($messages, function (array $message) use ($role) {
            return (string) ($message['role'] ?? '') === $role;
        }));
    }

    private function computeMessageStats(array $messages): array
    {
        $activeDays = [];
        $conversationIds = [];
        $characterCount = 0;
        $firstAt = null;
        $lastAt = null;

        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            $characterCount += mb_strlen($content, 'UTF-8');

            $createdAt = (string) ($message['created_at'] ?? '');
            if ($createdAt !== '') {
                $day = substr($createdAt, 0, 10);
                if ($day !== '') {
                    $activeDays[$day] = true;
                }
                $firstAt ??= $createdAt;
                $lastAt = $createdAt;
            }

            $conversationId = (int) ($message['conversation_id'] ?? 0);
            if ($conversationId > 0) {
                $conversationIds[$conversationId] = true;
            }
        }

        return [
            'message_count' => count($messages),
            'character_count' => $characterCount,
            'active_days' => count($activeDays),
            'conversation_count' => count($conversationIds),
            'first_message_at' => $firstAt,
            'last_message_at' => $lastAt,
        ];
    }

    private function buildSyntheticSummaryFromMessages(array $messages, array $child): array
    {
        $childMessages = $this->filterMessagesByRole($messages, 'user');
        $assistantMessages = $this->filterMessagesByRole($messages, 'assistant');
        $allStats = $this->computeMessageStats($messages);
        $childStats = $this->computeMessageStats($childMessages);

        return [
            'total_logins' => 0,
            'total_conversations' => $allStats['conversation_count'],
            'total_messages' => $allStats['message_count'],
            'total_child_messages' => $childStats['message_count'],
            'total_assistant_messages' => count($assistantMessages),
            'total_minutes' => 0,
            'active_days' => $allStats['active_days'],
            'average_minutes_per_active_day' => 0,
            'average_messages_per_active_day' => $allStats['active_days'] > 0
                ? (int) round($childStats['message_count'] / $allStats['active_days'])
                : 0,
            'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
            'peak_day' => null,
            'peak_score' => 0,
        ];
    }

    private function buildTrendMessageSummary(array $messages): array
    {
        $allStats = $this->computeMessageStats($messages);
        $childStats = $this->computeMessageStats($this->filterMessagesByRole($messages, 'user'));
        $assistantStats = $this->computeMessageStats($this->filterMessagesByRole($messages, 'assistant'));

        return [
            'total_message_count' => $allStats['message_count'],
            'child_message_count' => $childStats['message_count'],
            'assistant_message_count' => $assistantStats['message_count'],
            'active_days' => $allStats['active_days'],
            'first_message_at' => $allStats['first_message_at'],
            'last_message_at' => $allStats['last_message_at'],
        ];
    }

    private function buildPreviewAnalysis(array $child, array $summary, array $scope, array $readiness): array
    {
        $headline = !empty($readiness['can_generate'])
            ? 'Current chat scope is ready for AI report generation.'
            : 'More new chat activity is needed before generating another AI report.';

        $summaryText = !empty($readiness['can_generate'])
            ? 'The current report scope meets the chat-volume threshold. When generated, the report will be written by AI from the organized transcript and activity summary.'
            : ($readiness['reason'] ?? 'The current scope does not yet meet the incremental threshold for another AI report.');

        return [
            'source' => 'preview',
            'source_detail' => 'This is only a readiness preview. No AI report has been generated or saved yet.',
            'headline' => $headline,
            'sample_confidence' => $readiness['confidence'] ?? 'none',
            'disclaimer' => 'This report is generated from recent chat and activity context. It is not a diagnosis and should be read together with offline behavior.',
            'topic_overview' => $summaryText,
            'event_blocks' => [],
            'topics' => [],
            'interests' => [],
            'emotional_overview' => [
                'summary' => $summaryText,
                'supporting_signals' => [
                    'Child messages in scope: ' . (int) ($readiness['message_count'] ?? 0),
                    'New child messages since last saved report: ' . (int) ($readiness['increment_message_count'] ?? 0),
                    'Active days in scope: ' . (int) ($readiness['active_days'] ?? 0),
                ],
            ],
            'wellbeing' => [
                'summary' => $summaryText,
                'strengths' => [],
                'watch_points' => [
                    'AI generation waits until enough new child-authored chat has accumulated since the last saved report.',
                ],
            ],
            'risk_dimensions' => [],
            'thinking_patterns' => [],
            'protective_factors' => [],
            'parent_guidance' => [
                'Use saved reports as periodic summaries rather than re-running them after very small chat changes.',
                'Discuss patterns and feelings with the child rather than treating the report as a diagnosis.',
            ],
            'alerts' => [],
            'prompt_version' => self::REPORT_PROMPT_VERSION,
            'scope_overview' => [
                'start_at' => AppTime::toIso8601($scope['start_at'] ?? null),
                'end_at' => AppTime::toIso8601($scope['end_at'] ?? null),
                'total_child_messages' => (int) ($summary['total_child_messages'] ?? 0),
            ],
        ];
    }

    private function validateAnalysisPayload(array $analysis): array
    {
        $errors = [];
        $requiredKeys = self::REPORT_REQUIRED_KEYS;

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $analysis)) {
                $errors[] = "Missing key: {$key}";
            }
        }

        if (isset($analysis['sample_confidence']) && !in_array($analysis['sample_confidence'], ['none', 'low', 'medium', 'high'], true)) {
            $errors[] = 'Invalid sample_confidence value.';
        }

        foreach (['event_blocks', 'topics', 'interests', 'risk_dimensions', 'thinking_patterns', 'protective_factors', 'parent_guidance', 'alerts'] as $key) {
            if (isset($analysis[$key]) && !is_array($analysis[$key])) {
                $errors[] = "{$key} must be an array.";
            }
        }

        if (isset($analysis['emotional_overview']) && !is_array($analysis['emotional_overview'])) {
            $errors[] = 'emotional_overview must be an object.';
        }

        if (isset($analysis['wellbeing']) && !is_array($analysis['wellbeing'])) {
            $errors[] = 'wellbeing must be an object.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    private function buildAiReportPacket(
        array $child,
        array $summary,
        array $scope,
        array $readiness,
        array $messages
    ): array {
        return [
            'prompt_version' => self::REPORT_PROMPT_VERSION,
            'child' => [
                'age_years' => $this->calculateAgeYears($child['birth_date'] ?? null),
                'gender' => $child['gender'] ?? null,
                'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
            ],
            'scope' => [
                'type' => $scope['type'] ?? 'rolling_window',
                'start_at' => AppTime::toIso8601($scope['start_at'] ?? null),
                'end_at' => AppTime::toIso8601($scope['end_at'] ?? null),
                'days' => (int) ($scope['days'] ?? 0),
                'report_day' => $scope['report_day'] ?? null,
            ],
            'activity_summary' => $summary,
            'message_weighting' => $this->buildMessageWeightingMeta(
                (int) ($summary['total_child_messages'] ?? 0),
                (int) ($summary['total_assistant_messages'] ?? 0)
            ),
            'sample_readiness' => $readiness,
            'transcript_bundle' => $this->buildTranscriptBundle($messages),
        ];
    }

    private function buildTranscriptBundle(array $messages): array
    {
        $grouped = [];
        $includedMessages = 0;
        $omittedMessages = 0;
        $usedChars = 0;
        $childMessageCount = 0;
        $assistantMessageCount = 0;
        $weightedSignalScore = 0.0;

        foreach ($messages as $message) {
            $conversationId = (int) ($message['conversation_id'] ?? 0);
            $key = $conversationId > 0 ? $conversationId : ('unknown_' . count($grouped));
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'conversation_id' => $conversationId > 0 ? $conversationId : null,
                    'started_at' => AppTime::toIso8601($message['created_at'] ?? null),
                    'message_count' => 0,
                    'child_message_count' => 0,
                    'assistant_message_count' => 0,
                    'weighted_signal_score' => 0.0,
                    'messages' => [],
                ];
            }

            $role = (string) ($message['role'] ?? 'user');
            $roleWeight = $this->messageRoleWeight($role);
            $normalizedContent = $this->normalizeTranscriptContent((string) ($message['content'] ?? ''));
            $truncatedContent = $this->truncateTranscriptContent(
                $normalizedContent,
                $role === 'assistant'
                    ? max(120, (int) floor(self::TRANSCRIPT_MAX_MESSAGE_CHARS * 0.55))
                    : self::TRANSCRIPT_MAX_MESSAGE_CHARS
            );
            $contentLength = mb_strlen($truncatedContent, 'UTF-8');

            if (
                $includedMessages < self::TRANSCRIPT_MAX_MESSAGES
                && ($usedChars + $contentLength) <= self::TRANSCRIPT_MAX_TOTAL_CHARS
            ) {
                $grouped[$key]['messages'][] = [
                    'timestamp' => AppTime::toIso8601($message['created_at'] ?? null),
                    'role' => $role,
                    'content' => $truncatedContent,
                ];
                $includedMessages++;
                $usedChars += $contentLength;
            } else {
                $omittedMessages++;
            }

            $grouped[$key]['message_count']++;
            $grouped[$key]['weighted_signal_score'] = round($grouped[$key]['weighted_signal_score'] + $roleWeight, 1);
            $weightedSignalScore += $roleWeight;

            if ($role === 'user') {
                $grouped[$key]['child_message_count']++;
                $childMessageCount++;
            } elseif ($role === 'assistant') {
                $grouped[$key]['assistant_message_count']++;
                $assistantMessageCount++;
            }
        }

        return [
            'conversation_count' => count($grouped),
            'included_message_count' => $includedMessages,
            'omitted_message_count' => $omittedMessages,
            'weighted_signal_score' => round($weightedSignalScore, 1),
            'message_weighting' => $this->buildMessageWeightingMeta($childMessageCount, $assistantMessageCount),
            'conversations' => array_values($grouped),
        ];
    }

    private function buildMessageWeightingMeta(int $childMessageCount, int $assistantMessageCount): array
    {
        $weightedChildSignal = round($childMessageCount * self::CHILD_MESSAGE_WEIGHT, 1);
        $weightedAssistantContext = round($assistantMessageCount * self::ASSISTANT_MESSAGE_WEIGHT, 1);

        return [
            'child_message_weight' => self::CHILD_MESSAGE_WEIGHT,
            'assistant_message_weight' => self::ASSISTANT_MESSAGE_WEIGHT,
            'weighted_child_signal' => $weightedChildSignal,
            'weighted_assistant_context' => $weightedAssistantContext,
            'weighted_total_signal' => round($weightedChildSignal + $weightedAssistantContext, 1),
            'guidance' => 'Child-authored messages are primary evidence. Assistant replies are supporting context only.',
        ];
    }

    private function messageRoleWeight(string $role): float
    {
        return $role === 'assistant'
            ? self::ASSISTANT_MESSAGE_WEIGHT
            : self::CHILD_MESSAGE_WEIGHT;
    }

    private function normalizeTranscriptContent(string $content): string
    {
        $content = preg_replace('/\s+/u', ' ', trim($content)) ?? '';
        return $content;
    }

    private function truncateTranscriptContent(string $content, int $maxChars): string
    {
        if (mb_strlen($content, 'UTF-8') <= $maxChars) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, $maxChars - 1, 'UTF-8')) . '…';
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
            'auto_generate_window_days' => 7,
            'next_report_due_at' => null,
            'last_report_generated_at' => null,
        ];
    }

    private function resolveAutoReportPeriodDays(array $settings, int $default = 7): int
    {
        return $this->normalizeDays(
            $settings['auto_generate_frequency_days']
                ?? $settings['auto_generate_period_days']
                ?? $settings['auto_generate_window_days']
                ?? $default,
            $default
        );
    }

    private function buildSnapshot(array $child, int $days, string $mode, bool $allowLlm): array
    {
        return $this->buildSnapshotFromScope($child, $this->buildRollingScope($days), $mode, $allowLlm);
    }

    private function buildSnapshotFromScope(
        array $child,
        array $scope,
        string $mode,
        bool $allowLlm,
        ?array $messages = null,
        ?array $incrementMessages = null
    ): array
    {
        $series = $this->buildSeriesMap($scope['start_date'], $scope['end_date']);
        $childId = (int) $child['id'];

        $messages = $messages ?? $this->reportModel->getConversationMessagesBetween($childId, $scope['start_at'], $scope['end_at']);
        $reportMessages = $this->filterReportMessages($messages);
        $childMessages = $this->filterMessagesByRole($reportMessages, 'user');

        $incrementMessages = $incrementMessages ?? $messages;
        $incrementReportMessages = $this->filterReportMessages($incrementMessages);
        $incrementChildMessages = $this->filterMessagesByRole($incrementReportMessages, 'user');

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

        $summary = $this->buildSummary($series, $child);
        $readiness = $this->computeContentReadiness($childMessages, $scope['days'], $incrementChildMessages);
        $analysis = $this->buildAnalysis($child, $summary, $scope, $readiness, $reportMessages, $allowLlm);

        return [
            'version' => 4,
            'status' => !$readiness['eligible']
                ? 'insufficient_data'
                : ($readiness['can_generate'] ? 'ready' : 'awaiting_increment'),
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
            'analysis_version' => self::REPORT_PROMPT_VERSION,
            'summary' => $summary,
            'insights' => $this->buildOverviewInsights($summary, $scope['days']),
            'series' => array_values($series),
            'content_readiness' => $readiness,
            'analysis' => $analysis,
        ];
    }

    private function buildAnalysis(
        array $child,
        array $summary,
        array $scope,
        array $readiness,
        array $messages,
        bool $allowLlm
    ): array
    {
        if (($readiness['message_count'] ?? 0) === 0) {
            return $this->buildNoDataAnalysis($readiness, $scope['days']);
        }

        $signalPacket = $this->analyzeSignals(
            $this->filterMessagesByRole($messages, 'user'),
            $summary,
            (int) ($scope['days'] ?? 0)
        );
        $fallback = $this->createFallbackAnalysis($child, $readiness, $signalPacket, $scope['days']);
        if (!$allowLlm) {
            return $fallback;
        }

        if (empty($readiness['can_generate'])) {
            return $this->buildPreviewAnalysis($child, $summary, $scope, $readiness);
        }

        $payload = $this->requestLlmAnalysis(
            $this->buildAiReportPacket($child, $summary, $scope, $readiness, $messages)
        );

        if ($payload === null) {
            $fallback['source_detail'] = $this->lastAnalysisFailureReason
                ?? ($fallback['source_detail'] ?? 'AI transcript analysis was unavailable or invalid, so this report uses local rule-based analysis.');
            return $fallback;
        }

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

    private function computeContentReadiness(array $messages, int $days, ?array $incrementMessages = null): array
    {
        $totals = $this->computeMessageStats($messages);
        $increments = $this->computeMessageStats($incrementMessages ?? $messages);

        $recommendedSampleMet = $totals['message_count'] >= self::CONTENT_MIN_MESSAGES
            && $totals['character_count'] >= self::CONTENT_MIN_CHARACTERS;

        $incrementReady = $increments['message_count'] >= self::INCREMENT_MIN_MESSAGES
            && $increments['character_count'] >= self::INCREMENT_MIN_CHARACTERS;

        $confidence = 'none';
        if ($totals['message_count'] > 0 && $totals['character_count'] > 0) {
            $confidence = 'low';
            if ($totals['message_count'] >= 35 && $totals['character_count'] >= 1200 && $totals['active_days'] >= 4) {
                $confidence = 'high';
            } elseif ($totals['message_count'] >= 20 && $totals['character_count'] >= 600 && $totals['active_days'] >= 2) {
                $confidence = 'medium';
            }
        }

        $reason = 'Enough new child-authored chat is available for an AI report.';
        if ($totals['message_count'] === 0) {
            $reason = 'No recent child-authored chat messages were found in this report scope.';
        } elseif (!$recommendedSampleMet) {
            $reason = 'This scope still does not contain enough child-authored chat for a stable AI report.';
        } elseif (!$incrementReady) {
            $reason = 'There is not enough new child-authored chat since the last saved report to justify generating another AI report yet.';
        }

        return [
            'eligible' => $totals['message_count'] > 0,
            'can_generate' => $recommendedSampleMet && $incrementReady,
            'recommended_sample_met' => $recommendedSampleMet,
            'increment_ready' => $incrementReady,
            'message_count' => $totals['message_count'],
            'character_count' => $totals['character_count'],
            'active_days' => $totals['active_days'],
            'conversation_count' => $totals['conversation_count'],
            'increment_message_count' => $increments['message_count'],
            'increment_character_count' => $increments['character_count'],
            'increment_active_days' => $increments['active_days'],
            'increment_conversation_count' => $increments['conversation_count'],
            'window_days' => $days,
            'minimum_messages' => self::CONTENT_MIN_MESSAGES,
            'minimum_characters' => self::CONTENT_MIN_CHARACTERS,
            'minimum_active_days' => self::CONTENT_MIN_ACTIVE_DAYS,
            'minimum_increment_messages' => self::INCREMENT_MIN_MESSAGES,
            'minimum_increment_characters' => self::INCREMENT_MIN_CHARACTERS,
            'minimum_increment_active_days' => self::INCREMENT_MIN_ACTIVE_DAYS,
            'confidence' => $confidence,
            'reason' => $reason,
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
            'source_detail' => 'AI transcript analysis was unavailable or invalid, so this report uses local rule-based analysis.',
            'headline' => $headline,
            'sample_confidence' => $readiness['confidence'],
            'disclaimer' => 'This report summarizes recent chat patterns only. It is not a clinical diagnosis and should be read alongside offline behavior.',
            'topic_overview' => $topicOverview,
            'event_blocks' => [],
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
            'source' => 'no_data',
            'source_detail' => 'There was not enough recent child-authored chat to send a usable content sample for AI analysis.',
            'headline' => 'No recent child-authored chat sample is available yet.',
            'sample_confidence' => $readiness['confidence'] ?? 'none',
            'disclaimer' => 'This report is not a diagnosis. It simply reflects that there was not enough recent child-authored chat in the selected window to analyze content patterns.',
            'topic_overview' => 'There were no recent child-authored messages in the selected time window.',
            'event_blocks' => [],
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

    private function requestLlmAnalysis(array $reportPacket): ?array
    {
        return $this->requestJsonAnalysis(
            $this->buildReportSystemPrompt(),
            $reportPacket,
            self::REPORT_REQUIRED_KEYS,
            'child wellbeing report'
        );
    }

    private function requestTrendLlmAnalysis(array $trendPacket): ?array
    {
        return $this->requestJsonAnalysis(
            $this->buildTrendSystemPrompt(),
            $trendPacket,
            self::TREND_REQUIRED_KEYS,
            'cumulative trend analysis'
        );
    }

    private function requestJsonAnalysis(
        string $systemPrompt,
        array $packet,
        array $requiredKeys,
        string $analysisLabel
    ): ?array
    {
        $this->lastAnalysisFailureReason = null;
        $apiKey = trim((string) Config::get('LLM_API_KEY', ''));
        $apiUrl = trim((string) Config::get('LLM_API_URL', 'https://api.deepseek.com/v1/chat/completions'));
        $model = trim((string) Config::get('LLM_MODEL', 'deepseek-chat'));

        if ($apiUrl === '') {
            $apiUrl = 'https://api.deepseek.com/v1/chat/completions';
        }

        if ($model === '') {
            $model = 'deepseek-chat';
        }

        if ($apiKey === '') {
            $this->lastAnalysisFailureReason = 'AI is not configured because `LLM_API_KEY` is missing.';
            return null;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $packetJson = json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attempts = [
            [
                'system' => $systemPrompt,
                'user' => $packetJson,
                'use_response_format' => true,
            ],
            [
                'system' => $systemPrompt . "\nReturn exactly one valid JSON object with the required keys and no markdown, no explanation, and no extra wrapper keys.",
                'user' => "Generate a {$analysisLabel} from this JSON packet. Return one JSON object only.\n" . $packetJson,
                'use_response_format' => false,
            ],
        ];

        foreach ($attempts as $index => $attempt) {
            $response = $this->performJsonCompletionRequest(
                $apiUrl,
                $apiKey,
                $model,
                (string) $attempt['system'],
                (string) $attempt['user'],
                !empty($attempt['use_response_format'])
            );

            if ($response['content'] === null) {
                $this->lastAnalysisFailureReason = $response['error'];
                continue;
            }

            $payload = $this->extractJsonPayload($response['content'], $requiredKeys);
            if ($payload !== null) {
                return $payload;
            }

            $this->lastAnalysisFailureReason = $index === 0
                ? 'AI returned text, but not valid report JSON. Retrying with stricter formatting instructions.'
                : 'AI returned text, but not valid report JSON.';
        }

        return null;
    }

    private function performJsonCompletionRequest(
        string $apiUrl,
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userContent,
        bool $useResponseFormat
    ): array {
        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 2600,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ];

        if ($useResponseFormat) {
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
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

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError !== '' || $httpCode >= 400) {
            if ($curlError !== '') {
                return [
                    'content' => null,
                    'error' => 'AI request failed before a response was received: ' . $curlError . '.',
                ];
            }

            $decodedError = json_decode((string) $responseBody, true);
            $message = $decodedError['error']['message']
                ?? $decodedError['message']
                ?? ('HTTP ' . $httpCode);

            return [
                'content' => null,
                'error' => 'AI service returned an error: ' . trim((string) $message) . '.',
            ];
        }

        $decoded = json_decode((string) $responseBody, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            return [
                'content' => null,
                'error' => 'AI returned an empty response.',
            ];
        }

        return [
            'content' => $content,
            'error' => null,
        ];
    }

    private function buildReportSystemPrompt(): string
    {
        return implode("\n", [
            'You are writing a parent-facing child wellbeing report from organized chat records and activity context.',
            'You may read the raw transcript bundle, but your output must paraphrase only and summarize at theme level.',
            'Child-authored messages are the primary evidence. Treat assistant replies as low-weight context only, roughly 0.2 compared with a child-authored message.',
            'Do not let assistant wording dominate risk, topic, or emotional judgments when the child did not clearly express the same thing.',
            'Never reveal verbatim quotes, unique wording, names, school identifiers, account handles, contact details, or anything that would reconstruct a raw message.',
            'Do not use quotation marks, pseudo-quotes, or short quoted snippets from the transcript anywhere in the JSON.',
            'Evidence fields must describe patterns abstractly, for example "repeated peer-rejection worries across several chats" rather than repeating a child phrase.',
            'If you include any quoted or near-quoted child wording, the answer is invalid. Replace specific phrases with abstractions like harsh self-judgment, broad rejection beliefs, negative future expectations, or fear of being judged.',
            'Do not diagnose. Use careful, evidence-based language such as "may", "appears", "suggests", or "not enough evidence".',
            'Do not use simplistic keyword matching logic. Instead, reason over persistence across days, conversational context, repeated themes, change over time, coping style, conflict style, help-seeking, flexibility, emotional tone, and whether a signal stays isolated versus recurring.',
            'Use a child mental-health warning-sign lens that pays attention to recurring sadness or hopelessness, harsh self-worth, anxiety or overwhelm, peer rejection or bullying stress, irritability or revenge framing, avoidance or withdrawal, sleep/body/food strain, risky behavior, secrecy around unsafe contact, and protective signals such as help-seeking, curiosity, empathy, supportive relationships, and future orientation.',
            'For aggression or violence-related content, distinguish brief frustration, fantasy, roleplay, or hypothetical language from repeated hostile intent, target fixation, planning, access-to-weapon talk, or escalation across days.',
            'For self-harm or hopelessness content, distinguish passing dramatic language from repeated or direct safety concern; elevate only when the transcript provides meaningful evidence.',
            'Younger children can use exaggerated language loosely. Do not over-interpret single dramatic phrases without corroborating context.',
            'Keep the existing top-level summary fields focused on the macro overall picture across the whole report window.',
            'Also return event_blocks as a separate event-by-event breakdown of what concrete situations were discussed in the current report scope.',
            'An event can be a question, a personal update, a disclosure, a conflict, a coping discussion, or a situation the child mainly shared without explicitly asking a question.',
            'Group related turns into one event. Do not create one event per message. Merge nearby turns when they are about the same situation or concern.',
            'Order event_blocks by when the event first appears in the transcript.',
            'Every event block must stay privacy-preserving: remove names, school or class identifiers, places, dates, handles, contact details, and any unique facts that could reconstruct the original situation.',
            'For child_focus, describe what the child was asking, worrying about, deciding, or sharing. If the child mainly shared and did not ask a question, summarize the core situation or need instead.',
            'For assistant_strategy, summarize the response approach abstractly, such as validation, clarification, reframing, coping planning, encouragement to seek adult support, boundary-setting, or safety-oriented checking.',
            'For assistant_response_summary, describe at a high level how the assistant responded and the rough handling outcome, without revealing distinctive transcript details.',
            'Separate concerns into: risk dimensions, thinking patterns, protective factors, strengths, watch points, and parent guidance.',
            'If evidence is weak, lower confidence and say so clearly.',
            'Treat roleplay, hypothetical questions, jokes, and one-off curiosity carefully; do not overstate them as real-world intent without stronger evidence.',
            'Keep every field concise, concrete, privacy-preserving, and useful to a parent.',
            'Prefer 2-5 items per list. Omit weak items instead of padding.',
            'Alerts should be reserved for time-sensitive or clearly elevated concerns; if there is no meaningful alert, return an empty array.',
            'Return JSON only.',
            'Required JSON keys:',
            'headline: string',
            'sample_confidence: "none"|"low"|"medium"|"high"',
            'disclaimer: string',
            'topic_overview: string',
            'event_blocks: array of {event_title:string, event_type:"question"|"sharing"|"mixed", child_focus:string, event_summary:string, assistant_strategy:string, assistant_response_summary:string}',
            'topics: array of {name:string, summary:string}',
            'interests: array of {name:string, why_it_matters:string}',
            'emotional_overview: {summary:string, supporting_signals:string[]}',
            'wellbeing: {summary:string, strengths:string[], watch_points:string[]}',
            'risk_dimensions: array of {name:string, level:"low"|"medium"|"high", summary:string, evidence:string, why_it_matters:string, parent_action:string}',
            'thinking_patterns: array of {name:string, level:"low"|"medium"|"high", summary:string, evidence:string}',
            'protective_factors: array of {name:string, summary:string}',
            'parent_guidance: string[]',
            'alerts: array of {level:"low"|"medium"|"high", title:string, detail:string}',
        ]);
    }

    private function buildTrendSystemPrompt(): string
    {
        return implode("\n", [
            'You are writing a parent-facing cumulative trend analysis across multiple saved child wellbeing reports and retained chat context.',
            'You may read the retained transcript bundle and report digests, but your output must paraphrase only and summarize at pattern level.',
            'Child-authored messages are the primary evidence. Treat assistant replies as low-weight context only, roughly 0.2 compared with a child-authored message.',
            'Do not let assistant wording dominate trend, risk, or topic conclusions unless the child repeatedly expressed the same signal.',
            'Never reveal verbatim quotes, unique wording, names, school identifiers, account handles, contact details, or anything that would reconstruct a raw message.',
            'Do not use quotation marks, pseudo-quotes, or short quoted snippets from the transcript anywhere in the JSON.',
            'Do not diagnose. Use careful, evidence-based language such as "may", "appears", "suggests", or "not enough evidence".',
            'Focus on change over time: what intensified, eased, stayed persistent, newly appeared, or remained protective across the selected reports.',
            'Distinguish stable multi-report patterns from one-off events. Treat a single selected report as a snapshot, not a true long-term trend.',
            'For aggression, self-harm, secrecy, or other elevated-risk themes, avoid overstatement unless there is repeated or clearly meaningful evidence across the selected material.',
            'Keep every field concise, concrete, privacy-preserving, and useful to a parent.',
            'Prefer 2-6 items per list. Omit weak items instead of padding.',
            'Return JSON only.',
            'Required JSON keys:',
            'headline: string',
            'summary: string',
            'risk_trajectory: {direction:"single_snapshot"|"rising"|"easing"|"stable", level:"low"|"medium"|"high", summary:string}',
            'recurring_risks: array of {name:string, level:"low"|"medium"|"high", summary:string, reports:int}',
            'thinking_trends: array of {name:string, level:"low"|"medium"|"high", summary:string, reports:int}',
            'protective_trends: array of {name:string, summary:string, reports:int}',
            'topic_trends: array of {name:string, summary:string, reports:int}',
            'parent_guidance: string[]',
        ]);
    }

    private function extractJsonPayload(string $content, array $requiredKeys = []): ?array
    {
        $trimmed = trim($content);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $this->coerceJsonPayload($decoded, $requiredKeys);
        }

        if (preg_match('/```json\s*(\{.*\})\s*```/su', $trimmed, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $this->coerceJsonPayload($decoded, $requiredKeys);
            }
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $this->coerceJsonPayload($decoded, $requiredKeys);
            }
        }

        return null;
    }

    private function coerceJsonPayload(array $payload, array $requiredKeys): ?array
    {
        if ($requiredKeys === []) {
            return $payload;
        }

        $bestScore = 0;
        $candidate = $this->findBestJsonPayloadCandidate($payload, $requiredKeys, $bestScore);
        if ($candidate === null) {
            return null;
        }

        $minimumAcceptedScore = max(4, (int) ceil(count($requiredKeys) / 2));
        return $bestScore >= $minimumAcceptedScore ? $candidate : null;
    }

    private function findBestJsonPayloadCandidate(array $payload, array $requiredKeys, int &$bestScore): ?array
    {
        $bestCandidate = null;
        $score = $this->countPayloadKeyMatches($payload, $requiredKeys);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCandidate = $payload;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $candidate = $this->findBestJsonPayloadCandidate($value, $requiredKeys, $bestScore);
            if ($candidate !== null) {
                $bestCandidate = $candidate;
            }
        }

        return $bestCandidate;
    }

    private function countPayloadKeyMatches(array $payload, array $requiredKeys): int
    {
        $matches = 0;
        foreach ($requiredKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $matches++;
            }
        }

        return $matches;
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

        $analysis['event_blocks'] = $this->normalizeEventBlocks($analysis['event_blocks'] ?? ($fallback['event_blocks'] ?? []));

        if (!isset($analysis['wellbeing']) || !is_array($analysis['wellbeing'])) {
            $analysis['wellbeing'] = $fallback['wellbeing'];
        }

        if (!isset($analysis['emotional_overview']) || !is_array($analysis['emotional_overview'])) {
            $analysis['emotional_overview'] = $fallback['emotional_overview'];
        }

        foreach (['risk_dimensions', 'thinking_patterns'] as $leveledListKey) {
            foreach ($analysis[$leveledListKey] as $index => $item) {
                if (!is_array($item)) {
                    $analysis[$leveledListKey][$index] = $fallback[$leveledListKey][$index] ?? [];
                    continue;
                }

                $level = (string) ($item['level'] ?? 'low');
                if (!in_array($level, ['low', 'medium', 'high'], true)) {
                    $analysis[$leveledListKey][$index]['level'] = 'low';
                }
            }
        }

        foreach ($analysis['alerts'] as $index => $item) {
            if (!is_array($item)) {
                $analysis['alerts'][$index] = $fallback['alerts'][$index] ?? [];
                continue;
            }

            $level = (string) ($item['level'] ?? 'low');
            if (!in_array($level, ['low', 'medium', 'high'], true)) {
                $analysis['alerts'][$index]['level'] = 'low';
            }
        }

        $analysis['source'] = $payload !== [] ? 'llm_transcript' : $fallback['source'];
        $analysis['source_detail'] = $payload !== []
            ? 'Generated by AI from the retained transcript bundle and activity summary.'
            : ($fallback['source_detail'] ?? 'This report uses fallback analysis.');
        $analysis['disclaimer'] = 'This report summarizes recent chat patterns only. It is not a clinical diagnosis and should be read alongside offline behavior.';
        $analysis['prompt_version'] = self::REPORT_PROMPT_VERSION;
        $analysis = $this->sanitizeAnalysisPayload($analysis);

        return $analysis;
    }

    private function validateTrendAnalysisPayload(array $analysis): array
    {
        $errors = [];
        $requiredKeys = self::TREND_REQUIRED_KEYS;

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $analysis)) {
                $errors[] = "Missing key: {$key}";
            }
        }

        if (isset($analysis['risk_trajectory']) && !is_array($analysis['risk_trajectory'])) {
            $errors[] = 'risk_trajectory must be an object.';
        }

        foreach (['recurring_risks', 'thinking_trends', 'protective_trends', 'topic_trends', 'parent_guidance'] as $key) {
            if (isset($analysis[$key]) && !is_array($analysis[$key])) {
                $errors[] = "{$key} must be an array.";
            }
        }

        if (isset($analysis['risk_trajectory']['direction']) && !in_array($analysis['risk_trajectory']['direction'], ['single_snapshot', 'rising', 'easing', 'stable'], true)) {
            $errors[] = 'Invalid risk_trajectory.direction value.';
        }

        if (isset($analysis['risk_trajectory']['level']) && !in_array($analysis['risk_trajectory']['level'], ['low', 'medium', 'high'], true)) {
            $errors[] = 'Invalid risk_trajectory.level value.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    private function normalizeTrendAnalysisPayload(array $payload, array $fallback): array
    {
        $analysis = $fallback;
        foreach ($payload as $key => $value) {
            $analysis[$key] = $value;
        }

        foreach (['recurring_risks', 'thinking_trends', 'protective_trends', 'topic_trends', 'parent_guidance'] as $listKey) {
            if (!isset($analysis[$listKey]) || !is_array($analysis[$listKey])) {
                $analysis[$listKey] = $fallback[$listKey];
            }
        }

        if (!isset($analysis['risk_trajectory']) || !is_array($analysis['risk_trajectory'])) {
            $analysis['risk_trajectory'] = $fallback['risk_trajectory'];
        }

        $direction = (string) ($analysis['risk_trajectory']['direction'] ?? $fallback['risk_trajectory']['direction'] ?? 'stable');
        if (!in_array($direction, ['single_snapshot', 'rising', 'easing', 'stable'], true)) {
            $analysis['risk_trajectory']['direction'] = $fallback['risk_trajectory']['direction'] ?? 'stable';
        }

        $level = (string) ($analysis['risk_trajectory']['level'] ?? $fallback['risk_trajectory']['level'] ?? 'low');
        if (!in_array($level, ['low', 'medium', 'high'], true)) {
            $analysis['risk_trajectory']['level'] = $fallback['risk_trajectory']['level'] ?? 'low';
        }

        foreach (['recurring_risks', 'thinking_trends'] as $leveledListKey) {
            foreach ($analysis[$leveledListKey] as $index => $item) {
                if (!is_array($item)) {
                    $analysis[$leveledListKey][$index] = $fallback[$leveledListKey][$index] ?? [];
                    continue;
                }

                $itemLevel = (string) ($item['level'] ?? 'low');
                if (!in_array($itemLevel, ['low', 'medium', 'high'], true)) {
                    $analysis[$leveledListKey][$index]['level'] = 'low';
                }

                $analysis[$leveledListKey][$index]['reports'] = max(1, (int) ($item['reports'] ?? ($fallback[$leveledListKey][$index]['reports'] ?? 1)));
            }
        }

        foreach (['protective_trends', 'topic_trends'] as $countedListKey) {
            foreach ($analysis[$countedListKey] as $index => $item) {
                if (!is_array($item)) {
                    $analysis[$countedListKey][$index] = $fallback[$countedListKey][$index] ?? [];
                    continue;
                }

                $analysis[$countedListKey][$index]['reports'] = max(1, (int) ($item['reports'] ?? ($fallback[$countedListKey][$index]['reports'] ?? 1)));
            }
        }

        $analysis['source'] = $payload !== [] ? 'llm_trend' : $fallback['source'];
        $analysis['source_detail'] = $payload !== []
            ? 'Generated by AI from the selected saved reports and their retained transcript context.'
            : ($fallback['source_detail'] ?? 'This cumulative view uses fallback analysis.');
        $analysis['generated_at'] = AppTime::now()->format(DATE_ATOM);
        $analysis['prompt_version'] = self::TREND_PROMPT_VERSION;
        $analysis = $this->sanitizeAnalysisPayload($analysis);

        return $analysis;
    }

    private function sanitizeAnalysisPayload(array $analysis): array
    {
        foreach ($analysis as $key => $value) {
            $analysis[$key] = $this->sanitizeAnalysisValue($value);
        }

        return $analysis;
    }

    private function sanitizeAnalysisValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeAnalysisValue($item);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $sanitized = preg_replace_callback(
            "/\"([^\"]{1,80})\"|“([^”]{1,80})”|'([^']{1,80})'/u",
            static function (array $matches): string {
                $fragment = '';
                foreach (array_slice($matches, 1) as $candidate) {
                    if ($candidate !== null && $candidate !== '') {
                        $fragment = $candidate;
                        break;
                    }
                }

                return trim($fragment);
            },
            $value
        );

        $sanitized = str_replace(['“', '”'], '', $sanitized ?? $value);
        $sanitized = preg_replace("/(?<!\\pL)'|'(?!\\pL)/u", '', $sanitized ?? $value);
        $sanitized = preg_replace('/https?:\/\/\S+/iu', '[redacted link]', $sanitized ?? $value);
        $sanitized = preg_replace('/\b[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,}\b/u', '[redacted email]', $sanitized ?? $value);
        $sanitized = preg_replace('/(?<!\w)@[A-Za-z0-9_]{2,}\b/u', '[redacted handle]', $sanitized ?? $value);
        $sanitized = preg_replace('/(?<!\d)(?:\+?\d[\d\s().-]{6,}\d)(?!\d)/u', '[redacted number]', $sanitized ?? $value);
        $sanitized = preg_replace('/\s{2,}/u', ' ', $sanitized ?? $value);

        return trim((string) ($sanitized ?? $value));
    }

    private function normalizeEventBlocks($eventBlocks): array
    {
        if (!is_array($eventBlocks)) {
            return [];
        }

        $normalized = [];
        foreach (array_values($eventBlocks) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $eventType = (string) ($item['event_type'] ?? 'mixed');
            if (!in_array($eventType, ['question', 'sharing', 'mixed'], true)) {
                $eventType = 'mixed';
            }

            $normalizedItem = [
                'event_title' => trim((string) ($item['event_title'] ?? $item['title'] ?? ('Event ' . ($index + 1)))),
                'event_type' => $eventType,
                'child_focus' => trim((string) ($item['child_focus'] ?? $item['child_question'] ?? $item['child_situation'] ?? '')),
                'event_summary' => trim((string) ($item['event_summary'] ?? $item['summary'] ?? '')),
                'assistant_strategy' => trim((string) ($item['assistant_strategy'] ?? $item['response_strategy'] ?? '')),
                'assistant_response_summary' => trim((string) ($item['assistant_response_summary'] ?? $item['ai_response_summary'] ?? $item['response_summary'] ?? '')),
            ];

            if (
                $normalizedItem['child_focus'] === ''
                && $normalizedItem['event_summary'] === ''
                && $normalizedItem['assistant_strategy'] === ''
                && $normalizedItem['assistant_response_summary'] === ''
            ) {
                continue;
            }

            $normalized[] = $normalizedItem;
        }

        return array_slice($normalized, 0, 8);
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
        $periodDays = $this->resolveAutoReportPeriodDays($settings);

        return [
            'child_id' => (int) $settings['child_id'],
            'parent_id' => (int) $settings['parent_id'],
            'auto_generate_enabled' => !empty($settings['auto_generate_enabled']),
            'auto_generate_period_days' => $periodDays,
            'auto_generate_frequency_days' => $periodDays,
            'auto_generate_window_days' => $periodDays,
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

    private function buildTrendAnalysis(
        array $child,
        array $reports,
        array $records,
        array $messageSummary,
        array $retainedMessages
    ): array
    {
        $fallback = $this->buildRuleBasedTrendAnalysis($reports, $records, $messageSummary);
        $packet = $this->buildAiTrendPacket($child, $reports, $records, $messageSummary, $retainedMessages);
        $payload = $this->requestTrendLlmAnalysis($packet);

        if ($payload === null) {
            $fallback['source_detail'] = $this->lastAnalysisFailureReason
                ?? ($fallback['source_detail'] ?? 'AI cumulative analysis was unavailable or invalid, so this view uses local rule-based trend analysis.');
            return $fallback;
        }

        return $this->normalizeTrendAnalysisPayload($payload, $fallback);
    }

    private function buildRuleBasedTrendAnalysis(array $reports, array $records, array $messageSummary): array
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
            'source_detail' => 'AI cumulative analysis was unavailable or invalid, so this view uses local rule-based trend analysis.',
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

    private function buildAiTrendPacket(
        array $child,
        array $reports,
        array $records,
        array $messageSummary,
        array $retainedMessages
    ): array {
        $manualReports = 0;
        $autoReports = 0;

        foreach ($records as $record) {
            if (($record['generation_mode'] ?? 'manual') === 'auto') {
                $autoReports++;
            } else {
                $manualReports++;
            }
        }

        return [
            'prompt_version' => self::TREND_PROMPT_VERSION,
            'child' => [
                'age_years' => $this->calculateAgeYears($child['birth_date'] ?? null),
                'gender' => $child['gender'] ?? null,
                'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
            ],
            'selection_overview' => [
                'selected_report_count' => count($records),
                'manual_reports' => $manualReports,
                'auto_reports' => $autoReports,
                'date_span' => $this->buildTrendDateSpan($reports, $records, $messageSummary),
            ],
            'message_weighting' => $this->buildMessageWeightingMeta(
                (int) ($messageSummary['child_message_count'] ?? 0),
                (int) ($messageSummary['assistant_message_count'] ?? 0)
            ),
            'retained_message_summary' => [
                'total_message_count' => (int) ($messageSummary['total_message_count'] ?? 0),
                'child_message_count' => (int) ($messageSummary['child_message_count'] ?? 0),
                'assistant_message_count' => (int) ($messageSummary['assistant_message_count'] ?? 0),
                'active_days' => (int) ($messageSummary['active_days'] ?? 0),
                'first_message_at' => AppTime::toIso8601($messageSummary['first_message_at'] ?? null),
                'last_message_at' => AppTime::toIso8601($messageSummary['last_message_at'] ?? null),
            ],
            'report_digests' => $this->buildTrendReportDigests($reports, $records),
            'transcript_bundle' => $this->buildTranscriptBundle($retainedMessages),
        ];
    }

    private function buildTrendReportDigests(array $reports, array $records): array
    {
        $digests = [];

        foreach ($reports as $index => $report) {
            $record = $records[$index] ?? [];
            $analysis = $report['analysis'] ?? [];
            $scope = $report['scope'] ?? [];
            $digests[] = [
                'report_id' => (int) ($record['id'] ?? 0),
                'report_day' => $record['report_day'] ?? ($scope['report_day'] ?? null),
                'generation_mode' => $record['generation_mode'] ?? ($report['generation_mode'] ?? 'manual'),
                'risk_level' => $record['risk_level'] ?? ($report['risk_level'] ?? $this->riskLevelFromAnalysis($analysis)),
                'sample_confidence' => $record['confidence'] ?? ($analysis['sample_confidence'] ?? 'none'),
                'generated_at' => $report['generated_at'] ?? ($record['updated_at'] ?? $record['created_at'] ?? null),
                'scope' => [
                    'start_at' => $scope['start_at'] ?? $record['scope_started_at'] ?? null,
                    'end_at' => $scope['end_at'] ?? $record['scope_ended_at'] ?? $record['updated_at'] ?? null,
                ],
                'headline' => trim((string) ($analysis['headline'] ?? $record['headline'] ?? '')),
                'topic_overview' => trim((string) ($analysis['topic_overview'] ?? '')),
                'emotional_summary' => trim((string) ($analysis['emotional_overview']['summary'] ?? '')),
                'wellbeing_summary' => trim((string) ($analysis['wellbeing']['summary'] ?? '')),
                'risk_dimensions' => $this->compactTrendItems($analysis['risk_dimensions'] ?? [], true),
                'thinking_patterns' => $this->compactTrendItems($analysis['thinking_patterns'] ?? [], true),
                'protective_factors' => $this->compactTrendItems($analysis['protective_factors'] ?? [], false),
                'topics' => $this->compactTrendItems($analysis['topics'] ?? [], false),
                'parent_guidance' => $this->normalizeStringList($analysis['parent_guidance'] ?? [], 4),
            ];
        }

        return $digests;
    }

    private function compactTrendItems(array $items, bool $withLevel, int $limit = 4): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? $item['title'] ?? ''));
            if ($name === '') {
                continue;
            }

            $summary = trim((string) ($item['summary'] ?? $item['why_it_matters'] ?? $item['evidence'] ?? ''));
            $entry = [
                'name' => $name,
                'summary' => $summary,
            ];

            if ($withLevel) {
                $level = (string) ($item['level'] ?? 'low');
                $entry['level'] = in_array($level, ['low', 'medium', 'high'], true) ? $level : 'low';
            }

            $result[] = $entry;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function normalizeStringList(array $items, int $limit = 6): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $text = trim($item);
            if ($text === '') {
                continue;
            }

            $result[] = $text;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
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
