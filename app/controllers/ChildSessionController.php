<?php

namespace App\Controllers;

use App\Models\ChildAccount;
use Core\Middleware;
use Utils\AppTime;
use Utils\ChildLoginWindow;

class ChildSessionController
{
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function formatTime(string $time): string
    {
        return substr($time, 0, 5);
    }

    public function status(): void
    {
        $childId = (int) ($_SESSION['user']['id'] ?? 0);
        $childAccount = new ChildAccount();
        $status = $childAccount->getChildUsageStatus($childId);

        if (!$status) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $dailyLimit = (int) ($status['daily_login_minutes'] ?? 0);
        $usedToday = (int) ($status['used_today_minutes'] ?? 0);
        $remainingMinutes = max(0, $dailyLimit - $usedToday);
        $serverNow = AppTime::now();
        $window = ChildLoginWindow::evaluate(
            $status['allowed_login_start'] ?? null,
            $status['allowed_login_end'] ?? null,
            $serverNow
        );

        $this->jsonResponse([
            'success' => true,
            'status' => [
                'remaining_minutes' => $remainingMinutes,
                'used_today_minutes' => $usedToday,
                'daily_login_minutes' => $dailyLimit,
                'login_disabled' => (bool) ($status['login_disabled'] ?? false),
                'blocked_reason' => Middleware::childAccessBlockReason($status),
                'allowed_login_start' => $this->formatTime((string) $status['allowed_login_start']),
                'allowed_login_end' => $this->formatTime((string) $status['allowed_login_end']),
                'last_login_at' => $status['last_login_at'],
                'within_login_window' => $window['is_allowed_now'],
                'spans_overnight' => $window['spans_overnight'],
                'server_time' => $serverNow->format('Y-m-d H:i:s'),
                'server_time_display' => $serverNow->format('H:i'),
                'server_time_iso' => $serverNow->format(DATE_ATOM),
                'server_timezone' => AppTime::timezoneName(),
            ],
        ]);
    }
}
