<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $proposal_id = $data['proposal_id'] ?? '0';
    $design_id = $data['design_id'] ?? '0';
    $json_layout = $data['json_layout'] ?? '';
    $svg_data = $data['svg_data'] ?? '';

    if (empty($json_layout) || empty($svg_data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    // Get user ID
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    $user_id = $user['id'];

    // Handle proposal
    if ($proposal_id == '0') {
        $stmt = $pdo->prepare('INSERT INTO proposals (users_id) VALUES (?)');
        $stmt->execute([$user_id]);
        $proposal_id = $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare('SELECT id FROM proposals WHERE id = ? AND users_id = ?');
        $stmt->execute([$proposal_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid proposal']);
            exit;
        }
    }

    // Save or update design
    if ($design_id == '0') {
        $stmt = $pdo->prepare('INSERT INTO designs (proposal_id, json_layout, svg_data) VALUES (?, ?, ?)');
        $stmt->execute([$proposal_id, $json_layout, $svg_data]);
        $design_id = $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare('UPDATE designs SET json_layout = ?, svg_data = ? WHERE id = ? AND proposal_id = ?');
        $stmt->execute([$json_layout, $svg_data, $design_id, $proposal_id]);
    }

    echo json_encode(['success' => true, 'design_id' => $design_id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>