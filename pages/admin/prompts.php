<?php
use Utils\Helper;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();

// Database connection
$pdo = Core\Database::getInstance();

// Check if prompt_templates table exists
$tableExists = $pdo->query("SHOW TABLES LIKE 'prompt_templates'")->rowCount() > 0;

if (!$tableExists) {
    // Create prompt_templates table
    $createTableSQL = "CREATE TABLE IF NOT EXISTS prompt_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        content TEXT NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($createTableSQL);
}

// Get all prompt templates
$promptsStmt = $pdo->query("SELECT * FROM prompt_templates ORDER BY category, name");
$prompts = $promptsStmt->fetchAll();

// Handle form submission for editing prompt
$editingPrompt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit' && !empty($_POST['prompt_id'])) {
        $promptId = $_POST['prompt_id'];
        $stmt = $pdo->prepare("SELECT * FROM prompt_templates WHERE id = ?");
        $stmt->execute([$promptId]);
        $editingPrompt = $stmt->fetch();
    } elseif ($_POST['action'] === 'update' && !empty($_POST['prompt_id'])) {
        $promptId = $_POST['prompt_id'];
        $name = $_POST['name'];
        $category = $_POST['category'];
        $content = $_POST['content'];
        $description = $_POST['description'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE prompt_templates SET name = ?, category = ?, content = ?, description = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $category, $content, $description, $isActive, $promptId]);

        $_SESSION['success_message'] = "Prompt template updated successfully!";
        header("Location: /admin/prompts");
        exit;
    } elseif ($_POST['action'] === 'create') {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $content = $_POST['content'];
        $description = $_POST['description'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO prompt_templates (name, category, content, description, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category, $content, $description, $isActive]);

        $_SESSION['success_message'] = "Prompt template created successfully!";
        header("Location: /admin/prompts");
        exit;
    } elseif ($_POST['action'] === 'delete' && !empty($_POST['prompt_id'])) {
        $promptId = $_POST['prompt_id'];
        $stmt = $pdo->prepare("DELETE FROM prompt_templates WHERE id = ?");
        $stmt->execute([$promptId]);

        $_SESSION['success_message'] = "Prompt template deleted successfully!";
        header("Location: /admin/prompts");
        exit;
    }
}

// Handle edit request from GET
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $promptId = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM prompt_templates WHERE id = ?");
    $stmt->execute([$promptId]);
    $editingPrompt = $stmt->fetch();
    if (!$editingPrompt) {
        $_SESSION['error_message'] = "Prompt template not found!";
        header("Location: /admin/prompts");
        exit;
    }
}

