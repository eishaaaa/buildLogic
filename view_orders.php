<?php
// Set session configurations before starting session
ini_set('session.cookie_httponly', 1);

// Include the database connection
require_once 'db_connect.php';

// Start session
session_start();
session_regenerate_id(true); // Prevent session fixation

// // Check if user is logged in and is an admin
// if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
//     header('Location: login.php');
//     exit;
// }

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'], $_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
    $valid_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    if ($order_id && in_array($status, $valid_statuses)) {
        try {
            $query = "UPDATE orders SET status = :status WHERE id = :order_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['status' => $status, 'order_id' => $order_id]);
        } catch (PDOException $e) {
            error_log('Update Order Status Error: ' . $e->getMessage());
            echo "<div class='alert alert-danger'>Error updating status. Please try again.</div>";
        }
    }
    // Redirect to avoid form resubmission
    header('Location: view_orders.php');
    exit;
}

// Query to get orders with material names, user email, and new fields
$query = "
    SELECT 
        o.id AS order_id,
        u.email AS user_email,
        GROUP_CONCAT(m.name SEPARATOR ', ') AS material_names,
        GROUP_CONCAT(oi.quantity SEPARATOR ', ') AS quantities,
        GROUP_CONCAT(oi.unit_price SEPARATOR ', ') AS unit_prices,
        o.total_price,
        o.name,
        o.phone,
        o.address,
        o.promo_code,
        o.payment_method,
        o.created_at AS order_date,
        o.status
    FROM orders o
    JOIN users u ON o.users_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN materials m ON oi.material_id = m.id
    GROUP BY o.id
    ORDER BY o.created_at DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch Orders Error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Error fetching orders. Please try again later.</div>";
    exit;
}

// Prepare data for status pie chart
$status_query = "
    SELECT status, COUNT(*) AS count
    FROM orders
    GROUP BY status
";
try {
    $stmt = $pdo->prepare($status_query);
    $stmt->execute();
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch Status Data Error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Error fetching status data. Please try again later.</div>";
    exit;
}

