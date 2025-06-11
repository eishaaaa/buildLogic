
<?php
session_start();
require_once 'db_connect.php'; // PDO database connection file

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF validation failed: POST token=" . ($_POST['csrf_token'] ?? 'none') . ", SESSION token=" . ($_SESSION['csrf_token'] ?? 'none'), 3, "errors.log");
    $_SESSION['error'] = "Invalid CSRF token. Please try again.";
    header("Location: pricing.php");
    exit;
}
unset($_SESSION['csrf_token']);

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id === 0) {
    $_SESSION['error'] = "Please log in to subscribe.";
    header("Location: login.php");
    exit;
}

// Check if user has 'customer' role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'customer') {
    $_SESSION['error'] = "Only users with the 'customer' role can subscribe.";
    header("Location: pricing .php");
    exit;
}

// Validate and sanitize form data
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$card_number = filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_STRING);
$expiry_date = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_STRING);
$cvc = filter_input(INPUT_POST, 'cvc', FILTER_SANITIZE_STRING);
$plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_SANITIZE_NUMBER_INT);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    header("Location: pricing.php");
    exit;
}
if (empty($phone) || empty($card_number) || empty($expiry_date) || empty($cvc) || empty($plan_id)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: pricing.php");
    exit;
}

// Verify plan_id exists
$stmt = $pdo->prepare("SELECT id, name FROM pricing_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) {
    $_SESSION['error'] = "Invalid plan selected.";
    header("Location: pricing.php");
    exit;
}
$package = $plan['name'];

// Placeholder payment processing (replace with Stripe/PayPal in production)
$payment_token = hash('sha256', $card_number . $expiry_date . $cvc); // Temporary placeholder
$payment_status = true; // Simulate successful payment

if ($payment_status) {
    try {
        $pdo->beginTransaction();

        // Deactivate existing subscriptions
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = 'inactive' WHERE users_id = ?");
        $stmt->execute([$user_id]);

        // Insert new subscription
        $stmt = $pdo->prepare("INSERT INTO subscriptions (users_id, plan_id, payment_token, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
        $stmt->execute([$user_id, $plan_id, $payment_token]);

        // Reset design count
        $stmt = $pdo->prepare("UPDATE users SET design_count = 0 WHERE id = ?");
        $stmt->execute([$user_id]);

        $pdo->commit();

        $_SESSION['success'] = "Subscribed to the $package plan successfully!";
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
                        <p>Thank you for subscribing to the <strong>" . htmlspecialchars($package) . "</strong> plan.</p>
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
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Subscription error: " . $e->getMessage(), 3, "errors.log");
        $_SESSION['error'] = "Unable to process your subscription. Please try again.";
        header("Location: pricing.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Payment processing failed.";
    header("Location: pricing.php");
    exit;
}
?>
