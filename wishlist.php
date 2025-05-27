<?php
// Set session configurations before starting session
ini_set('session.cookie_httponly', 1);

// Include database connection
include 'db_connect.php';

// Start session
session_start();
session_regenerate_id(true); // Prevent session fixation

// Redirect to login if not authenticated or not a customer
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
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
    $wishlist_id = filter_var($_POST['wishlist_id'], FILTER_VALIDATE_INT);
    if ($wishlist_id) {
        try {
            $delete_wishlist_item = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND users_id = ?");
            $delete_wishlist_item->execute([$wishlist_id, $users_id]);
        } catch (PDOException $e) {
            error_log('Delete Wishlist Item Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error: An error occurred while deleting the item.</div>";
        }
    }
}

// Handle delete all items
if (isset($_GET['delete_all']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $delete_wishlist_item = $pdo->prepare("DELETE FROM wishlist WHERE users_id = ?");
        $delete_wishlist_item->execute([$users_id]);
        header('Location: wishlist.php');
        exit;
    } catch (PDOException $e) {
        error_log('Delete All Wishlist Items Error: ' . $e->getMessage());
        echo "<div class='alert alert-danger'>Error: An error occurred while deleting all items.</div>";
    }
}

// Handle add to cart
if (isset($_POST['add_to_cart']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $material_id = filter_var($_POST['material_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['qty'], FILTER_VALIDATE_INT);
    $wishlist_id = filter_var($_POST['wishlist_id'], FILTER_VALIDATE_INT);
    if ($material_id && $quantity > 0 && $wishlist_id) {
        try {
            // Check material stock
            $stmt = $pdo->prepare("SELECT quantity FROM materials WHERE id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($material && $material['quantity'] >= $quantity) {
                // Check if item already in cart
                $stmt = $pdo->prepare("SELECT * FROM cart WHERE material_id = ? AND users_id = ?");
                $stmt->execute([$material_id, $users_id]);

                if ($stmt->rowCount() > 0) {
                    // Update quantity in cart
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE material_id = ? AND users_id = ?");
                    $stmt->execute([$quantity, $material_id, $users_id]);
                } else {
                    // Add new item to cart
                    $stmt = $pdo->prepare("INSERT INTO cart (users_id, material_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$users_id, $material_id, $quantity]);
                }

                // Update material quantity
                $stmt = $pdo->prepare("UPDATE materials SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$quantity, $material_id]);

                // Remove from wishlist
                $delete_wishlist_item = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND users_id = ?");
                $delete_wishlist_item->execute([$wishlist_id, $users_id]);

                echo "<script>alert('Added to cart!');</script>";
            } else {
                echo "<script>alert('Not enough stock available!');</script>";
            }
        } catch (PDOException $e) {
            error_log('Add to Cart Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error: An error occurred while adding to cart.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist - BrickLogic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; }
        .wishlist-section { margin-top: 40px; }
        .wishlist-header { background-color: #007bff; color: #fff; padding: 15px; border-radius: 10px 10px 0 0; font-size: 1.25rem; }
        .wishlist-body { background-color: #fff; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; padding: 20px; }
        .wishlist-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
        .wishlist-item:last-child { border-bottom: none; }
        .wishlist-total { font-size: 1.5rem; font-weight: 700; color: #28a745; margin-top: 20px; text-align: right; }
        .empty-wishlist { color: #6c757d; text-align: center; padding: 20px; }
        .btn-add-to-cart { background-color: #28a745 !important; color: #fff !important; border-radius: 5px; padding: 5px 10px; border: none !important; }
        .btn-add-to-cart:hover { background-color: #218838 !important; }
        .btn-delete { background-color: #dc3545 !important; color: #fff !important; border-radius: 5px; padding: 5px 10px; border: none !important; }
        .btn-delete:hover { background-color: #c82333 !important; }
        .btn-continue { background-color: #6c757d !important; color: #fff !important; border-radius: 5px; padding: 8px 20px; border: none !important; }
        .btn-continue:hover { background-color: #5a6268 !important; }
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
    <?php include 'header.php'; ?>

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

    <div class="container wishlist-section">
        <div class="wishlist-header">Your Wishlist</div>
        <div class="wishlist-body">
            <?php
            $grand_total = 0;
            try {
                $select_wishlist = $pdo->prepare("SELECT w.*, m.name, m.price, m.image FROM wishlist w JOIN materials m ON w.material_id = m.id WHERE w.users_id = ?");
                $select_wishlist->execute([$users_id]);
                if ($select_wishlist->rowCount() > 0) {
                    while ($fetch_wishlist = $select_wishlist->fetch(PDO::FETCH_ASSOC)) {
                        $grand_total += $fetch_wishlist['price'];
            ?>
            <form action="" method="post" class="wishlist-item">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="wishlist_id" value="<?= $fetch_wishlist['id']; ?>">
                <input type="hidden" name="material_id" value="<?= $fetch_wishlist['material_id']; ?>">
                <div>
<img src="<?= htmlspecialchars($fetch_wishlist['image']); ?>" alt="<?= htmlspecialchars($fetch_wishlist['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">                    <span><?= htmlspecialchars($fetch_wishlist['name']); ?></span>
                </div>
                <div>
                    <span>$<?= number_format($fetch_wishlist['price'], 2); ?></span>
                    <input type="number" name="qty" class="form-control qty-input" min="1" value="1">
                    <button type="submit" class="btn btn-add-to-cart" name="add_to_cart"><i class="fas fa-cart-plus"></i></button>
                    <button type="submit" class="btn btn-delete" name="delete" onclick="return confirm('Delete this from wishlist?');"><i class="fas fa-trash"></i></button>
                </div>
            </form>
            <?php
                    }
                } else {
                    echo '<div class="empty-wishlist">Your wishlist is empty</div>';
                }
            } catch (PDOException $e) {
                error_log('Fetch Wishlist Error: ' . $e->getMessage());
                echo "<div class='alert alert-danger'>Error: An error occurred while fetching wishlist items.</div>";
            }
            ?>
            <div class="wishlist-total">Total: $<?= number_format($grand_total, 2); ?></div>
            <div class="mt-3 text-right">
                <a href="selectmaterial.php" class="btn btn-continue">Continue Shopping</a>
                <a href="wishlist.php?delete_all&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']); ?>" class="btn btn-delete <?= ($grand_total > 0) ? '' : 'btn-disabled'; ?>" onclick="return confirm('Delete all from wishlist?');">Delete All</a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
