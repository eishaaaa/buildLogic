<?php
// Set session configurations before starting session
ini_set('session.cookie_httponly', 1);

// Include database connection
require'db_connect.php';

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
        // session_unset();
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

// Handle adding to cart
if (isset($_POST['add_to_cart']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $material_id = filter_var($_POST['material_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);

    if ($material_id && $quantity > 0) {
        try {
            // Check material stock
            $stmt = $pdo->prepare("SELECT quantity FROM materials WHERE id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($material) {
                if ($material['quantity'] >= $quantity) {
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
                    echo "<script>alert('Added to cart!');</script>";
                } else {
                    echo "<script>alert('Not enough stock available!');</script>";
                }
            } else {
                echo "<script>alert('Material not found!');</script>";
            }
        } catch (PDOException $e) {
            error_log('Add to Cart Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error: An error occurred while adding to cart.</div>";
        }
    }
}

// Handle adding to wishlist
if (isset($_POST['add_to_wishlist']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $material_id = filter_var($_POST['material_id'], FILTER_VALIDATE_INT);
    if ($material_id) {
        try {
            // Check if item already in wishlist
            $stmt = $pdo->prepare("SELECT * FROM wishlist WHERE material_id = ? AND users_id = ?");
            $stmt->execute([$material_id, $users_id]);
            if ($stmt->rowCount() == 0) {
                // Add to wishlist
                $stmt = $pdo->prepare("INSERT INTO wishlist (users_id, material_id) VALUES (?, ?)");
                $stmt->execute([$users_id, $material_id]);
                echo "<script>alert('Added to wishlist!');</script>";
            } else {
                echo "<script>alert('Item already in wishlist!');</script>";
            }
        } catch (PDOException $e) {
            error_log('Add to Wishlist Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error: An error occurred while adding to wishlist.</div>";
        }
    }
}

// Fetch distinct cities from materials table for dropdown
try {
    $cities_query = $pdo->query("SELECT DISTINCT city FROM materials ORDER BY city");
    $cities = $cities_query->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Fetch Cities Error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Error fetching cities. Please try again later.</div>";
    exit;
}

// Fetch materials from database, filter by city and search term
try {
    $query = "SELECT m.* FROM materials m WHERE 1=1";
    $params = [];
    if (isset($_GET['location']) && !empty($_GET['location'])) {
        $query .= " AND m.city = ?";
        $params[] = $_GET['location'];
    }
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $query .= " AND m.name LIKE ?";
        $params[] = '%' . $_GET['search'] . '%';
    }
    $materials_query = $pdo->prepare($query);
    $materials_query->execute($params);
} catch (PDOException $e) {
    error_log('Fetch Materials Error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Error fetching materials. Please try again later.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Shop - BrickLogic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fa;
        }

        .material-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            transition: box-shadow 0.3s;
        }

        .material-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .material-image {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
        }

        .placeholder-image {
            max-width: 100%;
            height: 200px;
            background-color: #e0e0e0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 14px;
        }

        .out-of-stock {
            color: #dc3545;
            font-weight: 600;
        }

        .material-card .btn-add-to-cart,
        .material-details .btn-add-to-cart {
            background-color: #28a745 !important;
            color: #fff !important;
            border-radius: 5px;
            padding: 8px 20px;
            border: none !important;
        }

        .material-card .btn-add-to-cart:hover,
        .material-details .btn-add-to-cart:hover {
            background-color: #218838 !important;
        }

        .material-card .btn-add-to-wishlist,
        .material-details .btn-add-to-wishlist {
            background-color: #007bff !important;
            color: #fff !important;
            border-radius: 5px;
            padding: 8px 20px;
            border: none !important;
        }

        .material-card .btn-add-to-wishlist:hover,
        .material-details .btn-add-to-wishlist:hover {
            background-color: #0056b3 !important;
        }

        .quantity-input {
            width: 80px;
            display: inline-block;
        }

        .alert {
            margin: 20px auto;
            max-width: 600px;
        }

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

        .top-icons a:hover {
            background-color: #0056b3;
        }

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

        .location-search {
            width: 200px;
        }

        .general-search {
            width: 400px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="top-bar">
        <form class="location-search" method="GET" action="">
            <select name="location" class="form-control" onchange="this.form.submit()">
                <option value="">All Cities</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?= htmlspecialchars($city); ?>" <?= isset($_GET['location']) && $_GET['location'] == $city ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($city); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <form class="general-search" method="GET" action="">
            <?php if (isset($_GET['location']) && !empty($_GET['location'])): ?>
                <input type="hidden" name="location" value="<?= htmlspecialchars($_GET['location']); ?>">
            <?php endif; ?>
            <input type="text" name="search" class="form-control" placeholder="Search materials..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
        </form>
        <div class="top-icons">
            <a href="wishlist.php" title="View Wishlist">
                <i class="fas fa-heart"></i>
            </a>
            <a href="cart.php" title="View Cart">
                <i class="fas fa-shopping-cart"></i>
            </a>
            <a href="supplier_rating.php" title="View Ratings">
                <i class="fa-solid fa-star"></i>
            </a>
            <a href="track_order.php" title="Track Order">
                <i class="fa-solid fa-truck-fast"></i>
            </a>
        </div>
    </div>

    <div class="container mt-4">
        <h2 class="text-center mb-4 text-primary">Available Materials</h2>
        <div class="row">
            <?php if ($materials_query->rowCount() > 0): ?>
                <?php while ($material = $materials_query->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="col-md-4">
                        <div class="material-card">
                            <a href="material_details.php?id=<?php echo $material['id']; ?>">
                             <?php
// Use relative path for file_exists, assuming Uploads/ is in web root
$image_path = $material['image'] ? $_SERVER['DOCUMENT_ROOT'] . $material['image'] : '';
if (!empty($material['image']) && file_exists($image_path)):
    // Use URL-friendly path for src
    $image_url = htmlspecialchars($material['image']);
?>
    <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($material['name']); ?>" class="material-image">
<?php else: ?>
    <div class="placeholder-image">No Image Available</div>
    <?php
    // Log missing image for debugging
    error_log("Image missing for material ID {$material['id']}: {$material['image']}");
    ?>
<?php endif; ?>
                            </a>
                            <h4 class="mt-3"><?php echo htmlspecialchars($material['name']); ?></h4>
                            <p><strong class="text-success">Price:</strong> $<?php echo number_format($material['price'], 2); ?></p>
                            <p><strong>City:</strong> <?php echo htmlspecialchars($material['city']); ?></p>
                            <?php if ($material['quantity'] <= 0): ?>
                                <p><span class="out-of-stock">Out of Stock</span></p>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                    <div class="form-group d-inline-block mr-2">
                                        <label for="quantity_<?php echo $material['id']; ?>" class="sr-only">Quantity</label>
                                        <input type="number" name="quantity" id="quantity_<?php echo $material['id']; ?>" class="form-control quantity-input" min="1" value="1" required>
                                    </div>
                                    <button type="submit" name="add_to_cart" class="btn btn-add-to-cart">Add to Cart</button>
                                    <button type="submit" name="add_to_wishlist" class="btn btn-add-to-wishlist ml-2">Add to Wishlist</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-warning text-center">No materials available.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
