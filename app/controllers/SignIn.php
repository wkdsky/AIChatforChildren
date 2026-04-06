<?php

namespace App\Controllers;

use App\Models\ChildAccount;
use App\Models\User;
use Core\Middleware;
use Valitron\Validator;
use Utils\Helper;

class SignIn
{

    public static function signIn()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Method Not Allowed";
            exit;
        };
        $token = $_POST['csrf_token'] ?? null;

        if (!Helper::verifyCsrfToken($token)) {
            http_response_code(403);
            die("CSRF validation failed!");
        }
        $v = new Validator($_POST);
        $v->rule('required', ['identifier', 'password'])->message('{field} is required');
        $v->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters');

        if (!$v->validate()) {
            $_SESSION['errors'] = $v->errors();
            $_SESSION['old'] = $_POST;
            Helper::redirect('sign-in');
            exit;
        }

        $identifier = trim((string) $_POST['identifier']);
        $password = $_POST['password'];

        $user = new User();
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $user->findByEmail($identifier)
            : $user->findChildByName($identifier);
        if ($user) {
            if (password_verify($password, $user->password)) {
                if ($user->role === 'child') {
                    $childAccount = new ChildAccount();
                    $status = $childAccount->getChildUsageStatus((int) $user->id);

                    if (!$status) {
                        self::showError('identifier', 'Child account settings are unavailable', 'sign-in');
                    }

                    $blockReason = Middleware::childAccessBlockReason($status);
                    if ($blockReason !== null) {
                        self::showError('identifier', $blockReason, 'sign-in');
                    }

                    $childAccount->recordLogin((int) $user->id);
                }

                $_SESSION['user'] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_encrypted' => Helper::encryptEmail($user->email),
                    'role' => $user->role,
                    'last_activity' => time(),

                ];

                if ($user->role === 'child') {
                    $_SESSION['child_usage_last_tracked_at'] = time();
                }
                if (!empty($_POST['remember_me'])) {
                    setcookie("remember_me", base64_encode($user->id), time() + (30 * 24 * 60 * 60), "/", "", true, true);
                }

                // Redirect based on user role
                if ($user->role === 'admin') {
                    Helper::redirect("admin-dashboard");
                } elseif ($user->role === 'parent') {
                    Helper::redirect("parent");
                } elseif ($user->role === 'child') {
                    Helper::redirect("child");
                } else {
                    Helper::redirect("home");
                }
            } else {

                self::showError('password', 'Wrong credentials supplied', 'sign-in');
            }
        } else {
            self::showError('identifier', "Account not found", "sign-in");
        }
    }

    private static function showError(string $field, string $message, string $redirect)
    {
        $_SESSION['errors']["{$field}"][] = $message;
        $_SESSION['old'] = $_POST;
        Helper::redirect($redirect);
        exit;
    }
}
