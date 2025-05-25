<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
session_regenerate_id(true); // Prevent session fixation

// Redirect to login if not authenticated or not a customer
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}
// Include the database connection
require 'db_connect.php'; // Ensure connect.php is in C:\xampp\htdocs\Practice\

// City-specific base rates
$cityFactors = [
    'Karachi' => 1500,
    'Lahore' => 1600,
    'Islamabad' => 1700,
    'Other' => 1400
];
$laborCostPerSqFt = [
    'Karachi' => 300,
    'Lahore' => 320,
    'Islamabad' => 350,
    'Other' => 280
];

// Initialize variables
$budgetBreakdown = [];
$budgetSufficient = true;
$lastThree = [];
$area = isset($_POST['area']) ? (float)$_POST['area'] : 0;
$city = isset($_POST['city']) ? htmlspecialchars($_POST['city']) : 'Other';
$plotSize = isset($_POST['plot_size']) ? htmlspecialchars($_POST['plot_size']) : '';
$floors = isset($_POST['floors']) ? (int)$_POST['floors'] : 1;
$totalEstimate = 0;
$errors = [];

// Convert plot size to sq.ft
$plotSizeInSqFt = 0;
switch ($plotSize) {
    case '5 Marla':
        $plotSizeInSqFt = 1361.25;
        break;
    case '10 Marla':
        $plotSizeInSqFt = 2722.5;
        break;
    case '1 Kanal':
        $plotSizeInSqFt = 5445;
        break;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['budget'])) {
    // Validate inputs
    $userBudget = (int)$_POST['budget'];
    if ($userBudget <= 0) $errors[] = "Budget must be a positive number.";
    if ($area <= 0) $errors[] = "Area must be a positive number.";
    if ($floors <= 0 || !is_int($floors)) $errors[] = "Floors must be a positive integer.";
    if (empty($plotSize)) $errors[] = "Plot size is required.";
    if (!array_key_exists($city, $cityFactors)) $city = 'Other';
    if ($area > $plotSizeInSqFt) {
        $errors[] = "Area per floor ($area sq.ft) cannot exceed plot size ($plotSizeInSqFt sq.ft).";
    }
    $totalCoveredArea = $area * $floors;

    if (empty($errors)) {
        // Construction Cost
        try {
            $stmt = $pdo->prepare("SELECT name, price FROM materials WHERE city = ? AND name IN ('Bricks', 'Cement', 'Sand (Ravi)', 'Sand (Chenab)', 'Crush (Bajri)', 'Steel (Sarya)')");
            $stmt->execute([$city]);
            $materialCosts = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $materialCosts[$row['name']] = (float)$row['price'];
            }
            $brickCost = isset($materialCosts['Bricks']) ? $materialCosts['Bricks'] * 4.5 : 13 * 4.5;
            $cementCost = isset($materialCosts['Cement']) ? $materialCosts['Cement'] * 0.004 : 1160 * 0.004;
            $sandCost = isset($materialCosts['Sand (Ravi)']) ? $materialCosts['Sand (Ravi)'] * 0.5 : (isset($materialCosts['Sand (Chenab)']) ? $materialCosts['Sand (Chenab)'] * 0.5 : 60 * 0.5);
            $crushCost = isset($materialCosts['Crush (Bajri)']) ? $materialCosts['Crush (Bajri)'] * 0.4 : 110 * 0.4;
            $steelCost = isset($materialCosts['Steel (Sarya)']) ? $materialCosts['Steel (Sarya)'] * 10 : 238 * 10;
            $costPerSqFt = $brickCost + $cementCost + $sandCost + $crushCost + $steelCost;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            $costPerSqFt = $cityFactors[$city] ?? 1400;
        }

        // Allocate budget
        $contingency = round($userBudget * 0.07);
        $remainingBudget = $userBudget - $contingency;
        $constructionCost = $remainingBudget * 0.50;
        $materialCost = $remainingBudget * 0.30;
        $laborCost = $remainingBudget * 0.20;
        $totalEstimate = $constructionCost + $materialCost + $laborCost + $contingency;

        // Validate feasibility
        try {
            $stmt = $pdo->prepare("SELECT AVG(price) as avg_material FROM materials WHERE city = ? AND name IN ('Tiles', 'Wood', 'Wallpaper', 'Wall Sheets', 'Electrical Items (Basic Set)')");
            $stmt->execute([$city]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $requiredMaterialCost = $row ? (float)$row['avg_material'] * $totalCoveredArea * 0.01 : 600000;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            $requiredMaterialCost = 600000;
        }

        $requiredConstructionCost = $totalCoveredArea * $costPerSqFt;
        if ($constructionCost < $requiredConstructionCost) {
            $budgetSufficient = false;
            $errors[] = "Budget is too low for construction. Required: PKR " . number_format($requiredConstructionCost) . ", Allocated: PKR " . number_format($constructionCost);
        }
        if ($materialCost < $requiredMaterialCost) {
            $budgetSufficient = false;
            $errors[] = "Budget is too low for materials. Required: PKR " . number_format($requiredMaterialCost) . ", Allocated: PKR " . number_format($materialCost);
        }
        $requiredLaborCost = $totalCoveredArea * ($laborCostPerSqFt[$city] ?? 280);
        if ($laborCost < $requiredLaborCost) {
            $budgetSufficient = false;
            $errors[] = "Budget is too low for labor. Required: PKR " . number_format($requiredLaborCost) . ", Allocated: PKR " . number_format($laborCost);
        }

        $budgetBreakdown = [
            'Construction Cost' => $constructionCost,
            'Material Cost' => $materialCost,
            'Labor Cost' => $laborCost,
            'Contingency Fund' => $contingency
        ];

        if ($budgetSufficient) {
            // Insert into history
            try {
                $stmt = $pdo->prepare("INSERT INTO budget_history (budget, area, city, plot_size, floors, construction_cost, material, labor, contingency, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userBudget, $area, $city, $plotSize, $floors, $constructionCost, $materialCost, $laborCost, $contingency, $totalEstimate]);
            } catch (PDOException $e) {
                $errors[] = "Database insert failed: " . $e->getMessage();
            }
        }
    }
}

// View past result
if (isset($_GET['view_id'])) {
    try {
        $id = (int)$_GET['view_id'];
        $stmt = $pdo->prepare("SELECT * FROM budget_history WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $area = $row['area'];
            $city = $row['city'];
            $plotSize = $row['plot_size'];
            $floors = $row['floors'];
            $userBudget = $row['budget'];
            $totalEstimate = $row['total'];
            $budgetSufficient = $userBudget >= $totalEstimate;
            $budgetBreakdown = [
                'Construction Cost' => $row['construction_cost'],
                'Material Cost' => $row['material'],
                'Labor Cost' => $row['labor'],
                'Contingency Fund' => $row['contingency']
            ];
        } else {
            $errors[] = "No history entry found for ID: $id";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Get last 3 history entries
try {
    $stmt = $pdo->query("SELECT * FROM budget_history ORDER BY created_at DESC LIMIT 3");
    $lastThree = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>House Budget Estimator</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial;
            background: #f7f7f7;
            margin: 40px;
        }

        .container {
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        input[type="submit"] {
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .warning {
            background: #f8d7da;
            color: #721c24;
        }

        .error {
            background: #ffe6e6;
            color: #721c24;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
        }

        th {
            background: #f2f2f2;
        }

        canvas {
            margin-top: 30px;
            max-width: 400px;
            max-height: 400px;
        }

        .history {
            margin-top: 30px;
        }

        .history a {
            display: block;
            color: #2196F3;
            margin-bottom: 5px;
            text-decoration: none;
        }

        .history button {
            padding: 8px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>🏡 House Budget Estimator</h2>
        <form method="POST">
            <label>Total Budget (PKR):</label>
            <input type="number" name="budget" required placeholder="e.g. 3000000" min="1" value="<?= isset($_POST['budget']) ? htmlspecialchars($_POST['budget']) : '' ?>">
            <label>Area (sq.ft):</label>
            <input type="number" name="area" required min="1" value="<?= isset($_POST['area']) ? htmlspecialchars($_POST['area']) : '' ?>">
            <label>City:</label>
            <select name="city" required>
                <?php foreach ($cityFactors as $c => $rate): ?>
                    <option value="<?= $c ?>" <?= (isset($_POST['city']) && $_POST['city'] == $c) ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <label>Plot Size:</label>
            <select name="plot_size" required>
                <option value="">Select</option>
                <option value="5 Marla" <?= (isset($_POST['plot_size']) && $_POST['plot_size'] == '5 Marla') ? 'selected' : '' ?>>5 Marla</option>
                <option value="10 Marla" <?= (isset($_POST['plot_size']) && $_POST['plot_size'] == '10 Marla') ? 'selected' : '' ?>>10 Marla</option>
                <option value="1 Kanal" <?= (isset($_POST['plot_size']) && $_POST['plot_size'] == '1 Kanal') ? 'selected' : '' ?>>1 Kanal</option>
            </select>
            <label>Floors:</label>
            <input type="number" name="floors" required min="1" value="<?= isset($_POST['floors']) ? htmlspecialchars($_POST['floors']) : '' ?>">
            <input type="submit" value="Estimate Budget">
        </form>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['view_id'])) && empty($errors)): ?>
            <?php if (!$budgetSufficient): ?>
                <?php
                $shortfall = $totalEstimate - $userBudget;
                $suggestions = [];
                if ($floors > 1) {
                    $suggestions[] = "Reduce the number of floors to " . ($floors - 1) . " to save approximately PKR " . number_format($constructionCost / $floors);
                }
                if ($area > $plotSizeInSqFt * 0.5) {
                    $suggestions[] = "Reduce the area per floor to " . floor($area * 0.8) . " sq.ft to lower costs.";
                }
                $suggestions[] = "Opt for lower-cost materials (e.g., local cement or tiles) to reduce material costs.";
                ?>
                <div class="message warning">
                    <strong>Warning:</strong> Your budget of PKR <?= number_format($userBudget) ?> is too low by PKR <?= number_format($shortfall) ?> for the estimated cost of PKR <?= number_format($totalEstimate) ?>.
                    <br><strong>Suggestions to fit your budget:</strong>
                    <ul>
                        <?php foreach ($suggestions as $suggestion): ?>
                            <li><?= htmlspecialchars($suggestion) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="message success">
                    <strong>Success!</strong> Your budget breakdown:
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budgetBreakdown as $key => $val): ?>
                            <tr>
                                <td><?= htmlspecialchars($key) ?></td>
                                <td><?= number_format($val) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Total Estimated Cost</strong></td>
                            <td><strong><?= number_format($totalEstimate) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                <?php if (!empty($budgetBreakdown)): ?>
                    <canvas id="budgetChart"></canvas>
                    <script>
                        console.log('Budget Breakdown:', <?= json_encode($budgetBreakdown) ?>);
                        const ctx = document.getElementById('budgetChart').getContext('2d');
                        try {
                            new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: <?= json_encode(array_keys($budgetBreakdown)) ?>,
                                    datasets: [{
                                        data: <?= json_encode(array_values($budgetBreakdown)) ?>,
                                        backgroundColor: ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'right',
                                            labels: {
                                                boxWidth: 40,
                                                padding: 25
                                            }
                                        }
                                    }
                                }
                            });
                        } catch (error) {
                            console.error('Chart.js error:', error);
                        }
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <div class="history">
            <h3>🕓 Recent Estimates</h3>
            <?php foreach ($lastThree as $entry): ?>
                <div style="margin-bottom: 1em;">
                    <a href="?view_id=<?= $entry['id'] ?>">🗂 <?= htmlspecialchars($entry['city']) ?> - <?= number_format($entry['budget']) ?> PKR (<?= $entry['area'] ?> sqft, <?= htmlspecialchars($entry['plot_size']) ?>, <?= $entry['floors'] ?> floors)</a>
                    <button onclick="alert('Construction: <?= $entry['construction_cost'] ?>\nMaterial: <?= $entry['material'] ?>\nLabor: <?= $entry['labor'] ?>\nContingency: <?= $entry['contingency'] ?>')">View Breakdown</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
<?php include 'footer.php'; ?>

</html>