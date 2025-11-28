<?php

use Utils\Helper;

$csrfToken = Helper::generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="assets/css/auth_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="left">
            <h2>Join Us!</h2>
            <p>Create an account to get started.</p>
        </div>
        <div class="right">
            <h2>Sign Up</h2>

            <!-- Display general error messages -->
            <?php if (isset($_SESSION['errors'])): ?>
                <div class="error-messages">
                    <?php Helper::showError("general") ?>
                </div>
            <?php endif; ?>

            <form action="sign-up" method="POST">
                <!-- CSRF Token for security -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Full Name Input -->
                <div class="input-group">
                    <input type="text" name="full_name" <?= Helper::oldValue("full_name", "Full name") ?> required>
                    <?php Helper::showError("full_name") ?>
                </div>

                <!-- Email Input -->
                <div class="input-group">
                    <input type="email" name="email" <?= Helper::oldValue("email", "Email") ?> required>
                    <?php Helper::showError("email") ?>
                </div>

                <!-- Password Input -->
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="fa fa-eye toggle-password"></i>
                    <?php Helper::showError("password") ?>
                </div>

                <!-- Confirm Password Input -->
                <div class="input-group">
                    <input type="password" name="confirm_password" placeholder="Confirm password" required>
                    <i class="fa fa-eye toggle-password"></i>
                    <?php Helper::showError("confirm_password") ?>
                </div>

                <!-- Role Selection Dropdown -->
                <div class="input-group">
                    <select name="role" class="role-select" required>
                        <option value="" disabled <?= !Helper::oldValue("role") ? 'selected' : '' ?>>Select your role</option>
                        <option value="child" <?= Helper::oldValue("role") && $_SESSION['old']['role'] === 'child' ? 'selected' : '' ?>>
                            Child
                        </option>
                        <option value="parent" <?= Helper::oldValue("role") && $_SESSION['old']['role'] === 'parent' ? 'selected' : '' ?>>
                            Parent
                        </option>
                    </select>
                    <i class="fa fa-user-tag select-icon"></i>
                    <?php Helper::showError("role") ?>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn">Sign Up</button>
            </form>

            <!-- Sign In Link -->
            <div class="register">
                Already have an account? <a href=" ">Sign In</a>
            </div>
        </div>
    </div>

    <script src="assets/javascript/main.js"></script>
</body>

</html>

<?php
// Clear session errors and old input after displaying
unset($_SESSION['errors']);
unset($_SESSION['old']);
?>