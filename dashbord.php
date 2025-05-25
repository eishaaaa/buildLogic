<?php
session_start();
require 'db_connect.php';

$errors = [];
$designs = [];

error_log("Dashboard session: " . print_r($_SESSION, true));

if (isset($_SESSION['email'])) {
    try {
        $stmt = $pdo->prepare("SELECT email, role FROM users WHERE email = ? AND role = 'customer'");
        $stmt->execute([$_SESSION['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("User role check: " . print_r($user, true));

        if ($user) {
            $stmt = $pdo->prepare("
                SELECT d.id, d.proposal_id, d.json_layout, d.svg_data, d.updated_at, p.house_style, p.location
                FROM designs d
                JOIN proposals p ON d.proposal_id = p.id
                JOIN users u ON p.users_id = u.id
                WHERE u.email = ? AND p.status != 'archived'
                ORDER BY d.updated_at DESC
            ");
            $stmt->execute([$_SESSION['email']]);
            $designs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errors[] = "Access restricted to customers. Please log in with a customer account.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
        error_log("Database error in dashboard: " . $e->getMessage());
    }
} else {
    $errors[] = "Please log in to view your dashboard.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f7f7f7;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2, h3 {
            color: #333;
        }
        .section {
            margin-bottom: 30px;
        }
        .error {
            color: red;
            margin-bottom: 20px;
        }
        .item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .item:last-child {
            border-bottom: none;
        }
        .item h4 {
            margin: 0 0 10px;
            color: #2196F3;
        }
        .item p {
            margin: 5px 0;
            color: #555;
        }
        a, button {
            text-decoration: none;
            padding: 8px 15px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button {
            background: #4CAF50;
        }
        a:hover, button:hover {
            opacity: 0.9;
        }
        .no-data {
            color: #777;
            font-style: italic;
        }
        .svg-preview {
            max-width: 200px;
            max-height: 150px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <?php 
    include 'header.php'; ?>
    <div class="container">
        <h2>User Dashboard</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['email']) && empty($errors)): ?>
            <div class="section">
                <h3>Saved Designs</h3>
                <?php if (empty($designs)): ?>
                    <p class="no-data">No designs found. Create one in the <a href="design.php">Design Editor</a>.</p>
                <?php else: ?>
                    <?php foreach ($designs as $design): ?>
                        <div class="item">
                            <h4><?= htmlspecialchars($design['house_style']) ?> (Proposal #<?= $design['proposal_id'] ?>, <?= htmlspecialchars($design['location']) ?>)</h4>
                            <p>Updated: <?= date('F j, Y, g:i a', strtotime($design['updated_at'])) ?></p>
                            <?php if (!empty($design['svg_data'])): ?>
                                <p>Preview:</p>
                                <div class="svg-preview">
                                    <?= htmlspecialchars_decode($design['svg_data']) ?>
                                </div>
                            <?php else: ?>
                                <p>Layout Preview: <code><?= htmlspecialchars(substr(json_encode($design['json_layout']), 0, 50)) ?>...</code></p>
                            <?php endif; ?>
                            <a href="design.php?load_id=<?= $design['id'] ?>&proposal_id=<?= $design['proposal_id'] ?>">Edit Design</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p><a href="login.php">Log in</a> to view your saved designs.</p>
        <?php endif; ?>
    </div>
</body>
</html>