<?php
session_start();
require_once 'db_connect.php';

// Redirect if not logged in or not a supplier
if (!isset($_SESSION['user_id']) || !isset($_SESSION['supplier']['supplier_id'])) {
    header("Location: admin.php");
    exit;
}

// Validate material ID
$material_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($material_id <= 0) {
    echo "Invalid request.";
    exit;
}

// Fetch material data
try {
    $stmt = $pdo->prepare("SELECT id, name, city, description, price, quantity, image 
                           FROM materials 
                           WHERE id = ? AND supplier_id = ?");
    $stmt->execute([$material_id, $_SESSION['supplier']['supplier_id']]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        echo "Material not found or access denied.";
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    error_log("Error fetching material: " . $e->getMessage());
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

// Handle Update
if (isset($_POST['update_material'])) {
    $name = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0.0);
    $quantity = (int) ($_POST['quantity'] ?? 0);

    $image_path = $material['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Validate file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPEG, PNG, or GIF images are allowed.";
        } elseif ($file_size > $max_size) {
            $error = "Image size must be less than 5MB.";
        } else {
            $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/Uploads/';
            $relativePath = '/Uploads/';
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $error = "Failed to create Uploads directory.";
                    error_log("Failed to create directory: $targetDir");
                }
            }
            if (is_writable($targetDir)) {
                $fileName = basename($_FILES["image"]["name"]);
                $fileName = preg_replace("/[^A-Za-z0-9._-]/", "", $fileName); // Sanitize filename
                $targetFile = $targetDir . time() . "_" . $fileName;
                $image_path = $relativePath . basename($targetFile);
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                    // Delete old image if it exists
                    if (!empty($image_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $material['image'])) {
                        if (!unlink($_SERVER['DOCUMENT_ROOT'] . $material['image'])) {
                            error_log("Failed to delete old image: " . $material['image']);
                        }
                    }
                    if (!file_exists($targetFile)) {
                        $error = "Image saved but not found on server.";
                        error_log("Image not found after upload: $targetFile");
                    }
                } else {
                    $error = "Failed to upload image. Check directory permissions.";
                    error_log("Failed to move uploaded file to: $targetFile");
                }
            } else {
                $error = "Uploads directory is not writable.";
                error_log("Uploads directory not writable: $targetDir");
            }
        }
    }

    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE materials 
                                   SET name = ?, city = ?, description = ?, price = ?, quantity = ?, image = ? 
                                   WHERE id = ? AND supplier_id = ?");
            $stmt->execute([$name, $city, $description, $price, $quantity, $image_path, 
                            $material_id, $_SESSION['supplier']['supplier_id']]);
            logAction($pdo, $_SESSION['user_id'], "Updated material: $name");
            header("Location: admin.php?myuploads=true");
            exit;
        } catch (PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
            error_log("Database error during material update: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Material</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .placeholder-image { 
      width: 100px; 
      height: 70px; 
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
    .thumb { 
      width: 100px; 
      height: 70px; 
      object-fit: cover; 
      border-radius: 4px; 
    }
  </style>
</head>
<body>
<div class="container mt-5">
  <h3>Edit Material</h3>
  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="card p-4">
    <label>Material Name</label>
    <div class="material-select-wrapper">
      <select name="name" class="form-control mb-3" required>
        <option disabled>Select material</option>
        <option <?= $material['name'] == 'Steel' ? 'selected' : '' ?>>Steel</option>
        <option <?= $material['name'] == 'Cement' ? 'selected' : '' ?>>Cement</option>
        <option <?= $material['name'] == 'Concrete' ? 'selected' : '' ?>>Concrete</option>
        <option <?= $material['name'] == 'Ready Mix Concrete' ? 'selected' : '' ?>>Ready Mix Concrete</option>
        <option <?= $material['name'] == 'Binding Wires' ? 'selected' : '' ?>>Binding Wires</option>
        <option <?= $material['name'] == 'Stone' ? 'selected' : '' ?>>Stone</option>
        <option <?= $material['name'] == 'Brick Blocks' ? 'selected' : '' ?>>Brick Blocks</option>
        <option <?= $material['name'] == 'Aggregate' ? 'selected' : '' ?>>Aggregate</option>
        <option <?= $material['name'] == 'Ceramics' ? 'selected' : '' ?>>Ceramics</option>
        <option <?= $material['name'] == 'Plaster' ? 'selected' : '' ?>>Plaster</option>
        <option <?= $material['name'] == 'Pipes' ? 'selected' : '' ?>>Pipes</option>
        <option <?= $material['name'] == 'Roofing' ? 'selected' : '' ?>>Roofing</option>
        <option <?= $material['name'] == 'Plastic' ? 'selected' : '' ?>>Plastic</option>
        <option <?= $material['name'] == 'Glass' ? 'selected' : '' ?>>Glass</option>
        <option <?= $material['name'] == 'Wood' ? 'selected' : '' ?>>Wood</option>
        <option <?= $material['name'] == 'Flooring' ? 'selected' : '' ?>>Flooring</option>
        <option <?= $material['name'] == 'Sand' ? 'selected' : '' ?>>Sand</option>
        <option <?= $material['name'] == 'Gravel' ? 'selected' : '' ?>>Gravel</option>
        <option <?= $material['name'] == 'Waterproofing Chemicals' ? 'selected' : '' ?>>Waterproofing Chemicals</option>
        <option <?= $material['name'] == 'Clay Bricks' ? 'selected' : '' ?>>Clay Bricks</option>
        <option <?= $material['name'] == 'Fly Ash Bricks' ? 'selected' : '' ?>>Fly Ash Bricks</option>
        <option <?= $material['name'] == 'Solid Concrete Blocks' ? 'selected' : '' ?>>Solid Concrete Blocks</option>
        <option <?= $material['name'] == 'Hollow Blocks' ? 'selected' : '' ?>>Hollow Blocks</option>
        <option <?= $material['name'] == 'AAC Blocks' ? 'selected' : '' ?>>AAC Blocks</option>
        <option <?= $material['name'] == 'Paint' ? 'selected' : '' ?>>Paint</option>
      </select>
    </div>
    <label>City</label>
    <div class="material-select-wrapper">
      <select name="city" class="form-control mb-3" required>
        <option disabled>Select city</option>
        <option <?= $material['city'] == 'Karachi' ? 'selected' : '' ?>>Karachi</option>
        <option <?= $material['city'] == 'Lahore' ? 'selected' : '' ?>>Lahore</option>
        <option <?= $material['city'] == 'Faisalabad' ? 'selected' : '' ?>>Faisalabad</option>
        <option <?= $material['city'] == 'Rawalpindi' ? 'selected' : '' ?>>Rawalpindi</option>
        <option <?= $material['city'] == 'Multan' ? 'selected' : '' ?>>Multan</option>
        <option <?= $material['city'] == 'Hyderabad' ? 'selected' : '' ?>>Hyderabad</option>
        <option <?= $material['city'] == 'Gujranwala' ? 'selected' : '' ?>>Gujranwala</option>
        <option <?= $material['city'] == 'Peshawar' ? 'selected' : '' ?>>Peshawar</option>
        <option <?= $material['city'] == 'Quetta' ? 'selected' : '' ?>>Quetta</option>
        <option <?= $material['city'] == 'Islamabad' ? 'selected' : '' ?>>Islamabad</option>
        <option <?= $material['city'] == 'Sargodha' ? 'selected' : '' ?>>Sargodha</option>
        <option <?= $material['city'] == 'Sialkot' ? 'selected' : '' ?>>Sialkot</option>
        <option <?= $material['city'] == 'Bahawalpur' ? 'selected' : '' ?>>Bahawalpur</option>
        <option <?= $material['city'] == 'Sukkur' ? 'selected' : '' ?>>Sukkur</option>
        <option <?= $material['city'] == 'Larkana' ? 'selected' : '' ?>>Larkana</option>
        <option <?= $material['city'] == 'Sheikhupura' ? 'selected' : '' ?>>Sheikhupura</option>
        <option <?= $material['city'] == 'Mirpur' ? 'selected' : '' ?>>Mirpur</option>
        <option <?= $material['city'] == 'Rahim Yar Khan' ? 'selected' : '' ?>>Rahim Yar Khan</option>
        <option <?= $material['city'] == 'Gujrat' ? 'selected' : '' ?>>Gujrat</option>
        <option <?= $material['city'] == 'Mardan' ? 'selected' : '' ?>>Mardan</option>
        <option <?= $material['city'] == 'Kasur' ? 'selected' : '' ?>>Kasur</option>
        <option <?= $material['city'] == 'Dera Ghazi Khan' ? 'selected' : '' ?>>Dera Ghazi Khan</option>
        <option <?= $material['city'] == 'Sahiwal' ? 'selected' : '' ?>>Sahiwal</option>
        <option <?= $material['city'] == 'Nawabshah' ? 'selected' : '' ?>>Nawabshah</option>
        <option <?= $material['city'] == 'Okara' ? 'selected' : '' ?>>Okara</option>
        <option <?= $material['city'] == 'Gilgit' ? 'selected' : '' ?>>Gilgit</option>
        <option <?= $material['city'] == 'Chiniot' ? 'selected' : '' ?>>Chiniot</option>
        <option <?= $material['city'] == 'Sadiqabad' ? 'selected' : '' ?>>Sadiqabad</option>
        <option <?= $material['city'] == 'Burewala' ? 'selected' : '' ?>>Burewala</option>
        <option <?= $material['city'] == 'Jhelum' ? 'selected' : '' ?>>Jhelum</option>
        <option <?= $material['city'] == 'Khanewal' ? 'selected' : '' ?>>Khanewal</option>
        <option <?= $material['city'] == 'Hafizabad' ? 'selected' : '' ?>>Hafizabad</option>
        <option <?= $material['city'] == 'Kohat' ? 'selected' : '' ?>>Kohat</option>
        <option <?= $material['city'] == 'Muzaffargarh' ? 'selected' : '' ?>>Muzaffargarh</option>
        <option <?= $material['city'] == 'Abbottabad' ? 'selected' : '' ?>>Abbottabad</option>
        <option <?= $material['city'] == 'Mandi Bahauddin' ? 'selected' : '' ?>>Mandi Bahauddin</option>
        <option <?= $material['city'] == 'Jacobabad' ? 'selected' : '' ?>>Jacobabad</option>
        <option <?= $material['city'] == 'Jhang' ? 'selected' : '' ?>>Jhang</option>
        <option <?= $material['city'] == 'Khairpur' ? 'selected' : '' ?>>Khairpur</option>
        <option <?= $material['city'] == 'Chishtian' ? 'selected' : '' ?>>Chishtian</option>
        <option <?= $material['city'] == 'Daska' ? 'selected' : '' ?>>Daska</option>
        <option <?= $material['city'] == 'Kamoke' ? 'selected' : '' ?>>Kamoke</option>
        <option <?= $material['city'] == 'Attock' ? 'selected' : '' ?>>Attock</option>
        <option <?= $material['city'] == 'Bhakkar' ? 'selected' : '' ?>>Bhakkar</option>
        <option <?= $material['city'] == 'Zhob' ? 'selected' : '' ?>>Zhob</option>
        <option <?= $material['city'] == 'Ghotki' ? 'selected' : '' ?>>Ghotki</option>
        <option <?= $material['city'] == 'Mianwali' ? 'selected' : '' ?>>Mianwali</option>
        <option <?= $material['city'] == 'Jamshoro' ? 'selected' : '' ?>>Jamshoro</option>
        <option <?= $material['city'] == 'Mansehra' ? 'selected' : '' ?>>Mansehra</option>
        <option <?= $material['city'] == 'Tando Allahyar' ? 'selected' : '' ?>>Tando Allahyar</option>
        <option <?= $material['city'] == 'Nowshera' ? 'selected' : '' ?>>Nowshera</option>
      </select>
    </div>
    <label>Description</label>
    <textarea name="description" class="form-control mb-3"><?= htmlspecialchars($material['description'] ?? '') ?></textarea>
    <label>Price ($)</label>
    <input type="number" name="price" step="0.01" class="form-control mb-3" 
           value="<?= htmlspecialchars($material['price']) ?>" required>
    <label>Quantity</label>
    <input type="number" name="quantity" class="form-control mb-3" 
           value="<?= htmlspecialchars($material['quantity']) ?>" required>
    <label>Current Image</label><br>
    <?php if (!empty($material['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $material['image'])): ?>
      <img src="<?= htmlspecialchars($material['image']) ?>" class="thumb mb-2" alt="<?= htmlspecialchars($material['name']) ?>">
    <?php else: ?>
      <div class="placeholder-image">No Image</div>
      <?php error_log("Edit material: Image missing or inaccessible for material ID {$material['id']}: {$material['image']}"); ?>
    <?php endif; ?>
    <label>Upload New Image (optional)</label>
    <input type="file" name="image" class="form-control mb-3" accept="image/jpeg,image/png,image/gif">
    <button type="submit" name="update_material" class="btn btn-success">Update</button>
    <a href="admin.php?myuploads=true" class="btn btn-secondary">Cancel</a>
  </form>
</div>
</body>
</html>
