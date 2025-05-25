<?php
// Set session configurations before starting session
ini_set('session.cookie_httponly', 1);

// Include the database connection
require_once 'db_connect.php';

// Start session
session_start();
session_regenerate_id(true); // Prevent session fixation

// Check if user is logged in and is an admin
// if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
//     header('Location: login.php');
//     exit;
// }

// Get unique cities for filter
$city_query = "SELECT DISTINCT city FROM materials ORDER BY city";
try {
    $city_stmt = $pdo->prepare($city_query);
    $city_stmt->execute();
    $cities = $city_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Fetch Cities Error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Error fetching cities. Please try again later.</div>";
    exit;
}

// Handle city filter (via GET for simplicity)
$city_filter = isset($_GET['city']) && $_GET['city'] !== 'all' ? $_GET['city'] : null;
$city_condition = $city_filter ? "AND m.city = :city" : "";

// Query to get combined cart and wishlist statistics
$query = "
    SELECT 
        m.id AS material_id,
        m.name AS material_name,
        m.city AS material_city,
        COUNT(DISTINCT c.users_id) AS cart_users,
        COUNT(DISTINCT w.users_id) AS wishlist_users,
        COALESCE(SUM(c.quantity), 0) AS total_cart_quantity,
        (COUNT(c.material_id) + COUNT(w.material_id)) AS total_instances
    FROM materials m
    LEFT JOIN cart c ON m.id = c.material_id
    LEFT JOIN wishlist w ON m.id = w.material_id
    WHERE 1=1 $city_condition
    GROUP BY m.id, m.name, m.city
    ORDER BY total_instances DESC
";

