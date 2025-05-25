<?php
session_start();
require 'db_connect.php';

// Initialize variables
$action = $_POST['action'] ?? '';
$errors = [];
$success = false;
$token = $_GET['token'] ?? '';
$emailAddress = trim(strtolower($_GET['email'] ?? ''));

// Validate token and email
if (empty($token) || empty($emailAddress)) {
    $errors[] = 'Invalid or missing reset link.';
} elseif (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
} elseif (
    !isset($_SESSION['reset_token']) ||
    !isset($_SESSION['reset_email']) ||
    !isset($_SESSION['reset_time']) ||
    $_SESSION['reset_token'] !== $token ||
    $_SESSION['reset_email'] !== $emailAddress
) {
    $errors[] = 'Invalid or expired reset link.';
} elseif (time() - $_SESSION['reset_time'] > 3600) { // 1-hour expiration
    $errors[] = 'Reset link has expired.';
    unset($_SESSION['reset_token'], $_SESSION['reset_email'], $_SESSION['reset_time']);
}

// Reset Password Logic
if ($action === 'reset_password' && empty($errors)) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Server-side validation
    if (empty($password) || empty($confirmPassword)) {
        $errors[] = 'All fields are required';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        try {
            // Verify email exists in users table
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            if (!$stmt->execute(['email' => $emailAddress])) {
                $errors[] = 'Database query failed';
                error_log('Email check query failed: ' . print_r($pdo->errorInfo(), true));
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user === false || empty($user)) {
                    $errors[] = 'Email address not found';
                } else {
                    // Update password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                    if (!$stmt->execute(['password' => $hashedPassword, 'email' => $emailAddress])) {
                        $errors[] = 'Failed to update password';
                        error_log('Password update failed: ' . print_r($pdo->errorInfo(), true));
                    } else {
                        // Clear session to prevent reuse
                        unset($_SESSION['reset_token'], $_SESSION['reset_email'], $_SESSION['reset_time']);
                        error_log("Password updated for $emailAddress");
                        $success = true;
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log('Database error in reset password: ' . $e->getMessage());
        }
    }
}

// Clean up PDO connection
$pdo = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BrickLogic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .form-container {
            max-width: 450px;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 10px;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            border-radius: 6px;
            padding: 10px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .login-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #007bff;
            text-decoration: none;
        }
        .login-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="text-center mb-4">Reset Password</h2>
        <?php if ($success): ?>
            <div class="alert alert-success">
                Your password has been updated successfully. You can now log in with your new password.
            </div>
            <a href="login.php" class="login-link">Back to Login</a>
        <?php elseif (!empty($errors)): ?>
            <div class="error-message"><?php echo implode('<br>', $errors); ?></div>
            <a href="forget_password.php" class="login-link">Request a new reset link</a>
        <?php else: ?>
            <form id="resetPasswordForm" method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                </div>
                <?php if (!empty($errors)): ?>
                    <div class="error-message"><?php echo implode('<br>', $errors); ?></div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
            <a href="login.php" class="login-link">Back to Login</a>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (!password || !confirmPassword) {
                alert('Please fill in all fields.');
                e.preventDefault();
                return;
            }
            if (password.length < 8) {
                alert('Password must be at least 8 characters long.');
                e.preventDefault();
                return;
            }
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>