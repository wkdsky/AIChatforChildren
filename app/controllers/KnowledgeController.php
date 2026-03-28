<?php
/**
 * Knowledge Base Controller
 * Handles API requests for knowledge base management
 * Proxies requests to the ChromaDB Python service
 */

namespace App\Controllers;

class KnowledgeController
{
    private string $serviceUrl;
    private int $requestTimeout = 120;
    private string $projectRoot;

    public function __construct()
    {
        $host = $_ENV['CHROMA_SERVICE_HOST'] ?? '127.0.0.1';
        $port = $_ENV['CHROMA_SERVICE_PORT'] ?? '4001';
        $this->serviceUrl = "http://{$host}:{$port}";
        $this->projectRoot = dirname(__DIR__, 2);
    }

    /**
     * Get the maximum upload file size in bytes
     */
    private function getMaxUploadSize(): int
    {
        $phpLimit = $this->parseSize(ini_get('upload_max_filesize'));
        $postLimit = $this->parseSize(ini_get('post_max_size'));
        $envLimit = $this->parseSize(($_ENV['CHROMA_MAX_FILE_SIZE'] ?? '20') . 'M');

        return min($phpLimit, $postLimit, $envLimit);
    }

    /**
     * Parse size string (e.g., "2M", "512K") to bytes
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;

        switch ($unit) {
            case 'G':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'M':
                $value *= 1024 * 1024;
                break;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Format bytes to human readable size
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1) . 'GB';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . 'KB';
        }
        return $bytes . 'B';
    }

    /**
     * Check if ChromaDB service is running
     */
    private function isServiceRunning(): bool
    {
        $ch = curl_init($this->serviceUrl . '/api/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Inspect service API compatibility against the admin knowledge page requirements
     */
    private function inspectServiceApi(): array
    {
        $requiredEndpoints = [
            '/api/upload' => ['post'],
            '/api/files' => ['get'],
            '/api/files/{file_id}' => ['get', 'put', 'delete'],
            '/api/files/{file_id}/status' => ['get'],
            '/api/files/{file_id}/chunks' => ['get'],
            '/api/files/{file_id}/chunks/{chunk_id}' => ['get'],
            '/api/files/{file_id}/chunks/bulk' => ['put'],
            '/api/files/{file_id}/actions/{action}' => ['post'],
            '/api/search' => ['get'],
            '/api/context' => ['get'],
        ];

        $result = $this->proxyRequest('/openapi.json');
        if (!$result['success'] || $result['code'] !== 200 || !is_array($result['data'])) {
            return [
                'compatible' => false,
                'missing' => array_keys($requiredEndpoints),
                'required' => $requiredEndpoints,
            ];
        }

        $paths = $result['data']['paths'] ?? [];
        $missing = [];

        foreach ($requiredEndpoints as $path => $methods) {
            if (!isset($paths[$path]) || !is_array($paths[$path])) {
                $missing[] = $path;
                continue;
            }

            $availableMethods = array_map('strtolower', array_keys($paths[$path]));
            foreach ($methods as $method) {
                if (!in_array(strtolower($method), $availableMethods, true)) {
                    $missing[] = $path . ' [' . strtoupper($method) . ']';
                }
            }
        }

        return [
            'compatible' => empty($missing),
            'missing' => $missing,
            'required' => $requiredEndpoints,
        ];
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function getJsonInput(): array
    {
        $decoded = json_decode(file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getKnowledgeRebuildPaths(): array
    {
        $storageDir = $this->projectRoot . '/storage/knowledge';
        return [
            'service_dir' => $this->projectRoot . '/services/chroma',
            'script' => $this->projectRoot . '/services/chroma/rebuild_kb.py',
            'status' => $storageDir . '/rebuild_status.json',
            'log' => $storageDir . '/rebuild.log',
        ];
    }

    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeJsonFile(string $path, array $payload): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function isProcessRunning(?int $pid): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        if (!$pid || $pid <= 0) {
            return false;
        }

        $output = [];
        $exitCode = 1;
        @exec('ps -p ' . (int) $pid . ' -o pid=', $output, $exitCode);
        if ($exitCode !== 0) {
            return false;
        }

        foreach ($output as $line) {
            if (trim((string) $line) === (string) $pid) {
                return true;
            }
        }

        return false;
    }

    private function readLogTail(string $path, int $maxLines = 40): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $tail = array_slice($lines, -1 * $maxLines);
        return array_values(array_filter(array_map('trim', $tail), static fn ($line) => $line !== ''));
    }

    private function getKnowledgeRebuildStatusPayload(): array
    {
        $paths = $this->getKnowledgeRebuildPaths();
        $status = $this->readJsonFile($paths['status']);

        $payload = [
            'status' => 'idle',
            'message' => '未开始重建任务。',
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'pid' => null,
            'scanned' => 0,
            'inserted' => 0,
            'repaired' => 0,
            'skipped' => 0,
            'failed' => 0,
            'last_file' => null,
            'log_lines' => $this->readLogTail($paths['log']),
        ];

        if (!empty($status)) {
            $payload = array_merge($payload, $status);
        }

        $pid = isset($payload['pid']) ? (int) $payload['pid'] : null;
        $isRunning = $this->isProcessRunning($pid);
        $payload['is_running'] = $isRunning;

        if (in_array($payload['status'], ['queued', 'running'], true) && !$isRunning) {
            if (empty($payload['finished_at'])) {
                $payload['status'] = 'failed';
                $payload['message'] = '重建进程已退出，请检查日志。';
                $payload['finished_at'] = date('c');
                $this->writeJsonFile($paths['status'], $payload);
            }
        }

        return $payload;
    }

    /**
     * Make request to ChromaDB service
     */
    private function proxyRequest(string $endpoint, string $method = 'GET', array $data = null, array $files = null): array
    {
        if (!$this->isServiceRunning()) {
            return [
                'success' => false,
                'error' => 'Knowledge base service is not running. Please start the ChromaDB service first.',
                'code' => 503
            ];
        }

        $url = $this->serviceUrl . $endpoint;
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->requestTimeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($files) {
                    $postData = [];
                    foreach ($files as $key => $file) {
                        $postData[$key] = new \CURLFile(
                            $file['tmp_name'],
                            $file['type'],
                            $file['name']
                        );
                    }
                    if ($data) {
                        foreach ($data as $key => $value) {
                            $postData[$key] = $value;
                        }
                    }
                    $options[CURLOPT_POSTFIELDS] = $postData;
                } elseif ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                    $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
                }
                break;

            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                    $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
                }
                break;

            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Failed to connect to knowledge base service: ' . $error,
                'code' => 500
            ];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && $response !== 'null') {
            return [
                'success' => false,
                'error' => 'Invalid response from service',
                'code' => 500
            ];
        }

