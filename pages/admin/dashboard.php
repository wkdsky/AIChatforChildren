<?php
use Utils\Helper;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();

// Get admin statistics
$pdo = Core\Database::getInstance();

// User statistics
$totalUsersStmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $totalUsersStmt->fetch()['total'];

$childUsersStmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'child'");
$childUsers = $childUsersStmt->fetch()['total'];

$parentUsersStmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'parent'");
$parentUsers = $parentUsersStmt->fetch()['total'];

$adminUsersStmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$adminUsers = $adminUsersStmt->fetch()['total'];

// Additional statistics can be added here
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
                    <li class="nav-item active">
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
                <h1>Admin Dashboard</h1>
                <p class="breadcrumb">System Overview</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalUsers; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon child">
                        <i class="fas fa-child"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $childUsers; ?></h3>
                        <p>Child Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon parent">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $parentUsers; ?></h3>
                        <p>Parent Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon admin">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $adminUsers; ?></h3>
                        <p>Admin Users</p>
                    </div>
                </div>
            </div>

            <!-- Additional statistics can be added here -->
        </div>
    </div>

    <script src="/assets/javascript/main.js"></script>
    <script>
        // Set active navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');

            navLinks.forEach(link => {
                if (currentPath.includes(link.getAttribute('href'))) {
                    link.parentElement.classList.add('active');
                }
            });
        });
    </script>

    <style>
        /* Dashboard-specific styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.users { background: #3498db; color: white; }
        .stat-icon.child { background: #2ecc71; color: white; }
        .stat-icon.parent { background: #f39c12; color: white; }
        .stat-icon.admin { background: #e74c3c; color: white; }

        .stat-content h3 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-content p {
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>