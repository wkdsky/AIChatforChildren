<?php

namespace App\Controllers;

use App\Models\User;

class ValidationController
{
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function checkAccountAvailability(): void
    {
        $field = trim((string) ($_GET['field'] ?? ''));
        $value = trim((string) ($_GET['value'] ?? ''));

        if ($field === 'email') {
            if ($value === '') {
                $this->jsonResponse([
                    'success' => true,
                    'field' => 'email',
                    'available' => false,
                    'message' => 'Email is required.',
                ]);
            }

            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->jsonResponse([
                    'success' => true,
                    'field' => 'email',
                    'available' => false,
                    'message' => 'Please enter a valid email address.',
                ]);
            }

            $exists = (new User())->emailExists($value);
            $this->jsonResponse([
                'success' => true,
                'field' => 'email',
                'available' => !$exists,
                'message' => $exists ? 'This email is already registered.' : 'This email is available.',
            ]);
        }

        if ($field === 'child_name') {
            if (($_SESSION['user']['role'] ?? null) !== 'parent') {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parent access required.',
                ], 403);
            }

            if ($value === '') {
                $this->jsonResponse([
                    'success' => true,
                    'field' => 'child_name',
                    'available' => false,
                    'message' => 'Child username is required.',
                ]);
            }

            if (mb_strlen($value) > 100) {
                $this->jsonResponse([
                    'success' => true,
                    'field' => 'child_name',
                    'available' => false,
                    'message' => 'Child username must be 100 characters or fewer.',
                ]);
            }

            if (str_contains($value, '@')) {
                $this->jsonResponse([
                    'success' => true,
                    'field' => 'child_name',
                    'available' => false,
                    'message' => 'Child username cannot contain @.',
                ]);
            }

            if (preg_match('/[\r\n]/u', $value)) {
                $this->jsonResponse([
                    'success' => true,
                    'field' => 'child_name',
                    'available' => false,
                    'message' => 'Child username cannot contain line breaks.',
                ]);
            }

            $exists = (new User())->childNameExists($value);
            $this->jsonResponse([
                'success' => true,
                'field' => 'child_name',
                'available' => !$exists,
                'message' => $exists ? 'This child username is already in use.' : 'This child username is available.',
            ]);
        }

        $this->jsonResponse([
            'success' => false,
            'message' => 'Unsupported validation field.',
        ], 400);
    }
}
