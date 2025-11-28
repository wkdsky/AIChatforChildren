<?php
use Utils\Helper;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();

// Database connection
$pdo = Core\Database::getInstance();

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update-profile') {
            $name = $_POST['name'];

            $v = new Valitron\Validator($_POST);
            $v->rule('required', ['name'])->message('Name is required');
            $v->rule('regex', 'name', '/^[A-Za-z\s]+$/')->message('Name must contain only letters and spaces');

            if ($v->validate()) {
                try {
                    $userModel = new App\Models\User();
                    $updateData = ['name' => $name];

                    if ($userModel->updateUser($user['id'], $updateData)) {
                        $_SESSION['success_message'] = "Profile updated successfully!";
                        // Update session data
                        $_SESSION['user']['name'] = $name;
                    } else {
                        $_SESSION['error_message'] = "Error updating profile.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Error updating profile: " . $e->getMessage();
                }
            } else {
                $_SESSION['errors'] = $v->errors();
            }
            header("Location: /admin/profile");
            exit;
        } elseif ($_POST['action'] === 'update-password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];

            $v = new Valitron\Validator($_POST);
            $v->rule('required', ['current_password', 'password', 'confirm_password'])->message('All password fields are required');
            $v->rule('lengthMin', 'password', 6)->message('New password must be at least 6 characters');
            $v->rule('equals', 'password', 'confirm_password')->message('Passwords do not match');

            if ($v->validate()) {
                try {
                    // Verify current password
                    $userModel = new App\Models\User();
                    $userData = $userModel->getUserById($user['id']);

                    if ($userData && password_verify($currentPassword, $userData['password'])) {
                        $updateData = ['password' => password_hash($newPassword, PASSWORD_BCRYPT)];

                        if ($userModel->updateUser($user['id'], $updateData)) {
                            $_SESSION['success_message'] = "Password updated successfully!";
                        } else {
                            $_SESSION['error_message'] = "Error updating password.";
                        }
                    } else {
                        $_SESSION['error_message'] = "Current password is incorrect.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Error updating password: " . $e->getMessage();
                }
            } else {
                $_SESSION['errors'] = $v->errors();
            }
            header("Location: /admin/profile");
            exit;
        }
    }
}

// Get current user data for display
$stmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$currentUserData = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile Settings</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
                <div class="admin-info">
                    <div class="admin-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="admin-details">
                        <div class="admin-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="admin-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="/admin-dashboard" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/prompts" class="nav-link">
                            <i class="fas fa-edit"></i>
                            Prompt Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/users" class="nav-link">
                            <i class="fas fa-users"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/knowledge" class="nav-link">
                            <i class="fas fa-database"></i>
                            Knowledge Base
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="/admin/profile" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            Profile Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/logout" class="nav-link logout">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Admin Profile Settings</h1>
                <p class="breadcrumb">Manage your admin account</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['errors'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($_SESSION['errors'] as $field => $errors): ?>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['errors']); ?>
            <?php endif; ?>

            <!-- Admin Info Card -->
            <div class="content-section">
                <h2><i class="fas fa-user-shield"></i> Admin Information</h2>
                <div class="admin-info-card">
                    <div class="info-row">
                        <label>Email Address:</label>
                        <span><?php echo htmlspecialchars($currentUserData['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <label>Role:</label>
                        <span class="role-badge admin">Administrator</span>
                    </div>
                    <div class="info-row">
                        <label>Account Created:</label>
                        <span><?php echo date('F j, Y', strtotime($currentUserData['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Update Profile Form -->
            <div class="content-section">
                <h2><i class="fas fa-user-edit"></i> Update Profile</h2>
                <form method="POST" action="/admin/profile" class="profile-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update-profile">

                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name"
                               value="<?php echo htmlspecialchars($currentUserData['name']); ?>"
                               placeholder="Enter your full name" required>
                        <?php if (isset($_SESSION['errors']['name'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_SESSION['errors']['name'][0]); ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="content-section">
                <h2><i class="fas fa-key"></i> Change Password</h2>
                <form method="POST" action="/admin/profile" class="password-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update-password">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                        <?php if (isset($_SESSION['errors']['current_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_SESSION['errors']['current_password'][0]); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required>
                        <?php if (isset($_SESSION['errors']['password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_SESSION['errors']['password'][0]); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <?php if (isset($_SESSION['errors']['confirm_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($_SESSION['errors']['confirm_password'][0]); ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Admin Profile Specific Styles */
        .admin-info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row label {
            font-weight: 600;
            color: #2c3e50;
            width: 150px;
            margin: 0;
        }

        .info-row span {
            color: #34495e;
        }

        .role-badge.admin {
            background: #fadbd8;
            color: #e74c3c;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .profile-form,
        .password-form {
            max-width: 500px;
        }

        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .info-row label {
                width: auto;
            }

            .profile-form,
            .password-form {
                max-width: 100%;
            }
        }
    </style>
</body>
</html>