// Group prompts by category
$categories = [];
foreach ($prompts as $prompt) {
    if (!isset($categories[$prompt['category']])) {
        $categories[$prompt['category']] = [];
    }
    $categories[$prompt['category']][] = $prompt;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Templates - Admin</title>
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
                    <li class="nav-item active">
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
                <h1>Prompt Templates</h1>
                <p class="breadcrumb">Manage AI chat prompt templates</p>
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

            <!-- Edit/Create Form -->
            <?php if ($editingPrompt || isset($_GET['new'])): ?>
                <div class="content-section">
                    <h2><?php echo $editingPrompt ? 'Edit' : 'Create'; ?> Prompt Template</h2>
                    <form method="POST" class="prompt-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="<?php echo $editingPrompt ? 'update' : 'create'; ?>">
                        <?php if ($editingPrompt): ?>
                            <input type="hidden" name="prompt_id" value="<?php echo $editingPrompt['id']; ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Template Name *</label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo $editingPrompt ? htmlspecialchars($editingPrompt['name']) : ''; ?>"
                                       placeholder="e.g., Welcome Message">
                            </div>

                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="general" <?php echo ($editingPrompt && $editingPrompt['category'] === 'general') ? 'selected' : ''; ?>>General</option>
                                    <option value="welcome" <?php echo ($editingPrompt && $editingPrompt['category'] === 'welcome') ? 'selected' : ''; ?>>Welcome</option>
                                    <option value="help" <?php echo ($editingPrompt && $editingPrompt['category'] === 'help') ? 'selected' : ''; ?>>Help</option>
                                    <option value="education" <?php echo ($editingPrompt && $editingPrompt['category'] === 'education') ? 'selected' : ''; ?>>Education</option>
                                    <option value="entertainment" <?php echo ($editingPrompt && $editingPrompt['category'] === 'entertainment') ? 'selected' : ''; ?>>Entertainment</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" id="description" name="description"
                                   value="<?php echo $editingPrompt ? htmlspecialchars($editingPrompt['description']) : ''; ?>"
                                   placeholder="Brief description of this template">
                        </div>

                        <div class="form-group">
                            <label for="content">Prompt Content *</label>
                            <textarea id="content" name="content" rows="8" required
                                      placeholder="Enter the prompt template content..."><?php echo $editingPrompt ? htmlspecialchars($editingPrompt['content']) : ''; ?></textarea>
                            <small>Use variables like {name} for dynamic content</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_active" <?php echo ($editingPrompt && $editingPrompt['is_active']) || !$editingPrompt ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Active (available for use)
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $editingPrompt ? 'Update' : 'Create'; ?> Template
                            </button>
                            <a href="/admin/prompts" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Prompt Templates List -->
            <div class="content-section">
                <div class="section-header">
                    <h2>All Templates</h2>
                    <a href="/admin/prompts?new=1" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        New Template
                    </a>
                </div>

                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No prompt templates found</h3>
                        <p>Create your first prompt template to get started</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category => $categoryPrompts): ?>
                        <div class="category-section">
                            <h3 class="category-title">
                                <i class="fas fa-folder"></i>
                                <?php echo ucfirst($category); ?>
                                <span class="template-count"><?php echo count($categoryPrompts); ?></span>
                            </h3>

                            <div class="templates-grid">
                                <?php foreach ($categoryPrompts as $prompt): ?>
                                    <div class="prompt-card">
                                        <div class="prompt-header">
                                            <h4><?php echo htmlspecialchars($prompt['name']); ?></h4>
                                            <div class="prompt-status">
                                                <?php if ($prompt['is_active']): ?>
                                                    <span class="status-badge active">
                                                        <i class="fas fa-check-circle"></i> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge inactive">
                                                        <i class="fas fa-pause-circle"></i> Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($prompt['description']): ?>
                                            <p class="prompt-description"><?php echo htmlspecialchars($prompt['description']); ?></p>
                                        <?php endif; ?>

                                        <div class="prompt-content">
                                            <?php
                                            $content = $prompt['content'];
                                            if (strlen($content) > 150) {
                                                $content = substr($content, 0, 150) . '...';
                                            }
                                            echo htmlspecialchars($content);
                                            ?>
                                        </div>

                                        <div class="prompt-meta">
                                            <span class="date">Updated: <?php echo date('M j, Y', strtotime($prompt['updated_at'])); ?></span>
                                        </div>

                                        <div class="prompt-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="prompt_id" value="<?php echo $prompt['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this prompt template?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="prompt_id" value="<?php echo $prompt['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        /* Prompt-specific styles */
        .prompt-form {
            max-width: 800px;
        }

        .category-section {
            margin-bottom: 30px;
        }

        .category-title {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .template-count {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .prompt-card {
            background: #f8f9fa;
            border: 1px solid #ecf0f1;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .prompt-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .prompt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .prompt-header h4 {
            color: #2c3e50;
            margin: 0;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .status-badge.active {
            background: #d5f4e6;
            color: #27ae60;
        }

        .status-badge.inactive {
            background: #fef5e7;
            color: #f39c12;
        }

        .prompt-description {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .prompt-content {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.4;
            color: #34495e;
        }

        .prompt-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .date {
            color: #7f8c8d;
            font-size: 12px;
        }

        .prompt-actions {
            display: flex;
            gap: 10px;
        }

        .prompt-actions form {
            margin: 0;
        }

        @media (max-width: 768px) {
            .templates-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</body>
</html>