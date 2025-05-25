<?php
// Set session configurations before starting session
ini_set('session.cookie_httponly', 1);

// Include database connection
include 'db_connect.php';

// Start session
session_start();
session_regenerate_id(true); // Prevent session fixation

// Check if user is logged in
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}

$email = $_SESSION['email'];

// Fetch users_id from users table
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $users_id = $user['id'];
} catch (PDOException $e) {
    error_log('Fetch User ID Error: ' . $e->getMessage());
    die('<div class="alert alert-danger">An error occurred. Please try again later.</div>');
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle order placement
if (isset($_POST['order']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $number = filter_var($_POST['number'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $method = filter_var($_POST['method'], FILTER_SANITIZE_STRING);
    $address = 'flat no. ' . filter_var($_POST['flat'], FILTER_SANITIZE_STRING) . ', ' .
               filter_var($_POST['street'], FILTER_SANITIZE_STRING) . ', ' .
               filter_var($_POST['city'], FILTER_SANITIZE_STRING) . ', ' .
               filter_var($_POST['state'], FILTER_SANITIZE_STRING) . ', ' .
               filter_var($_POST['country'], FILTER_SANITIZE_STRING) . ' - ' .
               filter_var($_POST['pin_code'], FILTER_SANITIZE_STRING);
    $total_products = filter_var($_POST['total_products'], FILTER_SANITIZE_STRING);
    $total_price = filter_var($_POST['total_price'], FILTER_VALIDATE_FLOAT);
    $promo_code = filter_var($_POST['promo_code'], FILTER_SANITIZE_STRING);

    try {
        $check_cart = $pdo->prepare("SELECT * FROM cart WHERE users_id = ?");
        $check_cart->execute([$users_id]);

        if ($check_cart->rowCount() > 0) {
            // Insert order
            $insert_order = $pdo->prepare("
                INSERT INTO orders (users_id, total_price, name, phone, address, promo_code, payment_method, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_order->execute([$users_id, $total_price, $name, $number, $address, $promo_code, $method, 'pending']);

            $order_id = $pdo->lastInsertId();
            $select_cart = $pdo->prepare("SELECT c.*, m.price FROM cart c JOIN materials m ON c.material_id = m.id WHERE c.users_id = ?");
            $select_cart->execute([$users_id]);
            while ($cart_item = $select_cart->fetch(PDO::FETCH_ASSOC)) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, material_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $cart_item['material_id'], $cart_item['quantity'], $cart_item['price']]);
            }

            // Clear cart
            $delete_cart = $pdo->prepare("DELETE FROM cart WHERE users_id = ?");
            $delete_cart->execute([$users_id]);

            $message[] = 'Order placed successfully!';
        } else {
            $message[] = 'Your cart is empty';
        }
    } catch (PDOException $e) {
        error_log('Place Order Error: ' . $e->getMessage());
        echo "<div class='alert alert-danger'>Error: An error occurred while placing the order.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BrickLogic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; }
        .checkout-section { margin-top: 40px; }
        .checkout-header { background-color: #007bff; color: #fff; padding: 15px; border-radius: 10px 10px 0 0; font-size: 1.25rem; }
        .checkout-body { background-color: #fff; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; padding: 30px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); }
        .cart-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee; font-size: 1rem; }
        .cart-item:last-child { border-bottom: none; }
        .cart-total { font-size: 1.5rem; font-weight: 700; color: #28a745; margin-top: 20px; text-align: right; }
        .empty-cart { color: #6c757d; text-align: center; padding: 20px; font-size: 1.1rem; }
        .form-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { font-weight: 600; color: #333; margin-bottom: 8px; font-size: 0.95rem; }
        .form-control { border-radius: 6px; border: 1px solid #ced4da; padding: 10px; font-size: 0.95rem; transition: border-color 0.2s; }
        .form-control:focus { border-color: #28a745; box-shadow: 0 0 5px rgba(40, 167, 69, 0.3); }
        .btn-submit { background-color: #28a745 !important; color: #fff !important; border-radius: 6px; padding: 12px 30px; border: none !important; font-size: 1rem; font-weight: 600; transition: background-color 0.2s; }
        .btn-submit:hover { background-color: #218838 !important; }
        .btn-submit:disabled { background-color: #6c757d !important; cursor: not-allowed; }
        .alert { margin: 20px auto; max-width: 600px; }
        .btn-back { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: #28a745; color: #fff; border-radius: 50%; text-decoration: none; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); margin-right: 10px; }
        .btn-back:hover { background-color: #218838; }
        .top-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; background-color: #fff; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); margin-bottom: 20px; position: relative; }
        .location-search { width: 200px; }
        .general-search { width: 400px; margin: 0 auto; }
        .top-icons { position: absolute; top: 10px; right: 20px; display: flex; gap: 15px; z-index: 1000; }
        .top-icons a { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: #007bff; color: #fff; border-radius: 50%; text-decoration: none; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); transition: background-color 0.3s; }
        .top-icons a:hover { background-color: #0056b3; }
        .search-container { display: flex; align-items: center; }
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        @media (max-width: 767px) { .form-group { margin-bottom: 1.2rem; } .form-control { font-size: 0.9rem; padding: 8px; } .btn-submit { width: 100%; padding: 10px; } .col-md-6 { margin-bottom: 10px; } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="top-bar">
        <div class="search-container">
            <a href="cart.php" class="btn-back" title="Back to Cart">
                <i class="fas fa-arrow-left"></i>
            </a>
            <form class="location-search" method="GET" action="material_shop.php">
                <select name="location" class="form-control" onchange="this.form.submit()">
                    <option value="">Select City</option>
                    <option value="Lahore" <?php echo isset($_GET['location']) && $_GET['location'] == 'Lahore' ? 'selected' : ''; ?>>Lahore</option>
                    <option value="Karachi" <?php echo isset($_GET['location']) && $_GET['location'] == 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                    <option value="Islamabad" <?php echo isset($_GET['location']) && $_GET['location'] == 'Islamabad' ? 'selected' : ''; ?>>Islamabad</option>
                    <option value="Rawalpindi" <?php echo isset($_GET['location']) && $_GET['location'] == 'Rawalpindi' ? 'selected' : ''; ?>>Rawalpindi</option>
                    <option value="Faisalabad" <?php echo isset($_GET['location']) && $_GET['location'] == 'Faisalabad' ? 'selected' : ''; ?>>Faisalabad</option>
                </select>
            </form>
        </div>
        <form class="general-search" method="GET" action="material_shop.php">
            <input type="text" name="search" class="form-control" placeholder="Search materials..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
        </form>
        <div class="top-icons">
            <a href="wishlist.php" title="View Wishlist">
                <i class="fas fa-heart"></i>
            </a>
            <a href="cart.php" title="View Cart">
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>
    </div>

    <div class="container checkout-section">
        <div class="checkout-header">Checkout</div>
        <div class="checkout-body">
            <?php
            if (!empty($message)) {
                foreach ($message as $msg) {
                    echo "<div class='alert alert-success'>$msg</div>";
                }
            }
            ?>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <h4 class="mb-3">Your Orders</h4>
                <?php
                $grand_total = 0;
                $cart_items = [];
                try {
                    $select_cart = $pdo->prepare("SELECT c.*, m.name, m.price FROM cart c JOIN materials m ON c.material_id = m.id WHERE c.users_id = ?");
                    $select_cart->execute([$users_id]);
                    if ($select_cart->rowCount() > 0) {
                        while ($fetch_cart = $select_cart->fetch(PDO::FETCH_ASSOC)) {
                            $cart_items[] = $fetch_cart['name'] . ' ($' . $fetch_cart['price'] . ' x ' . $fetch_cart['quantity'] . ') - ';
                            $total_products = implode($cart_items);
                            $grand_total += ($fetch_cart['price'] * $fetch_cart['quantity']);
                ?>
                <div class="cart-item">
                    <span><?= htmlspecialchars($fetch_cart['name']); ?> (x<?= $fetch_cart['quantity']; ?>)</span>
                    <span>$<?= number_format($fetch_cart['price'] * $fetch_cart['quantity'], 2); ?></span>
                </div>
                <?php
                        }
                    } else {
                        echo '<div class="empty-cart">Your cart is empty!</div>';
                    }
                } catch (PDOException $e) {
                    error_log('Fetch Cart Error: ' . $e->getMessage());
                    echo "<div class='alert alert-danger'>Error: An error occurred while fetching cart items.</div>";
                }
                ?>
                <input type="hidden" name="total_products" value="<?= htmlspecialchars($total_products); ?>">
                <input type="hidden" name="total_price" value="<?= $grand_total; ?>">
                <div class="cart-total">Grand Total: $<?= number_format($grand_total, 2); ?></div>

                <?php if ($select_cart->rowCount() > 0) : ?>
                    <div class="form-section">
                        <h4 class="mt-2 mb-4">Place Your Order</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Your Name</label>
                                    <input type="text" name="name" id="name" placeholder="Enter your name" class="form-control" maxlength="20" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="number">Phone Number</label>
                                    <input type="text" name="number" id="number" placeholder="Enter your phone number" class="form-control" maxlength="10" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Your Email</label>
                                    <input type="email" name="email" id="email" placeholder="Enter your email" class="form-control" maxlength="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="promo_code">Promo Code</label>
                                    <input type="text" name="promo_code" id="promo_code" placeholder="Enter promo code" class="form-control" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="method">Payment Method</label>
                                    <select name="method" id="method" class="form-control" required>
                                        <option value="cash on delivery">Cash on Delivery</option>
                                        <option value="credit card">Credit Card</option>
                                        <option value="jazzcash">JazzCash</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="flat">Flat/Apartment</label>
                                    <input type="text" name="flat" id="flat" placeholder="e.g., Flat 12A, Building XYZ" class="form-control" maxlength="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="street">Street Address</label>
                                    <input type="text" name="street" id="street" placeholder="e.g., 123 Main Street" class="form-control" maxlength="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" name="city" id="city" placeholder="e.g., Lahore" class="form-control" maxlength="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="state">State</label>
                                    <input type="text" name="state" id="state" placeholder="e.g., Punjab" class="form-control" maxlength="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" name="country" id="country" placeholder="e.g., Pakistan" class="form-control" maxlength="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pin_code">Pin Code</label>
                                    <input type="text" name="pin_code" id="pin_code" placeholder="e.g., 123456" class="form-control" maxlength="6" pattern="[0-9]{6}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="order" class="btn btn-submit <?= ($grand_total > 0) ? '' : 'disabled'; ?>" <?= ($grand_total > 0) ? '' : 'disabled'; ?>>Place Order</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>