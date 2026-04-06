<?php

namespace Core;

use App\Controllers\ResetPassword;
use App\Controllers\VerifyEmail;
use App\Controllers\SignUp;
use App\Controllers\SignIn;
use App\Controllers\UpdateProfile;
use App\Controllers\KnowledgeController;
use App\Controllers\ConversationController;
use App\Controllers\ChatController;
use App\Controllers\ParentChildController;
use App\Controllers\ParentChildReportController;
use App\Controllers\ChildSessionController;
use App\Controllers\ValidationController;
use Utils\Helper;

use Bramus\Router\Router;

class AppRouter
{
    private static $router;

    public static function init()
    {
        if (!self::$router) {
            self::$router = new Router();
            // Set base path from config (removes it from REQUEST_URI for route matching)
            $basePath = rtrim(Config::get('base_url', '/'), '/');
            if ($basePath && $basePath !== '/') {
                self::$router->setBasePath($basePath);
            }
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

        // Knowledge Base API routes
        $router->get('/api/knowledge/health', function () {
            $controller = new KnowledgeController();
            $controller->health();
        });

        $router->get('/api/knowledge/files', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->listFiles();
        });

        $router->get('/api/knowledge/files/([^/]+)', function ($fileId) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->getFile($fileId);
        });

        $router->get('/api/knowledge/files/([^/]+)/status', function ($fileId) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->getFileStatus($fileId);
        });

        $router->post('/api/knowledge/files/([^/]+)/update', function ($fileId) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->updateFile($fileId);
        });

        $router->get('/api/knowledge/files/([^/]+)/chunks', function ($fileId) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->getChunks($fileId);
        });

        $router->get('/api/knowledge/files/([^/]+)/chunks/([^/]+)', function ($fileId, $chunkId) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->getChunk($fileId, $chunkId);
        });

        $router->post('/api/knowledge/files/([^/]+)/chunks/bulk-update', function ($fileId) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->bulkUpdateChunks($fileId);
        });

        $router->post('/api/knowledge/files/([^/]+)/actions/([^/]+)', function ($fileId, $action) {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->queueAction($fileId, $action);
        });

        $router->post('/api/knowledge/upload', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->upload();
        });

        $router->post('/api/knowledge/delete', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->delete();
        });

        $router->post('/api/knowledge/rename', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->rename();
        });

        $router->get('/api/knowledge/rebuild/status', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->rebuildStatus();
        });

        $router->post('/api/knowledge/rebuild', function () {
            Middleware::requireAuth();
            Middleware::requireAdmin();
            $controller = new KnowledgeController();
            $controller->rebuildKnowledgeBase();
        });

        $router->get('/api/knowledge/search', function () {
            Middleware::requireAuth();
            $controller = new KnowledgeController();
            $controller->search();
        });

        $router->get('/api/knowledge/context', function () {
            $controller = new KnowledgeController();
            $controller->getContext();
        });

        $router->post('/api/chat/reply', function () {
            Middleware::requireAuth();
            $controller = new ChatController();
            $controller->reply();
        });

        // Conversation API routes
        $router->get('/api/conversations', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->list();
        });

        $router->post('/api/conversations/create', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->create();
        });

        $router->get('/api/conversations/get', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->get();
        });

        $router->post('/api/conversations/update', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->update();
        });

        $router->post('/api/conversations/delete', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->delete();
        });

        $router->post('/api/conversations/message', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->addMessage();
        });

        $router->get('/api/conversations/search', function () {
            Middleware::requireAuth();
            $controller = new ConversationController();
            $controller->search();
        });

        $router->get('/api/parent/children', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildController();
            $controller->list();
        });

        $router->post('/api/parent/children', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildController();
            $controller->create();
        });

        $router->post('/api/parent/children/update', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildController();
            $controller->update();
        });

        $router->post('/api/parent/children/delete', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildController();
            $controller->delete();
        });

        $router->post('/api/parent/children/toggle-login', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildController();
            $controller->toggleLogin();
        });

        $router->get('/api/parent/children/report', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildReportController();
            $controller->overview();
        });

        $router->post('/api/parent/children/report/content', function () {
            Middleware::requireAuth();
            Middleware::requireParent();
            $controller = new ParentChildReportController();
            $controller->content();
        });

        $router->get('/api/child/session-status', function () {
            Middleware::requireAuth();
            Middleware::requireChild();
            $controller = new ChildSessionController();
            $controller->status();
        });

        $router->get('/api/validation/account-availability', function () {
            $controller = new ValidationController();
            $controller->checkAccountAvailability();
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