$chart_data = [
    'labels' => [],
    'counts' => [],
    'colors' => [
        'pending' => 'rgba(255, 99, 132, 0.7)',
        'confirmed' => 'rgba(54, 162, 235, 0.7)',
        'shipped' => 'rgba(75, 192, 192, 0.7)',
        'delivered' => 'rgba(153, 102, 255, 0.7)',
        'cancelled' => 'rgba(255, 159, 64, 0.7)'
    ]
];
foreach ($status_data as $row) {
    $chart_data['labels'][] = ucfirst($row['status']);
    $chart_data['counts'][] = $row['count'];
}
$chart_data_json = json_encode($chart_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS for sorting -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts (Roboto) -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f1f1f1;
            font-family: 'Roboto', sans-serif;
        }
        h1, h3, h4 {
            color: #333;
        }
        .container {
            margin-top: 30px;
        }
        .table-container {
            overflow-x: auto; /* Enable horizontal scrolling for wide tables */
        }
        .table {
            width: 100%;
            max-width: 100%; /* Prevent table from exceeding container */
            table-layout: auto; /* Allow columns to adjust dynamically */
        }
        .table th, .table td {
            vertical-align: middle;
            word-wrap: break-word; /* Wrap long text */
            white-space: normal; /* Allow text to wrap */
            max-width: 200px; /* Limit column width */
            padding: 8px; /* Reduce padding for better fit */
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        .btn {
            background-color: #007bff;
            color: white;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        .back-button-container {
            margin-bottom: 20px;
        }
        .back-btn {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        .back-btn:hover {
            background-color: #218838;
            border-color: #218838;
        }
        .status-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dataTables_wrapper .dataTables_filter {
            float: left !important;
            text-align: left !important;
        }
        .dataTables_wrapper .dataTables_length {
            float: right !important;
            text-align: right !important;
        }
        .checkboxes-container {
            padding: 10px 0;
        }
        .filter-btn {
            background-color: #007bff;
            border-color: #007bff;
        }
        .filter-btn:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .dropdown-menu {
            padding: 15px;
            min-width: 200px;
        }
        .clear-filters-btn {
            margin-top: 10px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div class="back-button-container">
            <a href="admin.php" class="btn back-btn" title="Back to Admin">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                Back
            </a>
        </div>

        <h1 class="text-center my-4">View Orders</h1>

        <!-- Orders Table -->
        <div class="card p-4">
            <h3 class="text-center mb-4">Orders</h3>
            <div class="table-container">
                <table id="ordersTable" class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Order ID</th>
                            <th>User Email</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Promo Code</th>
                            <th>Payment Method</th>
                            <th>Material Name(s)</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Price</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($order['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($order['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['address'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['promo_code'] ?? 'None'); ?></td>
                                <td><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['material_names']); ?></td>
                                <td><?php echo htmlspecialchars($order['quantities']); ?></td>
                                <td><?php echo htmlspecialchars($order['unit_prices']); ?></td>
                                <td>$<?php echo number_format($order['total_price'], 2); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                                <td><?php echo htmlspecialchars($order['status']); ?></td>
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="order_id" value="<?php echo ($order['order_id']); ?>">
                                        <select name="status" class="form-select" style="width: auto;">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="card p-4">
            <h3 class="text-center mb-4">Order Status Distribution</h3>
            <div class="chart-container">
                <canvas id="statusPieChart"></canvas>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable with custom DOM
            const table = $('#ordersTable').DataTable({
                "order": [[11, "desc"]], // Default sort by order date
                "pageLength": 10,
                "responsive": true,
                "scrollX": true, // Enable horizontal scrolling for wide tables
                "dom": '<"row"<"col-sm-4 checkboxes-container"><"col-sm-4"f><"col-sm-4"l>>t<"row"<"col-sm-6"i><"col-sm-6"p>>',
                "initComplete": function() {
                    // Append filter icon and dropdown to the custom container
                    $('.checkboxes-container').html(`
                        <div class="dropdown">
                            <button class="btn filter-btn dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel" viewBox="0 0 16 16">
                                    <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5zm1 .5v1.308l4.372 4.858A.5.5 0 0 1 7 8.5v5.306l2-.666V8.5a.5.5 0 0 1 .128-.334L13.5 3.308V2z"/>
                                </svg>
                                Filter by Status
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                                <li><label class="dropdown-item"><input type="checkbox" class="status-filter" value="pending" checked> Pending</label></li>
                                <li><label class="dropdown-item"><input type="checkbox" class="status-filter" value="confirmed"> Confirmed</label></li>
                                <li><label class="dropdown-item"><input type="checkbox" class="status-filter" value="shipped"> Shipped</label></li>
                                <li><label class="dropdown-item"><input type="checkbox" class="status-filter" value="delivered"> Delivered</label></li>
                                <li><label class="dropdown-item"><input type="checkbox" class="status-filter" value="cancelled"> Cancelled</label></li>
                                <li><button id="clearFilters" class="btn btn-secondary btn-sm clear-filters-btn">Clear Filters</button></li>
                            </ul>
                        </div>
                    `);

                    // Status filter with checkboxes
                    $('.status-filter').on('change', function() {
                        const selectedStatuses = $('.status-filter:checked').map(function() {
                            return this.value;
                        }).get();

                        if (selectedStatuses.length > 0) {
                            table.column(12).search(selectedStatuses.join('|'), true, false).draw();
                        } else {
                            // If no checkboxes selected, show all
                            table.column(12).search('').draw();
                        }
                    });

                    // Clear filters
                    $('#clearFilters').on('click', function() {
                        $('.status-filter').prop('checked', false);
                        table.column(12).search('').draw();
                    });
                }
            });

            // Initialize Pie Chart
            const pieCtx = document.getElementById('statusPieChart').getContext('2d');
            const chartData = <?php echo $chart_data_json; ?>;
            const pieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Order Status',
                        data: chartData.counts,
                        backgroundColor: chartData.labels.map(label => chartData.colors[label.toLowerCase()]),
                        borderColor: chartData.labels.map(label => chartData.colors[label.toLowerCase()].replace('0.7', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        title: { display: true, text: 'Distribution of Order Statuses' }
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
$pdo = null; // Close connection
?>