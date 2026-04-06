<?php

namespace Database\Migrations;

use Core\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('child', 'parent', 'admin') DEFAULT 'child' NOT NULL,
            parent_id INT NULL,
            verification_code INT NULL,
            verification_status ENUM('pending', 'verified') DEFAULT 'pending',
            gender ENUM('male', 'female', 'other') NULL,
            birth_date DATE NULL,
            allowed_login_start TIME NOT NULL DEFAULT '00:00:00',
            allowed_login_end TIME NOT NULL DEFAULT '23:50:00',
            daily_login_minutes INT NOT NULL DEFAULT 120,
            login_disabled TINYINT(1) NOT NULL DEFAULT 0,
            last_login_at DATETIME NULL,
            verification_requested_at TIMESTAMP NULL,
            request_attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_users_parent_id (parent_id)
        )";
        $this->pdo->exec($sql);
        echo " Users table created successfully.\n";
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS users");
        echo " Users table dropped.\n";
    }
}
