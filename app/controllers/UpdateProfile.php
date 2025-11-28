<?php

namespace App\Controllers;

use Utils\Helper;
use App\Models\User;
use Core\Middleware;
use Valitron\Validator;


class UpdateProfile
{
    private static function redirectToRolePage()
    {
        $role = $_SESSION['user']['role'] ?? 'child';
        switch ($role) {
            case 'admin':
                Helper::redirect('admin-dashboard');
                break;
            case 'parent':
                Helper::redirect('parent');
                break;
            case 'child':
                Helper::redirect('child');
                break;
            default:
                Helper::redirect('child');
                break;
        }
        exit;
    }

    public static function updateProfile()
    {
        // 设置响应头为JSON
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
            return;
        };
        $token = $_POST['csrf_token'] ?? null;

        if (!Helper::verifyCsrfToken($token)) {
            echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed!']);
            return;
        }

        $confirm_email = Helper::decryptEmail($_SESSION['user']['email_encrypted']);
        $email = $_SESSION['user']['email'];

        if ($confirm_email !== $email) {
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong!']);
            return;
        }

        $user = (new User())->findByEmail($email);
        if ($user) {
            $v = new Validator($_POST);

            if (isset($_POST['update-profile'])) {
                $name = Helper::sanitize($_POST['name'] ?? null, 'string');
                $v->rule('required', 'name')->message('Name is required');
                $v->rule('regex', 'name', '/^[A-Za-z\s]+$/')->message('Full name must contain only letters and spaces');
                if (!$v->validate()) {
                    echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $v->errors()]);
                    return;
                }

                (new User())->updateUser($user->id, [
                    'name' => $name,
                ]);

                $_SESSION['user']['name'] = $name;
                echo json_encode(['status' => 'success', 'message' => 'Profile name updated successfully', 'newName' => $name]);
                return;
            } elseif (isset($_POST['update-password'])) {
                $v->rule('lengthMin', ['password', 'current-password', 'confirm-password'], 6)->message('Password must be at least 6 characters');
                $v->rule('required', ['current-password', 'password', 'confirm-password',])->message('{field} is required');
                $v->rule('equals', 'password', 'confirm-password')->message('Passwords do not match');

                if (!$v->validate()) {
                    echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $v->errors()]);
                    return;
                }
                $currentPassword = $_POST['current-password'];
                $newPassword = $_POST['password'];
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);


                if (password_verify($currentPassword, $user->password)) {
                    (new User())->updateUser($user->id, [
                        'password' => $hashedPassword,
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
                    return;
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'You supplied wrong current password']);
                    return;
                }
            } elseif (isset($_POST['delete'])) {
                (new User())->deleteuser($user->id);
                Middleware::logout();
            } else {
                return;
            }
        }
    }
    private static function setSessionError($key, $message)
    {
        $_SESSION['errors'][$key] = $message;
    }
};
