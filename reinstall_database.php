#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Core\Config;

date_default_timezone_set(
    Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'))
);

const DEFAULT_ADMIN_NAME = 'Admin';
const DEFAULT_ADMIN_EMAIL = 'admin@example.com';
const DEFAULT_ADMIN_PASSWORD = 'password';

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function getArgFlag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

function getMigrationFiles(): array
{
    $files = glob(__DIR__ . '/database/migrations/*.php');

    usort($files, function ($a, $b) {
        $priorityMap = [
            'CreateUsersTable' => 10,
            'AddChildAccountFieldsToUsersTable' => 20,
            'CreateChildDailyUsageTable' => 30,
            'CreateChildDailyLoginsTable' => 35,
            'CreateConversationsTable' => 40,
            'CreateKnowledgeBaseTables' => 50,
        ];

        $aName = pathinfo($a, PATHINFO_FILENAME);
        $bName = pathinfo($b, PATHINFO_FILENAME);

        $aPriority = $priorityMap[$aName] ?? 100;
        $bPriority = $priorityMap[$bName] ?? 100;

        if ($aPriority === $bPriority) {
            return strcmp($aName, $bName);
        }

        return $aPriority <=> $bPriority;
    });

    return $files;
}

function runMigrations(bool $rollback): void
{
    $files = getMigrationFiles();
    if ($rollback) {
        $files = array_reverse($files);
    }

    foreach ($files as $file) {
        require_once $file;

        $filename = pathinfo($file, PATHINFO_FILENAME);
        $className = "Database\\Migrations\\{$filename}";

        if (!class_exists($className)) {
            throw new RuntimeException("Migration class {$className} not found.");
        }

        out(($rollback ? 'Rolling back: ' : 'Running migration: ') . $className);
        $migration = new $className();

        if ($rollback) {
            $migration->down();
        } else {
            $migration->up();
        }
    }
}

function connectServerPdo(): PDO
{
    $host = Config::get('DB_HOST', '127.0.0.1');
    $port = Config::get('DB_PORT', '3306');
    $username = Config::get('DB_USERNAME', 'root');
    $password = Config::get('DB_PASS', '');
    $socket = Config::get('DB_SOCKET', '');

    $dsn = $socket !== ''
        ? "mysql:unix_socket={$socket};charset=utf8mb4"
        : "mysql:host={$host};port={$port};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function connectDatabasePdo(string $databaseName): PDO
{
    $host = Config::get('DB_HOST', '127.0.0.1');
    $port = Config::get('DB_PORT', '3306');
    $username = Config::get('DB_USERNAME', 'root');
    $password = Config::get('DB_PASS', '');
    $socket = Config::get('DB_SOCKET', '');

    $dsn = $socket !== ''
        ? "mysql:unix_socket={$socket};dbname={$databaseName};charset=utf8mb4"
        : "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $timezoneName = Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'));
    $offset = (new DateTimeImmutable('now', new DateTimeZone($timezoneName)))->format('P');
    $stmt = $pdo->prepare('SET time_zone = :offset');
    $stmt->execute(['offset' => $offset]);

    return $pdo;
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function databaseExists(PDO $pdo, string $databaseName): bool
{
    $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([$databaseName]);
    return (bool) $stmt->fetchColumn();
}

function usersTableExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    return (bool) $stmt->fetchColumn();
}

function exportAdminAccounts(PDO $pdo): array
{
    if (!usersTableExists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT
            name,
            email,
            password,
            verification_status,
            created_at
        FROM users
        WHERE role = 'admin'
        ORDER BY id ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function seedAdminAccounts(PDO $pdo, array $admins): array
{
    if ($admins === []) {
        $admins = [[
            'name' => Config::get('ADMIN_NAME', DEFAULT_ADMIN_NAME),
            'email' => Config::get('ADMIN_EMAIL', DEFAULT_ADMIN_EMAIL),
            'password' => password_hash(Config::get('ADMIN_PASSWORD', DEFAULT_ADMIN_PASSWORD), PASSWORD_BCRYPT),
            'verification_status' => 'verified',
            'created_at' => date('Y-m-d H:i:s'),
        ]];
    }

    $insert = $pdo->prepare(
        "INSERT INTO users (
            name,
            email,
            password,
            role,
            verification_code,
            verification_status,
            created_at
        ) VALUES (
            :name,
            :email,
            :password,
            'admin',
            NULL,
            :verification_status,
            :created_at
        )"
    );

    $seeded = [];
    foreach ($admins as $admin) {
        $insert->execute([
            'name' => $admin['name'] ?: DEFAULT_ADMIN_NAME,
            'email' => $admin['email'],
            'password' => $admin['password'],
            'verification_status' => $admin['verification_status'] ?: 'verified',
            'created_at' => $admin['created_at'] ?: date('Y-m-d H:i:s'),
        ]);
        $seeded[] = [
            'name' => $admin['name'] ?: DEFAULT_ADMIN_NAME,
            'email' => $admin['email'],
        ];
    }

    return $seeded;
}

try {
    $fresh = !getArgFlag($argv, '--up-only');
    $databaseName = Config::get('DB_NAME', 'starter');

    out('== AIChatforChildren database setup ==');
    out('Database: ' . $databaseName);
    out('Mode: ' . ($fresh ? 'fresh rebuild' : 'migrate up only'));

    $serverPdo = connectServerPdo();
    if (!databaseExists($serverPdo, $databaseName)) {
        out("Creating database {$databaseName}...");
        $serverPdo->exec(
            'CREATE DATABASE IF NOT EXISTS ' . quoteIdentifier($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    $preservedAdmins = [];
    if ($fresh) {
        $databasePdo = connectDatabasePdo($databaseName);
        $preservedAdmins = exportAdminAccounts($databasePdo);
        if ($preservedAdmins !== []) {
            out('Found ' . count($preservedAdmins) . ' existing admin account(s); preserving them across rebuild.');
        } else {
            out('No existing admin account found; a default admin will be created after migration.');
        }

        runMigrations(true);
    }

    runMigrations(false);

    $databasePdo = connectDatabasePdo($databaseName);
    $seededAdmins = seedAdminAccounts($databasePdo, $preservedAdmins);

    out('');
    out('Database setup completed successfully.');
    out('Admin account(s):');
    foreach ($seededAdmins as $admin) {
        out(' - ' . $admin['email'] . ' (' . $admin['name'] . ')');
    }

    if ($preservedAdmins === []) {
        out('');
        out('Default admin login:');
        out(' - Email: ' . Config::get('ADMIN_EMAIL', DEFAULT_ADMIN_EMAIL));
        out(' - Password: ' . Config::get('ADMIN_PASSWORD', DEFAULT_ADMIN_PASSWORD));
        out('Change this password immediately after first login.');
    }

    out('');
    out('Done.');
    exit(0);
} catch (PDOException $exception) {
    err('Database error: ' . $exception->getMessage());
    exit(1);
} catch (Throwable $exception) {
    err('Setup failed: ' . $exception->getMessage());
    exit(1);
}
