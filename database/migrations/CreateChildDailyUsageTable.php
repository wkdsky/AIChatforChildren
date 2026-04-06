<?php

namespace Database\Migrations;

use Core\Migration;

class CreateChildDailyUsageTable extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS child_daily_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id INT NOT NULL,
            usage_date DATE NOT NULL,
            used_minutes INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_child_usage_date (child_id, usage_date),
            INDEX idx_child_usage_date (child_id, usage_date),
            FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
        echo " Child daily usage table created successfully.\n";
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS child_daily_usage");
        echo " Child daily usage table dropped.\n";
    }
}