try {
    $stmt = $pdo->prepare($query);
    if ($city_filter) {
        $stmt->bindParam(':city', $city_filter, PDO::PARAM_STR);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch Analysis Data Error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Error fetching data. Please try again later.</div>";
    exit;
}

// Prepare data for Chart.js
$chart_data = [
    'labels' => [],
    'cart_users' => [],
    'wishlist_users' => [],
    'total_instances' => []
];
foreach ($results as $row) {
    $chart_data['labels'][] = $row['material_name'];
    $chart_data['cart_users'][] = $row['cart_users'];
    $chart_data['wishlist_users'][] = $row['wishlist_users'];
    $chart_data['total_instances'][] = $row['total_instances'];
}
$chart_data_json = json_encode($chart_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart and Wishlist Analysis</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS for sorting -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 CSS for multi-select -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
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
        .filter-section {
            margin-bottom: 30px;
        }
        .select2-container {
            width: 100% !important;
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

        <h1 class="text-center my-4">Cart and Wishlist Analysis</h1>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row">
                <!-- Search and City Filter Card (Left) -->
                <div class="col-md-6">
                    <div class="card p-4">
                        <h4 class="mb-3">Search and Filter</h4>
                        <input type="text" class="form-control mb-3" id="materialSearch" placeholder="Enter material name...">
                        <select id="cityFilter" class="form-select">
                            <option value="all">All Cities</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $city_filter === $city ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Material Select Card (Right) -->
                <div class="col-md-6">
                    <div class="card p-4">
                        <h4 class="mb-3">Select Materials</h4>
                        <select id="materialSelect" class="form-select" multiple>
                            <?php foreach ($results as $row): ?>
                                <option value="<?php echo htmlspecialchars($row['material_name']); ?>">
                                    <?php echo htmlspecialchars($row['material_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="clearFilters" class="btn btn-secondary mt-3">Clear Filters</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card p-4">
            <h3 class="text-center mb-4">Detailed Statistics</h3>
            <div class="table-container">
                <table id="analysisTable" class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Material Name</th>
                            <th>City</th>
                            <th>Users with in Cart</th>
                            <th>Users with in Wishlist</th>
                            <th>Total Cart Quantity</th>
                            <th>Total Instances (Cart + Wishlist)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['material_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['material_city']); ?></td>
                                <td><?php echo $row['cart_users']; ?></td>
                                <td><?php echo $row['wishlist_users']; ?></td>
                                <td><?php echo $row['total_cart_quantity']; ?></td>
                                <td><?php echo $row['total_instances']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="card p-4">
            <h3 class="text-center mb-4">Material Popularity (Total Instances)</h3>
            <div class="chart-container">
                <canvas id="pieChart"></canvas>
            </div>
        </div>

        <!-- Bar Chart -->
        <div class="card p-4">
            <h3 class="text-center mb-4">Users Adding Materials to Cart and Wishlist</h3>
            <div class="chart-container">
                <canvas id="barChart"></canvas>
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
    <!-- Select2 JS for multi-select -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for multi-select
            $('#materialSelect').select2({
                placeholder: 'Select materials...',
                allowClear: true
            });

            // Initialize DataTable
            const table = $('#analysisTable').DataTable({
                "order": [[5, "desc"]], // Default sort by total instances
                "pageLength": 10,
                "responsive": true,
                "scrollX": true // Enable horizontal scrolling for wide tables
            });

            // Chart data from PHP
            const originalChartData = <?php echo $chart_data_json; ?>;
            let chartData = JSON.parse(JSON.stringify(originalChartData)); // Deep copy

            // Initialize Bar Chart
            const barCtx = document.getElementById('barChart').getContext('2d');
            const barChart = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Users with in Cart',
                            data: chartData.cart_users,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Users with in Wishlist',
                            data: chartData.wishlist_users,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Users' }
                        },
                        x: {
                            title: { display: true, text: 'Materials' }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'User Engagement by Material' }
                    }
                }
            });

            // Initialize Pie Chart
            const pieCtx = document.getElementById('pieChart').getContext('2d');
            const pieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Total Instances',
                        data: chartData.total_instances,
                        backgroundColor: [
                            'rgba(255, 0, 0, 0.7)', 'rgba(0, 128, 0, 0.7)', 'rgba(0, 0, 255, 0.7)',
                            'rgba(255, 255, 0, 0.7)', 'rgba(128, 0, 128, 0.7)', 'rgba(255, 165, 0, 0.7)',
                            'rgba(0, 255, 255, 0.7)', 'rgba(255, 20, 147, 0.7)', 'rgba(139, 69, 19, 0.7)',
                            'rgba(50, 205, 50, 0.7)', 'rgba(75, 0, 130, 0.7)', 'rgba(255, 215, 0, 0.7)',
                            'rgba(0, 191, 255, 0.7)', 'rgba(199, 21, 133, 0.7)', 'rgba(47, 79, 79, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 0, 0, 1)', 'rgba(0, 128, 0, 1)', 'rgba(0, 0, 255, 1)',
                            'rgba(255, 255, 0, 1)', 'rgba(128, 0, 128, 1)', 'rgba(255, 165, 0, 1)',
                            'rgba(0, 255, 255, 1)', 'rgba(255, 20, 147, 1)', 'rgba(139, 69, 19, 1)',
                            'rgba(50, 205, 50, 1)', 'rgba(75, 0, 130, 1)', 'rgba(255, 215, 0, 1)',
                            'rgba(0, 191, 255, 1)', 'rgba(199, 21, 133, 1)', 'rgba(47, 79, 79, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        title: { display: true, text: 'Distribution of Material Popularity' }
                    }
                }
            });

            // Function to update charts and table based on filters
            function updateDisplay() {
                const searchTerm = $('#materialSearch').val().toLowerCase();
                const selectedMaterials = $('#materialSelect').val() || [];

                // Filter data
                const filteredData = {
                    labels: [],
                    cart_users: [],
                    wishlist_users: [],
                    total_instances: []
                };

                for (let i = 0; i < originalChartData.labels.length; i++) {
                    const label = originalChartData.labels[i].toLowerCase();
                    const isSearchMatch = searchTerm === '' || label.includes(searchTerm);
                    const isSelectedMatch = selectedMaterials.length === 0 || selectedMaterials.includes(originalChartData.labels[i]);

                    if (isSearchMatch && isSelectedMatch) {
                        filteredData.labels.push(originalChartData.labels[i]);
                        filteredData.cart_users.push(originalChartData.cart_users[i]);
                        filteredData.wishlist_users.push(originalChartData.wishlist_users[i]);
                        filteredData.total_instances.push(originalChartData.total_instances[i]);
                    }
                }

                // Update charts
                barChart.data.labels = filteredData.labels;
                barChart.data.datasets[0].data = filteredData.cart_users;
                barChart.data.datasets[1].data = filteredData.wishlist_users;
                barChart.update();

                pieChart.data.labels = filteredData.labels;
                pieChart.data.datasets[0].data = filteredData.total_instances;
                pieChart.data.datasets[0].backgroundColor = filteredData.labels.map((_, index) => [
                    'rgba(255, 0, 0, 0.7)', 'rgba(0, 128, 0, 0.7)', 'rgba(0, 0, 255, 0.7)',
                    'rgba(255, 255, 0, 0.7)', 'rgba(128, 0, 128, 0.7)', 'rgba(255, 165, 0, 0.7)',
                    'rgba(0, 255, 255, 0.7)', 'rgba(255, 20, 147, 0.7)', 'rgba(139, 69, 19, 0.7)',
                    'rgba(50, 205, 50, 0.7)', 'rgba(75, 0, 130, 0.7)', 'rgba(255, 215, 0, 0.7)',
                    'rgba(0, 191, 255, 0.7)', 'rgba(199, 21, 133, 0.7)', 'rgba(47, 79, 79, 0.7)'
                ][index % 15]);
                pieChart.data.datasets[0].borderColor = filteredData.labels.map((_, index) => [
                    'rgba(255, 0, 0, 1)', 'rgba(0, 128, 0, 1)', 'rgba(0, 0, 255, 1)',
                    'rgba(255, 255, 0, 1)', 'rgba(128, 0, 128, 1)', 'rgba(255, 165, 0, 1)',
                    'rgba(0, 255, 255, 1)', 'rgba(255, 20, 147, 1)', 'rgba(139, 69, 19, 1)',
                    'rgba(50, 205, 50, 1)', 'rgba(75, 0, 130, 1)', 'rgba(255, 215, 0, 1)',
                    'rgba(0, 191, 255, 1)', 'rgba(199, 21, 133, 1)', 'rgba(47, 79, 79, 1)'
                ][index % 15]);
                pieChart.update();

                // Update DataTable
                table.search(searchTerm).draw();
                if (selectedMaterials.length > 0) {
                    table.column(0).search(selectedMaterials.join('|'), true, false).draw();
                } else {
                    table.column(0).search('').draw();
                }
            }

            // Event listeners for filters
            $('#materialSearch').on('input', updateDisplay);
            $('#materialSelect').on('change', updateDisplay);
            $('#cityFilter').on('change', function() {
                window.location.href = '?city=' + encodeURIComponent(this.value);
            });
            $('#clearFilters').on('click', function() {
                $('#materialSearch').val('');
                $('#materialSelect').val(null).trigger('change');
                $('#cityFilter').val('all').trigger('change');
                updateDisplay();
            });
        });
    </script>
</body>
</html>

<?php
$pdo = null; // Close connection
?>