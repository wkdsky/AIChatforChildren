<?php

namespace App\Models;

use Core\BaseModel;
use PDO;
use Throwable;

class ChildReport extends BaseModel
{
    protected $table = 'users';

    public function transaction(callable $callback)
    {
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $callback();
            if ($started) {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $throwable) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public function getChildForParent(int $childId, int $parentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, gender, birth_date, last_login_at
            FROM users
            WHERE id = :child_id AND role = 'child' AND parent_id = :parent_id
            LIMIT 1"
        );
        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        return $child ?: null;
    }

    public function getReportSettings(int $childId, int $parentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                child_id,
                parent_id,
                auto_generate_enabled,
                auto_generate_frequency_days,
                auto_generate_window_days,
                next_report_due_at,
                last_report_generated_at,
                created_at,
                updated_at
            FROM child_report_settings
            WHERE child_id = :child_id
                AND parent_id = :parent_id
            LIMIT 1"
        );
        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        return $settings ?: null;
    }

    public function upsertReportSettings(int $childId, int $parentId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO child_report_settings (
                child_id,
                parent_id,
                auto_generate_enabled,
                auto_generate_frequency_days,
                auto_generate_window_days,
                next_report_due_at,
                last_report_generated_at
            ) VALUES (
                :child_id,
                :parent_id,
                :auto_generate_enabled,
                :auto_generate_frequency_days,
                :auto_generate_window_days,
                :next_report_due_at,
                :last_report_generated_at
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                auto_generate_enabled = VALUES(auto_generate_enabled),
                auto_generate_frequency_days = VALUES(auto_generate_frequency_days),
                auto_generate_window_days = VALUES(auto_generate_window_days),
                next_report_due_at = VALUES(next_report_due_at),
                last_report_generated_at = VALUES(last_report_generated_at),
                updated_at = CURRENT_TIMESTAMP"
        );

        return $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
            'auto_generate_enabled' => !empty($data['auto_generate_enabled']) ? 1 : 0,
            'auto_generate_frequency_days' => (int) ($data['auto_generate_frequency_days'] ?? 7),
            'auto_generate_window_days' => (int) ($data['auto_generate_window_days'] ?? 14),
            'next_report_due_at' => $data['next_report_due_at'] ?? null,
            'last_report_generated_at' => $data['last_report_generated_at'] ?? null,
        ]);
    }

    public function listStoredReports(int $childId, int $parentId, int $limit = 24): array
    {
        $safeLimit = max(1, min($limit, 100));
        $stmt = $this->pdo->prepare(
            "SELECT
                id,
                generation_mode,
                status,
                window_days,
                window_start_date,
                window_end_date,
                scope_started_at,
                scope_ended_at,
                report_day,
                sample_message_count,
                sample_character_count,
                sample_active_days,
                message_record_count,
                confidence,
                risk_level,
                headline,
                created_at,
                updated_at
            FROM child_reports
            WHERE child_id = :child_id
                AND parent_id = :parent_id
            ORDER BY COALESCE(scope_ended_at, updated_at, created_at) DESC, id DESC
            LIMIT {$safeLimit}"
        );
        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStoredReportsByIds(int $childId, int $parentId, array $reportIds): array
    {
        $reportIds = array_values(array_unique(array_map('intval', $reportIds)));
        if ($reportIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($reportIds), '?'));
        $params = array_merge([$childId, $parentId], $reportIds);

        $stmt = $this->pdo->prepare(
            "SELECT *
            FROM child_reports
            WHERE child_id = ?
                AND parent_id = ?
                AND id IN ({$placeholders})
            ORDER BY COALESCE(scope_ended_at, updated_at, created_at) ASC, id ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStoredReport(int $reportId, int $childId, int $parentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
            FROM child_reports
            WHERE id = :report_id
                AND child_id = :child_id
                AND parent_id = :parent_id
            LIMIT 1"
        );
        $stmt->execute([
            'report_id' => $reportId,
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        return $report ?: null;
    }

    public function getLatestStoredReport(int $childId, int $parentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
            FROM child_reports
            WHERE child_id = :child_id
                AND parent_id = :parent_id
            ORDER BY COALESCE(scope_ended_at, updated_at, created_at) DESC, id DESC
            LIMIT 1"
        );
        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        return $report ?: null;
    }

    public function getLatestStoredReportBefore(
        int $childId,
        int $parentId,
        string $beforeAt,
        ?int $excludeReportId = null
    ): ?array {
        $sql = "SELECT *
            FROM child_reports
            WHERE child_id = :child_id
                AND parent_id = :parent_id
                AND COALESCE(scope_ended_at, updated_at, created_at) < :before_at";

        $params = [
            'child_id' => $childId,
            'parent_id' => $parentId,
            'before_at' => $beforeAt,
        ];

        if ($excludeReportId !== null) {
            $sql .= " AND id <> :exclude_report_id";
            $params['exclude_report_id'] = $excludeReportId;
        }

        $sql .= " ORDER BY COALESCE(scope_ended_at, updated_at, created_at) DESC, id DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        return $report ?: null;
    }

    public function findManualReportForDay(int $childId, int $parentId, string $reportDay): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
            FROM child_reports
            WHERE child_id = :child_id
                AND parent_id = :parent_id
                AND generation_mode = 'manual'
                AND report_day = :report_day
            ORDER BY id DESC
            LIMIT 1"
        );
        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
            'report_day' => $reportDay,
        ]);

        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        return $report ?: null;
    }

    public function createStoredReport(int $childId, int $parentId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO child_reports (
                child_id,
                parent_id,
                generation_mode,
                status,
                window_days,
                window_start_date,
                window_end_date,
                scope_started_at,
                scope_ended_at,
                report_day,
                sample_message_count,
                sample_character_count,
                sample_active_days,
                message_record_count,
                confidence,
                risk_level,
                headline,
                report_json
            ) VALUES (
                :child_id,
                :parent_id,
                :generation_mode,
                :status,
                :window_days,
                :window_start_date,
                :window_end_date,
                :scope_started_at,
                :scope_ended_at,
                :report_day,
                :sample_message_count,
                :sample_character_count,
                :sample_active_days,
                :message_record_count,
                :confidence,
                :risk_level,
                :headline,
                :report_json
            )"
        );

        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
            'generation_mode' => $data['generation_mode'] ?? 'manual',
            'status' => $data['status'] ?? 'ready',
            'window_days' => (int) ($data['window_days'] ?? 14),
            'window_start_date' => $data['window_start_date'],
            'window_end_date' => $data['window_end_date'],
            'scope_started_at' => $data['scope_started_at'] ?? null,
            'scope_ended_at' => $data['scope_ended_at'] ?? null,
            'report_day' => $data['report_day'] ?? null,
            'sample_message_count' => (int) ($data['sample_message_count'] ?? 0),
            'sample_character_count' => (int) ($data['sample_character_count'] ?? 0),
            'sample_active_days' => (int) ($data['sample_active_days'] ?? 0),
            'message_record_count' => (int) ($data['message_record_count'] ?? 0),
            'confidence' => $data['confidence'] ?? 'none',
            'risk_level' => $data['risk_level'] ?? 'low',
            'headline' => $data['headline'] ?? '',
            'report_json' => $data['report_json'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStoredReport(int $reportId, int $childId, int $parentId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE child_reports
            SET
                generation_mode = :generation_mode,
                status = :status,
                window_days = :window_days,
                window_start_date = :window_start_date,
                window_end_date = :window_end_date,
                scope_started_at = :scope_started_at,
                scope_ended_at = :scope_ended_at,
                report_day = :report_day,
                sample_message_count = :sample_message_count,
                sample_character_count = :sample_character_count,
                sample_active_days = :sample_active_days,
                message_record_count = :message_record_count,
                confidence = :confidence,
                risk_level = :risk_level,
                headline = :headline,
                report_json = :report_json,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :report_id
                AND child_id = :child_id
                AND parent_id = :parent_id"
        );

        return $stmt->execute([
            'report_id' => $reportId,
            'child_id' => $childId,
            'parent_id' => $parentId,
            'generation_mode' => $data['generation_mode'] ?? 'manual',
            'status' => $data['status'] ?? 'ready',
            'window_days' => (int) ($data['window_days'] ?? 14),
            'window_start_date' => $data['window_start_date'],
            'window_end_date' => $data['window_end_date'],
            'scope_started_at' => $data['scope_started_at'] ?? null,
            'scope_ended_at' => $data['scope_ended_at'] ?? null,
            'report_day' => $data['report_day'] ?? null,
            'sample_message_count' => (int) ($data['sample_message_count'] ?? 0),
            'sample_character_count' => (int) ($data['sample_character_count'] ?? 0),
            'sample_active_days' => (int) ($data['sample_active_days'] ?? 0),
            'message_record_count' => (int) ($data['message_record_count'] ?? 0),
            'confidence' => $data['confidence'] ?? 'none',
            'risk_level' => $data['risk_level'] ?? 'low',
            'headline' => $data['headline'] ?? '',
            'report_json' => $data['report_json'],
        ]);
    }

    public function replaceStoredReportMessages(int $reportId, array $messages): void
    {
        $deleteStmt = $this->pdo->prepare("DELETE FROM child_report_messages WHERE report_id = :report_id");
        $deleteStmt->execute(['report_id' => $reportId]);

        if ($messages === []) {
            return;
        }

        $insertStmt = $this->pdo->prepare(
            "INSERT INTO child_report_messages (
                report_id,
                message_id,
                conversation_id,
                role,
                content,
                created_at
            ) VALUES (
                :report_id,
                :message_id,
                :conversation_id,
                :role,
                :content,
                :created_at
            )"
        );

        foreach ($messages as $message) {
            $insertStmt->execute([
                'report_id' => $reportId,
                'message_id' => (int) ($message['message_id'] ?? $message['id'] ?? 0),
                'conversation_id' => (int) ($message['conversation_id'] ?? 0),
                'role' => $message['role'] ?? 'user',
                'content' => $message['content'] ?? '',
                'created_at' => $message['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getRetainedMessageSummaryForReports(int $childId, int $parentId, array $reportIds): array
    {
        $reportIds = array_values(array_unique(array_map('intval', $reportIds)));
        if ($reportIds === []) {
            return [
                'total_message_count' => 0,
                'child_message_count' => 0,
                'assistant_message_count' => 0,
                'active_days' => 0,
                'first_message_at' => null,
                'last_message_at' => null,
            ];
        }

        $placeholders = implode(', ', array_fill(0, count($reportIds), '?'));
        $params = array_merge([$childId, $parentId], $reportIds);

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(DISTINCT crm.message_id) AS total_message_count,
                COUNT(DISTINCT CASE WHEN crm.role = 'user' THEN crm.message_id END) AS child_message_count,
                COUNT(DISTINCT CASE WHEN crm.role = 'assistant' THEN crm.message_id END) AS assistant_message_count,
                COUNT(DISTINCT DATE(crm.created_at)) AS active_days,
                MIN(crm.created_at) AS first_message_at,
                MAX(crm.created_at) AS last_message_at
            FROM child_report_messages crm
            INNER JOIN child_reports cr
                ON cr.id = crm.report_id
            WHERE cr.child_id = ?
                AND cr.parent_id = ?
                AND cr.id IN ({$placeholders})"
        );
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_message_count' => (int) ($row['total_message_count'] ?? 0),
            'child_message_count' => (int) ($row['child_message_count'] ?? 0),
            'assistant_message_count' => (int) ($row['assistant_message_count'] ?? 0),
            'active_days' => (int) ($row['active_days'] ?? 0),
            'first_message_at' => $row['first_message_at'] ?? null,
            'last_message_at' => $row['last_message_at'] ?? null,
        ];
    }

    public function getDueAutoReportChildren(?int $parentId = null, int $limit = 20): array
    {
        $safeLimit = max(1, min($limit, 100));
        $sql = "SELECT
                s.child_id,
                s.parent_id,
                s.auto_generate_enabled,
                s.auto_generate_frequency_days,
                s.auto_generate_window_days,
                s.next_report_due_at,
                s.last_report_generated_at,
                u.name,
                u.gender,
                u.birth_date,
                u.last_login_at
            FROM child_report_settings s
            INNER JOIN users u
                ON u.id = s.child_id
                AND u.role = 'child'
                AND u.parent_id = s.parent_id
            WHERE s.auto_generate_enabled = 1
                AND (s.next_report_due_at IS NULL OR s.next_report_due_at <= NOW())";

        $params = [];
        if ($parentId !== null) {
            $sql .= " AND s.parent_id = :parent_id";
            $params['parent_id'] = $parentId;
        }

        $sql .= " ORDER BY COALESCE(s.next_report_due_at, '1970-01-01 00:00:00') ASC
            LIMIT {$safeLimit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyLogins(int $childId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                login_date AS activity_date,
                login_count,
                first_login_at,
                last_login_at
            FROM child_daily_logins
            WHERE child_id = :child_id
                AND login_date BETWEEN :start_date AND :end_date
            ORDER BY login_date ASC"
        );
        $stmt->execute([
            'child_id' => $childId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyUsage(int $childId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                usage_date AS activity_date,
                used_minutes
            FROM child_daily_usage
            WHERE child_id = :child_id
                AND usage_date BETWEEN :start_date AND :end_date
            ORDER BY usage_date ASC"
        );
        $stmt->execute([
            'child_id' => $childId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyConversations(int $childId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                DATE(created_at) AS activity_date,
                COUNT(*) AS conversation_count
            FROM conversations
            WHERE user_id = :child_id
                AND DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(created_at)
            ORDER BY activity_date ASC"
        );
        $stmt->execute([
            'child_id' => $childId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyMessages(int $childId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                DATE(m.created_at) AS activity_date,
                COUNT(*) AS message_count,
                SUM(CASE WHEN m.role = 'user' THEN 1 ELSE 0 END) AS child_message_count,
                SUM(CASE WHEN m.role = 'assistant' THEN 1 ELSE 0 END) AS assistant_message_count,
                SUM(CASE WHEN m.role = 'user' THEN CHAR_LENGTH(m.content) ELSE 0 END) AS child_character_count
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            WHERE c.user_id = :child_id
                AND DATE(m.created_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(m.created_at)
            ORDER BY activity_date ASC"
        );
        $stmt->execute([
            'child_id' => $childId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentChildMessages(int $childId, string $startDate, string $endDate, int $limit = 80): array
    {
        return $this->getRecentChildMessagesBetween(
            $childId,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
            $limit
        );
    }

    public function getRecentChildMessagesBetween(
        int $childId,
        ?string $startAt,
        string $endAt,
        int $limit = 80
    ): array {
        $safeLimit = max(20, min($limit, 300));
        $sql = "SELECT
                m.id,
                m.conversation_id,
                m.content,
                m.created_at
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            WHERE c.user_id = :child_id
                AND m.role = 'user'
                AND m.created_at <= :end_at";

        $params = [
            'child_id' => $childId,
            'end_at' => $endAt,
        ];

        if ($startAt !== null) {
            $sql .= " AND m.created_at > :start_at";
            $params['start_at'] = $startAt;
        }

        $sql .= " ORDER BY m.created_at DESC, m.id DESC LIMIT {$safeLimit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_reverse($messages);
    }

    public function getConversationMessagesBetween(int $childId, ?string $startAt, string $endAt): array
    {
        $sql = "SELECT
                m.id AS message_id,
                m.conversation_id,
                m.role,
                m.content,
                m.created_at
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            WHERE c.user_id = :child_id
                AND m.created_at <= :end_at";

        $params = [
            'child_id' => $childId,
            'end_at' => $endAt,
        ];

        if ($startAt !== null) {
            $sql .= " AND m.created_at > :start_at";
            $params['start_at'] = $startAt;
        }

        $sql .= " ORDER BY m.created_at ASC, m.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
