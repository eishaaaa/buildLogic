<?php
session_start();
require 'db_connect.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $login_type = $_POST['login_type'] ?? 'user'; // Get login type (user or supplier)

    try {
        // Fetch user details including ID
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check if the role matches the login type
            $expected_role = ($login_type === 'user') ? 'customer' : 'supplier';
            if ($user['role'] !== $expected_role) {
                $message = '<div class="alert alert-danger">Invalid login type for this account</div>';
            } else {
                // Role matches, proceed with login
                $_SESSION['user_id'] = $user['id']; // Set user_id
                $_SESSION['email'] = $email;
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // For suppliers, use the user ID as supplier_id
                if ($login_type === 'supplier') {
                    $_SESSION['supplier']['supplier_id'] = $user['id'];
                }

                // Redirect based on login type
                if ($login_type === 'supplier') {
                    header('Location: admin.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid credentials</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        error_log("Login error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
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

        .gap-1 {
            gap: 0.5rem;
        }

        .gap-2 {
            gap: 1rem;
        }

        .social-btns .btn {
            min-width: 120px;
        }

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

        .toggle-btn {
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .toggle-btn.active {
            background-color: #28a745;
            color: white;
        }

        .toggle-btn:not(.active) {
            background-color: #e9ecef;
            color: #495057;
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
                <fieldset class="signIn">
                    <h3 class="text-center mb-4">Sign In to your account</h3>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <button type="button" class="toggle-btn active" onclick="toggleLoginType('user')" id="userBtn">User</button>
                        <button type="button" class="toggle-btn" onclick="toggleLoginType('supplier')" id="supplierBtn">Supplier</button>
                    </div>
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
                        <input type="hidden" name="login_type" id="login_type" value="user">
                        <input type="email" class="form-control mb-3" name="email" placeholder="Your Email" required>
                        <input type="password" class="form-control mb-4" name="password" placeholder="Password" required>
                        <button type="submit" class="btn btn-success btn-block w-100 mb-3">Sign In</button>
                    </form>
                    <div class="text-center">
                        <span class="text-muted">Don't have an account? </span>
                        <a href="signup.php" class="text-primary" id="signupLink">Sign Up</a>
                        <span class="text-muted"> | </span>
                        <a href="forget_password.php" class="text-primary">Forget Password?</a>
                    </div>
                </fieldset>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleLoginType(type) {
            const userBtn = document.getElementById('userBtn');
            const supplierBtn = document.getElementById('supplierBtn');
            const loginTypeInput = document.getElementById('login_type');
            const signupLink = document.getElementById('signupLink');

            if (type === 'user') {
                userBtn.classList.add('active');
                supplierBtn.classList.remove('active');
                loginTypeInput.value = 'user';
                signupLink.textContent = 'Sign Up';
                signupLink.href = 'signup.php';
            } else {
                supplierBtn.classList.add('active');
                userBtn.classList.remove('active');
                loginTypeInput.value = 'supplier';
                signupLink.textContent = 'Register';
                signupLink.href = 'register.php';
            }
        }
    </script>
</body>

</html>