<?php

use Utils\Helper;

$csrfToken = Helper::generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <link rel="stylesheet" href="<?php echo Helper::url('assets/css/auth_styles.css'); ?>">
</head>

<body>
    <div class="container">
        <div class="left">
            <h2>Welcome back!</h2>
            <p>Parents sign in with email. Children sign in with their account name.</p>
        </div>
        <div class="right">
            <h2>Sign In</h2>

            <form action="<?php echo Helper::url('sign-in'); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="input-group">
                    <input type="text" name="identifier" id="signin-identifier" <?= Helper::oldValue("identifier", "Enter your email or child account name") ?> required>
                    <small class="field-feedback <?= Helper::firstError('identifier') !== '' ? 'error' : '' ?>" id="signin-identifier-feedback"><?= htmlspecialchars(Helper::firstError('identifier')) ?></small>

                </div>
                <div class="input-group">
                    <input type="password" name="password" id="signin-password" placeholder="Password" required>
                    <small class="field-feedback <?= Helper::firstError('password') !== '' ? 'error' : '' ?>" id="signin-password-feedback"><?= htmlspecialchars(Helper::firstError('password')) ?></small>
                    <button type="button" class="password-toggle" aria-label="Show password">Show</button>
                </div>
                <div class="options">
                    <label>
                        <input name="remember_me" type="checkbox"> Remember me</label>
                    <a href="<?php echo Helper::url('reset-password'); ?>">Forgot password?</a>
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
            <div class="register">
                New here? <a href="<?php echo Helper::url('sign-up'); ?>">Create an Account</a>
            </div>
        </div>
    </div>
    <script src="<?php echo Helper::url('assets/javascript/main.js'); ?>"></script>
</body>

</html>
<?php
unset($_SESSION['errors']);
unset($_SESSION['old']);
?>
