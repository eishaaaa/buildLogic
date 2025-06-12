<?php
session_start();
require_once 'db_connect.php';

// Ensure $pdo is set and is a PDO instance
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("PDO connection not established in save_design.php");
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

header('Content-Type: application/json');

error_log("save_design.php called at " . date('Y-m-d H:i:s') . " for email: " . ($_SESSION['email'] ?? 'none'));

if (!isset($_SESSION['email'])) {
    error_log("No session email");
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

// Handle GET request to load design
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $design_id = $_GET['design_id'] ?? null;

    if (!$design_id) {
        error_log("No design ID provided for GET request");
        echo json_encode(['success' => false, 'message' => 'No design ID provided']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT json_layout, svg_data
            FROM designs
            WHERE id = ? AND users_id = (
                SELECT id FROM users WHERE email = ? AND role = 'customer'
            )
        ");
        $stmt->execute([$design_id, $_SESSION['email']]);
        $design = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$design) {
            error_log("Design not found or unauthorized for ID: $design_id");
            echo json_encode(['success' => false, 'message' => 'Design not found or unauthorized']);
            exit;
        }

        error_log("Design loaded successfully for ID: $design_id");
        echo json_encode([
            'success' => true,
            'json_layout' => $design['json_layout'],
            'svg_data' => $design['svg_data']
        ]);
    } catch (PDOException $e) {
        error_log("Database error in save_design.php (GET): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request to save design
$input = json_decode(file_get_contents('php://input'), true);
$design_id = $input['design_id'] ?? '0';
$json_layout = $input['json_layout'] ?? '';
$svg_data = $input['svg_data'] ?? '';

error_log("Input: design_id=$design_id, json_layout_length=" . strlen($json_layout));

if (empty($json_layout) && empty($svg_data)) {
    error_log("Empty design data");
    echo json_encode(['success' => false, 'message' => 'No design data provided']);
    exit;
}

try {
    error_log("Fetching user data for email: {$_SESSION['email']}");
    $stmt = $pdo->prepare("SELECT id, design_count FROM users WHERE email = ? AND role = 'customer'");
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found or not customer");
        echo json_encode(['success' => false, 'message' => 'User not found or not a customer']);
        exit;
    }

    error_log("Fetching subscription for user_id: {$user['id']}");
    $stmt = $pdo->prepare("
        SELECT pp.design_limit
        FROM subscriptions s
        JOIN pricing_plans pp ON s.plan_id = pp.id
        WHERE s.users_id = ? AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        error_log("No active subscription");
        echo json_encode(['success' => false, 'message' => 'No active subscription']);
        exit;
    }

    $pdo->beginTransaction();

    if ($design_id === '0') {
        error_log("Creating new design, design_count={$user['design_count']}, limit={$plan['design_limit']}");
        if ($user['design_count'] >= $plan['design_limit']) {
            $pdo->rollBack();
            error_log("Design limit exceeded");
            echo json_encode(['success' => false, 'error' => 'design_limit_exceeded']);
            exit;
        }

        error_log("Inserting design for users_id={$user['id']}");
        $stmt = $pdo->prepare("
            INSERT INTO designs (users_id, json_layout, svg_data, updated_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $json_layout, $svg_data]);
        $new_design_id = $pdo->lastInsertId();

        error_log("Updating design_count for user_id={$user['id']}");
        $stmt = $pdo->prepare("UPDATE users SET design_count = design_count + 1 WHERE id = ?");
        $stmt->execute([$user['id']]);

        $pdo->commit();
        error_log("New design saved successfully, ID: $new_design_id");
        echo json_encode(['success' => true, 'redirect' => true, 'design_id' => $new_design_id]);
    } else {
        error_log("Updating design ID: $design_id");
        $stmt = $pdo->prepare("
            UPDATE designs
            SET json_layout = ?, svg_data = ?, updated_at = NOW()
            WHERE id = ? AND users_id = ?
        ");
        $stmt->execute([$json_layout, $svg_data, $design_id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            error_log("Design not found or unauthorized");
            echo json_encode(['success' => false, 'message' => 'Design not found or unauthorized']);
            exit;
        }

        $pdo->commit();
        error_log("Design updated successfully, ID: $design_id");
        echo json_encode(['success' => true, 'redirect' => true, 'design_id' => $design_id]);
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in save_design.php (POST): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
