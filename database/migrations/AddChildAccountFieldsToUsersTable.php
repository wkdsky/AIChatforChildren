<?php

namespace Database\Migrations;

use Core\Migration;
use PDO;

class AddChildAccountFieldsToUsersTable extends Migration
{
    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function indexExists(string $indexName): bool
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM users WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function up()
    {
        if (!$this->columnExists('parent_id')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN parent_id INT NULL AFTER role");
        }

        if (!$this->columnExists('gender')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other') NULL AFTER verification_status");
        }

        if (!$this->columnExists('birth_date')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN birth_date DATE NULL AFTER gender");
        }

        if (!$this->columnExists('allowed_login_start')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN allowed_login_start TIME NOT NULL DEFAULT '00:00:00' AFTER birth_date");
        }

        if (!$this->columnExists('allowed_login_end')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN allowed_login_end TIME NOT NULL DEFAULT '23:50:00' AFTER allowed_login_start");
        }

        if (!$this->columnExists('daily_login_minutes')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN daily_login_minutes INT NOT NULL DEFAULT 120 AFTER allowed_login_end");
        }

        if (!$this->columnExists('login_disabled')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN login_disabled TINYINT(1) NOT NULL DEFAULT 0 AFTER daily_login_minutes");
        }

        if (!$this->columnExists('last_login_at')) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER login_disabled");
        }

        if (!$this->indexExists('idx_users_parent_id')) {
            $this->pdo->exec("ALTER TABLE users ADD INDEX idx_users_parent_id (parent_id)");
        }

        echo " Child account fields added to users table.\n";
    }

    public function down()
    {
        if ($this->indexExists('idx_users_parent_id')) {
            $this->pdo->exec("ALTER TABLE users DROP INDEX idx_users_parent_id");
        }

        if ($this->columnExists('birth_date')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN birth_date");
        }

        if ($this->columnExists('gender')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN gender");
        }

        if ($this->columnExists('last_login_at')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN last_login_at");
        }

        if ($this->columnExists('login_disabled')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN login_disabled");
        }

        if ($this->columnExists('daily_login_minutes')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN daily_login_minutes");
        }

        if ($this->columnExists('allowed_login_end')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN allowed_login_end");
        }

        if ($this->columnExists('allowed_login_start')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN allowed_login_start");
        }

        if ($this->columnExists('parent_id')) {
            $this->pdo->exec("ALTER TABLE users DROP COLUMN parent_id");
        }

        echo " Child account fields removed from users table.\n";
    }
}
