<?php

namespace Database\Migrations;

use Core\Migration;

class CreateChildReportsTables extends Migration
{
    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE :table_name");
        $stmt->execute(['table_name' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name"
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND INDEX_NAME = :index_name"
        );
        $stmt->execute([
            'table_name' => $tableName,
            'index_name' => $indexName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function up()
    {
        $settingsSql = "CREATE TABLE IF NOT EXISTS child_report_settings (
            child_id INT NOT NULL,
            parent_id INT NOT NULL,
            auto_generate_enabled TINYINT(1) NOT NULL DEFAULT 0,
            auto_generate_frequency_days INT NOT NULL DEFAULT 7,
            auto_generate_window_days INT NOT NULL DEFAULT 14,
            next_report_due_at DATETIME NULL,
            last_report_generated_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (child_id),
            INDEX idx_child_report_settings_parent (parent_id),
            INDEX idx_child_report_settings_due (auto_generate_enabled, next_report_due_at),
            CONSTRAINT fk_child_report_settings_child FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_child_report_settings_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $reportsSql = "CREATE TABLE IF NOT EXISTS child_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id INT NOT NULL,
            parent_id INT NOT NULL,
            generation_mode ENUM('manual', 'auto') NOT NULL DEFAULT 'manual',
            status VARCHAR(32) NOT NULL DEFAULT 'ready',
            window_days INT NOT NULL DEFAULT 14,
            window_start_date DATE NOT NULL,
            window_end_date DATE NOT NULL,
            scope_started_at DATETIME NULL,
            scope_ended_at DATETIME NULL,
            report_day DATE NULL,
            sample_message_count INT NOT NULL DEFAULT 0,
            sample_character_count INT NOT NULL DEFAULT 0,
            sample_active_days INT NOT NULL DEFAULT 0,
            message_record_count INT NOT NULL DEFAULT 0,
            confidence VARCHAR(16) NOT NULL DEFAULT 'none',
            risk_level VARCHAR(16) NOT NULL DEFAULT 'low',
            headline VARCHAR(255) NOT NULL DEFAULT '',
            report_json LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_child_reports_child_created (child_id, created_at),
            INDEX idx_child_reports_parent_created (parent_id, created_at),
            INDEX idx_child_reports_child_day (child_id, report_day),
            INDEX idx_child_reports_scope_end (child_id, scope_ended_at),
            CONSTRAINT fk_child_reports_child FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_child_reports_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $reportMessagesSql = "CREATE TABLE IF NOT EXISTS child_report_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            message_id INT NOT NULL,
            conversation_id INT NOT NULL,
            role ENUM('user', 'assistant', 'system') NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_child_report_messages_report (report_id),
            INDEX idx_child_report_messages_message (message_id),
            UNIQUE KEY uniq_child_report_message (report_id, message_id),
            CONSTRAINT fk_child_report_messages_report FOREIGN KEY (report_id) REFERENCES child_reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($settingsSql);
        $this->pdo->exec($reportsSql);
        $this->pdo->exec($reportMessagesSql);

        if (!$this->columnExists('child_reports', 'scope_started_at')) {
            $this->pdo->exec("ALTER TABLE child_reports ADD COLUMN scope_started_at DATETIME NULL AFTER window_end_date");
        }

        if (!$this->columnExists('child_reports', 'scope_ended_at')) {
            $this->pdo->exec("ALTER TABLE child_reports ADD COLUMN scope_ended_at DATETIME NULL AFTER scope_started_at");
        }

        if (!$this->columnExists('child_reports', 'report_day')) {
            $this->pdo->exec("ALTER TABLE child_reports ADD COLUMN report_day DATE NULL AFTER scope_ended_at");
        }

        if (!$this->columnExists('child_reports', 'message_record_count')) {
            $this->pdo->exec("ALTER TABLE child_reports ADD COLUMN message_record_count INT NOT NULL DEFAULT 0 AFTER sample_active_days");
        }

        if (!$this->indexExists('child_reports', 'idx_child_reports_child_day')) {
            $this->pdo->exec("ALTER TABLE child_reports ADD INDEX idx_child_reports_child_day (child_id, report_day)");
        }

        if (!$this->indexExists('child_reports', 'idx_child_reports_scope_end')) {
            $this->pdo->exec("ALTER TABLE child_reports ADD INDEX idx_child_reports_scope_end (child_id, scope_ended_at)");
        }

        if (!$this->tableExists('child_report_messages')) {
            $this->pdo->exec($reportMessagesSql);
        }

        echo " Child report tables created successfully.\n";
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS child_report_messages");
        $this->pdo->exec("DROP TABLE IF EXISTS child_reports");
        $this->pdo->exec("DROP TABLE IF EXISTS child_report_settings");
        echo " Child report tables dropped.\n";
    }
}
