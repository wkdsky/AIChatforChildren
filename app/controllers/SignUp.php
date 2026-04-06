<?php

namespace App\Controllers;

use App\Models\User;
use Valitron\Validator;
use Utils\Helper;
use Core\MailService;
use Core\Config;

class SignUp
{
    public static function signUp()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Method Not Allowed";
            exit;
        }
        $token = $_POST['csrf_token'] ?? null;

        if (!Helper::verifyCsrfToken($token)) {
            http_response_code(403);
            die("CSRF validation failed!");
        }


        $v = new Validator($_POST);
        $v->rule('required', ['email', 'password', 'confirm_password'])->message('{field} is required');
        $v->rule('email', 'email')->message('Invalid email format');
        $v->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters');
        $v->rule('equals', 'password', 'confirm_password')->message('Passwords do not match');

        // Validate
        if (!$v->validate()) {
            $_SESSION['errors'] = $v->errors(); // Store errors in session
            $_SESSION['old'] = $_POST; // Store old input values
            header("Location: " . Helper::url('sign-up')); // Redirect back to form
            exit;
        }

        // Get validated inputs
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $email = trim((string) $_POST['email']);
        $full_name = strstr($email, '@', true) ?: $email;
        $role = 'parent';
        $verificationCode = rand(100000, 999999);



        // Store user in database
        $user = new User();
        if ($user->emailExists($email)) {
            $_SESSION['errors']['email'][] = "Email is already registered!";
            $_SESSION['old'] = $_POST;
            header("Location: " . Helper::url('sign-up'));
            exit;
        }

        $created = $user->createUser($full_name, $email, $password, $role, $verificationCode);

        if ($created) {
            $_SESSION['success'] = "Account created successfully!";

            $require_verification = Config::get('auth.require_verification');

            if ($require_verification) {

                $mailService = new MailService();
                $emailTemplate = file_get_contents(__DIR__ . '/../../pages/auth/email_template/verify.php');
                $emailBody = str_replace('{{VERIFICATION_CODE}}', $verificationCode, $emailTemplate);
                $email_sent =  $mailService->sendEmail($email, "Your Verification Code", $emailBody);
                if ($email_sent) {
                    $_SESSION['verification_email'] = $email;
                    Helper::redirect("verify-email");
                }
            } else {
                Helper::redirect("sign-in");
            }
        } else {
            $_SESSION['errors']['general'][] = "Something went wrong!";
            header("Location: " . Helper::url('sign-up'));
        }
        exit;
    }
}
