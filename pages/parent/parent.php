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
  <link rel="stylesheet" href="assets/css/profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .navbar {
      width: 100%;
      background: #fff;
      padding: 15px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--box-shadow);
    }

    .brand {
      font-size: 1.5rem;
      font-weight: 600;
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
      border-radius: 6px;
      transition: background-color 0.3s;
    }

    .user-info:hover {
      background-color: #f0f0f0;
    }

    .user-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background-color: var(--primary-color);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }

    .user-name {
      font-weight: 500;
      color: #333;
    }

    .dropdown-menu {
      position: absolute;
      top: 100%;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 6px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: none;
      min-width: 180px;
      z-index: 1000;
    }

    .dropdown-menu.show {
      display: block;
    }

    .dropdown-item {
      display: block;
      padding: 10px 15px;
      color: #333;
      text-decoration: none;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .dropdown-item:hover {
      background-color: #f8f9fa;
    }

    .dropdown-item.danger {
      color: #dc3545;
    }

    .dropdown-item i {
      width: 20px;
      margin-right: 8px;
    }

    /* Alert Messages */
    #alertMessage {
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-size: 14px;
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #alertMessage.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    #alertMessage.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .error-text {
      color: #dc3545;
      font-size: 12px;
      display: block;
      margin-top: 5px;
    }

    .btn-danger {
      transition: background-color 0.3s;
    }

    .btn-danger:hover {
      background-color: #c82333 !important;
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
        <a href="logout" class="dropdown-item danger">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>

  <div class="container">
    <h1>Welcome to Parent Area</h1>
    <p>This page is for parents only.</p>
  </div>

  <!-- Profile Modal -->
  <div class="modal" id="profileModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Profile Settings</h2>
        <span class="close" onclick="closeProfileModal()">&times;</span>
      </div>
      <div class="modal-body">
        <!-- Alert Message Area -->
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

        <a href="logout" class="btn btn-danger" style="width: 100%; text-align: center; display: block; background: #dc3545; color: white; text-decoration: none;">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>

  <style>
    .modal {
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
      background-color: #fefefe;
      margin: 5% auto;
      padding: 0;
      border-radius: 10px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 30px;
      border-bottom: 1px solid #eee;
    }

    .modal-header h2 {
      margin: 0;
    }

    .close {
      color: #aaa;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }

    .close:hover {
      color: #000;
    }

    .modal-body {
      padding: 30px;
    }

    .container {
      max-width: 600px;
      margin: 50px auto;
      background: #fff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: var(--box-shadow);
    }
  </style>

  <script>
    let alertTimeout = null;

    function toggleDropdown() {
      const dropdown = document.getElementById('userDropdown');
      dropdown.classList.toggle('show');
    }

    function showProfileModal() {
      document.getElementById('profileModal').style.display = 'block';
      document.getElementById('userDropdown').classList.remove('show');
      clearAlert();
    }

    function closeProfileModal() {
      document.getElementById('profileModal').style.display = 'none';
      clearAlert();
      clearFormErrors();
    }

    function showAlert(message, type = 'success') {
      const alertDiv = document.getElementById('alertMessage');
      alertDiv.textContent = message;
      alertDiv.className = type;
      alertDiv.style.display = 'block';

      // Clear existing timeout
      if (alertTimeout) {
        clearTimeout(alertTimeout);
      }

      // Auto-hide after 5 seconds
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

    function clearFormErrors() {
      document.querySelectorAll('.error-text').forEach(el => el.textContent = '');
    }

    function displayErrors(errors) {
      clearFormErrors();
      for (const [field, messages] of Object.entries(errors)) {
        let errorElementId = field + 'Error';

        // Handle special field names
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

    // Handle profile form submission
    document.getElementById('profileForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      clearFormErrors();

      const formData = new FormData(this);
      formData.append('update-profile', '1');

      try {
        const response = await fetch('update-profile', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showAlert(result.message, 'success');

          // Update the displayed username
          if (result.newName) {
            document.querySelector('.user-name').textContent = result.newName;
            document.querySelector('.user-avatar').textContent = result.newName.charAt(0).toUpperCase();
            document.getElementById('name').value = result.newName;
          }
        } else {
          if (result.errors) {
            displayErrors(result.errors);
            showAlert('Please fix the errors and try again.', 'error');
          } else {
            showAlert(result.message || 'An error occurred', 'error');
          }
        }
      } catch (error) {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    // Handle password form submission
    document.getElementById('passwordForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      clearFormErrors();

      const formData = new FormData(this);
      formData.append('update-password', '1');

      try {
        const response = await fetch('update-profile', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showAlert(result.message, 'success');

          // Clear password fields
          this.reset();
          // Re-add the CSRF token
          const csrfInput = this.querySelector('input[name="csrf_token"]');
          csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';
        } else {
          if (result.errors) {
            displayErrors(result.errors);
            showAlert('Please fix the errors and try again.', 'error');
          } else {
            showAlert(result.message || 'An error occurred', 'error');
          }
        }
      } catch (error) {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    // Close modal and dropdown when clicking outside
    window.onclick = function(event) {
      // Close modal if clicking outside
      if (event.target == document.getElementById('profileModal')) {
        closeProfileModal();
      }

      // Close dropdown if clicking outside
      if (!event.target.matches('.user-info') && !event.target.closest('.user-info')) {
        const dropdowns = document.getElementsByClassName('dropdown-menu');
        for (let dropdown of dropdowns) {
          if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
          }
        }
      }
    }
  </script>
</body>

</html>

<?php
unset($_SESSION['errors']);
unset($_SESSION['old']);
unset($_SESSION['success']);
?>