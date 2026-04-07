#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Core\Config;
use Utils\ChildReportService;

date_default_timezone_set(
    Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'))
);

$parentId = null;
$limit = 20;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--parent=')) {
        $parentId = (int) substr($arg, 9);
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min((int) substr($arg, 8), 100));
    }
}

$service = new ChildReportService();
$results = $service->runDueAutoReports($parentId ?: null, $limit);

echo json_encode([
    'generated' => count($results),
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
