<?php

namespace App\Controllers;

use App\Models\ChildAccount;
use App\Models\User;
use Core\Database;
use DateTimeImmutable;
use Utils\AppTime;
use Utils\ChildPromptService;
use Utils\Helper;
use Valitron\Validator;

class ParentChildController
{
    private const PROMPT_MAX_LENGTH = 24000;

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
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

    private function formatTime(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function formatChildren(array $children): array
    {
        return array_map(function (array $child) {
            $birthDate = $child['birth_date'] ?? null;
            $age = $birthDate ? $this->calculateAge($birthDate) : null;
            $dailyLimit = (int) ($child['daily_login_minutes'] ?? 0);
            $usedToday = (int) ($child['used_today_minutes'] ?? 0);

            return [
                'id' => (int) $child['id'],
                'name' => $child['name'],
                'gender' => $child['gender'],
                'gender_label' => $this->genderLabel($child['gender'] ?? ''),
                'birth_date' => $birthDate,
                'age' => $age,
                'allowed_login_start' => $this->formatTime($child['allowed_login_start'] ?? null),
                'allowed_login_end' => $this->formatTime($child['allowed_login_end'] ?? null),
                'daily_login_minutes' => $dailyLimit,
                'used_today_minutes' => $usedToday,
                'remaining_today_minutes' => max(0, $dailyLimit - $usedToday),
                'login_disabled' => (bool) ($child['login_disabled'] ?? false),
                'last_login_at' => AppTime::toIso8601($child['last_login_at'] ?? null),
                'created_at' => $child['created_at'],
            ];
        }, $children);
    }

    private function calculateAge(string $birthDate): ?int
    {
        try {
            $today = new DateTimeImmutable('today');
            $birth = new DateTimeImmutable($birthDate);
            return $birth->diff($today)->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function isValidBirthDate(?string $birthDate): bool
    {
        if (!$birthDate) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $birthDate) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        if ($parsed > $today) {
            return false;
        }

        $age = $parsed->diff($today)->y;
        return $age >= 0 && $age < 18;
    }

    private function generateChildEmail(int $parentId, string $childName): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '.', $childName), '.'));
        if ($slug === '') {
            $slug = 'child';
        }

        return sprintf('%s.%d.%s@child.local', $slug, $parentId, bin2hex(random_bytes(4)));
    }

    private function genderLabel(string $gender): string
    {
        return match ($gender) {
            'male' => 'Male',
            'female' => 'Female',
            default => 'Unknown',
        };
    }

    private function normalizeTime(string $time): ?string
    {
        $parsed = DateTimeImmutable::createFromFormat('H:i', $time);
        if (!$parsed || $parsed->format('H:i') !== $time) {
            return null;
        }

        $minutes = (int) $parsed->format('i');
        if ($minutes % 10 !== 0) {
            return null;
        }

        return $time . ':00';
    }

    private function buildChildPromptProfile(array $child): array
    {
        return [
            'id' => (int) ($child['id'] ?? 0),
            'parent_id' => $this->getParentId(),
            'name' => trim((string) ($child['name'] ?? '')),
            'role' => 'child',
            'birth_date' => $child['birth_date'] ?? null,
            'age_years' => isset($child['birth_date']) ? $this->calculateAge((string) $child['birth_date']) : null,
        ];
    }

    private function formatPromptEditorData(?array $editorData): array
    {
        if (!$editorData) {
            return [
                'child_id' => 0,
                'source_age_band' => '6_12',
                'source_template_key' => 'child_chat_age_6_12',
                'default_prompt_content' => '',
                'prompt_content' => '',
                'is_customized' => false,
                'updated_at' => null,
            ];
        }

        return [
            'child_id' => (int) ($editorData['child_id'] ?? 0),
            'source_age_band' => (string) ($editorData['source_age_band'] ?? '6_12'),
            'source_template_key' => (string) ($editorData['source_template_key'] ?? 'child_chat_age_6_12'),
            'default_prompt_content' => (string) ($editorData['default_prompt_content'] ?? ''),
            'prompt_content' => (string) ($editorData['prompt_content'] ?? ''),
            'is_customized' => !empty($editorData['is_customized']),
            'updated_at' => AppTime::toIso8601($editorData['updated_at'] ?? null),
        ];
    }

    public function list(): void
    {
        $childAccount = new ChildAccount();
        $children = $childAccount->getManagedChildrenByParentId($this->getParentId());

        $this->jsonResponse([
            'success' => true,
            'children' => $this->formatChildren($children),
        ]);
    }

    public function prompt(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $childId = (int) ($_GET['child_id'] ?? 0);
        if ($childId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account id is required.',
            ], 422);
        }

        $childAccount = new ChildAccount();
        $child = $childAccount->getManagedChildById($childId, $this->getParentId());
        if (!$child) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $promptService = new ChildPromptService(Database::getInstance());
        $editorData = $promptService->getPromptEditorData($this->buildChildPromptProfile($child));

        if ($editorData === null) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Unable to load child prompt right now.',
            ], 500);
        }

        $this->jsonResponse([
            'success' => true,
            'prompt_profile' => $this->formatPromptEditorData($editorData),
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $this->verifyCsrf();

        $input = [
            'child_name' => trim((string) ($_POST['child_name'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
        ];

        $validator = new Validator($input);
        $validator->rule('required', ['child_name', 'password', 'confirm_password', 'gender', 'birth_date'])->message('{field} is required');
        $validator->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters');
        $validator->rule('lengthMax', 'child_name', 100)->message('Child username must be 100 characters or fewer');
        $validator->rule('equals', 'password', 'confirm_password')->message('Passwords do not match');
        $validator->rule('in', 'gender', ['male', 'female'])->message('Please choose a valid gender');

        if (!$this->isValidBirthDate($input['birth_date'])) {
            $validator->error('birth_date', 'Please choose a valid birth date for a child under 18');
        }

        if (str_contains($input['child_name'], '@')) {
            $validator->error('child_name', 'Child username cannot contain @');
        }

        if (preg_match('/[\r\n]/u', $input['child_name'])) {
            $validator->error('child_name', 'Child username cannot contain line breaks');
        }

        $user = new User();
        if ($input['child_name'] !== '' && $user->childNameExists($input['child_name'])) {
            $validator->error('child_name', 'This child account name is already in use');
        }

        if (!$validator->validate()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pdo = Database::getInstance();
        $childPromptService = new ChildPromptService($pdo);

        try {
            $pdo->beginTransaction();

            $childId = $user->createChildUserForParent($this->getParentId(), [
                'name' => $input['child_name'],
                'email' => $this->generateChildEmail($this->getParentId(), $input['child_name']),
                'password' => password_hash($input['password'], PASSWORD_BCRYPT),
                'gender' => $input['gender'],
                'birth_date' => $input['birth_date'],
            ]);

            if (!$childId) {
                throw new \RuntimeException('Unable to create child account right now.');
            }

            $promptProfile = $childPromptService->initializePromptProfileForChild([
                'id' => $childId,
                'parent_id' => $this->getParentId(),
                'name' => $input['child_name'],
                'role' => 'child',
                'birth_date' => $input['birth_date'],
            ]);

            if ($promptProfile === null) {
                throw new \RuntimeException('Unable to initialize child prompt profile right now.');
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->jsonResponse([
                'success' => false,
                'message' => $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'Unable to create child account right now.',
            ], 500);
        }

        $childAccount = new ChildAccount();
        $children = $childAccount->getManagedChildrenByParentId($this->getParentId());

        $this->jsonResponse([
            'success' => true,
            'message' => 'Child account created successfully.',
            'children' => $this->formatChildren($children),
        ], 201);
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $this->verifyCsrf();

        $input = [
            'child_id' => (int) ($_POST['child_id'] ?? 0),
            'allowed_login_start' => trim((string) ($_POST['allowed_login_start'] ?? '')),
            'allowed_login_end' => trim((string) ($_POST['allowed_login_end'] ?? '')),
            'daily_login_minutes' => trim((string) ($_POST['daily_login_minutes'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
            'prompt_content' => trim((string) ($_POST['prompt_content'] ?? '')),
        ];
        $promptSubmitted = array_key_exists('prompt_content', $_POST);

        $validator = new Validator($input);
        $validator->rule('required', ['child_id', 'allowed_login_start', 'allowed_login_end', 'daily_login_minutes'])->message('{field} is required');
        $validator->rule('integer', 'daily_login_minutes')->message('Daily login time must be a whole number of minutes');
        $validator->rule('min', 'daily_login_minutes', 1)->message('Daily login time must be at least 1 minute');
        $validator->rule('max', 'daily_login_minutes', 1440)->message('Daily login time cannot exceed 1440 minutes');

        $startTime = $this->normalizeTime($input['allowed_login_start']);
        $endTime = $this->normalizeTime($input['allowed_login_end']);

        if (!$startTime) {
            $validator->error('allowed_login_start', 'Please choose a valid start time');
        }

        if (!$endTime) {
            $validator->error('allowed_login_end', 'Please choose a valid end time');
        }

        if ($startTime && $endTime && $startTime === $endTime) {
            $validator->error('allowed_login_end', 'Start and end time cannot be the same');
        }

        $passwordProvided = $input['password'] !== '' || $input['confirm_password'] !== '';
        if ($passwordProvided) {
            $validator->rule('required', ['password', 'confirm_password'])->message('{field} is required');
            $validator->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters');
            $validator->rule('equals', 'password', 'confirm_password')->message('Passwords do not match');
        }

        if ($promptSubmitted) {
            if ($input['prompt_content'] === '') {
                $validator->error('prompt_content', 'Private prompt cannot be empty');
            } elseif (mb_strlen($input['prompt_content'], 'UTF-8') > self::PROMPT_MAX_LENGTH) {
                $validator->error('prompt_content', 'Private prompt is too long');
            }
        }

        if (!$validator->validate()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $childAccount = new ChildAccount();
        $existingChild = $childAccount->getManagedChildById($input['child_id'], $this->getParentId());

        if (!$existingChild) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $data = [
            'allowed_login_start' => $startTime,
            'allowed_login_end' => $endTime,
            'daily_login_minutes' => (int) $input['daily_login_minutes'],
        ];

        if ($passwordProvided) {
            $data['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        $settingsChanged = (string) ($existingChild['allowed_login_start'] ?? '') !== $startTime
            || (string) ($existingChild['allowed_login_end'] ?? '') !== $endTime
            || (int) ($existingChild['daily_login_minutes'] ?? 0) !== (int) $input['daily_login_minutes']
            || $passwordProvided;

        $childPromptService = new ChildPromptService(Database::getInstance());
        $promptProfile = $childPromptService->getPromptEditorData($this->buildChildPromptProfile($existingChild));
        $promptChanged = $promptSubmitted
            && trim((string) ($promptProfile['prompt_content'] ?? '')) !== $input['prompt_content'];

        if (!$settingsChanged && !$promptChanged) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No changes were saved.',
            ], 400);
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            if ($settingsChanged) {
                $updated = $childAccount->updateManagedChild($input['child_id'], $this->getParentId(), $data);
                if (!$updated) {
                    throw new \RuntimeException('Unable to update child account settings right now.');
                }
            }

            if ($promptChanged) {
                $savedPrompt = $childPromptService->savePromptContentForChild(
                    $this->buildChildPromptProfile($existingChild),
                    $input['prompt_content']
                );

                if (!$savedPrompt) {
                    throw new \RuntimeException('Unable to save child private prompt right now.');
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->jsonResponse([
                'success' => false,
                'message' => $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'Unable to update child account right now.',
            ], 500);
        }

        $children = $childAccount->getManagedChildrenByParentId($this->getParentId());

        $this->jsonResponse([
            'success' => true,
            'message' => 'Child account settings updated successfully.',
            'children' => $this->formatChildren($children),
        ]);
    }

    public function delete(): void
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

        $childAccount = new ChildAccount();
        $deleted = $childAccount->deleteManagedChild($childId, $this->getParentId());

        if (!$deleted) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $children = $childAccount->getManagedChildrenByParentId($this->getParentId());

        $this->jsonResponse([
            'success' => true,
            'message' => 'Child account deleted successfully.',
            'children' => $this->formatChildren($children),
        ]);
    }

    public function toggleLogin(): void
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

        $childAccount = new ChildAccount();
        $child = $childAccount->getManagedChildById($childId, $this->getParentId());

        if (!$child) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Child account not found.',
            ], 404);
        }

        $nextState = !((bool) ($child['login_disabled'] ?? false));
        $updated = $childAccount->setManagedChildLoginDisabled($childId, $this->getParentId(), $nextState);

        if (!$updated) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Unable to update child login access right now.',
            ], 500);
        }

        $children = $childAccount->getManagedChildrenByParentId($this->getParentId());

        $this->jsonResponse([
            'success' => true,
            'message' => $nextState
                ? 'Child login has been temporarily disabled.'
                : 'Child login has been enabled again.',
            'children' => $this->formatChildren($children),
        ]);
    }
}
