<?php
session_start(); // Start session for user ID and CSRF protection

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF validation failed: POST token=" . ($_POST['csrf_token'] ?? 'none') . ", SESSION token=" . ($_SESSION['csrf_token'] ?? 'none'), 3, "errors.log");
    die("Invalid CSRF token. Please try again.");
}

// Unset CSRF token after successful validation
unset($_SESSION['csrf_token']);

// Database connection settings
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "web";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed. Please try again later.");
}

// Get users_id (assuming user is logged in and ID is stored in session)
$users_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($users_id === 0) {
    die("User not logged in.");
}

// Check if user has 'customer' role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $users_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'customer') {
    die("Only users with the 'customer' role can subscribe.");
}

// Validate and sanitize form data
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$card_number = filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_STRING);
$expiry_date = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_STRING);
$cvc = filter_input(INPUT_POST, 'cvc', FILTER_SANITIZE_STRING);
$package = filter_input(INPUT_POST, 'package', FILTER_SANITIZE_STRING);

// Basic validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format.");
}
if (empty($phone) || empty($card_number) || empty($expiry_date) || empty($cvc) || empty($package)) {
    die("All fields are required.");
}

// Get plan_id from pricing_plans table
$stmt = $conn->prepare("SELECT id FROM pricing_plans WHERE name = ?");
$stmt->bind_param("s", $package);
$stmt->execute();
$result = $stmt->get_result();
$plan = $result->fetch_assoc();
$stmt->close();

if (!$plan) {
    die("Invalid package selected.");
}
$plan_id = (int)$plan['id'];

// Placeholder for payment_token (use a payment gateway like Stripe in production)
$payment_token = hash('sha256', $card_number . $expiry_date . $cvc); // Temporary, insecure placeholder

// Insert into subscriptions table
$stmt = $conn->prepare("INSERT INTO subscriptions (users_id, plan_id, payment_token, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
$stmt->bind_param("iis", $users_id, $plan_id, $payment_token);

if ($stmt->execute()) {
    // Success response with popup and fallback redirect
    echo "<script>
            window.onload = function(){
                var popup = document.createElement('div');
                popup.style.position = 'fixed';
                popup.style.top = '50%';
                popup.style.left = '50%';
                popup.style.transform = 'translate(-50%, -50%)';
                popup.style.backgroundColor = '#fff';
                popup.style.border = '2px solid #4CAF50';
                popup.style.boxShadow = '0 0 10px rgba(0,0,0,0.3)';
                popup.style.padding = '30px';
                popup.style.zIndex = '1000';
                popup.style.textAlign = 'center';
                popup.style.borderRadius = '10px';
                popup.style.width = '300px';

                popup.innerHTML = `
                    <h2 style='color: #4CAF50;'>Subscription Successful!</h2>
                    <p>Thank you for subscribing to the <strong>" . htmlspecialchars($package) . "</strong> package.</p>
                    <button id='okButton' style='margin-top: 20px; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;'>OK</button>
                `;

                document.body.appendChild(popup);

                document.getElementById('okButton').addEventListener('click', function() {
                    window.location.href = 'design.php';
                });
            };
          </script>
          <noscript>
            <meta http-equiv='refresh' content='2;url=design.php'>
            <p>Subscription successful! Redirecting to design page...</p>
          </noscript>";
} else {
    error_log("Subscription error: " . $stmt->error, 3, "errors.log");
    echo "Error: Unable to process your subscription. Please try again.";
}

// Close statement and connection
$stmt->close();
$conn->close();
