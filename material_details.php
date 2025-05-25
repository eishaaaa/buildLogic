<?php
// Include database connection
include 'db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header('location:login.php');
    exit;
}

// Check if material ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger text-center'>Invalid material ID.</div>";
    exit;
}

$material_id = (int)$_GET['id'];

// Fetch material details
try {
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        echo "<div class='alert alert-danger text-center'>Material not found.</div>";
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger text-center'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// Handle adding to cart
if (isset($_POST['add_to_cart'])) {
    $quantity = (int)$_POST['quantity'];
    $email = $_SESSION['email'];

    try {
        // Re-fetch material to ensure latest stock
        $stmt = $pdo->prepare("SELECT quantity FROM materials WHERE id = ?");
        $stmt->execute([$material_id]);
        $current_material = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_material['quantity'] >= $quantity && $quantity > 0) {
            // Check if item already in cart
            $stmt = $pdo->prepare("SELECT * FROM cart WHERE material_id = ? AND email = ?");
            $stmt->execute([$material_id, $email]);

            if ($stmt->rowCount() > 0) {
                // Update quantity in cart
                $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE material_id = ? AND email = ?");
                $stmt->execute([$quantity, $material_id, $email]);
            } else {
                // Add new item to cart
                $stmt = $pdo->prepare("INSERT INTO cart (email, material_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$email, $material_id, $quantity]);
            }

            // Update material quantity
            $stmt = $pdo->prepare("UPDATE materials SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $material_id]);
            echo "<script>alert('Added to cart!');</script>";
        } else {
            echo "<script>alert('Error: Requested quantity exceeds available stock!');</script>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Handle adding to wishlist
if (isset($_POST['add_to_wishlist'])) {
    $email = $_SESSION['email'];
    try {
        // Check if item already in wishlist
        $stmt = $pdo->prepare("SELECT * FROM wishlist WHERE material_id = ? AND email = ?");
        $stmt->execute([$material_id, $email]);
        if ($stmt->rowCount() == 0) {
            // Add to wishlist
            $stmt = $pdo->prepare("INSERT INTO wishlist (email, material_id) VALUES (?, ?)");
            $stmt->execute([$email, $material_id]);
            echo "<script>alert('Added to wishlist!');</script>";
        } else {
            echo "<script>alert('Item already in wishlist!');</script>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($material['name']); ?> - BrickLogic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; }
        .material-image { max-width: 100%; height: 400px; object-fit: cover; border-radius: 10px; }
        .material-details { padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .material-card .btn-add-to-cart,
        .material-details .btn-add-to-cart {
            background-color: #28a745 !important;
            color: #fff !important;
            border-radius: 5px;
            padding: 10px 20px;
            border: none !important;
        }
        .material-card .btn-add-to-cart:hover,
        .material-details .btn-add-to-cart:hover { background-color: #218838 !important; }
        .material-card .btn-add-to-wishlist,
        .material-details .btn-add-to-wishlist {
            background-color: #007bff !important;
            color: #fff !important;
            border-radius: 5px;
            padding: 10px 20px;
            border: none !important;
        }
        .material-card .btn-add-to-wishlist:hover,
        .material-details .btn-add-to-wishlist:hover { background-color: #0056b3 !important; }
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: #28a745;
            color: #fff;
            border-radius: 50%;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            margin-right: 10px;
        }
        .btn-back:hover { background-color: #218838; }
        .quantity-input { width: 100px; }
        .out-of-stock { color: #dc3545; font-weight: 600; }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            position: relative;
        }
        .location-search { width: 200px; }
        .general-search { width: 400px; margin: 0 auto; }
        .top-icons {
            position: absolute;
            top: 10px;
            right: 20px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        }
        .top-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: #007bff;
            color: #fff;
            border-radius: 50%;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s;
        }
        .top-icons a:hover { background-color: #0056b3; }
        .search-container { display: flex; align-items: center; }
    </style>
</head>
<body>
    <?php
    session_start();
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

    <div class="container mt-4">
        <h2 class="text-center mb-4 text-primary"><?php echo htmlspecialchars($material['name']); ?></h2>
        <div class="row">
            <div class="col-md-6">
                <img src="uploaded_img/<?php echo htmlspecialchars($material['image']); ?>" alt="<?php echo htmlspecialchars($material['name']); ?>" class="material-image">
            </div>
            <div class="col-md-6">
                <div class="material-details">
                    <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description']); ?></p>
                    <p><strong>Price:</strong> $<?php echo number_format($material['price'], 2); ?></p>
                    <?php if ($material['quantity'] <= 0): ?>
                        <p><span class="out-of-stock">Out of Stock</span></p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                            <div class="form-group">
                                <label for="quantity">Quantity:</label>
                                <input type="number" name="quantity" id="quantity" class="form-control quantity-input" min="1" value="1" required>
                            </div>
                            <button type="submit" name="add_to_cart" class="btn btn-add-to-cart mr-2" style="background-color: #28a745; color: #fff; border: none;">Add to Cart</button>
                            <button type="submit" name="add_to_wishlist" class="btn btn-add-to-wishlist" style="background-color: #007bff; color: #fff; border: none;">Add to Wishlist</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>