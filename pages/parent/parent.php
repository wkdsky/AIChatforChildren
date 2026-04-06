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
  <title>Parent Area</title>
  <link rel="stylesheet" href="<?php echo Helper::url('assets/css/profile.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body {
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, rgba(102, 126, 234, 0.16), transparent 30%),
        radial-gradient(circle at top right, rgba(118, 75, 162, 0.12), transparent 25%),
        #f3f4f8;
    }

    .navbar {
      width: 100%;
      background: rgba(255, 255, 255, 0.92);
      backdrop-filter: blur(10px);
      padding: 15px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--box-shadow);
      position: sticky;
      top: 0;
      z-index: 50;
    }

    .brand {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary-color);
      text-decoration: none;
    }

    .user-menu {
      position: relative;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 999px;
      transition: background-color 0.2s ease;
    }

    .user-info:hover {
      background-color: #f1f3f8;
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .user-name {
      font-weight: 600;
      color: #2d3748;
    }

    .dropdown-menu {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      background: white;
      border: 1px solid #e6e8ef;
      border-radius: 12px;
      box-shadow: 0 18px 45px rgba(31, 41, 55, 0.12);
      display: none;
      min-width: 190px;
      z-index: 1000;
      overflow: hidden;
    }

    .dropdown-menu.show {
      display: block;
    }

    .dropdown-item {
      display: block;
      padding: 12px 16px;
      color: #333;
      text-decoration: none;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      cursor: pointer;
      transition: background-color 0.2s ease;
      font-size: 14px;
    }

    .dropdown-item:hover {
      background-color: #f8f9fc;
    }

    .dropdown-item.danger {
      color: #dc3545;
    }

    .dropdown-item i {
      width: 20px;
      margin-right: 8px;
    }

    .page-shell {
      max-width: 1200px;
      margin: 0 auto;
      padding: 32px 20px 48px;
    }

    .hero-panel {
      margin-bottom: 24px;
    }

    .summary-card,
    .children-section {
      background: rgba(255, 255, 255, 0.94);
      border: 1px solid rgba(230, 232, 239, 0.9);
      border-radius: 22px;
      box-shadow: 0 18px 45px rgba(31, 41, 55, 0.08);
    }

    .summary-card {
      padding: 26px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
    }

    .summary-label {
      font-size: 13px;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .summary-number {
      font-size: 2.4rem;
      font-weight: 700;
      color: #1f2937;
    }

    .summary-caption {
      color: #6b7280;
      line-height: 1.6;
      margin: 0;
      max-width: 520px;
    }

    .children-section {
      padding: 28px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 20px;
    }

    .section-title h2 {
      margin: 0 0 8px;
      font-size: 1.5rem;
      color: #111827;
    }

    .section-title p {
      margin: 0;
      color: #6b7280;
      line-height: 1.6;
    }

    .action-inline {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn-inline {
      width: auto;
      min-width: 0;
      margin: 0;
      padding: 12px 18px;
      border-radius: 12px;
      font-weight: 600;
    }

    .btn-secondary {
      background: #eef2ff;
      color: #4338ca;
    }

    .btn-secondary:hover {
      background: #e0e7ff;
    }

    .children-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .child-card {
      border: 1px solid #e8eaf3;
      border-radius: 16px;
      padding: 16px 18px;
      background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
    }

    .child-card h3 {
      margin: 0;
      color: #111827;
      font-size: 1rem;
    }

    .child-summary {
      display: flex;
      align-items: center;
      gap: 14px;
      min-width: 0;
      flex: 1;
    }

    .child-main {
      min-width: 0;
    }

    .child-meta {
      margin: 2px 0 0;
      color: #6b7280;
      font-size: 13px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .child-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      background: #ede9fe;
      color: #6d28d9;
      white-space: nowrap;
    }

    .child-inline-stats {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .child-stat-chip {
      padding: 8px 12px;
      border-radius: 999px;
      background: #f8fafc;
      border: 1px solid #edf2f7;
      color: #475569;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .card-actions {
      display: flex;
      gap: 10px;
      flex-shrink: 0;
    }

    .ghost-btn,
    .warning-btn,
    .danger-btn {
      border: none;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .ghost-btn {
      background: #eef2ff;
      color: #4338ca;
    }

    .danger-btn {
      background: #fee2e2;
      color: #b91c1c;
    }

    .warning-btn {
      background: #fff4d6;
      color: #b45309;
    }

    .ghost-btn:hover,
    .warning-btn:hover,
    .danger-btn:hover {
      transform: translateY(-1px);
      opacity: 0.92;
    }

    .empty-state {
      border: 2px dashed #d8dcef;
      border-radius: 18px;
      padding: 34px 24px;
      text-align: center;
      color: #6b7280;
      background: #fafbff;
    }

    .empty-state i {
      font-size: 28px;
      color: #818cf8;
      margin-bottom: 12px;
      display: inline-block;
    }

    .section-alert,
    #alertMessage {
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 18px;
      font-size: 14px;
    }

    .section-alert.success,
    #alertMessage.success {
      background: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .section-alert.error,
    #alertMessage.error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .modal {
      position: fixed;
      inset: 0;
      z-index: 1000;
      background-color: rgba(17, 24, 39, 0.48);
      backdrop-filter: blur(4px);
    }

    .modal-content {
      background-color: #fff;
      margin: 4% auto;
      padding: 0;
      border-radius: 18px;
      width: 92%;
      max-width: 640px;
      max-height: 86vh;
      overflow-y: auto;
      box-shadow: 0 30px 80px rgba(17, 24, 39, 0.2);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid #eef2f7;
    }

    .modal-header h2 {
      margin: 0;
      color: #111827;
      font-size: 1.2rem;
    }

    .close {
      color: #9ca3af;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      line-height: 1;
    }

    .close:hover {
      color: #111827;
    }

    .modal-body {
      padding: 24px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group.full {
      grid-column: 1 / -1;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #374151;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #d7dbe8;
      border-radius: 12px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background: white;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
    }

    .time-select-group {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      gap: 10px;
      align-items: center;
    }

    .time-select-separator {
      color: #64748b;
      font-size: 18px;
      font-weight: 700;
      text-align: center;
    }

    .gender-symbols {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .gender-symbol-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .gender-symbol-option {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      min-height: 140px;
      border: 1px solid #d7dbe8;
      border-radius: 20px;
      background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
      cursor: pointer;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background 0.2s ease;
    }

    .gender-symbol-option:hover {
      transform: translateY(-2px);
      border-color: #b9c3ff;
      box-shadow: 0 12px 24px rgba(79, 70, 229, 0.08);
    }

    .gender-symbol-input:checked + .gender-symbol-option {
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12), 0 14px 28px rgba(102, 126, 234, 0.12);
      background: linear-gradient(180deg, #f7f9ff 0%, #eef2ff 100%);
    }

    .gender-symbol-badge {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 700;
      color: white;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
    }

    .gender-symbol-option[data-gender="male"] .gender-symbol-badge {
      background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
    }

    .gender-symbol-option[data-gender="female"] .gender-symbol-badge {
      background: linear-gradient(135deg, #f472b6 0%, #db2777 100%);
    }

    .gender-symbol-text {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .gender-symbol-title {
      font-size: 16px;
      font-weight: 700;
      color: #1f2937;
      letter-spacing: 0.01em;
    }

    .gender-symbol-subtitle {
      font-size: 12px;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .error-text {
      color: #dc2626;
      font-size: 12px;
      display: block;
      margin-top: 6px;
      min-height: 16px;
    }

    .error-text.success {
      color: #059669;
    }

    .error-text.info {
      color: #64748b;
    }

    .form-note {
      margin: 0 0 18px;
      padding: 12px 14px;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      color: #6b7280;
      line-height: 1.6;
      font-size: 14px;
    }

    .settings-overview {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }

    .settings-overview-item {
      padding: 12px;
      border-radius: 12px;
      background: #f8fafc;
      border: 1px solid #edf2f7;
    }

    .settings-overview-item strong {
      display: block;
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .settings-overview-item span {
      color: #111827;
      font-weight: 600;
      line-height: 1.5;
    }

    .status-chip-paused {
      background: #fff1f2;
      color: #be123c;
    }

    .status-chip-active {
      background: #ecfdf5;
      color: #047857;
    }

    .manage-login-actions {
      display: flex;
      justify-content: flex-start;
      margin-bottom: 18px;
    }

    .btn-danger {
      transition: background-color 0.2s ease;
    }

    .btn-danger:hover {
      background-color: #c82333 !important;
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 14px 16px;
      }

      .page-shell {
        padding: 20px 14px 36px;
      }

      .children-section,
      .summary-card {
        border-radius: 18px;
      }

      .summary-card {
        align-items: flex-start;
        flex-direction: column;
      }

      .section-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .form-grid,
      .settings-overview {
        grid-template-columns: 1fr;
      }

      .child-card {
        flex-direction: column;
        align-items: stretch;
      }

      .card-actions {
        width: 100%;
      }

      .child-summary,
      .child-inline-stats {
        width: 100%;
      }

      .ghost-btn,
      .danger-btn,
      .btn-inline {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <div class="navbar">
    <div class="brand">Parent Area</div>

    <div class="user-menu">
      <div class="user-info" onclick="toggleDropdown()">
        <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <i class="fas fa-chevron-down"></i>
      </div>

      <div class="dropdown-menu" id="userDropdown">
        <a href="#" class="dropdown-item" onclick="showProfileModal(); return false;">
          <i class="fas fa-user"></i> Profile
        </a>
        <a href="<?php echo Helper::url('logout'); ?>" class="dropdown-item danger">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>

  <main class="page-shell">
    <section class="hero-panel">
      <div class="summary-card">
        <div>
          <div class="summary-label">Children Managed</div>
          <div class="summary-number" id="childrenCount">0</div>
        </div>
        <p class="summary-caption">View how many child accounts are linked to your parent account and manage their daily access in the section below.</p>
      </div>
    </section>

    <section class="children-section">
      <div class="section-header">
        <div class="section-title">
          <h2>Child Account Management</h2>
          <p>Create your child's account, review today's usage, and manage their safety limits.</p>
        </div>
        <div class="action-inline">
          <button type="button" class="btn btn-inline" onclick="showCreateChildModal()">
            <i class="fas fa-user-plus"></i> Create Child Account
          </button>
        </div>
      </div>

      <div id="pageAlert" class="section-alert" style="display: none;"></div>
      <div id="childrenList" class="children-grid"></div>
    </section>
  </main>

  <div class="modal" id="profileModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Profile Settings</h2>
        <span class="close" onclick="closeProfileModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div id="alertMessage" style="display: none;"></div>

        <form id="profileForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            <small class="error-text" id="nameError"></small>
          </div>
          <div class="form-group">
            <label for="email">Email (Cannot be changed)</label>
            <input id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
          </div>
          <button name="update-profile" type="submit" class="btn">Update Profile</button>
        </form>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

        <h3 style="margin-bottom: 20px;">Change Password</h3>

        <form id="passwordForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

          <div class="form-group">
            <label for="current-password">Current Password</label>
            <input type="password" name="current-password" id="current-password" required>
            <small class="error-text" id="currentPasswordError"></small>
          </div>
          <div class="form-group">
            <label for="new-password">New Password</label>
            <input type="password" name="password" id="new-password" required>
            <small class="error-text" id="newPasswordError"></small>
          </div>
          <div class="form-group">
            <label for="confirm-password">Confirm New Password</label>
            <input type="password" name="confirm-password" id="confirm-password" required>
            <small class="error-text" id="confirmPasswordError"></small>
          </div>
          <button name="update-password" type="submit" class="btn">Update Password</button>
        </form>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

        <a href="<?php echo Helper::url('logout'); ?>" class="btn btn-danger" style="width: 100%; text-align: center; display: block; background: #dc3545; color: white; text-decoration: none;">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>

  <div class="modal" id="createChildModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create Child Account</h2>
        <span class="close" onclick="closeCreateChildModal()">&times;</span>
      </div>
      <div class="modal-body">
        <p class="form-note">Set your child's username, password, gender, and birth date. Child usernames cannot contain @. Birth date can only be selected from the date picker.</p>

        <form id="createChildForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="form-grid">
            <div class="form-group full">
              <label for="child_name">Child Username</label>
              <input type="text" name="child_name" id="child_name" required>
              <small class="error-text" id="child_nameError"></small>
            </div>

            <div class="form-group">
              <label for="child_password">Password</label>
              <input type="password" name="password" id="child_password" required>
              <small class="error-text" id="passwordError"></small>
            </div>

            <div class="form-group">
              <label for="child_confirm_password">Confirm Password</label>
              <input type="password" name="confirm_password" id="child_confirm_password" required>
              <small class="error-text" id="confirm_passwordError"></small>
            </div>

            <div class="form-group">
              <label>Gender</label>
              <div class="gender-symbols">
                <label>
                  <input type="radio" name="gender" value="male" class="gender-symbol-input" required>
                  <span class="gender-symbol-option" data-gender="male" aria-label="Boy">
                    <span class="gender-symbol-badge">&#9794;</span>
                    <span class="gender-symbol-text">
                      <span class="gender-symbol-title">Boy</span>
                      <span class="gender-symbol-subtitle">Male</span>
                    </span>
                  </span>
                </label>
                <label>
                  <input type="radio" name="gender" value="female" class="gender-symbol-input" required>
                  <span class="gender-symbol-option" data-gender="female" aria-label="Girl">
                    <span class="gender-symbol-badge">&#9792;</span>
                    <span class="gender-symbol-text">
                      <span class="gender-symbol-title">Girl</span>
                      <span class="gender-symbol-subtitle">Female</span>
                    </span>
                  </span>
                </label>
              </div>
              <small class="error-text" id="genderError"></small>
            </div>

            <div class="form-group">
              <label for="birth_date">Birth Date</label>
              <input type="date" name="birth_date" id="birth_date" max="<?= date('Y-m-d') ?>" required>
              <small class="error-text" id="birth_dateError"></small>
            </div>
          </div>

          <button type="submit" class="btn">Create Account</button>
        </form>
      </div>
    </div>
  </div>

  <div class="modal" id="manageChildModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="manageChildTitle">Manage Child Account</h2>
        <span class="close" onclick="closeManageChildModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div class="settings-overview">
          <div class="settings-overview-item">
            <strong>Last Login</strong>
            <span id="manageChildLastLogin">Never</span>
          </div>
          <div class="settings-overview-item">
            <strong>Used Today</strong>
            <span id="manageChildUsage">0 / 0 minutes</span>
          </div>
          <div class="settings-overview-item">
            <strong>Login Access</strong>
            <span id="manageChildLoginStatus">Allowed to log in</span>
          </div>
        </div>

        <p class="form-note">Update the daily login window, total allowed minutes, or set a new password. Leaving the password fields empty keeps the current password unchanged.</p>

        <div class="manage-login-actions">
          <button type="button" id="manageChildToggleLoginBtn" class="warning-btn" onclick="toggleChildLoginFromModal()">
            Temporarily Disable Login
          </button>
        </div>

        <form id="manageChildForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="child_id" id="manage_child_id">

          <div class="form-grid">
            <div class="form-group">
              <label>Allowed Start Time</label>
              <input type="hidden" name="allowed_login_start" id="allowed_login_start" required>
              <div class="time-select-group">
                <select id="allowed_login_start_hour" aria-label="Allowed start hour"></select>
                <span class="time-select-separator">:</span>
                <select id="allowed_login_start_minute" aria-label="Allowed start minute"></select>
              </div>
              <small class="error-text" id="allowed_login_startError"></small>
            </div>

            <div class="form-group">
              <label>Allowed End Time</label>
              <input type="hidden" name="allowed_login_end" id="allowed_login_end" required>
              <div class="time-select-group">
                <select id="allowed_login_end_hour" aria-label="Allowed end hour"></select>
                <span class="time-select-separator">:</span>
                <select id="allowed_login_end_minute" aria-label="Allowed end minute"></select>
              </div>
              <small class="error-text" id="allowed_login_endError"></small>
            </div>

            <div class="form-group full">
              <label for="daily_login_minutes">Daily Total Login Time (minutes)</label>
              <input type="number" name="daily_login_minutes" id="daily_login_minutes" min="1" max="1440" required>
              <small class="error-text" id="daily_login_minutesError"></small>
            </div>

            <div class="form-group">
              <label for="manage_password">New Password</label>
              <input type="password" name="password" id="manage_password">
              <small class="error-text" id="passwordManageError"></small>
            </div>

            <div class="form-group">
              <label for="manage_confirm_password">Confirm New Password</label>
              <input type="password" name="confirm_password" id="manage_confirm_password">
              <small class="error-text" id="confirm_passwordManageError"></small>
            </div>
          </div>

          <button type="submit" class="btn">Save Settings</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    const CHILDREN_API_URL = '<?= Helper::url('api/parent/children') ?>';
    const UPDATE_CHILD_API_URL = '<?= Helper::url('api/parent/children/update') ?>';
    const DELETE_CHILD_API_URL = '<?= Helper::url('api/parent/children/delete') ?>';
    const TOGGLE_CHILD_LOGIN_API_URL = '<?= Helper::url('api/parent/children/toggle-login') ?>';
    const UPDATE_PROFILE_URL = '<?= Helper::url('update-profile') ?>';
    const VALIDATION_API_URL = '<?= Helper::url('api/validation/account-availability') ?>';
    const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
    let alertTimeout = null;
    let pageAlertTimeout = null;
    let childrenState = [];
    let childNameCheckTimer = null;
    let childNameCheckController = null;
    const TIME_MINUTE_OPTIONS = ['00', '10', '20', '30', '40', '50'];

    function toggleDropdown() {
      document.getElementById('userDropdown').classList.toggle('show');
    }

    function showProfileModal() {
      document.getElementById('profileModal').style.display = 'block';
      document.getElementById('userDropdown').classList.remove('show');
      clearAlert();
      clearProfileErrors();
    }

    function closeProfileModal() {
      document.getElementById('profileModal').style.display = 'none';
      clearAlert();
      clearProfileErrors();
    }

    function showCreateChildModal() {
      clearChildErrors('create');
      document.getElementById('createChildForm').reset();
      document.getElementById('createChildModal').style.display = 'block';
    }

    function closeCreateChildModal() {
      document.getElementById('createChildModal').style.display = 'none';
      clearChildErrors('create');
    }

    function showManageChildModal(childId) {
      const child = childrenState.find(item => item.id === childId);
      if (!child) {
        return;
      }

      clearChildErrors('manage');
      populateManageChildModal(child);
      document.getElementById('manageChildModal').style.display = 'block';
    }

    function closeManageChildModal() {
      document.getElementById('manageChildModal').style.display = 'none';
      clearChildErrors('manage');
    }

    function buildTimeOptions(selectId, values) {
      const select = document.getElementById(selectId);
      if (!select) {
        return;
      }

      select.innerHTML = values.map(value => `<option value="${value}">${value}</option>`).join('');
    }

    function initializeTimeSelectors() {
      const hourOptions = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
      buildTimeOptions('allowed_login_start_hour', hourOptions);
      buildTimeOptions('allowed_login_end_hour', hourOptions);
      buildTimeOptions('allowed_login_start_minute', TIME_MINUTE_OPTIONS);
      buildTimeOptions('allowed_login_end_minute', TIME_MINUTE_OPTIONS);
    }

    function normalizeTimeForSelector(timeValue, fallback = '00:00') {
      const baseValue = typeof timeValue === 'string' && timeValue.includes(':') ? timeValue : fallback;
      const [hourPart, minutePart] = baseValue.split(':');
      const hour = String(Math.max(0, Math.min(23, parseInt(hourPart || '0', 10) || 0))).padStart(2, '0');
      const minuteNumber = parseInt(minutePart || '0', 10) || 0;
      const normalizedMinute = String(Math.floor(minuteNumber / 10) * 10).padStart(2, '0');
      return { hour, minute: normalizedMinute };
    }

    function setTimeSelectorValue(prefix, timeValue, fallback) {
      const normalized = normalizeTimeForSelector(timeValue, fallback);
      document.getElementById(`${prefix}_hour`).value = normalized.hour;
      document.getElementById(`${prefix}_minute`).value = normalized.minute;
      document.getElementById(prefix).value = `${normalized.hour}:${normalized.minute}`;
    }

    function syncTimeHiddenValue(prefix) {
      const hour = document.getElementById(`${prefix}_hour`).value;
      const minute = document.getElementById(`${prefix}_minute`).value;
      document.getElementById(prefix).value = `${hour}:${minute}`;
    }

    function getChildById(childId) {
      return childrenState.find(item => item.id === childId) || null;
    }

    function getLoginToggleButtonLabel(loginDisabled) {
      return loginDisabled ? 'Allow Login Again' : 'Temporarily Disable Login';
    }

    function getLoginStatusLabel(loginDisabled) {
      return loginDisabled ? 'Temporarily blocked by parent' : 'Allowed to log in';
    }

    function getLoginStatusChipClass(loginDisabled) {
      return loginDisabled ? 'status-chip-paused' : 'status-chip-active';
    }

    function populateManageChildModal(child) {
      document.getElementById('manageChildTitle').textContent = `Manage ${child.name}`;
      document.getElementById('manage_child_id').value = child.id;
      setTimeSelectorValue('allowed_login_start', child.allowed_login_start || '00:00', '00:00');
      setTimeSelectorValue('allowed_login_end', child.allowed_login_end || '23:50', '23:50');
      document.getElementById('daily_login_minutes').value = child.daily_login_minutes;
      document.getElementById('manage_password').value = '';
      document.getElementById('manage_confirm_password').value = '';
      document.getElementById('manageChildLastLogin').textContent = formatDateTime(child.last_login_at) || 'Never';
      document.getElementById('manageChildUsage').textContent = `${child.used_today_minutes} / ${child.daily_login_minutes} minutes`;
      document.getElementById('manageChildLoginStatus').textContent = getLoginStatusLabel(child.login_disabled);

      const toggleButton = document.getElementById('manageChildToggleLoginBtn');
      toggleButton.textContent = getLoginToggleButtonLabel(child.login_disabled);
      toggleButton.className = child.login_disabled ? 'ghost-btn' : 'warning-btn';
    }

    function showAlert(message, type = 'success') {
      const alertDiv = document.getElementById('alertMessage');
      alertDiv.textContent = message;
      alertDiv.className = type;
      alertDiv.style.display = 'block';

      if (alertTimeout) {
        clearTimeout(alertTimeout);
      }

      alertTimeout = setTimeout(() => {
        alertDiv.style.display = 'none';
      }, 5000);
    }

    function clearAlert() {
      const alertDiv = document.getElementById('alertMessage');
      alertDiv.style.display = 'none';
      if (alertTimeout) {
        clearTimeout(alertTimeout);
      }
    }

    function showPageAlert(message, type = 'success') {
      const alertDiv = document.getElementById('pageAlert');
      alertDiv.textContent = message;
      alertDiv.className = `section-alert ${type}`;
      alertDiv.style.display = 'block';

      if (pageAlertTimeout) {
        clearTimeout(pageAlertTimeout);
      }

      pageAlertTimeout = setTimeout(() => {
        alertDiv.style.display = 'none';
      }, 5000);
    }

    function clearProfileErrors() {
      document.querySelectorAll('#profileModal .error-text').forEach(el => el.textContent = '');
    }

    function clearChildErrors(scope) {
      const selector = scope === 'manage'
        ? '#manageChildModal .error-text'
        : '#createChildModal .error-text';
      document.querySelectorAll(selector).forEach(el => {
        el.textContent = '';
        el.className = 'error-text';
      });
    }

    function displayProfileErrors(errors) {
      clearProfileErrors();
      for (const [field, messages] of Object.entries(errors)) {
        let errorElementId = `${field}Error`;

        if (field === 'current-password') {
          errorElementId = 'currentPasswordError';
        } else if (field === 'password') {
          errorElementId = 'newPasswordError';
        } else if (field === 'confirm-password') {
          errorElementId = 'confirmPasswordError';
        }

        const errorElement = document.getElementById(errorElementId);
        if (errorElement && messages.length > 0) {
          errorElement.textContent = messages[0];
        }
      }
    }

    function displayChildErrors(errors, scope) {
      clearChildErrors(scope);
      const suffix = scope === 'manage' ? 'ManageError' : 'Error';

      for (const [field, messages] of Object.entries(errors)) {
        let errorElementId = `${field}${suffix}`;
        if (scope === 'manage') {
          if (field === 'password') {
            errorElementId = 'passwordManageError';
          } else if (field === 'confirm_password') {
            errorElementId = 'confirm_passwordManageError';
          }
        }

        const errorElement = document.getElementById(errorElementId);
        if (errorElement && messages.length > 0) {
          errorElement.textContent = messages[0];
          errorElement.className = 'error-text error';
        }
      }
    }

    function setFieldMessage(elementId, message = '', type = '') {
      const element = document.getElementById(elementId);
      if (!element) {
        return;
      }

      element.textContent = message;
      element.className = type ? `error-text ${type}` : 'error-text';
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
      if (!value) {
        return 'Not set';
      }

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleDateString();
    }

    function formatDateTime(value) {
      if (!value) {
        return '';
      }

      const date = new Date(value.replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleString();
    }

    function renderChildren(children) {
      childrenState = Array.isArray(children) ? children : [];
      document.getElementById('childrenCount').textContent = childrenState.length;

      const container = document.getElementById('childrenList');
      if (childrenState.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <i class="fas fa-child"></i>
            <div>No child accounts yet.</div>
            <div>Create the first child account to start managing login time and activity.</div>
          </div>
        `;
        return;
      }

      container.innerHTML = childrenState.map(child => `
        <article class="child-card">
          <div class="child-summary">
            <span class="child-pill ${getLoginStatusChipClass(child.login_disabled)}">
              <i class="fas ${child.login_disabled ? 'fa-pause-circle' : 'fa-circle-check'}"></i>
              ${child.login_disabled ? 'Login Paused' : 'Login Allowed'}
            </span>
            <div class="child-main">
              <h3>${escapeHtml(child.name)}</h3>
              <p class="child-meta">${escapeHtml(child.gender_label)} · ${child.age ?? 'N/A'} years old · Born ${escapeHtml(formatDate(child.birth_date))}</p>
            </div>
          </div>

          <div class="child-inline-stats">
            <span class="child-stat-chip">${escapeHtml(child.allowed_login_start)} - ${escapeHtml(child.allowed_login_end)}</span>
            <span class="child-stat-chip">${child.remaining_today_minutes} min left</span>
            <span class="child-stat-chip">Last login: ${escapeHtml(formatDateTime(child.last_login_at) || 'Never')}</span>
          </div>

          <div class="card-actions">
            <button type="button" class="${child.login_disabled ? 'ghost-btn' : 'warning-btn'}" onclick="toggleChildLogin(${child.id})">
              ${child.login_disabled ? 'Allow Login' : 'Block Login'}
            </button>
            <button type="button" class="ghost-btn" onclick="showManageChildModal(${child.id})">Details</button>
            <button type="button" class="danger-btn" onclick="deleteChild(${child.id})">Delete</button>
          </div>
        </article>
      `).join('');
    }

    async function loadChildren() {
      try {
        const response = await fetch(CHILDREN_API_URL, {
          headers: {
            'Accept': 'application/json'
          }
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to load child accounts');
        }

        renderChildren(result.children);
      } catch (error) {
        console.error(error);
        showPageAlert(error.message || 'Failed to load child accounts.', 'error');
      }
    }

    async function deleteChild(childId) {
      const child = getChildById(childId);
      if (!child) {
        return;
      }

      const confirmed = window.confirm(`Delete ${child.name}'s account? This will also remove that child's conversations.`);
      if (!confirmed) {
        return;
      }

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);

      try {
        const response = await fetch(DELETE_CHILD_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to delete child account');
        }

        renderChildren(result.children);
        closeManageChildModal();
        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        showPageAlert(error.message || 'Failed to delete child account.', 'error');
      }
    }

    async function toggleChildLogin(childId) {
      const child = getChildById(childId);
      if (!child) {
        return;
      }

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);

      try {
        const response = await fetch(TOGGLE_CHILD_LOGIN_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to update child login access');
        }

        renderChildren(result.children);

        const updatedChild = result.children.find(item => item.id === childId);
        if (
          updatedChild &&
          document.getElementById('manageChildModal').style.display === 'block' &&
          Number(document.getElementById('manage_child_id').value) === childId
        ) {
          populateManageChildModal(updatedChild);
        }

        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        showPageAlert(error.message || 'Failed to update child login access.', 'error');
      }
    }

    function toggleChildLoginFromModal() {
      const childId = Number(document.getElementById('manage_child_id').value);
      if (childId > 0) {
        toggleChildLogin(childId);
      }
    }

    document.getElementById('profileForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearProfileErrors();

      const formData = new FormData(this);
      formData.append('update-profile', '1');

      try {
        const response = await fetch(UPDATE_PROFILE_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showAlert(result.message, 'success');

          if (result.newName) {
            document.querySelector('.user-name').textContent = result.newName;
            document.querySelector('.user-avatar').textContent = result.newName.charAt(0).toUpperCase();
            document.getElementById('name').value = result.newName;
          }
        } else if (result.errors) {
          displayProfileErrors(result.errors);
          showAlert('Please fix the errors and try again.', 'error');
        } else {
          showAlert(result.message || 'An error occurred', 'error');
        }
      } catch (error) {
        console.error(error);
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    document.getElementById('passwordForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearProfileErrors();

      const formData = new FormData(this);
      formData.append('update-password', '1');

      try {
        const response = await fetch(UPDATE_PROFILE_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showAlert(result.message, 'success');
          this.reset();
          this.querySelector('input[name="csrf_token"]').value = CSRF_TOKEN;
        } else if (result.errors) {
          displayProfileErrors(result.errors);
          showAlert('Please fix the errors and try again.', 'error');
        } else {
          showAlert(result.message || 'An error occurred', 'error');
        }
      } catch (error) {
        console.error(error);
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    document.getElementById('createChildForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearChildErrors('create');

      const formData = new FormData(this);

      try {
        const response = await fetch(CHILDREN_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          if (result.errors) {
            displayChildErrors(result.errors, 'create');
          }
          throw new Error(result.message || result.error || 'Failed to create child account');
        }

        renderChildren(result.children);
        closeCreateChildModal();
        this.reset();
        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        if (!document.querySelector('#createChildModal .error-text:not(:empty)')) {
          showPageAlert(error.message || 'Failed to create child account.', 'error');
        }
      }
    });

    document.getElementById('manageChildForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearChildErrors('manage');
      syncTimeHiddenValue('allowed_login_start');
      syncTimeHiddenValue('allowed_login_end');

      const formData = new FormData(this);

      try {
        const response = await fetch(UPDATE_CHILD_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          if (result.errors) {
            displayChildErrors(result.errors, 'manage');
          }
          throw new Error(result.message || result.error || 'Failed to update child account');
        }

        renderChildren(result.children);
        closeManageChildModal();
        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        if (!document.querySelector('#manageChildModal .error-text:not(:empty)')) {
          showPageAlert(error.message || 'Failed to update child account.', 'error');
        }
      }
    });

    document.querySelectorAll('input[type="date"]').forEach(input => {
      input.addEventListener('keydown', (event) => event.preventDefault());
      input.addEventListener('focus', () => {
        if (typeof input.showPicker === 'function') {
          input.showPicker();
        }
      });
      input.addEventListener('click', () => {
        if (typeof input.showPicker === 'function') {
          input.showPicker();
        }
      });
    });

    ['allowed_login_start', 'allowed_login_end'].forEach(prefix => {
      const hourSelect = document.getElementById(`${prefix}_hour`);
      const minuteSelect = document.getElementById(`${prefix}_minute`);

      if (hourSelect && minuteSelect) {
        hourSelect.addEventListener('change', () => syncTimeHiddenValue(prefix));
        minuteSelect.addEventListener('change', () => syncTimeHiddenValue(prefix));
      }
    });

    const childNameInput = document.getElementById('child_name');
    if (childNameInput) {
      childNameInput.addEventListener('input', () => {
        const value = childNameInput.value.trim();
        setFieldMessage('child_nameError', '');

        if (childNameCheckTimer) {
          clearTimeout(childNameCheckTimer);
        }

        if (childNameCheckController) {
          childNameCheckController.abort();
        }

        childNameCheckTimer = setTimeout(async () => {
          if (value === '') {
            setFieldMessage('child_nameError', 'Child username is required.', 'error');
            return;
          }

          childNameCheckController = new AbortController();

          try {
            const response = await fetch(`${VALIDATION_API_URL}?field=child_name&value=${encodeURIComponent(value)}`, {
              signal: childNameCheckController.signal,
              headers: {
                'Accept': 'application/json'
              }
            });

            const result = await response.json();
            if (!result.success) {
              setFieldMessage('child_nameError', result.message || 'Unable to verify child username.', 'error');
              return;
            }

            setFieldMessage('child_nameError', result.message, result.available ? 'success' : 'error');
          } catch (error) {
            if (error.name !== 'AbortError') {
              setFieldMessage('child_nameError', 'Unable to verify child username right now.', 'error');
            }
          }
        }, 450);
      });
    }

    window.onclick = function (event) {
      if (event.target === document.getElementById('profileModal')) {
        closeProfileModal();
      }

      if (event.target === document.getElementById('createChildModal')) {
        closeCreateChildModal();
      }

      if (event.target === document.getElementById('manageChildModal')) {
        closeManageChildModal();
      }

      if (!event.target.matches('.user-info') && !event.target.closest('.user-info')) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown.classList.contains('show')) {
          dropdown.classList.remove('show');
        }
      }
    };

    initializeTimeSelectors();
    setTimeSelectorValue('allowed_login_start', '00:00', '00:00');
    setTimeSelectorValue('allowed_login_end', '23:50', '23:50');
    loadChildren();
  </script>
</body>

</html>

<?php
unset($_SESSION['errors']);
unset($_SESSION['old']);
unset($_SESSION['success']);
?>
