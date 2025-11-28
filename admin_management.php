<?php
require_once 'vendor/autoload.php';
require_once 'core/Database.php';
require_once 'core/Config.php';
require_once 'app/models/User.php';

use App\Models\User;
use Valitron\Validator;
use PDO;

session_start();

// Database connection check
try {
    $pdo = Core\Database::getInstance();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$errors = [];
$success = '';
$action = $_GET['action'] ?? 'list';
$editId = $_GET['edit'] ?? null;
$deleteId = $_GET['delete'] ?? null;

// Handle delete action
if ($deleteId && is_numeric($deleteId)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin' AND id != ?");
        $stmt->execute([$deleteId, 1]); // Prevent deleting admin with ID 1
        if ($stmt->rowCount() > 0) {
            $success = "Admin user deleted successfully!";
        } else {
            $errors[] = "Cannot delete this admin user or admin not found.";
        }
    } catch (Exception $e) {
        $errors[] = "Error deleting admin user: " . $e->getMessage();
    }
    $action = 'list';
}

// Handle form submission for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEdit = !empty($_POST['edit_id']);

    $v = new Validator($_POST);
    $v->rule('required', ['full_name', 'email'])->message('{field} is required');
    $v->rule('email', 'email')->message('Invalid email format');
    $v->rule('regex', 'full_name', '/^[A-Za-z\s]+$/')->message('Full name must contain only letters and spaces');

    if (!$isEdit) {
        $v->rule('required', ['password', 'confirm_password'])->message('{field} is required');
        $v->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters');
        $v->rule('equals', 'password', 'confirm_password')->message('Passwords do not match');
    } else {
        $v->rule('optional', ['password', 'confirm_password']);
        if (!empty($_POST['password'])) {
            $v->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters');
            $v->rule('equals', 'password', 'confirm_password')->message('Passwords do not match');
        }
    }

    if ($v->validate()) {
        try {
            $userModel = new User();

            if ($isEdit) {
                // Edit existing admin
                $id = $_POST['edit_id'];
                $updateData = [
                    'name' => $_POST['full_name'],
                    'email' => $_POST['email'],
                    'role' => 'admin'
                ];

                if (!empty($_POST['password'])) {
                    $updateData['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                }

                // Check if email exists for other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$_POST['email'], $id]);
                if ($stmt->fetch()) {
                    $errors[] = "Email is already registered by another user!";
                } else {
                    if ($userModel->updateUser($id, $updateData)) {
                        $success = "Admin user updated successfully!";
                        $action = 'list';
                        $editId = null;
                    } else {
                        $errors[] = "Error updating admin user.";
                    }
                }
            } else {
                // Add new admin
                if ($userModel->emailExists($_POST['email'])) {
                    $errors[] = "Email is already registered!";
                } else {
                    $verificationCode = rand(100000, 999999);
                    if ($userModel->createUser(
                        $_POST['full_name'],
                        $_POST['email'],
                        password_hash($_POST['password'], PASSWORD_BCRYPT),
                        'admin',
                        $verificationCode
                    )) {
                        // Mark as verified since admin is trusted
                        $stmt = $pdo->prepare("UPDATE users SET verification_status = 'verified' WHERE email = ?");
                        $stmt->execute([$_POST['email']]);

                        $success = "Admin user created successfully!";
                        $action = 'list';
                    } else {
                        $errors[] = "Error creating admin user.";
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    } else {
        $errors = array_merge($errors, $v->errors());
    }
}

// Fetch admin user for editing
$editUser = null;
if ($editId && is_numeric($editId)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editUser) {
        $errors[] = "Admin user not found.";
        $action = 'list';
        $editId = null;
    }
}

// Fetch all admin users for listing
$adminUsers = [];
$stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC");
$stmt->execute();
$adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function oldValue($field, $default = '') {
    return !empty($_POST[$field]) ? sanitize($_POST[$field]) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .content {
            padding: 30px;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ecf0f1;
        }

        .nav-tab {
            padding: 15px 25px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .nav-tab.active {
            background: white;
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .nav-tab:hover {
            background: white;
            color: #3498db;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .btn-success:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d5f4e6;
            border-left-color: #27ae60;
            color: #145a32;
        }

        .alert-error {
            background: #fadbd8;
            border-left-color: #e74c3c;
            color: #922b21;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .admin-table th,
        .admin-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .admin-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .admin-table tr:hover {
            background: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .admin-badge {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .no-admins {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .page-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin User Management</h1>
            <p>Manage administrator accounts for the system</p>
        </div>

        <div class="content">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?php echo sanitize($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Error(s):</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo is_array($error) ? implode(', ', $error) : sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="nav-tabs">
                <button class="nav-tab <?php echo $action === 'list' ? 'active' : ''; ?>" onclick="showSection('list')">
                    Admin Users
                </button>
                <button class="nav-tab <?php echo $action === 'add' ? 'active' : ''; ?>" onclick="showSection('add')">
                    Add Admin
                </button>
                <?php if ($action === 'edit' && $editUser): ?>
                    <button class="nav-tab active" onclick="showSection('edit')">
                        Edit Admin
                    </button>
                <?php endif; ?>
            </div>

            <!-- Admin List Section -->
            <div id="list" class="form-section <?php echo $action === 'list' ? 'active' : ''; ?>">
                <h2 class="section-title">Existing Admin Users</h2>

                <?php if (empty($adminUsers)): ?>
                    <div class="no-admins">
                        <i style="font-size: 48px; margin-bottom: 10px;">👥</i>
                        <h3>No admin users found</h3>
                        <p>Create your first admin user using the "Add Admin" tab.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminUsers as $admin): ?>
                                <tr>
                                    <td><?php echo sanitize($admin['name']); ?></td>
                                    <td><?php echo sanitize($admin['email']); ?></td>
                                    <td><span class="admin-badge">ADMIN</span></td>
                                    <td><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=edit&edit=<?php echo $admin['id']; ?>" class="btn">Edit</a>
                                            <?php if ($admin['id'] != 1): ?>
                                                <a href="?action=list&delete=<?php echo $admin['id']; ?>"
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this admin user?')">
                                                   Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Add Admin Section -->
            <div id="add" class="form-section <?php echo $action === 'add' ? 'active' : ''; ?>">
                <h2 class="section-title">Add New Admin User</h2>

                <form method="POST" action="?action=add">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name"
                               value="<?php echo oldValue('full_name'); ?>"
                               placeholder="Enter full name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo oldValue('email'); ?>"
                               placeholder="Enter email address" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password"
                               placeholder="Enter password (min 6 characters)" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Confirm password" required>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i>➕</i> Create Admin User
                    </button>
                    <button type="button" class="btn" onclick="showSection('list')">Cancel</button>
                </form>
            </div>

            <!-- Edit Admin Section -->
            <?php if ($action === 'edit' && $editUser): ?>
                <div id="edit" class="form-section active">
                    <h2 class="section-title">Edit Admin User</h2>

                    <form method="POST" action="?action=edit&edit=<?php echo $editUser['id']; ?>">
                        <input type="hidden" name="edit_id" value="<?php echo $editUser['id']; ?>">

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name"
                                   value="<?php echo oldValue('full_name', $editUser['name']); ?>"
                                   placeholder="Enter full name" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email"
                                   value="<?php echo oldValue('email', $editUser['email']); ?>"
                                   placeholder="Enter email address" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password (leave blank to keep current)</label>
                            <input type="password" id="password" name="password"
                                   placeholder="Enter new password (min 6 characters)">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Confirm new password">
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i>💾</i> Update Admin User
                        </button>
                        <a href="?action=list" class="btn">Cancel</a>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected section
            const section = document.getElementById(sectionId);
            if (section) {
                section.classList.add('active');
            }

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Clear success messages after 5 seconds
        setTimeout(() => {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>