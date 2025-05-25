
<?php
session_start();

require 'db_connect.php'; // Ensure connect.php is in C:\xampp\htdocs\Practice\


// Redirect to login if not authenticated or not a customer
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'customer') {
    error_log('Session validation failed: Email=' . ($_SESSION['email'] ?? 'unset') . ', Role=' . ($_SESSION['role'] ?? 'unset'));
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
        error_log('User not found for email: ' . $email);
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $users_id = $user['id'];
    error_log("Session validated: Email=$email, Users_id=$users_id");
} catch (PDOException $e) {
    error_log('Fetch User ID Error: ' . $e->getMessage());
    die('<div class="alert alert-danger">An error occurred. Please try again later.</div>');
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$selected_supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$location_filter = isset($_GET['location']) ? trim($_GET['location']) : '';
error_log("Parameters: order_id=$order_id, supplier_id=$selected_supplier_id, location=$location_filter");

// Fetch delivered orders for the user
$delivered_orders = fetchDeliveredOrders($pdo, $users_id);

// Fetch order details (if order_id is provided)
$order = $order_id ? fetchOrderDetails($pdo, $order_id, $users_id) : null;

// Fetch suppliers associated with the order (or all if no order_id)
$all_suppliers = fetchAllSuppliers($pdo, $order_id, $location_filter, $users_id);

// Fetch available locations for filter
$available_locations = fetchAvailableLocations($pdo, $order_id);

// Fetch supplier details and ratings if a supplier is selected
$supplier = null;
$avg_rating = null;
$ratings = null;
if ($selected_supplier_id && $order && $order['status'] === 'delivered') {
    if (isSupplierValidForOrder($pdo, $order_id, $selected_supplier_id)) {
        $supplier = fetchSupplier($pdo, $selected_supplier_id);
        $avg_rating = fetchAverageRating($pdo, $selected_supplier_id);
        $ratings = fetchSupplierRatings($pdo, $selected_supplier_id);
    } else {
        error_log("Invalid supplier_id=$selected_supplier_id for order_id=$order_id");
        $selected_supplier_id = 0;
    }
}

/**
 * Fetch all delivered orders for the user
 */
function fetchDeliveredOrders($pdo, $users_id)
{
    try {
        $query = "SELECT id, created_at FROM orders 
                  WHERE users_id = :users_id AND status = 'delivered' 
                  ORDER BY created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['users_id' => $users_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Fetch Delivered Orders Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Fetch order details
 */
function fetchOrderDetails($pdo, $order_id, $users_id)
{
    try {
        $query = "SELECT id, status FROM orders WHERE id = :order_id AND users_id = :customer_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id, 'customer_id' => $users_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('Fetch Order Details Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Fetch suppliers associated with the order (or all if no order_id)
 */
function fetchAllSuppliers($pdo, $order_id = 0, $location_filter = '', $users_id = 0)
{
    try {
        $query = "SELECT s.id, s.company_name, s.location, 
                         AVG(sr.rating) as avg_rating, COUNT(sr.id) as rating_count
                  FROM suppliers s
                  LEFT JOIN supplier_ratings sr ON s.id = sr.supplier_id";
        $params = [];
        $whereClauses = [];

        if ($order_id) {
            $query .= " INNER JOIN materials m ON s.id = m.supplier_id
                        INNER JOIN order_items oi ON m.id = oi.material_id";
            $whereClauses[] = "oi.order_id = :order_id";
            $whereClauses[] = "NOT EXISTS (
                                SELECT 1 FROM supplier_ratings sr2 
                                WHERE sr2.supplier_id = s.id 
                                AND sr2.users_id = :users_id 
                                AND sr2.order_id = :order_id
                              )";
            $params['order_id'] = $order_id;
            $params['users_id'] = $users_id;
        }

        if (!empty($location_filter)) {
            $whereClauses[] = "s.location = :location";
            $params['location'] = $location_filter;
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $query .= " GROUP BY s.id, s.company_name, s.location
                    ORDER BY avg_rating DESC, rating_count DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Fetch All Suppliers Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Fetch available locations for filter
 */
function fetchAvailableLocations($pdo, $order_id = 0)
{
    try {
        $query = "SELECT DISTINCT s.location 
                  FROM suppliers s";
        $params = [];

        if ($order_id) {
            $query .= " INNER JOIN materials m ON s.id = m.supplier_id
                        INNER JOIN order_items oi ON m.id = oi.material_id
                        WHERE oi.order_id = :order_id";
            $params['order_id'] = $order_id;
        }

        $query .= " AND s.location IS NOT NULL ORDER BY s.location";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Fetch Available Locations Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Validate if supplier is associated with the order
 */
function isSupplierValidForOrder($pdo, $order_id, $supplier_id)
{
    try {
        $query = "SELECT 1
                  FROM order_items oi
                  INNER JOIN materials m ON oi.material_id = m.id
                  WHERE oi.order_id = :order_id AND m.supplier_id = :supplier_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id, 'supplier_id' => $supplier_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (PDOException $e) {
        error_log('Is Supplier Valid Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch supplier details
 */
function fetchSupplier($pdo, $supplier_id)
{
    try {
        $query = "SELECT company_name, location 
                  FROM suppliers 
                  WHERE id = :supplier_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['supplier_id' => $supplier_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['company_name' => 'Unknown', 'location' => 'Unknown'];
    } catch (PDOException $e) {
        error_log('Fetch Supplier Error: ' . $e->getMessage());
        return ['company_name' => 'Unknown', 'location' => 'Unknown'];
    }
}

/**
 * Fetch average rating for supplier
 */
function fetchAverageRating($pdo, $supplier_id)
{
    try {
        $query = "SELECT AVG(rating) AS avg_rating 
                  FROM supplier_ratings 
                  WHERE supplier_id = :supplier_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['supplier_id' => $supplier_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['avg_rating']) ? round($row['avg_rating'], 1) : 'No ratings yet';
    } catch (PDOException $e) {
        error_log('Fetch Average Rating Error: ' . $e->getMessage());
        return 'No ratings yet';
    }
}

/**
 * Fetch existing ratings for supplier
 */
function fetchSupplierRatings($pdo, $supplier_id)
{
    try {
        $query = "SELECT sr.*, u.name 
                  FROM supplier_ratings sr 
                  JOIN users u ON sr.users_id = u.id 
                  WHERE sr.supplier_id = :supplier_id 
                  ORDER BY sr.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['supplier_id' => $supplier_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Fetch Supplier Ratings Error: ' . $e->getMessage());
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Ratings</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --warning-color: #f8961e;
            --success-color: #4cc9f0;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        h1, h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        h1 {
            font-size: 2rem;
            position: relative;
            padding-bottom: 10px;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--primary-color);
        }

        .filter-section {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            background: white;
            width: 200px;
        }

        select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .orders-section {
            margin-bottom: 30px;
        }

        .orders-list {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .order-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-item:hover {
            background-color: var(--light-color);
        }

        .order-item.selected {
            background-color: #e6f0fa;
            font-weight: 500;
        }

        .rate-suppliers-btn {
            background-color: var(--success-color);
            color: white;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            border: none;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .rate-suppliers-btn:hover {
            background-color: #3aa8d8;
            transform: translateY(-1px);
        }

        .supplier-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            border-radius: var(--border-radius);
        }

        .supplier-name {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .rating-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
        }

        .star-rating {
            font-size: 1.5rem;
            display: inline-flex;
            gap: 5px;
        }

        .star {
            color: #e4e5e9;
            cursor: pointer;
            transition: var(--transition);
        }

        .star.filled, .star:hover, .star:hover~.star {
            color: var(--warning-color);
        }

        .rating-controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            transition: var(--transition);
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            border: none;
        }

        .submit-btn {
            background-color: var(--success-color);
            color: white;
            align-self: flex-end;
        }

        .submit-btn:hover {
            background-color: #3aa8d8;
            transform: translateY(-1px);
        }

        .order-reference {
            text-align: center;
            margin-bottom: 20px;
            color: #6c757d;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            display: none;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .ratings-section, .top-suppliers {
            margin-top: 30px;
        }

        .ratings-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .rating-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }

        .supplier-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
            cursor: pointer;
        }

        .supplier-item:hover {
            background-color: var(--light-color);
        }

        small {
            color: #6c757d;
        }

        .rating-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .rating-value {
            font-weight: bold;
            color: var(--warning-color);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .rating-meta {
                flex-direction: column;
                gap: 5px;
            }
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Supplier Ratings</h1>

        <!-- Delivered Orders Section -->
        <div class="orders-section">
            <h2>Your Delivered Orders</h2>
            <?php if (!empty($delivered_orders)): ?>
                <div class="orders-list">
                    <?php foreach ($delivered_orders as $delivered_order): ?>
                        <div class="order-item <?php echo $delivered_order['id'] == $order_id ? 'selected' : ''; ?>">
                            <span>
                                Order #<?php echo htmlspecialchars($delivered_order['id']); ?> - Placed on
                                <?php echo date('M j, Y', strtotime($delivered_order['created_at'])); ?>
                            </span>
                            <a href="supplier_rating.php?order_id=<?php echo htmlspecialchars($delivered_order['id']); ?>" class="rate-suppliers-btn">Rate Suppliers</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d;">No delivered orders found.</p>
            <?php endif; ?>
        </div>

        <!-- Location Filter -->
        <div class="filter-section">
            <label for="location-filter">Filter by Location:</label>
            <select id="location-filter" onchange="applyLocationFilter()">
                <option value="">All Locations</option>
                <?php foreach ($available_locations as $location): ?>
                    <option value="<?php echo htmlspecialchars($location); ?>"
                            <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($location); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($order && $order['status'] === 'delivered'): ?>
            <div class="order-reference">
                You're rating your experience with Order #<?php echo htmlspecialchars($order['id']); ?>
            </div>

            <?php if ($selected_supplier_id && $supplier): ?>
                <div class="supplier-info">
                    <p class="supplier-name"><?php echo htmlspecialchars($supplier['company_name']); ?></p>
                    <p>Location: <?php echo htmlspecialchars($supplier['location']); ?></p>
                    <div class="rating-display">
                        <p>Average Rating:</p>
                        <span id="avg-rating"><?php echo htmlspecialchars($avg_rating); ?></span>
                        <div class="star-rating">
                            <?php
                            $stars = is_numeric($avg_rating) ? round($avg_rating) : 0;
                            for ($i = 1; $i <= 5; $i++) {
                                echo '<span class="star ' . ($i <= $stars ? 'filled' : '') . '">★</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="rating-controls">
                    <div class="star-rating" id="rating-stars">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <textarea id="feedback" placeholder="Share your experience with this supplier (optional)"></textarea>
                    <button class="submit-btn" onclick="submitRating()">Submit Rating</button>
                </div>

                <div id="message" class="message"></div>

                <!-- Existing Ratings Section -->
                <div class="ratings-section">
                    <h2>Customer Ratings for <?php echo htmlspecialchars($supplier['company_name']); ?></h2>
                    <?php if (!empty($ratings)): ?>
                        <div class="ratings-list">
                            <?php foreach ($ratings as $rating): ?>
                                <div class="rating-item">
                                    <div class="rating-meta">
                                        <strong><?php echo htmlspecialchars($rating['name']); ?></strong>
                                        <div class="star-rating">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo '<span style="color: ' . ($i <= $rating['rating'] ? 'var(--warning-color)' : '#e4e5e9') . '">★</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($rating['feedback'])): ?>
                                        <p style="margin-top: 5px; font-style: italic;">"<?php echo htmlspecialchars($rating['feedback']); ?>"</p>
                                    <?php endif; ?>
                                    <small style="display: block; margin-top: 5px;">
                                        <?php echo date('M j, Y', strtotime($rating['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6c757d;">No ratings yet for this supplier.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d;">Please select a supplier to rate from the list below.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="message error" style="display: block;">
                Please select a delivered order to rate its suppliers.
            </div>
        <?php endif; ?>

        <!-- All Suppliers Section -->
        <div class="top-suppliers">
            <h2>Suppliers for Order #<?php echo $order_id ?: 'All Suppliers'; ?></h2>
            <?php if (!empty($all_suppliers)): ?>
                <div class="suppliers-list">
                    <?php foreach ($all_suppliers as $supplier): ?>
                        <div class="supplier-item" onclick="selectSupplier(<?php echo $supplier['id']; ?>)">
                            <div style="display: flex; justify-content: space-between;">
                                <div>
                                    <strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong>
                                    <p>Location: <?php echo htmlspecialchars($supplier['location']); ?></p>
                                </div>
                                <div>
                                    <span class="star-rating">
                                        <?php
                                        $avg = round($supplier['avg_rating'] ?? 0, 1);
                                        $stars = round($avg);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<span style="color: ' . ($i <= $stars ? 'var(--warning-color)' : '#e4e5e9') . '">★</span>';
                                        }
                                        ?>
                                    </span>
                                    <small>(<?php echo htmlspecialchars($avg); ?> from <?php echo htmlspecialchars($supplier['rating_count']); ?> ratings)</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d;">No suppliers found for this order.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedRating = 0;

        function setupRatingStars() {
            const stars = document.querySelectorAll('#rating-stars .star');
            stars.forEach(star => {
                star.addEventListener('click', () => {
                    selectedRating = parseInt(star.dataset.value);
                    stars.forEach((s, index) => {
                        s.classList.toggle('filled', index < selectedRating);
                    });
                });

                star.addEventListener('mouseover', () => {
                    const hoverValue = parseInt(star.dataset.value);
                    stars.forEach((s, index) => {
                        s.style.color = index < hoverValue ? 'var(--warning-color)' : '#e4e5e9';
                    });
                });

                star.addEventListener('mouseout', () => {
                    stars.forEach((s, index) => {
                        s.style.color = index < selectedRating ? 'var(--warning-color)' : '#e4e5e9';
                    });
                });
            });
        }

        function selectSupplier(supplierId) {
            const url = new URL(window.location);
            url.searchParams.set('supplier_id', supplierId);
            window.location = url;
        }

        function submitRating() {
            if (!selectedRating) {
                showMessage('Please select a rating before submitting', 'error');
                return;
            }

            const feedback = document.getElementById('feedback').value;
            const submitBtn = document.querySelector('.submit-btn');
            const originalText = submitBtn.textContent;

            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;

            const data = {
                order_id: <?php echo json_encode($order_id); ?>,
                rating: selectedRating,
                users_id: <?php echo json_encode($users_id); ?>,
                supplier_id: <?php echo json_encode($selected_supplier_id); ?>,
                feedback: feedback
            };

            console.log('Submitting rating:', data);

            fetch('submit_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Response text:', text);
                        throw new Error(`Network response was not ok: ${response.status} ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showMessage(data.message || 'Thank you for your feedback!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message || 'Failed to submit rating. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Submit Rating Error:', error);
                showMessage(`An error occurred while submitting your rating: ${error.message}`, 'error');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }

        function applyLocationFilter() {
            const location = document.getElementById('location-filter').value;
            const url = new URL(window.location);
            if (location) {
                url.searchParams.set('location', location);
            } else {
                url.searchParams.delete('location');
            }
            url.searchParams.delete('supplier_id');
            window.location = url;
        }

        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = text;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', setupRatingStars);
    </script>
</body>
</html>
