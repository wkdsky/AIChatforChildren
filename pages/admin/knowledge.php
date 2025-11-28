<?php
use Utils\Helper;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base Management - Admin</title>
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
                    <li class="nav-item active">
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
                <h1>Knowledge Base Management</h1>
                <p class="breadcrumb">Manage system knowledge and data</p>
            </div>

            <div class="content-section">
                <div class="coming-soon-container">
                    <div class="coming-soon-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h2>Knowledge Base Management</h2>
                    <p>This feature is currently under development.</p>
                    <p class="coming-soon-description">
                        The knowledge base management system will allow administrators to:
                    </p>
                    <ul class="feature-list">
                        <li><i class="fas fa-plus-circle"></i> Add and manage knowledge documents</li>
                        <li><i class="fas fa-folder-open"></i> Organize content by categories</li>
                        <li><i class="fas fa-search"></i> Search and filter knowledge items</li>
                        <li><i class="fas fa-sync"></i> Update and maintain information</li>
                        <li><i class="fas fa-chart-line"></i> Monitor knowledge base usage</li>
                    </ul>
                    <div class="development-status">
                        <div class="status-badge">
                            <i class="fas fa-tools"></i>
                            Under Development
                        </div>
                        <p>Expected completion: Coming Soon</p>
                    </div>
                </div>
            </div>

            <style>
                /* Knowledge-specific styles */
                .content-section {
                    padding: 40px;
                }

                .coming-soon-container {
                    text-align: center;
                    max-width: 600px;
                    margin: 0 auto;
                }

                .coming-soon-icon {
                    width: 120px;
                    height: 120px;
                    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 30px;
                    font-size: 3rem;
                    color: white;
                    box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
                }

                .coming-soon-container h2 {
                    color: #2c3e50;
                    font-size: 2.5rem;
                    margin-bottom: 15px;
                }

                .coming-soon-container p {
                    color: #7f8c8d;
                    font-size: 1.2rem;
                    margin-bottom: 20px;
                }

                .coming-soon-description {
                    color: #34495e;
                    margin-bottom: 30px;
                }

                .feature-list {
                    list-style: none;
                    text-align: left;
                    max-width: 400px;
                    margin: 0 auto 40px;
                }

                .feature-list li {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    padding: 12px 0;
                    border-bottom: 1px solid #ecf0f1;
                    color: #2c3e50;
                    font-size: 1.1rem;
                }

                .feature-list li:last-child {
                    border-bottom: none;
                }

                .feature-list i {
                    color: #3498db;
                    font-size: 1.2rem;
                    width: 20px;
                    text-align: center;
                }

                .development-status {
                    background: #f8f9fa;
                    padding: 30px;
                    border-radius: 10px;
                    border: 2px dashed #3498db;
                }

                .development-status .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: #fef5e7;
                    color: #f39c12;
                    padding: 10px 20px;
                    border-radius: 25px;
                    font-weight: bold;
                    margin-bottom: 15px;
                }

                .development-status p {
                    color: #7f8c8d;
                    font-size: 1rem;
                    margin: 0;
                }

                @media (max-width: 768px) {
                    .content-section {
                        padding: 20px;
                    }

                    .coming-soon-icon {
                        width: 80px;
                        height: 80px;
                        font-size: 2rem;
                    }

                    .coming-soon-container h2 {
                        font-size: 2rem;
                    }

                    .feature-list {
                        max-width: 100%;
                    }
                }
            </style>
        </div>
    </div>
</body>
</html>