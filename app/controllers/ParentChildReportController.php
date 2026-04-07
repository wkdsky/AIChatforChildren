<?php

namespace App\Controllers;

use Utils\ChildReportService;
use Utils\Helper;

class ParentChildReportController
{
    private ChildReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ChildReportService();
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? null;
        if (!Helper::verifyCsrfToken($token)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'CSRF validation failed.',
            ], 403);
        }
    }

    private function getParentId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    public function overview(): void
    {
        $childId = (int) ($_GET['child_id'] ?? 0);
        $days = $this->reportService->normalizeDays($_GET['days'] ?? 14);

        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        try {
            $report = $this->reportService->getOverview($childId, $this->getParentId(), $days);
            $this->jsonResponse([
                'success' => true,
                'report' => $report,
            ]);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Child account not found.' ? 404 : 400;
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function history(): void
    {
        $childId = (int) ($_GET['child_id'] ?? 0);
        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        try {
            $bundle = $this->reportService->getHistoryBundle($childId, $this->getParentId());
            $this->jsonResponse([
                'success' => true,
                'settings' => $bundle['settings'],
                'reports' => $bundle['reports'],
            ]);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Child account not found.' ? 404 : 400;
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function item(): void
    {
        $childId = (int) ($_GET['child_id'] ?? 0);
        $reportId = (int) ($_GET['report_id'] ?? 0);

        if ($childId <= 0 || $reportId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id and report id are required.',
            ], 422);
        }

        try {
            $bundle = $this->reportService->getStoredReport($reportId, $childId, $this->getParentId());
            $this->jsonResponse([
                'success' => true,
                'report' => $bundle['report'],
                'report_record' => $bundle['report_record'],
            ]);
        } catch (\RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Child account not found.', 'Report not found.'], true) ? 404 : 400;
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function settings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $this->verifyCsrf();

        $childId = (int) ($_POST['child_id'] ?? 0);
        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        try {
            $settings = $this->reportService->updateSettings($childId, $this->getParentId(), [
                'auto_generate_enabled' => $_POST['auto_generate_enabled'] ?? '',
                'auto_generate_frequency_days' => $_POST['auto_generate_frequency_days'] ?? 7,
                'auto_generate_window_days' => $_POST['auto_generate_window_days'] ?? 14,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Report settings updated successfully.',
                'settings' => $settings,
            ]);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Child account not found.' ? 404 : 400;
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function content(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $this->verifyCsrf();

        $childId = (int) ($_POST['child_id'] ?? 0);
        $days = $this->reportService->normalizeDays($_POST['days'] ?? 14);

        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        try {
            $bundle = $this->reportService->generateAndStore($childId, $this->getParentId(), $days, 'manual');
            $this->jsonResponse([
                'success' => true,
                'message' => !empty($bundle['updated_existing'])
                    ? "Today's manual report updated."
                    : 'Report generated successfully.',
                'report' => $bundle['report'],
                'report_record' => $bundle['report_record'],
                'settings' => $bundle['settings'],
                'reports' => $bundle['reports'],
                'updated_existing' => !empty($bundle['updated_existing']),
            ]);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Child account not found.' ? 404 : 400;
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function trend(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $this->verifyCsrf();

        $childId = (int) ($_POST['child_id'] ?? 0);
        $reportIds = $_POST['report_ids'] ?? [];

        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        if (!is_array($reportIds)) {
            $reportIds = [$reportIds];
        }

        try {
            $bundle = $this->reportService->getCumulativeAnalysis(
                $childId,
                $this->getParentId(),
                array_map('intval', $reportIds)
            );

            $this->jsonResponse([
                'success' => true,
                'analysis' => $bundle['analysis'],
                'reports' => $bundle['reports'],
            ]);
        } catch (\RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Child account not found.', 'Select at least one saved report.'], true) ? 400 : 404;
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
