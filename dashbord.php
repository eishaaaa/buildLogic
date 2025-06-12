<?php
session_start();
session_regenerate_id(true); // Prevent session fixation

require_once 'db_connect.php';

$errors = [];
$designs = [];
$user_plan = null;
$user = null;

error_log("Dashboard session: " . print_r($_SESSION, true));

if (!isset($_SESSION['email'])) {
    $errors[] = "Please log in to view your dashboard.";
    header("Location: login.php");
    exit;
}

try {
    // Check user role and fetch user data
    $stmt = $pdo->prepare("SELECT id, email, role, design_count FROM users WHERE email = ? AND role = 'customer'");
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("User role check: " . print_r($user, true));

    if (!$user) {
        $errors[] = "Access restricted to customers. Please log in with a customer account.";
        header("Location: login.php");
        exit;
    }

    // Fetch subscription details
    $stmt = $pdo->prepare("
        SELECT pp.name, pp.design_limit, s.status
        FROM subscriptions s
        JOIN pricing_plans pp ON s.plan_id = pp.id
        WHERE s.users_id = ? AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $user_plan = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if proposals table exists
    $has_proposals = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'proposals'");
    if ($stmt->rowCount() > 0) {
        $has_proposals = true;
    }

    // Fetch designs
    if ($has_proposals) {
        // Try fetching with proposals if table exists
        $stmt = $pdo->prepare("
            SELECT d.id, d.users_id, d.json_layout, d.svg_data, d.updated_at, 
                   COALESCE(p.house_style, 'Unknown') AS house_style, 
                   COALESCE(p.location, 'Unknown') AS location, 
                   COALESCE(p.id, 0) AS proposal_id
            FROM designs d
            LEFT JOIN proposals p ON d.users_id = p.users_id
            JOIN users u ON d.users_id = u.id
            WHERE u.email = ? AND (p.status IS NULL OR p.status != 'archived')
            ORDER BY d.updated_at DESC
        ");
    } else {
        // Fetch without proposals
        $stmt = $pdo->prepare("
            SELECT d.id, d.users_id, d.json_layout, d.svg_data, d.updated_at
            FROM designs d
            JOIN users u ON d.users_id = u.id
            WHERE u.email = ?
            ORDER BY d.updated_at DESC
        ");
    }
    $stmt->execute([$_SESSION['email']]);
    $designs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    error_log("Database error in dashboard: " . $e->getMessage());
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2,
        h3 {
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

        a,
        button {
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

        a:hover,
        button:hover {
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

        .plan-info {
            background: #e0f2e0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>User Dashboard</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php elseif (isset($_SESSION['email'])): ?>
            <div class="section">
                <h3>Subscription Status</h3>
                <?php if ($user_plan): ?>
                    <div class="plan-info">
                        <p><strong>Plan:</strong> <?= htmlspecialchars($user_plan['name']) ?></p>
                        <p><strong>Designs Used:</strong> <?= $user['design_count'] ?> / <?= $user_plan['design_limit'] ?></p>
                        <p><a href="pricing.php">Upgrade Plan</a></p>
                    </div>
                <?php else: ?>
                    <p class="no-data">No active subscription. <a href="pricing.php">Choose a plan</a> to start designing.</p>
                <?php endif; ?>
            </div>
            <div class="section">
                <h3>Saved Designs</h3>
                <?php if (empty($designs)): ?>
                    <p class="no-data">No designs found. Create one in the <a href="design.php">Design Editor</a>.</p>
                <?php else: ?>
                    <?php foreach ($designs as $index => $design): ?>
                        <div class="item">
                            <h4>
                                Design #<?= $index + 1 ?>
                                <?php if ($has_proposals && $design['proposal_id'] > 0): ?>
                                    (<?= htmlspecialchars($design['house_style']) ?>, <?= htmlspecialchars($design['location']) ?>)
                                <?php endif; ?>
                            </h4>
                            <p>Updated: <?= date('F j, Y, g:i a', strtotime($design['updated_at'])) ?></p>
                            <?php if (!empty($design['svg_data'])): ?>
                                <p>Preview:</p>
                                <div class="svg-preview">
                                    <?= htmlspecialchars_decode($design['svg_data']) ?>
                                </div>
                            <?php else: ?>
                                <p>Layout Preview: <code><?= htmlspecialchars(substr(json_encode($design['json_layout']), 0, 50)) ?>...</code></p>
                            <?php endif; ?>
                            <a href="design.php?load_id=<?= $design['id'] ?>">Edit Design</a>
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
