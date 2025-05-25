<?php
session_start();

// Ensure session is started properly
if (session_status() !== PHP_SESSION_ACTIVE) {
    die("Session failed to start.");
}

if (isset($_POST['package'])) {
    $package = strtolower($_POST['package']);
    $_SESSION['package'] = $package;
} elseif (isset($_SESSION['package'])) {
    $package = $_SESSION['package'];
} else {
    $package = "unknown";
}

// Generate CSRF token for Pro and Premium plans
if ($package !== "free") {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Free plan subscription
if ($package === "free") {
    if (!isset($_SESSION['user_id'])) {
        die("User not logged in.");
    }
    $users_id = (int)$_SESSION['user_id'];

    // Database connection
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "web";

    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        die("Connection failed. Please try again later.");
    }

    // Check if user has 'customer' role
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $users_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || $user['role'] !== 'customer') {
        $conn->close();
        die("Only users with the 'customer' role can subscribe.");
    }

    // Get plan_id for Free plan
    $stmt = $conn->prepare("SELECT id FROM pricing_plans WHERE name = ?");
    $plan_name = "Free";
    $stmt->bind_param("s", $plan_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();
    $stmt->close();

    if ($plan) {
        $plan_id = (int)$plan['id'];
        $payment_token = 'free_plan_' . uniqid();

        // Insert into subscriptions table
        $stmt = $conn->prepare("INSERT INTO subscriptions (users_id, plan_id, payment_token, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
        $stmt->bind_param("iis", $users_id, $plan_id, $payment_token);
        if ($stmt->execute()) {
            // Redirect to design.php with a success message
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
                            <h2 style='color: #4CAF50;'>Free Plan Selected!</h2>
                            <p>You can create up to 3 proposal plans for free.</p>
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
            $stmt->close();
            $conn->close();
            exit;
        } else {
            error_log("Subscription error: " . $stmt->error, 3, "errors.log");
            $stmt->close();
            $conn->close();
            die("Error: Unable to process free subscription.");
        }
    } else {
        $conn->close();
        die("Invalid package: Free plan not found.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subscribe</title>
    <link rel="stylesheet" href="css/style.css"> <!-- Ensure this file exists or remove if unnecessary -->
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
    <title>Subscribe to <?php echo ucfirst($package); ?> Package</title>
</head>

<body>

    <div class="container">
        <h1>Subscribe to <?php echo ucfirst($package); ?> Package</h1>

        <div class="price-box">
            <?php if ($package === "pro"): ?>
                <strong>Amount:</strong> Rs. 750 / Month
            <?php elseif ($package === "premium"): ?>
                <strong>Amount:</strong> Rs. 1000 / Month
            <?php else: ?>
                <strong>Invalid Package</strong>
            <?php endif; ?>
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

            <!-- Send selected package hidden -->
            <input type="hidden" name="package" value="<?php echo htmlspecialchars($package); ?>">
            <!-- CSRF token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <button type="submit" class="subscribe-btn">Subscribe</button>
        </form>
    </div>

</body>

</html>