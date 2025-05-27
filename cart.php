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

// Handle delete item
if (isset($_POST['delete']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $cart_id = filter_var($_POST['cart_id'], FILTER_VALIDATE_INT);
    if ($cart_id) {
        try {
            $delete_cart_item = $pdo->prepare("DELETE FROM cart WHERE id = ? AND users_id = ?");
            $delete_cart_item->execute([$cart_id, $users_id]);
        } catch (PDOException $e) {
            error_log('Delete Cart Item Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error: An error occurred while deleting the item.</div>";
        }
    }
}

// Handle delete all items
if (isset($_GET['delete_all']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $delete_cart_item = $pdo->prepare("DELETE FROM cart WHERE users_id = ?");
        $delete_cart_item->execute([$users_id]);
        header('Location: cart.php');
        exit;
    } catch (PDOException $e) {
        error_log('Delete All Cart Items Error: ' . $e->getMessage());
        echo "<div class='alert alert-danger'>Error: An error occurred while deleting all items.</div>";
    }
}

// Handle quantity update
if (isset($_POST['update_qty']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $cart_id = filter_var($_POST['cart_id'], FILTER_VALIDATE_INT);
    $qty = filter_var($_POST['qty'], FILTER_VALIDATE_INT);
    if ($cart_id && $qty > 0) {
        try {
            $update_qty = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND users_id = ?");
            $update_qty->execute([$qty, $cart_id, $users_id]);
            $message[] = 'Cart quantity updated';
        } catch (PDOException $e) {
            error_log('Update Cart Quantity Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error: An error occurred while updating quantity.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - BrickLogic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; }
        .cart-section { margin-top: 40px; }
        .cart-header { background-color: #007bff; color: #fff; padding: 15px; border-radius: 10px 10px 0 0; font-size: 1.25rem; }
        .cart-body { background-color: #fff; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; padding: 20px; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
        .cart-item:last-child { border-bottom: none; }
        .cart-total { font-size: 1.5rem; font-weight: 700; color: #28a745; margin-top: 20px; text-align: right; }
        .empty-cart { color: #6c757d; text-align: center; padding: 20px; }
        .btn-update { background-color: #007bff !important; color: #fff !important; border-radius: 5px; padding: 5px 10px; border: none !important; }
        .btn-update:hover { background-color: #0056b3 !important; }
        .btn-edit { background-color: #007bff !important; color: #fff !important; border-radius: 5px; padding: 5px 10px; border: none !important; }
        .btn-edit:hover { background-color: #0056b3 !important; }
        .btn-delete { background-color: #dc3545 !important; color: #fff !important; border-radius: 5px; padding: 5px 10px; border: none !important; }
        .btn-delete:hover { background-color: #c82333 !important; }
        .btn-continue { background-color: #6c757d !important; color: #fff !important; border-radius: 5px; padding: 8px 20px; border: none !important; }
        .btn-continue:hover { background-color: #5a6268 !important; }
        .btn-checkout { background-color: #28a745 !important; color: #fff !important; border-radius: 5px; padding: 8px 20px; border: none !important; }
        .btn-checkout:hover { background-color: #218838 !important; }
        .btn-disabled { background-color: #6c757d !important; cursor: not-allowed; }
        .qty-input { width: 80px; }
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
    </style>
</head>
<body>
    <?php 
   
    include 'header.php'; ?>

    <div class="top-bar">
        <div class="search-container">
            <a href="selectmaterial.php" class="btn-back" title="Back to Shop">
                <i class="fas fa-arrow-left"></i>
            </a>
            <form class="location-search" method="GET" action="selectmaterial.php">
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
        <form class="general-search" method="GET" action="selectmaterial.php">
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

    <div class="container cart-section">
        <div class="cart-header">Shopping Cart</div>
        <div class="cart-body">
            <?php
            if (!empty($message)) {
                foreach ($message as $msg) {
                    echo "<div class='alert alert-success'>$msg</div>";
                }
            }
            $grand_total = 0;
            try {
                $select_cart = $pdo->prepare("SELECT c.*, m.name, m.price, m.image FROM cart c JOIN materials m ON c.material_id = m.id WHERE c.users_id = ?");
                $select_cart->execute([$users_id]);
                if ($select_cart->rowCount() > 0) {
                    while ($fetch_cart = $select_cart->fetch(PDO::FETCH_ASSOC)) {
                        $sub_total = $fetch_cart['price'] * $fetch_cart['quantity'];
                        $grand_total += $sub_total;
            ?>
            <form action="" method="post" class="cart-item" data-cart-id="<?= $fetch_cart['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="cart_id" value="<?= $fetch_cart['id']; ?>">
                <div>
<img src="<?= htmlspecialchars($fetch_cart['image']); ?>" alt="<?= htmlspecialchars($fetch_cart['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">                    <span><?= htmlspecialchars($fetch_cart['name']); ?> (x<span class="quantity-display"><?= $fetch_cart['quantity']; ?></span>)</span>
                </div>
                <div>
                    <span class="sub-total">$<?= number_format($sub_total, 2); ?></span>
                    <input type="number" name="qty" class="form-control qty-input" min="1" value="<?= $fetch_cart['quantity']; ?>" readonly>
                    <button type="button" class="btn btn-edit" onclick="enableEdit(this, <?= $fetch_cart['id']; ?>)"><i class="fas fa-edit"></i></button>
                    <button type="submit" class="btn btn-update" name="update_qty" style="display: none;"><i class="fas fa-check"></i></button>
                    <button type="submit" class="btn btn-delete" name="delete" onclick="return confirm('Delete this from cart?');"><i class="fas fa-trash"></i></button>
                </div>
            </form>
            <?php
                    }
                } else {
                    echo '<div class="empty-cart">Your cart is empty</div>';
                }
            } catch (PDOException $e) {
                error_log('Fetch Cart Error: ' . $e->getMessage());
                echo "<div class='alert alert-danger'>Error: An error occurred while fetching cart items.</div>";
            }
            ?>
            <div class="cart-total">Grand Total: $<span id="grand-total"><?= number_format($grand_total, 2); ?></span></div>
            <div class="mt-3 text-right">
                <a href="selectmaterial.php" class="btn btn-continue">Continue Shopping</a>
                <a href="cart.php?delete_all&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']); ?>" class="btn btn-delete <?= ($grand_total > 0) ? '' : 'btn-disabled'; ?>" onclick="return confirm('Delete all from cart?');">Delete All</a>
                <a href="checkout.php" class="btn btn-checkout <?= ($grand_total > 0) ? '' : 'btn-disabled'; ?>">Proceed to Checkout</a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function enableEdit(button, cartId) {
            const form = button.closest('form');
            const qtyInput = form.querySelector('.qty-input');
            const updateBtn = form.querySelector('.btn-update');
            qtyInput.removeAttribute('readonly');
            qtyInput.focus();
            button.style.display = 'none';
            updateBtn.style.display = 'inline-block';
        }

        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('input', function() {
                const form = this.closest('form');
                const quantity = parseInt(this.value) || 1;
                const price = parseFloat(form.querySelector('.sub-total').textContent.replace('$', '')) / parseInt(form.querySelector('.quantity-display').textContent);
                const newSubTotal = (price * quantity).toFixed(2);
                form.querySelector('.sub-total').textContent = `$${newSubTotal}`;
                form.querySelector('.quantity-display').textContent = quantity;

                let grandTotal = 0;
                document.querySelectorAll('.sub-total').forEach(sub => {
                    grandTotal += parseFloat(sub.textContent.replace('$', ''));
                });
                document.getElementById('grand-total').textContent = grandTotal.toFixed(2);
            });
        });

        document.querySelectorAll('.btn-update').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('form');
                const qtyInput = form.querySelector('.qty-input');
                const editBtn = form.querySelector('.btn-edit');
                qtyInput.setAttribute('readonly', 'readonly');
                this.style.display = 'none';
                editBtn.style.display = 'inline-block';
            });
        });
    </script>
</body>
</html>
