<?php
use Utils\Helper;
use Utils\PromptTemplateService;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();
$pdo = Core\Database::getInstance();
$promptService = new PromptTemplateService($pdo);
$promptService->ensureDefaultTemplates();
$promptsUrl = Helper::url('admin/prompts');

$redirectToPrompts = static function () use ($promptsUrl): void {
    header('Location: ' . $promptsUrl);
    exit;
};

$formatCategoryLabel = static function (string $category): string {
    return ucwords(str_replace('_', ' ', $category));
};

$editingPrompt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Helper::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        $redirectToPrompts();
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $promptId = (int) ($_POST['prompt_id'] ?? 0);

    if ($action === 'edit' && $promptId > 0) {
        $editingPrompt = $promptService->getTemplateById($promptId);
    } elseif ($action === 'update' && $promptId > 0) {
        $existingPrompt = $promptService->getTemplateById($promptId);
        if (!$existingPrompt) {
            $_SESSION['error_message'] = 'Prompt template not found.';
            $redirectToPrompts();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'general'));
        $content = trim((string) ($_POST['content'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "UPDATE prompt_templates
            SET name = ?, category = ?, content = ?, description = ?, is_active = ?
            WHERE id = ?"
        );
        $stmt->execute([$name, $category !== '' ? $category : 'general', $content, $description, $isActive, $promptId]);

        $_SESSION['success_message'] = 'Prompt template updated successfully!';
        $redirectToPrompts();
    } elseif ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'general'));
        $content = trim((string) ($_POST['content'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $templateKey = $promptService->generateTemplateKey($category !== '' ? $category : 'general', $name);

        $stmt = $pdo->prepare(
            "INSERT INTO prompt_templates (template_key, name, category, content, description, is_active)
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $templateKey,
            $name,
            $category !== '' ? $category : 'general',
            $content,
            $description,
            $isActive,
        ]);

        $_SESSION['success_message'] = 'Prompt template created successfully!';
        $redirectToPrompts();
    } elseif ($action === 'delete' && $promptId > 0) {
        $existingPrompt = $promptService->getTemplateById($promptId);
        if (!$existingPrompt) {
            $_SESSION['error_message'] = 'Prompt template not found.';
            $redirectToPrompts();
        }

        $templateKey = (string) ($existingPrompt['template_key'] ?? '');
        if (PromptTemplateService::isDefaultTemplateKey($templateKey)) {
            $_SESSION['error_message'] = 'Built-in system templates cannot be deleted.';
            $redirectToPrompts();
        }

        $stmt = $pdo->prepare("DELETE FROM prompt_templates WHERE id = ?");
        $stmt->execute([$promptId]);

        $_SESSION['success_message'] = 'Prompt template deleted successfully!';
        $redirectToPrompts();
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $promptId = (int) $_GET['edit'];
    $editingPrompt = $promptService->getTemplateById($promptId);
    if (!$editingPrompt) {
        $_SESSION['error_message'] = 'Prompt template not found!';
        $redirectToPrompts();
    }
}

$prompts = $promptService->getAllTemplates();
$formCategories = $promptService->getFormCategories($prompts);
$categories = [];
foreach ($prompts as $prompt) {
    if (!isset($categories[$prompt['category']])) {
        $categories[$prompt['category']] = [];
    }
    $categories[$prompt['category']][] = $prompt;
}
ksort($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Templates - Admin</title>
    <link rel="stylesheet" href="<?php echo Helper::url('assets/css/admin.css'); ?>">
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
                        <a href="<?php echo Helper::url('admin-dashboard'); ?>" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="<?php echo Helper::url('admin/prompts'); ?>" class="nav-link">
                            <i class="fas fa-edit"></i>
                            Prompt Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin/users'); ?>" class="nav-link">
                            <i class="fas fa-users"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin/knowledge'); ?>" class="nav-link">
                            <i class="fas fa-database"></i>
                            Knowledge Base
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin/profile'); ?>" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            Profile Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('logout'); ?>" class="nav-link logout">
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
                                    <?php foreach ($formCategories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($editingPrompt && $editingPrompt['category'] === $category) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formatCategoryLabel($category)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ($editingPrompt): ?>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="template_key">Template Key</label>
                                    <input type="text" id="template_key" value="<?php echo htmlspecialchars($editingPrompt['template_key'] ?? ''); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Template Type</label>
                                    <input type="text" value="<?php echo PromptTemplateService::isDefaultTemplateKey((string) ($editingPrompt['template_key'] ?? '')) ? 'Built-in system template' : 'Custom template'; ?>" readonly>
                                </div>
                            </div>
                        <?php endif; ?>

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
                            <small class="form-help">Use variables like {name} for dynamic content. Child chat templates support {child_profile}, {child_name}, {age_band}, and {age_years}.</small>
                        </div>

                        <?php if ($editingPrompt && PromptTemplateService::isDefaultTemplateKey((string) ($editingPrompt['template_key'] ?? ''))): ?>
                            <div class="form-note">
                                Disabling a built-in child chat template makes the system fall back to its default prompt content.
                            </div>
                        <?php endif; ?>

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
                            <a href="<?php echo Helper::url('admin/prompts'); ?>" class="btn btn-secondary">
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
                    <a href="<?php echo Helper::url('admin/prompts?new=1'); ?>" class="btn btn-primary">
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
                                <?php echo htmlspecialchars($formatCategoryLabel((string) $category)); ?>
                                <span class="template-count"><?php echo count($categoryPrompts); ?></span>
                            </h3>

                            <div class="templates-grid">
                                <?php foreach ($categoryPrompts as $prompt): ?>
                                    <?php $isSystemTemplate = PromptTemplateService::isDefaultTemplateKey((string) ($prompt['template_key'] ?? '')); ?>
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

                                        <div class="prompt-key-row">
                                            <span class="prompt-key"><?php echo htmlspecialchars((string) ($prompt['template_key'] ?? '')); ?></span>
                                            <?php if ($isSystemTemplate): ?>
                                                <span class="system-badge">Built-in</span>
                                            <?php endif; ?>
                                        </div>

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
                                            <a href="<?php echo Helper::url('admin/prompts?edit=' . (int) $prompt['id']); ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if (!$isSystemTemplate): ?>
                                                <form method="POST" style="display: inline;"
                                                      onsubmit="return confirm('Are you sure you want to delete this prompt template?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="prompt_id" value="<?php echo $prompt['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

        .prompt-key-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .prompt-key {
            display: inline-flex;
            align-items: center;
            background: #eef3f8;
            color: #2c3e50;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-family: 'Courier New', monospace;
        }

        .system-badge {
            display: inline-flex;
            align-items: center;
            background: #eaf7ef;
            color: #1d7a46;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
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

        .form-help {
            display: block;
            margin-top: 8px;
            color: #7f8c8d;
        }

        .form-note {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #eef6ff;
            color: #2f5f87;
            font-size: 14px;
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
