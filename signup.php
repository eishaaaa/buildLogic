<?php
session_start();
require 'db_connect.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $message = '<div class="alert alert-danger">Email already exists!</div>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password]);
            $message = '<div class="alert alert-success">Signup successful! Please <a href="login.php">login</a>.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
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
        .social-btns .btn {
            min-width: 120px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0056b3, #003f7f);
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
                <fieldset class="signUp">
                    <h3 class="text-center mb-4">Create your account</h3>
                    <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap social-btns">
                        <button class="btn btn-outline-primary d-flex align-items-center gap-1">
                            <i class="fab fa-linkedin"></i> LinkedIn
                        </button>
                        <button class="btn btn-outline-danger d-flex align-items-center gap-1">
                            <i class="fab fa-google"></i> Google
                        </button>
                        <button class="btn btn-outline-primary d-flex align-items-center gap-1">
                            <i class="fab fa-facebook"></i> Facebook
                        </button>
                    </div>
                    <p class="text-center text-muted">or use your email</p>
                    <?php echo $message; ?>
                    <form method="POST">
                        <input type="text" class="form-control mb-3" name="name" placeholder="Your Name" required>
                        <input type="email" class="form-control mb-3" name="email" placeholder="Your Email" required>
                        <input type="password" class="form-control mb-4" name="password" placeholder="Password" required>
                        <button type="submit" class="btn btn-primary btn-block w-100 mb-3">Sign Up</button>
                    </form>
                    <div class="text-center">
                        <span class="text-muted">Already have an account? </span>
                        <a href="login.php" class="text-primary">Sign In</a>
                    </div>
                </fieldset>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>