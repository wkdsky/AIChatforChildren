<?php

namespace App\Models;

use Core\BaseModel;
use PDO;

class ChildReport extends BaseModel
{
    protected $table = 'users';

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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentChildMessages(int $childId, string $startDate, string $endDate, int $limit = 80): array
    {
        $safeLimit = max(20, min($limit, 200));
        $stmt = $this->pdo->prepare(
            "SELECT
                m.id,
                m.content,
                m.created_at
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            WHERE c.user_id = :child_id
                AND m.role = 'user'
                AND DATE(m.created_at) BETWEEN :start_date AND :end_date
            ORDER BY m.created_at DESC
            LIMIT {$safeLimit}"
        );
        $stmt->execute([
            'child_id' => $childId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_reverse($messages);
    }
}
