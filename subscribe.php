<?php
session_start();
require_once 'db_connect.php'; // PDO database connection file

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    die("Session failed to start.");
}

if (isset($_POST['plan_id'])) {
    $plan_id = (int)$_POST['plan_id'];
    $_SESSION['plan_id'] = $plan_id;
} elseif (isset($_SESSION['plan_id'])) {
    $plan_id = (int)$_SESSION['plan_id'];
} else {
    die("No plan selected.");
}

// Fetch plan details
$stmt = $pdo->prepare("SELECT id, name, monthly_price, design_limit FROM pricing_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) {
    die("Invalid plan selected.");
}
$package = strtolower($plan['name']);

// Generate CSRF token for Pro and Premium plans
if ($package !== 'basic') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Basic plan subscription
if ($package === 'basic') {
    if (!isset($_SESSION['user_id'])) {
        die("User not logged in.");
    }
    $user_id = (int)$_SESSION['user_id'];

    // Check if user has 'customer' role
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'customer') {
        die("Only users with the 'customer' role can subscribe.");
    }

    try {
        $pdo->beginTransaction();

        // Deactivate existing subscriptions
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = 'inactive' WHERE users_id = ?");
        $stmt->execute([$user_id]);

        // Insert new subscription
        $payment_token = 'basic_plan_' . uniqid();
        $stmt = $pdo->prepare("INSERT INTO subscriptions (users_id, plan_id, payment_token, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
        $stmt->execute([$user_id, $plan_id, $payment_token]);

        // Reset design count
        $stmt = $pdo->prepare("UPDATE users SET design_count = 0 WHERE id = ?");
        $stmt->execute([$user_id]);

        $pdo->commit();

        // Redirect to design.php with success message
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
                        <h2 style='color: #4CAF50;'>Basic Plan Selected!</h2>
                        <p>You can create up to {$plan['design_limit']} designs for free.</p>
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
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Subscription error: " . $e->getMessage(), 3, "errors.log");
        die("Error: Unable to process Basic subscription.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subscribe</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f9f1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 50px;
        }

        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 500px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .container h1 {
            margin-bottom: 20px;
            text-align: center;
        }

        .input-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .subscribe-btn {
            width: 100%;
            padding: 12px;
            background-color: #333;
            color: white;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .subscribe-btn:hover {
            background-color: #555;
        }

        .price-box {
            margin-bottom: 20px;
            background: #e0f2e0;
            padding: 15px;
            border-radius: 8px;
        }
    </style>
    <title>Subscribe to <?php echo ucfirst($package); ?> Plan</title>
</head>

<body>
    <div class="container">
        <h1>Subscribe to <?php echo ucfirst($package); ?> Plan</h1>
        <div class="price-box">
            <strong>Amount:</strong> Rs.<?php echo number_format($plan['monthly_price'], 2); ?> / Month
        </div>
        <form action="process_payment.php" method="POST">
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="input-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" required>
            </div>
            <div class="input-group">
                <label>Credit Card Number</label>
                <input type="text" name="card_number" required placeholder="1234 1234 1234 1234">
            </div>
            <div class="input-group">
                <label>Expiry Date (MM/YY)</label>
                <input type="text" name="expiry_date" required placeholder="MM/YY">
            </div>
            <div class="input-group">
                <label>CVC</label>
                <input type="number" name="cvc" required placeholder="123">
            </div>
            <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan_id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="subscribe-btn">Subscribe</button>
        </form>
    </div>
</body>

</html>
