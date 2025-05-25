<?php
session_start();
require 'db_connect.php';

// Initialize variables
$message = '';
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['email'];

    // Server-side validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = '<div class="alert alert-danger">All fields are required</div>';
    } elseif (strlen($newPassword) < 8) {
        $message = '<div class="alert alert-danger">New password must be at least 8 characters long</div>';
    } elseif ($newPassword !== $confirmPassword) {
        $message = '<div class="alert alert-danger">New password and confirm password do not match</div>';
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($currentPassword, $user['password'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                if ($stmt->execute([$hashedPassword, $email])) {
                    $message = '<div class="alert alert-success">Password updated successfully</div>';
                    error_log("Password updated for $email");
                } else {
                    $message = '<div class="alert alert-danger">Failed to update password</div>';
                    error_log('Password update failed: ' . print_r($pdo->errorInfo(), true));
                }
            } else {
                $message = '<div class="alert alert-danger">Incorrect current password</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            error_log('Database error in change password: ' . $e->getMessage());
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
    <title>Change Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: auto;
        }
        .auth-container fieldset {
            border: none;
        }
        h3 {
            font-weight: 600;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
        }
        .btn-block {
            padding: 10px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
        }
        .text-muted {
            font-size: 0.9rem;
        }
        .gap-1 { gap: 0.5rem; }
        .gap-2 { gap: 1rem; }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-success:hover {
            background: linear-gradient(45deg, #1e7e34, #155d27);
            transform: scale(1.05);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="modal fade show d-block" id="authModal" tabindex="-1" role="dialog" aria-labelledby="authModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content p-4">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" onclick="window.location.href='index.php'" aria-label="Close"></button>
                </div>
                <fieldset class="changePassword">
                    <h3 class="text-center mb-4">Change Password</h3>
                    <?php echo $message; ?>
                    <form method="POST">
                        <input type="password" class="form-control mb-3" name="current_password" placeholder="Current Password" required>
                        <input type="password" class="form-control mb-3" name="new_password" placeholder="New Password" required>
                        <input type="password" class="form-control mb-4" name="confirm_password" placeholder="Confirm New Password" required>
                        <button type="submit" class="btn btn-success btn-block w-100 mb-3">Change Password</button>
                    </form>
                    <div class="text-center">
                        <span class="text-muted">Forgot your password? </span>
                        <a href="forget_password.php" class="text-primary">Reset Password</a>
                    </div>
                </fieldset>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                alert('Please fill in all fields.');
                e.preventDefault();
                return;
            }
            if (newPassword.length < 8) {
                alert('New password must be at least 8 characters long.');
                e.preventDefault();
                return;
            }
            if (newPassword !== confirmPassword) {
                alert('New password and confirm password do not match.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>