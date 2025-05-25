<?php
session_start();
require_once 'db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect to login.php if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['supplier']['supplier_id'])) {
    header("Location: login.php");
    exit;
}

// Log action to logs table
function logAction($pdo, $user_id, $action) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (users_id, action) VALUES (?, ?)");
        $stmt->execute([$user_id, $action]);
    } catch (PDOException $e) {
        error_log("Log error: " . $e->getMessage());
    }
}

// Handle Upload Material
$upload_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'], $_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && isset($_SESSION['supplier']['supplier_id'])) {
    $name = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0.0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $supplier_id = $_SESSION['supplier']['supplier_id'];

    // Validate CSRF token
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $upload_msg = "Invalid CSRF token.";
        error_log("Upload error: Invalid CSRF token");
    }
    // Validate required fields
    elseif (empty($name)) {
        $upload_msg = "Material name is required.";
        error_log("Upload error: Material name is empty");
    } elseif (empty($city)) {
        $upload_msg = "City is required.";
        error_log("Upload error: City is empty");
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] == UPLOAD_ERR_NO_FILE) {
        $upload_msg = "Image upload is required.";
        error_log("Upload error: No image uploaded");
    } elseif ($price <= 0) {
        $upload_msg = "Price must be greater than zero.";
        error_log("Upload error: Invalid price ($price)");
    } elseif ($quantity < 0) {
        $upload_msg = "Quantity cannot be negative.";
        error_log("Upload error: Invalid quantity ($quantity)");
    } else {
        $image_path = '';
        if ($_FILES['image']['error'] == UPLOAD_ERR_OK) {
            // Validate file type and size
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_size = $_FILES['image']['size'];

            // Check file extension
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {
                $upload_msg = "Only JPG, PNG, or GIF files are allowed.";
                error_log("Upload error: Invalid file extension ($file_ext)");
            } elseif ($file_size > $max_size) {
                $upload_msg = "Image size must be less than 5MB.";
                error_log("Upload error: File size too large ($file_size bytes)");
            } else {
                // Verify MIME type using finfo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $file_type = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);

                if (!in_array($file_type, $allowed_types)) {
                    $upload_msg = "Invalid file type. Only JPEG, PNG, or GIF images are allowed.";
                    error_log("Upload error: Invalid MIME type ($file_type)");
                } else {
                    $targetDir = __DIR__ . "/Uploads/";
                    // Create directory if it doesn't exist
                    if (!is_dir($targetDir)) {
                        if (!mkdir($targetDir, 0755, true)) {
                            $upload_msg = "Failed to create Uploads directory. Contact administrator.";
                            error_log("Upload error: Failed to create directory: $targetDir");
                        }
                    }
                    // Check writability
                    if (is_writable($targetDir)) {
                        // Sanitize and generate unique filename
                        $fileName = basename($_FILES["image"]["name"]);
                        $fileName = preg_replace("/[^A-Za-z0-9._-]/", "", $fileName);
                        $targetFile = $targetDir . time() . "_" . $fileName;

                        // Move uploaded file
                        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                            $image_path = "Uploads/" . basename($targetFile);
                            error_log("Image uploaded successfully to: $targetFile");
                        } else {
                            $upload_msg = "Failed to upload image. Check server permissions.";
                            error_log("Upload error: Failed to move uploaded file to: $targetFile");
                        }
                    } else {
                        $upload_msg = "Uploads directory is not writable. Contact administrator.";
                        error_log("Upload error: Uploads directory not writable: $targetDir, Permissions: " . (is_dir($targetDir) ? substr(sprintf('%o', fileperms($targetDir)), -4) : 'directory does not exist'));
                    }
                }
            }
        } else {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => "File exceeds server size limit.",
                UPLOAD_ERR_FORM_SIZE => "File exceeds form size limit.",
                UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
                UPLOAD_ERR_NO_FILE => "No file was uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
            ];
            $upload_msg = $upload_errors[$_FILES['image']['error']] ?? "Unknown upload error: " . $_FILES['image']['error'];
            error_log("Upload error: Image upload error code: " . $_FILES['image']['error']);
        }

        // Proceed to database if upload was successful
        if (empty($upload_msg) && !empty($image_path)) {
            try {
                $pdo->beginTransaction();

                // Verify or create supplier_id
                $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ?");
                $stmt->execute([$supplier_id]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Supplier ID invalid, create new supplier
                    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'supplier'");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $stmt = $pdo->prepare("INSERT INTO suppliers (users_id, users_email) VALUES (?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $user['email']]);
                        $supplier_id = $pdo->lastInsertId();
                        $_SESSION['supplier']['supplier_id'] = $supplier_id;
                        logAction($pdo, $_SESSION['user_id'], "Created new supplier record: ID $supplier_id");
                        error_log("Created new supplier ID $supplier_id for user ID {$_SESSION['user_id']}");
                    } else {
                        $upload_msg = "User not found or not a supplier.";
                        error_log("Upload error: User ID {$_SESSION['user_id']} not found or not a supplier");
                        if (file_exists($targetFile)) {
                            unlink($targetFile);
                            error_log("Deleted orphaned file: $targetFile");
                        }
                        $pdo->rollBack();
                        throw new Exception("Invalid user data");
                    }
                }

                // Insert material with valid supplier_id
                $stmt = $pdo->prepare("INSERT INTO materials (supplier_id, name, city, description, price, quantity, image) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$supplier_id, $name, $city, $description, $price, $quantity, $image_path]);
                $material_id = $pdo->lastInsertId();

                $pdo->commit();
                logAction($pdo, $_SESSION['user_id'], "Uploaded material: $name (ID: $material_id)");
                $upload_msg = "Material uploaded successfully!";
                header("Location: admin.php?myuploads=true");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $upload_msg = "Database error: " . $e->getMessage();
                error_log("Upload error: Database error during material upload: " . $e->getMessage() . ", Supplier ID: $supplier_id");
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                    error_log("Deleted orphaned file: $targetFile");
                }
            }
        }
    }
}

