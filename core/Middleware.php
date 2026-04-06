<?php

namespace Core;

use App\Models\ChildAccount;
use Utils\Helper;

class Middleware
{
    private static function clearSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        setcookie("remember_me", "", time() - 3600, "/", "", true, true);
    }

    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($uri, '/api/')
            || str_contains($accept, 'application/json')
            || strtolower($requestedWith) === 'xmlhttprequest';
    }

    private static function jsonError(string $message, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
        ]);
        exit;
    }

    public static function childAccessBlockReason(array $status): ?string
    {
        if ((bool) ($status['login_disabled'] ?? false)) {
            return 'This child account has been temporarily disabled by the parent.';
        }

        $now = date('H:i:s');
        $allowedStart = $status['allowed_login_start'] ?? null;
        $allowedEnd = $status['allowed_login_end'] ?? null;

        if ($allowedStart && $allowedEnd && ($now < $allowedStart || $now >= $allowedEnd)) {
            return 'Child login is not allowed at this time.';
        }

        $dailyLimit = (int) ($status['daily_login_minutes'] ?? 0);
        $usedToday = (int) ($status['used_today_minutes'] ?? 0);

        if ($dailyLimit > 0 && $usedToday >= $dailyLimit) {
            return 'Daily login time has been used up.';
        }

        return null;
    }

    private static function enforceChildAccessPolicy(): void
    {
        if (($_SESSION['user']['role'] ?? null) !== 'child') {
            return;
        }

        $childId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($childId <= 0) {
            return;
        }

        $childAccount = new ChildAccount();
        $lastTrackedAt = (int) ($_SESSION['child_usage_last_tracked_at'] ?? time());
        $now = time();
        $elapsedMinutes = intdiv(max(0, $now - $lastTrackedAt), 60);

        if ($elapsedMinutes > 0) {
            $childAccount->addUsageMinutes($childId, $elapsedMinutes);
            $_SESSION['child_usage_last_tracked_at'] = $lastTrackedAt + ($elapsedMinutes * 60);
        } elseif (!isset($_SESSION['child_usage_last_tracked_at'])) {
            $_SESSION['child_usage_last_tracked_at'] = $now;
        }

        $status = $childAccount->getChildUsageStatus($childId);
        if (!$status) {
            if (self::isApiRequest()) {
                self::clearSession();
                self::jsonError('Child account not found.', 404);
            }
            self::logout();
        }

        $blockReason = self::childAccessBlockReason($status);
        if ($blockReason !== null) {
            if (self::isApiRequest()) {
                self::clearSession();
                self::jsonError($blockReason, 403);
            }
            $_SESSION['errors']['general'][] = $blockReason;
            self::logout();
        }
    }

    /**
     * Check if the user is authenticated.
     * Redirect to sign-in page if not logged in.
     */
    public static function requireAuth()
    {
        if (!isset($_SESSION['user'])) {
            if (self::isApiRequest()) {
                self::jsonError('Authentication required', 401);
            }
            header("Location: " . Helper::url('sign-in'));
            exit;
        }

        // Auto logout if inactive for too long
        self::checkSessionTimeout();
        self::enforceChildAccessPolicy();
    }

    /**
     * Ensure a guest (unauthenticated user) is accessing certain pages.
     * Redirect logged-in users away from sign-in/sign-up pages.
     */
    public static function guestOnly()
    {
        if (isset($_SESSION['user'])) {
            $role = $_SESSION['user']['role'];
            switch ($role) {
                case 'admin':
                    header("Location: " . Helper::url('admin-dashboard'));
                    break;
                case 'parent':
                    header("Location: " . Helper::url('parent'));
                    break;
                case 'child':
                    header("Location: " . Helper::url('child'));
                    break;
                default:
                    header("Location: " . Helper::url('home'));
                    break;
            }
            exit;
        }
    }

    /**
     * Check if the user is an admin.
     * Redirect to home if not an admin.
     */
    public static function requireAdmin()
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            if (self::isApiRequest()) {
                self::jsonError('Admin access required', isset($_SESSION['user']) ? 403 : 401);
            }
            header("Location: " . Helper::url('home'));
            exit;
        }
    }

    /**
     * Check if the user is a parent.
     * Redirect to home if not a parent.
     */
    public static function requireParent()
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'parent') {
            if (self::isApiRequest()) {
                self::jsonError('Parent access required', isset($_SESSION['user']) ? 403 : 401);
            }
            header("Location: " . Helper::url('home'));
            exit;
        }
    }

    /**
     * Check if the user is a child.
     * Redirect to home if not a child.
     */
    public static function requireChild()
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'child') {
            if (self::isApiRequest()) {
                self::jsonError('Child access required', isset($_SESSION['user']) ? 403 : 401);
            }
            header("Location: " . Helper::url('home'));
            exit;
        }
    }

    /**
     * Auto logout inactive users.
     */
    private static function checkSessionTimeout()
    {
        $timeout_duration = 900; // 15 minutes
        if (
            isset($_SESSION['user']['last_activity']) &&
            (time() - $_SESSION['user']['last_activity']) > $timeout_duration
        ) {
            self::clearSession();
            if (self::isApiRequest()) {
                self::jsonError('Session expired, please sign in again', 401);
            }
            header("Location: " . Helper::url('sign-in'));
            exit;
        }

        $_SESSION['user']['last_activity'] = time();
    }
    public static function logout()
    {
        self::clearSession();
        header("Location: " . Helper::url('sign-in'));
        exit;
    }
}
