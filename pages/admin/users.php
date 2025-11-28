<?php
use Utils\Helper;
use App\Models\User;
use Valitron\Validator;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();

// Database connection
$pdo = Core\Database::getInstance();

// Handle user actions
$action = $_GET['action'] ?? '';
$editUserId = $_GET['edit'] ?? null;
$deleteUserId = $_GET['delete'] ?? null;

// Handle delete action
if ($deleteUserId && is_numeric($deleteUserId)) {
    try {
        // Prevent admin from deleting themselves
        if ($deleteUserId == $_SESSION['user']['id']) {
            $_SESSION['error_message'] = "You cannot delete your own account!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$deleteUserId]);
            $_SESSION['success_message'] = "User deleted successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
    }
    header("Location: /admin/users");
    exit;
}

// Handle edit user data fetch
$editUser = null;
if ($editUserId && is_numeric($editUserId)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editUserId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editUser) {
        $_SESSION['error_message'] = "User not found!";
        header("Location: /admin/users");
        exit;
    }
}

// Handle form submission for user update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
        $userId = $_POST['user_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $verificationStatus = $_POST['verification_status'] ?? 'pending';

        try {
            // Check if email exists for other users
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $_SESSION['error_message'] = "Email is already registered by another user!";
            } else {
                $updateData = [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'verification_status' => $verificationStatus
                ];

                // Update password if provided
                if (!empty($_POST['password'])) {
                    $updateData['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                }

                // Build update query
                $setClause = [];
                $values = [];
                foreach ($updateData as $key => $value) {
                    $setClause[] = "$key = ?";
                    $values[] = $value;
                }
                $values[] = $userId;

                $sql = "UPDATE users SET " . implode(", ", $setClause) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                $_SESSION['success_message'] = "User updated successfully!";
                header("Location: /admin/users");
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating user: " . $e->getMessage();
        }
    }
}

// Get all users with pagination
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE (name LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Build final WHERE clause excluding admins
if ($whereClause) {
    $whereClause .= " AND role != 'admin'";
} else {
    $whereClause = "WHERE role != 'admin'";
}

// Count total users (excluding admins)
$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users
$sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
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
                    <li class="nav-item active">
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
                    <li class="nav-item">
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
                <h1>User Management</h1>
                <p class="breadcrumb">Manage system users</p>
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

            <!-- Search and Filters -->
            <?php if (!$editUser): ?>
                <div class="content-section">
                    <div class="search-bar">
                        <form method="GET" action="/admin/users" class="search-form">
                            <div class="search-input">
                                <input type="text" name="search" placeholder="Search users by name or email..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                    Search
                                </button>
                            </div>
                            <?php if (!empty($search)): ?>
                                <a href="/admin/users" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Clear
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Edit User Form -->
            <?php if ($editUser): ?>
                <div class="content-section">
                    <h2>Edit User: <?php echo htmlspecialchars($editUser['name']); ?></h2>
                    <form method="POST" action="/admin/users" class="user-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($editUser['name']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($editUser['email']); ?>">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="role">Role *</label>
                                <select id="role" name="role" required>
                                    <option value="child" <?php echo $editUser['role'] === 'child' ? 'selected' : ''; ?>>Child</option>
                                    <option value="parent" <?php echo $editUser['role'] === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                    <option value="admin" <?php echo $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="verification_status">Verification Status</label>
                                <select id="verification_status" name="verification_status">
                                    <option value="pending" <?php echo $editUser['verification_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="verified" <?php echo $editUser['verification_status'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">New Password (leave blank to keep current)</label>
                            <input type="password" id="password" name="password"
                                   placeholder="Enter new password if changing">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Update User
                            </button>
                            <a href="/admin/users" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Users List -->
            <?php if (!$editUser): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2>
                            All Users
                            <?php if (!empty($search)): ?>
                                (Search: "<?php echo htmlspecialchars($search); ?>")
                            <?php endif; ?>
                        </h2>
                        <div class="user-stats">
                            <span class="stat-badge">
                                <i class="fas fa-users"></i>
                                <?php echo $totalUsers; ?> Total
                            </span>
                        </div>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>
                                <?php if (!empty($search)) { ?>
                                    No users found matching your search
                                <?php } else { ?>
                                    No users found
                                <?php } ?>
                            </h3>
                            <p>
                                <?php if (!empty($search)) { ?>
                                    Try adjusting your search terms
                                <?php } else { ?>
                                    Users will appear here when they register
                                <?php } ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="users-table-container">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userItem): ?>
                                        <tr class="<?php echo $userItem['role']; ?>-row">
                                            <td class="user-cell">
                                                <div class="user-avatar">
                                                    <i class="fas fa-<?php echo $userItem['role'] === 'admin' ? 'user-shield' : ($userItem['role'] === 'parent' ? 'user-tie' : 'child'); ?>"></i>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($userItem['name']); ?></div>
                                                    <?php if ($userItem['id'] == $_SESSION['user']['id']): ?>
                                                        <small class="current-user">You</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="role-badge <?php echo $userItem['role']; ?>">
                                                    <?php echo ucfirst($userItem['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($userItem['email']); ?></td>
                                            <td>
                                                <?php if ($userItem['verification_status'] === 'verified'): ?>
                                                    <span class="status-badge verified">
                                                        <i class="fas fa-check-circle"></i> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge pending">
                                                        <i class="fas fa-clock"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($userItem['created_at'])); ?></td>
                                            <td class="actions-cell">
                                                <div class="action-buttons">
                                                    <a href="?edit=<?php echo $userItem['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($userItem['id'] != $_SESSION['user']['id']): ?>
                                                        <a href="?delete=<?php echo $userItem['id']; ?>"
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                       class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                        Previous
                                    </a>
                                <?php endif; ?>

                                <div class="page-info">
                                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                                </div>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                       class="pagination-btn">
                                        Next
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        /* User-specific styles */
        .user-stats {
            display: flex;
            gap: 10px;
        }

        .stat-badge {
            background: #3498db;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .search-bar {
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-input {
            flex: 1;
            display: flex;
            gap: 10px;
        }

        .search-input input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 16px;
        }

        .user-form {
            max-width: 800px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
        }

        .admin-row {
            background: rgba(231, 76, 60, 0.05);
        }

        .parent-row {
            background: rgba(243, 156, 18, 0.05);
        }

        .child-row {
            background: rgba(46, 204, 113, 0.05);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
        }

        .admin-row .user-avatar { background: #e74c3c; }
        .parent-row .user-avatar { background: #f39c12; }
        .child-row .user-avatar { background: #27ae60; }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .current-user {
            color: #3498db;
            font-weight: 600;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .role-badge.child { background: #d5f4e6; color: #27ae60; }
        .role-badge.parent { background: #fef5e7; color: #f39c12; }
        .role-badge.admin { background: #fadbd8; color: #e74c3c; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .status-badge.verified {
            background: #d5f4e6;
            color: #27ae60;
        }

        .status-badge.pending {
            background: #fef5e7;
            color: #f39c12;
        }

        .actions-cell {
            text-align: right;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        .pagination-btn {
            background: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover {
            background: #2980b9;
        }

        .page-info {
            color: #7f8c8d;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                flex-direction: column;
            }

            .table-container {
                margin: 0 -15px;
            }

            .users-table {
                min-width: 700px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .pagination {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</body>
</html>