        return [
            'success' => true,
            'data' => $decoded,
            'code' => $httpCode
        ];
    }

    /**
     * Handle file upload
     */
    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $maxSize = $this->formatSize($this->getMaxUploadSize());
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => "File exceeds server limit ({$maxSize})",
                UPLOAD_ERR_FORM_SIZE => "File exceeds form limit ({$maxSize})",
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            ];
            $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errorMsg = $errorMessages[$errorCode] ?? 'Unknown upload error';
            $this->jsonResponse(['success' => false, 'error' => $errorMsg], 400);
        }

        $title = isset($_POST['title']) ? trim((string)$_POST['title']) : null;
        $payload = [];
        if ($title !== null && $title !== '') {
            $payload['title'] = $title;
        }

        $result = $this->proxyRequest('/api/upload', 'POST', $payload, ['file' => $_FILES['file']]);

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    /**
     * List all files
     */
    public function listFiles(): void
    {
        $params = [];

        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
        if ($search !== '') {
            $params[] = 'search=' . urlencode($search);
        }

        $ageBand = isset($_GET['age_band']) ? trim((string)$_GET['age_band']) : '';
        if ($ageBand !== '') {
            $params[] = 'age_band=' . urlencode($ageBand);
        }

        $reviewStatus = isset($_GET['review_status']) ? trim((string)$_GET['review_status']) : '';
        if ($reviewStatus !== '') {
            $params[] = 'review_status=' . urlencode($reviewStatus);
        }

        $endpoint = '/api/files';
        if (!empty($params)) {
            $endpoint .= '?' . implode('&', $params);
        }

        $result = $this->proxyRequest($endpoint);

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    /**
     * Delete a file
     */
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        $input = $this->getJsonInput();
        $fileId = $input['file_id'] ?? null;

        if (!$fileId) {
            $this->jsonResponse(['success' => false, 'error' => 'File ID is required'], 400);
        }

        $result = $this->proxyRequest('/api/files/' . urlencode($fileId), 'DELETE');

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    /**
     * Rename a file
     */
    public function rename(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        $input = $this->getJsonInput();
        $fileId = $input['file_id'] ?? null;
        $newName = $input['new_name'] ?? null;

        if (!$fileId || !$newName) {
            $this->jsonResponse(['success' => false, 'error' => 'File ID and new name are required'], 400);
        }

        $result = $this->proxyRequest(
            '/api/files/' . urlencode($fileId) . '/rename?new_name=' . urlencode($newName),
            'PUT'
        );

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    /**
     * Search knowledge base
     */
    public function search(): void
    {
        $query = $_GET['query'] ?? '';
        $limit = (int)($_GET['limit'] ?? 5);
        $sessionType = $_GET['session_type'] ?? 'system';
        $ageBand = $_GET['age_band'] ?? null;
        $includeFiltered = $_GET['include_filtered'] ?? null;

        if (empty($query)) {
            $this->jsonResponse(['success' => false, 'error' => 'Query is required'], 400);
        }

        $endpoint = '/api/search?query=' . urlencode($query)
            . '&limit=' . $limit
            . '&session_type=' . urlencode($sessionType);

        if ($ageBand) {
            $endpoint .= '&age_band=' . urlencode($ageBand);
        }

        if ($includeFiltered !== null) {
            $endpoint .= '&include_filtered=' . urlencode((string) $includeFiltered);
        }

        $result = $this->proxyRequest($endpoint);

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function getFile(string $fileId): void
    {
        $result = $this->proxyRequest('/api/files/' . urlencode($fileId));

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function getFileStatus(string $fileId): void
    {
        $result = $this->proxyRequest('/api/files/' . urlencode($fileId) . '/status');

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function updateFile(string $fileId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        $input = $this->getJsonInput();
        unset($input['csrf_token']);

        $result = $this->proxyRequest('/api/files/' . urlencode($fileId), 'PUT', $input);

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function getChunks(string $fileId): void
    {
        $params = [];
        $supported = ['search', 'visibility', 'audience', 'age_band', 'retrieval_enabled', 'sort_by', 'sort_dir'];

        foreach ($supported as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $params[] = urlencode($key) . '=' . urlencode((string) $_GET[$key]);
            }
        }

        $endpoint = '/api/files/' . urlencode($fileId) . '/chunks';
        if (!empty($params)) {
            $endpoint .= '?' . implode('&', $params);
        }

        $result = $this->proxyRequest($endpoint);

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function getChunk(string $fileId, string $chunkId): void
    {
        $result = $this->proxyRequest('/api/files/' . urlencode($fileId) . '/chunks/' . urlencode($chunkId));

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function bulkUpdateChunks(string $fileId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        $input = $this->getJsonInput();
        unset($input['csrf_token']);

        $result = $this->proxyRequest('/api/files/' . urlencode($fileId) . '/chunks/bulk', 'PUT', $input);

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    public function queueAction(string $fileId, string $action): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        $result = $this->proxyRequest('/api/files/' . urlencode($fileId) . '/actions/' . urlencode($action), 'POST');

        if (!$result['success']) {
            $this->jsonResponse(['success' => false, 'error' => $result['error']], $result['code']);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    /**
     * Get context for AI chat
     */
    public function getContext(): void
    {
        $query = $_GET['query'] ?? '';
        $limit = (int)($_GET['limit'] ?? 3);
        $sessionType = $_GET['session_type'] ?? 'child';
        $ageBand = $_GET['age_band'] ?? null;

        if (empty($query)) {
            $this->jsonResponse(['context' => '', 'sources' => []], 200);
        }

        $endpoint = '/api/context?query=' . urlencode($query)
            . '&limit=' . $limit
            . '&session_type=' . urlencode($sessionType);

        if ($ageBand) {
            $endpoint .= '&age_band=' . urlencode($ageBand);
        }

        $result = $this->proxyRequest($endpoint);

        if (!$result['success']) {
            $this->jsonResponse(['context' => '', 'sources' => []], 200);
        }

        $this->jsonResponse($result['data'], $result['code']);
    }

    /**
     * Check service health and return config
     */
    public function health(): void
    {
        $isRunning = $this->isServiceRunning();
        $maxSize = $this->getMaxUploadSize();
        $serviceVersion = null;
        $compatibility = [
            'compatible' => false,
            'missing' => [],
            'required' => [],
        ];

        if ($isRunning) {
            $healthResult = $this->proxyRequest('/api/health');
            if ($healthResult['success'] && $healthResult['code'] === 200 && is_array($healthResult['data'])) {
                $serviceVersion = $healthResult['data']['version'] ?? null;
            }
            $compatibility = $this->inspectServiceApi();
        }

        $this->jsonResponse([
            'success' => true,
            'service_running' => $isRunning,
            'service_version' => $serviceVersion,
            'api_compatible' => $isRunning ? $compatibility['compatible'] : false,
            'missing_endpoints' => $compatibility['missing'],
            'message' => !$isRunning
                ? 'Knowledge base service is not running'
                : ($compatibility['compatible']
                    ? 'Knowledge base service is running'
                    : 'Knowledge base service is running but uses an older API surface'),
            'config' => [
                'max_file_size' => $maxSize,
                'max_file_size_formatted' => $this->formatSize($maxSize),
                'python_path' => $_ENV['CHROMA_PYTHON_PATH'] ?? '/home/wkd/miniconda3/envs/py39/bin/python',
                'service_host' => $_ENV['CHROMA_SERVICE_HOST'] ?? '127.0.0.1',
                'service_port' => $_ENV['CHROMA_SERVICE_PORT'] ?? '4001',
            ]
        ], 200);
    }

    public function rebuildStatus(): void
    {
        $this->jsonResponse([
            'success' => true,
            'job' => $this->getKnowledgeRebuildStatusPayload(),
        ], 200);
    }

    public function rebuildKnowledgeBase(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $this->verifyCsrf();

        if (!function_exists('exec')) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'PHP exec() is disabled. Cannot start rebuild task from the admin page.',
            ], 500);
        }

        $paths = $this->getKnowledgeRebuildPaths();
        if (!is_file($paths['script'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Rebuild script not found: ' . $paths['script'],
            ], 500);
        }

        $currentJob = $this->getKnowledgeRebuildStatusPayload();
        if (!empty($currentJob['is_running'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'A rebuild task is already running.',
                'job' => $currentJob,
            ], 409);
        }

        $pythonPath = trim((string) ($_ENV['CHROMA_PYTHON_PATH'] ?? 'python3'));
        if ($pythonPath === '') {
            $pythonPath = 'python3';
        }

        $startedAt = date('c');
        $queuedStatus = [
            'status' => 'queued',
            'message' => '重建任务已排队，等待启动。',
            'started_at' => $startedAt,
            'finished_at' => null,
            'updated_at' => $startedAt,
            'pid' => null,
            'scanned' => 0,
            'inserted' => 0,
            'repaired' => 0,
            'skipped' => 0,
            'failed' => 0,
            'last_file' => null,
        ];

        $this->writeJsonFile($paths['status'], $queuedStatus);
        file_put_contents($paths['log'], '');

        $command = sprintf(
            'cd %s && nohup %s %s --status-file %s > %s 2>&1 & echo $!',
            escapeshellarg($paths['service_dir']),
            escapeshellarg($pythonPath),
            escapeshellarg($paths['script']),
            escapeshellarg($paths['status']),
            escapeshellarg($paths['log'])
        );

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        $pid = isset($output[0]) ? (int) trim((string) $output[0]) : 0;
        if ($exitCode !== 0 || $pid <= 0) {
            $queuedStatus['status'] = 'failed';
            $queuedStatus['message'] = '无法启动重建进程，请检查 PHP exec/nohup 权限。';
            $queuedStatus['finished_at'] = date('c');
            $this->writeJsonFile($paths['status'], $queuedStatus);

            $this->jsonResponse([
                'success' => false,
                'error' => $queuedStatus['message'],
                'job' => $queuedStatus,
            ], 500);
        }

        $queuedStatus['pid'] = $pid;
        $queuedStatus['status'] = 'running';
        $queuedStatus['message'] = '知识库重建任务已启动。';
        $queuedStatus['updated_at'] = date('c');
        $this->writeJsonFile($paths['status'], $queuedStatus);

        $this->jsonResponse([
            'success' => true,
            'message' => '知识库重建任务已启动。',
            'job' => $queuedStatus,
        ], 202);
    }

    /**
     * Verify CSRF token
     */
    private function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$token || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
    }
}
