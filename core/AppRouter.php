<?php

namespace Core;

use App\Controllers\ResetPassword;
use App\Controllers\VerifyEmail;
use App\Controllers\SignUp;
use App\Controllers\SignIn;
use App\Controllers\UpdateProfile;
use Utils\Helper;

use Bramus\Router\Router;

class AppRouter
{
    private static $router;

    public static function init()
    {
        if (!self::$router) {
            self::$router = new Router();
        }
        return self::$router;
    }

    public static function defineRoutes()
    {
        $router = self::init();

        // Default route - redirect to sign-in
        $router->get('/', function () {
            Helper::redirect('sign-in');
        });

        // Home route
        $router->get('/home', function () {
            Middleware::requireAuth();
            require __DIR__ . '/../pages/home.php';
        });


        // Authentication routes
        $router->get('/sign-in', function () {
            Middleware::guestOnly();
            require __DIR__ . '/../pages/auth/signin.php';
        });

        $router->get('/sign-up', function () {
            Middleware::guestOnly();
            require __DIR__ . '/../pages/auth/signup.php';
        });

        $router->get('/verify-email', function () {
            Middleware::guestOnly();
            require __DIR__ . '/../pages/auth/verify.php';
        });

        $router->get('/reset-password', function () {
            Middleware::guestOnly();
            $require_verification = Config::get('auth.require_verification');
            if ($require_verification) {
                require __DIR__ . '/../pages/auth/recover.php';
            } else {
                $_SESSION['errors']["general"] = "Enable email verification on config";
                Helper::redirect('sign-in');
            }
        });

        $router->get('/logout', function () {
            Middleware::logout();
        });

        $router->get('/email-confirmation', function () {
            Middleware::guestOnly();
            require __DIR__ . '/../pages/auth/email-confirmation.php';
        });


        
        // Child route
        $router->get('/child', function () {
            Middleware::requireAuth();
            Middleware::requireChild();
            require __DIR__ . '/../pages/child/index.php';
        });

        // Parent route
        $router->get('/parent', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            require __DIR__ . '/../pages/parent/parent.php';
        });

        // Admin dashboard route
        $router->get('/admin-dashboard', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/dashboard.php';
        });

        // Admin routes for different sections
        $router->get('/admin/prompts', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/prompts.php';
        });

        $router->get('/admin/users', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/users.php';
        });

        $router->post('/admin/users', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/users.php';
        });

        $router->get('/admin/knowledge', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/knowledge.php';
        });

        $router->get('/admin/profile', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/profile.php';
        });

        // Admin profile post routes
        $router->post('/admin/profile', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            require __DIR__ . '/../pages/admin/profile.php';
        });

        // Admin post routes are handled directly in the views



        //post routes

        $router->post('/sign-up', function () {
            SignUp::signUp();
        });
        $router->post('/sign-in', function () {
            SignIn::signIn();
        });
        $router->post('/reset-password', function () {
            $controller = new ResetPassword();
            $controller->handleRequest();
        });
        $router->post('/update-profile', function () {
            UpdateProfile::updateProfile();
        });




        $router->post('/verify-email', function () {
            $controller = new VerifyEmail();
            $controller->handleRequest();
        });


        //worldcard
        $router->set404(function () {
            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            echo "404 - Page Not Found!";
        });
    }

    public static function run()
    {
        self::defineRoutes();
        self::$router->run();
    }
}
