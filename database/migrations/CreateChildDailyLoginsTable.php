<?php

namespace Database\Migrations;

use Core\Migration;

class CreateChildDailyLoginsTable extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS child_daily_logins (
            child_id INT NOT NULL,
            login_date DATE NOT NULL,
            login_count INT NOT NULL DEFAULT 0,
            first_login_at DATETIME NULL,
            last_login_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (child_id, login_date),
            FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_child_daily_logins_date (login_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
        echo " Child daily logins table created successfully.\n";
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS child_daily_logins");
        echo " Child daily logins table dropped.\n";
    }
}
