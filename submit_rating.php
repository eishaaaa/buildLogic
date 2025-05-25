<?php
// Disable error display to prevent HTML output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0); // Suppress warnings

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');
header('X-Debug-Source: submit_rating.php');
header('X-Debug-Time: ' . date('Y-m-d H:i:s'));

error_log('submit_rating.php: Script started at ' . date('Y-m-d H:i:s'));

// Check db_connect.php
$includePath = $_SERVER['DOCUMENT_ROOT'] . '/Practice/db_connect.php';
if (!file_exists($includePath)) {
    error_log('db_connect.php not found at: ' . $includePath);
    $response = ['success' => false, 'message' => 'Server configuration error: Database file missing'];
    echo json_encode($response);
    error_log('submit_rating.php: Response: ' . json_encode($response));
    ob_end_clean();
    exit;
}
require_once $includePath;

try {
    // Log raw input
    $rawInput = file_get_contents('php://input');
    error_log('submit_rating.php: Raw input: ' . $rawInput);

    $data = json_decode($rawInput, true);
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $required = ['order_id', 'rating', 'users_id', 'supplier_id'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            error_log('submit_rating.php: Missing field: ' . $field);
            $response = ['success' => false, 'message' => "Missing required field: $field"];
            echo json_encode($response);
            error_log('submit_rating.php: Response: ' . json_encode($response));
            ob_end_clean();
            exit;
        }
    }

    // Sanitize input
    $order_id = (int)$data['order_id'];
    $rating = (int)$data['rating'];
    $users_id = (int)$data['users_id'];
    $supplier_id = (int)$data['supplier_id'];
    $feedback = isset($data['feedback']) ? trim($data['feedback']) : '';

    error_log("submit_rating.php: Processed data: order_id=$order_id, rating=$rating, users_id=$users_id, supplier_id=$supplier_id");

    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        error_log('submit_rating.php: Invalid rating: ' . $rating);
        $response = ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        echo json_encode($response);
        error_log('submit_rating.php: Response: ' . json_encode($response));
        ob_end_clean();
        exit;
    }

    // Verify user exists and is a customer
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :users_id AND role = 'customer'");
    $stmt->execute(['users_id' => $users_id]);
    if (!$stmt->fetch()) {
        error_log('submit_rating.php: Invalid user or not a customer: users_id=' . $users_id);
        $response = ['success' => false, 'message' => 'Invalid user or not a customer'];
        echo json_encode($response);
        error_log('submit_rating.php: Response: ' . json_encode($response));
        ob_end_clean();
        exit;
    }

    // Verify supplier exists
    $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = :supplier_id");
    $stmt->execute(['supplier_id' => $supplier_id]);
    if (!$stmt->fetch()) {
        error_log('submit_rating.php: Invalid supplier: supplier_id=' . $supplier_id);
        $response = ['success' => false, 'message' => 'Invalid supplier ID'];
        echo json_encode($response);
        error_log('submit_rating.php: Response: ' . json_encode($response));
        ob_end_clean();
        exit;
    }

    // Verify order exists, belongs to user, and is delivered
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = :order_id AND users_id = :users_id AND status = 'delivered'");
    $stmt->execute(['order_id' => $order_id, 'users_id' => $users_id]);
    if (!$stmt->fetch()) {
        error_log('submit_rating.php: Invalid order: order_id=' . $order_id . ', users_id=' . $users_id);
        $response = ['success' => false, 'message' => 'Order not found, not yours, or not delivered'];
        echo json_encode($response);
        error_log('submit_rating.php: Response: ' . json_encode($response));
        ob_end_clean();
        exit;
    }

    // Verify supplier is associated with order
    $stmt = $pdo->prepare("SELECT 1 FROM order_items oi
                           JOIN materials m ON oi.material_id = m.id
                           WHERE oi.order_id = :order_id AND m.supplier_id = :supplier_id");
    $stmt->execute(['order_id' => $order_id, 'supplier_id' => $supplier_id]);
    if (!$stmt->fetch()) {
        error_log('submit_rating.php: Supplier not associated with order: order_id=' . $order_id . ', supplier_id=' . $supplier_id);
        $response = ['success' => false, 'message' => 'Supplier not associated with this order'];
        echo json_encode($response);
        error_log('submit_rating.php: Response: ' . json_encode($response));
        ob_end_clean();
        exit;
    }

    // Check if rating already exists
    $stmt = $pdo->prepare("SELECT id FROM supplier_ratings 
                           WHERE order_id = :order_id AND users_id = :users_id AND supplier_id = :supplier_id");
    $stmt->execute(['order_id' => $order_id, 'users_id' => $users_id, 'supplier_id' => $supplier_id]);
    if ($stmt->fetch()) {
        error_log('submit_rating.php: Rating already exists: order_id=' . $order_id . ', users_id=' . $users_id . ', supplier_id=' . $supplier_id);
        $response = ['success' => false, 'message' => 'You have already rated this supplier for this order'];
        echo json_encode($response);
        error_log('submit_rating.php: Response: ' . json_encode($response));
        ob_end_clean();
        exit;
    }

    // Insert rating
    $stmt = $pdo->prepare("INSERT INTO supplier_ratings (order_id, supplier_id, users_id, rating, feedback, created_at) 
                           VALUES (:order_id, :supplier_id, :users_id, :rating, :feedback, NOW())");
    $result = $stmt->execute([
        'order_id' => $order_id,
        'supplier_id' => $supplier_id,
        'users_id' => $users_id,
        'rating' => $rating,
        'feedback' => $feedback
    ]);

    if (!$result) {
        error_log('submit_rating.php: Failed to insert rating: order_id=' . $order_id . ', users_id=' . $users_id . ', supplier_id=' . $supplier_id);
        throw new Exception('Failed to insert rating into database');
    }

    error_log('submit_rating.php: Rating inserted successfully: order_id=' . $order_id . ', users_id=' . $users_id . ', supplier_id=' . $supplier_id);
    $response = ['success' => true, 'message' => 'Thank you for your rating!'];
    echo json_encode($response);
    error_log('submit_rating.php: Response: ' . json_encode($response));
} catch (PDOException $e) {
    error_log('submit_rating.php: PDO Error: ' . $e->getMessage() . ' | Data: ' . json_encode($data ?? []));
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    echo json_encode($response);
    error_log('submit_rating.php: Response: ' . json_encode($response));
} catch (Exception $e) {
    error_log('submit_rating.php: General Error: ' . $e->getMessage() . ' | Data: ' . json_encode($data ?? []));
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    echo json_encode($response);
    error_log('submit_rating.php: Response: ' . json_encode($response));
}

// Ensure no trailing code
ob_end_clean();
exit;
?>