// Handle Delete Material
if (isset($_GET['delete']) && isset($_SESSION['supplier']['supplier_id'])) {
    $material_id = (int) ($_GET['delete'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT name, image FROM materials WHERE id = ? AND supplier_id = ?");
        $stmt->execute([$material_id, $_SESSION['supplier']['supplier_id']]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material) {
            if (!empty($material['image']) && file_exists(__DIR__ . "/" . $material['image'])) {
                if (!unlink(__DIR__ . "/" . $material['image'])) {
                    error_log("Delete error: Failed to delete image: " . $material['image']);
                }
            }
            $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ? AND supplier_id = ?");
            $stmt->execute([$material_id, $_SESSION['supplier']['supplier_id']]);
            logAction($pdo, $_SESSION['user_id'], "Deleted material: " . $material['name']);
            header("Location: admin.php?myuploads=true");
            exit;
        } else {
            $error = "Material not found or not owned by this supplier.";
            error_log("Delete error: Material ID $material_id not found for supplier ID " . $_SESSION['supplier']['supplier_id']);
        }
    } catch (PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
        error_log("Delete error: " . $e->getMessage());
    }
}

// Handle Place Order
$order_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'], $_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && isset($_SESSION['supplier']['supplier_id'])) {
    $material_id = (int) ($_POST['material_id'] ?? 0);
    $quantity_ordered = (int) ($_POST['quantity_ordered'] ?? 0);

    if ($material_id <= 0 || $quantity_ordered <= 0) {
        $order_msg = "Invalid material ID or quantity.";
        error_log("Order error: Invalid material ID ($material_id) or quantity ($quantity_ordered)");
    } else {
        try {
            $stmt = $pdo->prepare("SELECT name, quantity, supplier_id FROM materials WHERE id = ? AND supplier_id = ?");
            $stmt->execute([$material_id, $_SESSION['supplier']['supplier_id']]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($material) {
                $current_quantity = $material['quantity'];
                $material_name = $material['name'];

                if ($quantity_ordered > $current_quantity) {
                    $order_msg = "Ordered quantity exceeds available stock.";
                    error_log("Order error: Ordered quantity ($quantity_ordered) exceeds stock ($current_quantity) for material ID $material_id");
                } else {
                    $new_quantity = $current_quantity - $quantity_ordered;
                    $stmt = $pdo->prepare("UPDATE materials SET quantity = ? WHERE id = ? AND supplier_id = ?");
                    $stmt->execute([$new_quantity, $material_id, $_SESSION['supplier']['supplier_id']]);

                    logAction($pdo, $_SESSION['user_id'], "Order placed for $quantity_ordered units of $material_name");
                    $order_msg = "Order processed successfully!";
                }
            } else {
                $order_msg = "Material not found or not owned by this supplier.";
                error_log("Order error: Material ID $material_id not found for supplier ID " . $_SESSION['supplier']['supplier_id']);
            }
        } catch (PDOException $e) {
            $order_msg = "Order processing failed: " . $e->getMessage();
            error_log("Order error: " . $e->getMessage());
        }
    }
}

// Handle Update Order Status
$status_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'], $_POST['order_id'], $_POST['status'], $_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && isset($_SESSION['supplier']['supplier_id'])) {
    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
    $valid_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    if ($order_id && in_array($status, $valid_statuses)) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN materials m ON oi.material_id = m.id
                WHERE o.id = ? AND m.supplier_id = ?
            ");
            $stmt->execute([$order_id, $_SESSION['supplier']['supplier_id']]);
            if ($stmt->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$status, $order_id]);
                logAction($pdo, $_SESSION['user_id'], "Updated order $order_id status to $status");
                $status_msg = "Order status updated successfully!";
            } else {
                $status_msg = "Order not found or not associated with your materials.";
                error_log("Status update error: Order ID $order_id not found for supplier ID " . $_SESSION['supplier']['supplier_id']);
            }
        } catch (PDOException $e) {
            $status_msg = "Error updating status: " . $e->getMessage();
            error_log("Status update error: " . $e->getMessage());
        }
    } else {
        $status_msg = "Invalid order ID or status.";
        error_log("Status update error: Invalid order ID ($order_id) or status ($status)");
    }
    header("Location: admin.php?vieworders=true");
    exit;
}

