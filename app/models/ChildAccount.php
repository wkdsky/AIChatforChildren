<?php

namespace App\Models;

use Core\BaseModel;
use PDO;

class ChildAccount extends BaseModel
{
    protected $table = 'users';

    public function getManagedChildrenByParentId(int $parentId): array
    {
        $sql = "SELECT
                    u.id,
                    u.name,
                    u.gender,
                    u.birth_date,
                    u.allowed_login_start,
                    u.allowed_login_end,
                    u.daily_login_minutes,
                    u.login_disabled,
                    u.last_login_at,
                    u.created_at,
                    COALESCE(cdu.used_minutes, 0) AS used_today_minutes
                FROM users u
                LEFT JOIN child_daily_usage cdu
                    ON cdu.child_id = u.id
                    AND cdu.usage_date = CURDATE()
                WHERE u.role = 'child' AND u.parent_id = :parent_id
                ORDER BY u.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getManagedChildById(int $childId, int $parentId): ?array
    {
        $sql = "SELECT
                    u.id,
                    u.name,
                    u.gender,
                    u.birth_date,
                    u.allowed_login_start,
                    u.allowed_login_end,
                    u.daily_login_minutes,
                    u.login_disabled,
                    u.last_login_at,
                    u.created_at,
                    COALESCE(cdu.used_minutes, 0) AS used_today_minutes
                FROM users u
                LEFT JOIN child_daily_usage cdu
                    ON cdu.child_id = u.id
                    AND cdu.usage_date = CURDATE()
                WHERE u.id = :child_id
                    AND u.role = 'child'
                    AND u.parent_id = :parent_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        return $child ?: null;
    }

    public function getChildUsageStatus(int $childId): ?array
    {
        $sql = "SELECT
                    u.id,
                    u.name,
                    u.allowed_login_start,
                    u.allowed_login_end,
                    u.daily_login_minutes,
                    u.login_disabled,
                    u.last_login_at,
                    COALESCE(cdu.used_minutes, 0) AS used_today_minutes
                FROM users u
                LEFT JOIN child_daily_usage cdu
                    ON cdu.child_id = u.id
                    AND cdu.usage_date = CURDATE()
                WHERE u.id = :child_id
                    AND u.role = 'child'
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['child_id' => $childId]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        return $status ?: null;
    }

    public function updateManagedChild(int $childId, int $parentId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $setParts = [];
        $params = [
            'child_id' => $childId,
            'parent_id' => $parentId,
        ];

        foreach ($data as $field => $value) {
            $setParts[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }

        $sql = "UPDATE users
                SET " . implode(', ', $setParts) . "
                WHERE id = :child_id AND role = 'child' AND parent_id = :parent_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteManagedChild(int $childId, int $parentId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'child' AND parent_id = ?");
        $stmt->execute([$childId, $parentId]);
        return $stmt->rowCount() > 0;
    }

    public function setManagedChildLoginDisabled(int $childId, int $parentId, bool $disabled): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
            SET login_disabled = :login_disabled
            WHERE id = :child_id AND role = 'child' AND parent_id = :parent_id"
        );

        $stmt->execute([
            'login_disabled' => $disabled ? 1 : 0,
            'child_id' => $childId,
            'parent_id' => $parentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function addUsageMinutes(int $childId, int $minutes): void
    {
        if ($minutes <= 0) {
            return;
        }

        $sql = "INSERT INTO child_daily_usage (child_id, usage_date, used_minutes)
                VALUES (:child_id, CURDATE(), :used_minutes)
                ON DUPLICATE KEY UPDATE
                    used_minutes = used_minutes + VALUES(used_minutes),
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'child_id' => $childId,
            'used_minutes' => $minutes,
        ]);
    }

    public function recordLogin(int $childId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ? AND role = 'child'");
        $stmt->execute([$childId]);

        $dailyLoginStmt = $this->pdo->prepare(
            "INSERT INTO child_daily_logins (child_id, login_date, login_count, first_login_at, last_login_at)
            VALUES (:child_id, CURDATE(), 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                login_count = login_count + 1,
                last_login_at = NOW(),
                updated_at = CURRENT_TIMESTAMP"
        );
        $dailyLoginStmt->execute([
            'child_id' => $childId,
        ]);
    }
}
