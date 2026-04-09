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
    <link rel="stylesheet" href="<?php echo Helper::url('assets/css/auth_styles.css'); ?>">
</head>

<body>
    <div class="container">
        <div class="left">
            <h2>Join Us!</h2>
            <p>Create an account to get started.</p>
        </div>
        <div class="right">
            <h2>Sign Up</h2>
            <p class="signup-notice">Hello, new parent.</p>

            <form action="<?php echo Helper::url('sign-up'); ?>" method="POST">
                <!-- CSRF Token for security -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Email Input -->
                <div class="input-group">
                    <input type="email" name="email" id="signup-email" <?= Helper::oldValue("email", "Email") ?> required>
                    <small class="field-feedback <?= Helper::firstError('email') !== '' ? 'error' : '' ?>" id="signup-email-feedback"><?= htmlspecialchars(Helper::firstError('email')) ?></small>
                </div>

                <!-- Password Input -->
                <div class="input-group">
                    <input type="password" name="password" id="signup-password" placeholder="Password" required>
                    <button type="button" class="password-toggle" aria-label="Show password">Show</button>
                    <small class="field-feedback <?= Helper::firstError('password') !== '' ? 'error' : '' ?>" id="signup-password-feedback"><?= htmlspecialchars(Helper::firstError('password')) ?></small>
                </div>

                <!-- Confirm Password Input -->
                <div class="input-group">
                    <input type="password" name="confirm_password" id="signup-confirm-password" placeholder="Confirm password" required>
                    <button type="button" class="password-toggle" aria-label="Show password">Show</button>
                    <small class="field-feedback <?= Helper::firstError('confirm_password') !== '' ? 'error' : '' ?>" id="signup-confirm-password-feedback"><?= htmlspecialchars(Helper::firstError('confirm_password')) ?></small>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn">Sign Up</button>
            </form>

            <!-- Sign In Link -->
            <div class="register">
                Already have an account? <a href="<?php echo Helper::url('sign-in'); ?>">Sign In</a>
            </div>
        </div>
    </div>

    <script src="<?php echo Helper::url('assets/javascript/main.js'); ?>"></script>
    <script>
        const AVAILABILITY_URL = '<?php echo Helper::url('api/validation/account-availability'); ?>';
        const signupEmailInput = document.getElementById('signup-email');
        const signupPasswordInput = document.getElementById('signup-password');
        const signupConfirmPasswordInput = document.getElementById('signup-confirm-password');
        const signupEmailFeedback = document.getElementById('signup-email-feedback');
        const signupPasswordFeedback = document.getElementById('signup-password-feedback');
        const signupConfirmPasswordFeedback = document.getElementById('signup-confirm-password-feedback');
        let signupEmailCheckTimer = null;
        let signupEmailCheckController = null;

        function setFeedback(element, message = '', type = '') {
            element.textContent = message;
            element.className = `field-feedback${type ? ' ' + type : ''}`;
        }

        function validateSignupPassword() {
            const password = signupPasswordInput.value;
            if (password === '') {
                setFeedback(signupPasswordFeedback, 'Password is required.', 'error');
                return false;
            }

            if (password.length < 6) {
                setFeedback(signupPasswordFeedback, 'Password must be at least 6 characters.', 'error');
                return false;
            }

            setFeedback(signupPasswordFeedback, '');
            return true;
        }

        function validateSignupConfirmPassword() {
            const confirmPassword = signupConfirmPasswordInput.value;
            if (confirmPassword === '') {
                setFeedback(signupConfirmPasswordFeedback, 'Please confirm your password.', 'error');
                return false;
            }

            if (confirmPassword !== signupPasswordInput.value) {
                setFeedback(signupConfirmPasswordFeedback, 'Passwords do not match.', 'error');
                return false;
            }

            setFeedback(signupConfirmPasswordFeedback, '');
            return true;
        }

        async function checkSignupEmailAvailability() {
            const email = signupEmailInput.value.trim();

            if (email === '') {
                setFeedback(signupEmailFeedback, 'Email is required.', 'error');
                return false;
            }

            if (signupEmailCheckController) {
                signupEmailCheckController.abort();
            }

            signupEmailCheckController = new AbortController();

            try {
                const response = await fetch(`${AVAILABILITY_URL}?field=email&value=${encodeURIComponent(email)}`, {
                    signal: signupEmailCheckController.signal,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const result = await response.json();

                if (!result.available) {
                    setFeedback(signupEmailFeedback, result.message, 'error');
                    return false;
                }

                setFeedback(signupEmailFeedback, result.message, 'success');
                return true;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setFeedback(signupEmailFeedback, 'Unable to verify email right now.', 'error');
                }
                return false;
            }
        }

        signupEmailInput.addEventListener('input', () => {
            setFeedback(signupEmailFeedback, '');
            if (signupEmailCheckTimer) {
                clearTimeout(signupEmailCheckTimer);
            }

            signupEmailCheckTimer = setTimeout(() => {
                checkSignupEmailAvailability();
            }, 450);
        });

        signupPasswordInput.addEventListener('input', () => {
            validateSignupPassword();
            if (signupConfirmPasswordInput.value !== '') {
                validateSignupConfirmPassword();
            }
        });

        signupConfirmPasswordInput.addEventListener('input', validateSignupConfirmPassword);
    </script>
</body>

</html>

<?php
// Clear session errors and old input after displaying
unset($_SESSION['errors']);
unset($_SESSION['old']);
?>