// Fetch supplier profile details
$profile = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'supplier'");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Profile fetch error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Supplier Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"/>
  <style>
    body { background-color: #f8f9fa; font-family: 'Roboto', sans-serif; }
    nav { 
      background-color: #8A2BE2; 
      color: white; 
      padding: 1.25rem 1.5rem; 
      display: flex; 
      justify-content: space-between;
      align-items: center; 
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .nav-items {
      display: flex;
      align-items: center;
    }
    .nav-right {
      display: flex;
      align-items: center;
    }
    .nav-link { 
      color: white; 
      margin: 0 20px; 
      text-decoration: none; 
      font-size: 1.25rem; 
      font-weight: 700;
      transition: color 0.2s, border-bottom 0.2s; 
      border-bottom: 2px solid transparent;
    }
    .nav-link:hover { 
      color: #e0e0e0; 
      border-bottom: 2px solid white;
    }
    .nav-title {
      font-size: 2rem; 
      font-family: 'Montserrat', sans-serif; 
      font-weight: 700; 
      letter-spacing: 1px;
      margin-right: 20px;
    }
    .card { margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .form-section { max-width: 400px; margin: auto; margin-top: 50px; }
    img.thumb { width: 120px; height: 90px; object-fit: cover; border-radius: 4px; }
    .placeholder-image { 
      width: 120px; 
      height: 90px; 
      background-color: #e0e0e0; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      color: #666; 
      font-size: 12px; 
      border-radius: 4px;
    }
    .material-select-wrapper {
      position: relative;
    }
    .material-select-wrapper select {
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      padding-right: 30px;
    }
    .material-select-wrapper::after {
      content: '▼';
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 12px;
      color: #495057;
      pointer-events: none;
    }
    .description-text {
      cursor: pointer;
      color: #007bff;
      text-decoration: underline;
    }
    .description-text:hover {
      color: #0056b3;
    }
    .profile-container {
      display: inline-flex;
      align-items: center;
    }
    .profile-icon {
      width: 36px;
      height: 36px;
      background-color: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #343a40;
      font-size: 18px;
      cursor: pointer;
      margin-left: 15px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .profile-icon:hover {
      transform: scale(1.05);
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    .profile-dropdown {
      display: none;
      position: absolute;
      top: 48px;
      right: 0;
      background-color: #ffffff;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 1000;
      min-width: 250px;
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .profile-dropdown.show {
      display: block;
      opacity: 1;
      transform: translateY(0);
    }
    .profile-dropdown .profile-header {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
      padding-bottom: 10px;
      border-bottom: 1px solid #e9ecef;
    }
    .profile-dropdown .profile-header .avatar {
      width: 40px;
      height: 40px;
      background-color: #007bff;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      margin-right: 10px;
    }
    .profile-dropdown .profile-header .name {
      font-size: 1.1rem;
      font-weight: 600;
      color: #343a40;
    }
    .profile-dropdown .profile-info {
      padding: 10px 0;
    }
    .profile-dropdown .profile-info p {
      margin: 0 0 10px 0;
      font-size: 0.95rem;
      color: #495057;
    }
    .profile-dropdown .profile-info p strong {
      color: #343a40;
    }
    .profile-dropdown .profile-info hr {
      margin: 10px 0;
      border-top: 1px solid #e9ecef;
    }
    .profile-dropdown .logout-link {
      display: block;
      margin-top: 10px;
      padding: 8px 12px;
      background-color: #dc3545;
      color: white;
      text-align: center;
      border-radius: 4px;
      text-decoration: none;
      font-size: 0.9rem;
      transition: background-color 0.2s;
    }
    .profile-dropdown .logout-link:hover {
      background-color: #c82333;
    }
    .btn-edit {
      background-color: #4dabf7;
      border-color: #4dabf7;
    }
    .btn-edit:hover {
      background-color: #1a91ff;
      border-color: #1a91ff;
    }
    .btn-delete {
      background-color: #e65b65;
      border-color: #e65b65;
    }
    .btn-delete:hover {
      background-color: #d94853;
      border-color: #d94853;
    }
    .table-container {
      overflow-x: auto;
    }
    .table th, .table td {
      vertical-align: middle;
      word-wrap: break-word;
      white-space: normal;
      max-width: 200px;
      padding: 8px;
    }
    .table-striped tbody tr:nth-of-type(odd) {
      background-color: #f9f9f9;
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

<nav>
  <div class="nav-items">
    <a href="admin.php" class="nav-link nav-title">Suppliers</a>
  </div>
  <div class="nav-right">
    <a href="material_analysis.php" class="nav-link">View Statistics</a>
    <a href="admin.php" class="nav-link">Upload Material</a>
    <a href="?myuploads=true" class="nav-link">My Uploaded Materials</a>
    <a href="?vieworders=true" class="nav-link">View Orders</a>
    <span class="profile-container">
      <span class="profile-icon" onclick="toggleProfileDropdown()">👤</span>
      <div class="profile-dropdown" id="profileDropdown">
        <?php if ($profile): ?>
          <div class="profile-header">
            <span class="avatar"><?php echo htmlspecialchars(substr($profile['name'], 0, 1)); ?></span>
            <span class="name"><?php echo htmlspecialchars($profile['name']); ?></span>
          </div>
          <div class="profile-info">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
            <hr>
            <a href="javascript:void(0);" onclick="confirmLogout()" class="logout-link">Logout</a>
          </div>
        <?php else: ?>
          <div class="profile-info">
            <p>Profile data unavailable</p>
            <hr>
            <a href="javascript:void(0);" onclick="confirmLogout()" class="logout-link">Logout</a>
          </div>
        <?php endif; ?>
      </div>
    </span>
  </div>
</nav>

<div class="container">
  <?php if ($order_msg): ?>
    <div class='alert alert-<?php echo strpos($order_msg, "success") !== false ? "info" : "danger"; ?>'>
      <?php echo htmlspecialchars($order_msg); ?>
    </div>
  <?php endif; ?>
  <?php if ($status_msg): ?>
    <div class='alert alert-<?php echo strpos($status_msg, "success") !== false ? "info" : "danger"; ?>'>
      <?php echo htmlspecialchars($status_msg); ?>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['home'])): ?>
    <h4 class="mt-4">Home</h4>
    <div class="card p-4">
      <p>Welcome to the Supplier Panel.</p>
    </div>
  <?php elseif (isset($_GET['vieworders'])): ?>
    <h4 class="mt-4">View Orders</h4>
    <div class="card p-4">
      <h5 class="mb-4">My Orders</h5>
      <div class="table-container">
        <table id="ordersTable" class="table table-striped table-bordered">
          <thead class="table-dark">
            <tr>
              <th>Order ID</th>
              <th>Customer Email</th>
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
            <?php
            try {
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
                    WHERE m.supplier_id = ?
                    GROUP BY o.id
                    ORDER BY o.created_at DESC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$_SESSION['supplier']['supplier_id']]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($orders as $order):
            ?>
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
                    <input type="hidden" name="update_order_status" value="true">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
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
            <?php
            } catch (PDOException $e) {
                echo "<div class='alert alert-danger'>Error fetching orders: " . htmlspecialchars($e->getMessage()) . "</div>";
                error_log("Orders fetch error: " . $e->getMessage());
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php elseif (isset($_GET['myuploads'])): ?>
    <h4 class="mt-4">My Uploaded Materials</h4>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="row">
      <?php
      try {
          $stmt = $pdo->prepare("SELECT id, name, city, description, price, quantity, image 
                                 FROM materials 
                                 WHERE supplier_id = ?");
          $stmt->execute([$_SESSION['supplier']['supplier_id']]);
          $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if (empty($materials)) {
              echo "<div class='alert alert-info'>No materials found for supplier ID: " . htmlspecialchars($_SESSION['supplier']['supplier_id']) . "</div>";
              error_log("My uploads: No materials found for supplier ID " . $_SESSION['supplier']['supplier_id']);
          }

          foreach ($materials as $mat):
      ?>
        <div class="col-md-4">
          <div class="card p-3">
            <?php if (!empty($mat['image']) && file_exists(__DIR__ . "/" . $mat['image'])): ?>
              <img src="<?php echo htmlspecialchars($mat['image']); ?>" class="thumb mb-2" alt="<?php echo htmlspecialchars($mat['name']); ?>">
            <?php else: ?>
              <div class="placeholder-image">No Image</div>
              <?php error_log("My uploads: Image missing or inaccessible for material ID {$mat['id']}: {$mat['image']}"); ?>
            <?php endif; ?>
            <p><strong><?php echo htmlspecialchars($mat['name']); ?></strong></p>
            <p>City: <?php echo htmlspecialchars($mat['city']); ?></p>
            <p>Price: $<?php echo htmlspecialchars($mat['price']); ?></p>
            <p>Quantity: <?php echo htmlspecialchars($mat['quantity']); ?></p>
            <?php if (!empty($mat['description'])): ?>
              <p><span class="description-text" onclick="alert('<?php echo htmlspecialchars(addslashes($mat['description'])); ?>')">View Description</span></p>
            <?php else: ?>
              <p>No Description</p>
            <?php endif; ?>
            <a href="edit_materials.php?id=<?php echo $mat['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
            <a href="admin.php?delete=<?php echo $mat['id']; ?>" class="btn btn-sm btn-delete" 
               onclick="return confirm('Do you want to delete this item?')">Delete</a>
          </div>
        </div>
      <?php endforeach; ?>
      <?php
      } catch (PDOException $e) {
          echo "<div class='alert alert-danger'>Error fetching materials: " . htmlspecialchars($e->getMessage()) . "</div>";
          error_log("My uploads fetch error: " . $e->getMessage());
      }
      ?>
    </div>
  <?php else: ?>
    <h4 class="mt-4">Upload Material</h4>
    <?php if ($upload_msg): ?>
      <div class='alert alert-<?php echo strpos($upload_msg, "success") !== false ? "info" : "danger"; ?>'>
        <?php echo htmlspecialchars($upload_msg); ?>
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="card p-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
      <label>Material Name</label>
      <div class="material-select-wrapper">
        <select name="name" class="form-control mb-3" required>
          <option disabled selected>Select material</option>
          <option>Steel</option>
          <option>Cement</option>
          <option>Concrete</option>
          <option>Ready Mix Concrete</option>
          <option>Binding Wires</option>
          <option>Stone</option>
          <option>Brick Blocks</option>
          <option>Aggregate</option>
          <option>Ceramics</option>
          <option>Plaster</option>
          <option>Pipes</option>
          <option>Roofing</option>
          <option>Plastic</option>
          <option>Glass</option>
          <option>Wood</option>
          <option>Flooring</option>
          <option>Sand</option>
          <option>Gravel</option>
          <option>Waterproofing Chemicals</option>
          <option>Clay Bricks</option>
          <option>Fly Ash Bricks</option>
          <option>Solid Concrete Blocks</option>
          <option>Hollow Blocks</option>
          <option>AAC Blocks</option>
          <option>Paint</option>
        </select>
      </div>
      <label>City</label>
      <div class="material-select-wrapper">
        <select name="city" class="form-control mb-3" required>
          <option disabled selected>Select city</option>
          <option>Karachi</option>
          <option>Lahore</option>
          <option>Faisalabad</option>
          <option>Rawalpindi</option>
          <option>Multan</option>
          <option>Hyderabad</option>
          <option>Gujranwala</option>
          <option>Peshawar</option>
          <option>Quetta</option>
          <option>Islamabad</option>
          <option>Sargodha</option>
          <option>Sialkot</option>
          <option>Bahawalpur</option>
          <option>Sukkur</option>
          <option>Larkana</option>
          <option>Sheikhupura</option>
          <option>Mirpur</option>
          <option>Rahim Yar Khan</option>
          <option>Gujrat</option>
          <option>Mardan</option>
          <option>Kasur</option>
          <option>Dera Ghazi Khan</option>
          <option>Sahiwal</option>
          <option>Nawabshah</option>
          <option>Okara</option>
          <option>Gilgit</option>
          <option>Chiniot</option>
          <option>Sadiqabad</option>
          <option>Burewala</option>
          <option>Jhelum</option>
          <option>Khanewal</option>
          <option>Hafizabad</option>
          <option>Kohat</option>
          <option>Muzaffargarh</option>
          <option>Abbottabad</option>
          <option>Mandi Bahauddin</option>
          <option>Jacobabad</option>
          <option>Jhang</option>
          <option>Khairpur</option>
          <option>Chishtian</option>
          <option>Daska</option>
          <option>Kamoke</option>
          <option>Attock</option>
          <option>Bhakkar</option>
          <option>Zhob</option>
          <option>Ghotki</option>
          <option>Mianwali</option>
          <option>Jamshoro</option>
          <option>Mansehra</option>
          <option>Tando Allahyar</option>
          <option>Nowshera</option>
        </select>
      </div>
      <label>Description</label>
      <textarea name="description" class="form-control mb-3"></textarea>
      <label>Price ($)</label>
      <input type="number" name="price" step="0.01" min="0.01" class="form-control mb-3" required>
      <label>Quantity</label>
      <input type="number" name="quantity" min="0" class="form-control mb-3" required>
      <label>Upload Image</label>
      <input type="file" name="image" class="form-control mb-3" accept="image/jpeg,image/png,image/gif" required>
      <button type="submit" name="upload_material" class="btn btn-primary">Upload</button>
    </form>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
function confirmLogout() {
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "logout.php";
  }
}

function toggleProfileDropdown() {
  const dropdown = document.getElementById('profileDropdown');
  dropdown.classList.toggle('show');
}

document.addEventListener('click', function(event) {
  const profileContainer = document.querySelector('.profile-container');
  if (!profileContainer.contains(event.target)) {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.remove('show');
  }
});

$(document).ready(function() {
  if ($('#ordersTable').length) {
    const table = $('#ordersTable').DataTable({
      "order": [[11, "desc"]],
      "pageLength": 10,
      "responsive": true,
      "scrollX": true,
      "dom": '<"row"<"col-sm-4 checkboxes-container"><"col-sm-4"f><"col-sm-4"l>>t<"row"<"col-sm-6"i><"col-sm-6"p>>',
      "initComplete": function() {
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

        $('.status-filter').on('change', function() {
          const selectedStatuses = $('.status-filter:checked').map(function() {
            return this.value;
          }).get();

          if (selectedStatuses.length > 0) {
            table.column(12).search(selectedStatuses.join('|'), true, false).draw();
          } else {
            table.column(12).search('').draw();
          }
        });

        $('#clearFilters').on('click', function() {
          $('.status-filter').prop('checked', false);
          table.column(12).search('').draw();
        });
      }
    });
  }
});
</script>

</body>
</html>