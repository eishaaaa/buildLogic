<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

session_start();
require 'db_connect.php';

// Initialize variables
$action = $_POST['action'] ?? '';
$errors = [];
$success = false;

// Gmail SMTP credentials
$gmailAddress = ''; // Replace with your Gmail address
$gmailAppPassword = ''; // Replace with your Google App Password
error_log('Session ID: ' . session_id());

// Forgot Password Logic
if ($action === 'forgot_password') {
    $emailAddress = trim(strtolower($_POST['emailAddress'] ?? '')); // Normalize to lowercase

    // Server-side validation
    if (empty($emailAddress)) {
        $errors[] = 'Email address is required';
    }
    if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    if (empty($errors)) {
        try {
            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email");
            if (!$stmt->execute(['email' => $emailAddress])) {
                $errors[] = 'Database query failed';
                error_log('Email check query failed: ' . print_r($pdo->errorInfo(), true));
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Email check result for $emailAddress: " . print_r($user, true));
                if ($user === false || empty($user)) {
                    $errors[] = 'Email address not found';
                } else {
                    // Generate unique token
                    $token = bin2hex(random_bytes(32));
                    $userName = $user['name'];

                    // Store token in session for one-time use
                    $_SESSION['reset_token'] = $token;
                    $_SESSION['reset_email'] = $emailAddress;
                    $_SESSION['reset_time'] = time(); // For 1-hour expiration
                    error_log("Reset token generated: $token for $emailAddress");

                    // Send reset link via email using PHPMailer
                    $resetLink = "http://localhost/Practice/reset_password.php?token=$token&email=" . urlencode($emailAddress);
                    $mail = new PHPMailer(true);
                    try {
                        // Enable SMTP debugging
                        $mail->SMTPDebug = 2;
                        $mail->Debugoutput = function($str, $level) {
                            error_log("PHPMailer Debug [$level]: $str");
                        };

                        // SMTP settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $gmailAddress;
                        $mail->Password = $gmailAppPassword;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Email details
                        $mail->setFrom($gmailAddress, 'BrickLogic');
                        $mail->addAddress($emailAddress);
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request for BrickLogic';
                        $mail->Body = "Dear $userName,<br><br>Click the link below to reset your password:<br><a href='$resetLink'>Reset Password</a><br><br>This link is valid for 1 hour and can only be used once.<br><br>Thank you,<br>BrickLogic Team<br><small>Inspired by Feeta.pk</small>";
                        $mail->AltBody = "Dear $userName,\n\nCopy and paste the link below to reset your password:\n$resetLink\n\nThis link is valid for 1 hour and can only be used once.\n\nThank you,\nBrickLogic Team\nInspired by Feeta.pk";

                        $mail->send();
                        error_log("Password reset email sent to $emailAddress with token $token");
                        $success = true;
                    } catch (Exception $e) {
                        $errors[] = 'Failed to send reset email. Please try again.';
                        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log('Database error in forgot password: ' . $e->getMessage());
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
    <title>Forgot Password - BrickLogic</title>
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
        <h2 class="text-center mb-4">Forgot Password</h2>
        <?php if ($success): ?>
            <div class="alert alert-success">
                A password reset link has been sent to your email. Please check your inbox (and spam/junk folder).
            </div>
            <a href="login.php" class="login-link">Back to Login</a>
        <?php else: ?>
            <form id="forgotPasswordForm" method="POST" action="">
                <input type="hidden" name="action" value="forgot_password">
                <div class="form-group">
                    <label for="emailAddress">Email Address</label>
                    <input type="email" class="form-control" id="emailAddress" name="emailAddress" required>
                </div>
                <?php if (!empty($errors)): ?>
                    <div class="error-message"><?php echo implode('<br>', $errors); ?></div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
            </form>
            <a href="login.php" class="login-link">Back to Login</a>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        document.getElementById('forgotPasswordForm')?.addEventListener('submit', function(e) {
            const emailAddress = document.getElementById('emailAddress').value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailAddress) {
                alert('Please enter an email address.');
                e.preventDefault();
                return;
            }
            if (!emailPattern.test(emailAddress)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>