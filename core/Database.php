<?php

namespace Core;

use PDO;
use PDOException;
use Core\Config;
use DateTimeImmutable;
use DateTimeZone;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            // Read database settings from .env (via Config class)
            $host = Config::get('DB_HOST');
            $port = Config::get('DB_PORT'); // default to 3306 if not set
            $dbname = Config::get('DB_NAME');
            $username = Config::get('DB_USERNAME');
            $password = Config::get('DB_PASS'); // matches .env key

            // Build DSN with port and charset
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

            // Create PDO instance
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true
            ]);

            $timezoneName = Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'));
            $offset = (new DateTimeImmutable('now', new DateTimeZone($timezoneName)))->format('P');
            $timeZoneStatement = $this->pdo->prepare("SET time_zone = :offset");
            $timeZoneStatement->execute(['offset' => $offset]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    // Singleton pattern — ensures only one PDO instance
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}
