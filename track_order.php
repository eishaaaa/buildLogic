<?php
session_start();

// Include the connection file
require_once 'db_connect.php';

// Redirect to login if not authenticated or not a customer
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];

// Fetch users_id from users table
try {
   $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
   $stmt->execute([$email]);
   $user = $stmt->fetch(PDO::FETCH_ASSOC);
   if (!$user) {
      session_unset();
      session_destroy();
      header('Location: login.php');
      exit;
   }
   $users_id = $user['id'];
} catch (PDOException $e) {
   error_log('Fetch User ID Error: ' . $e->getMessage());
   die('<div class="alert alert-danger">An error occurred. Please try again later.</div>');
}

// Fetch customer orders
$orders = fetchCustomerOrders($pdo, users_id: $users_id);

/**
 * Fetch customer orders
 */
function fetchCustomerOrders($pdo, $users_id)
{
   try {
      $query = "SELECT id, total_price, status FROM orders WHERE users_id = ? ORDER BY created_at DESC";
      $stmt = $pdo->prepare($query);
      $stmt->execute([$users_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
   } catch (PDOException $e) {
      // Log database error
      error_log("Database error in fetchCustomerOrders: " . $e->getMessage());
      return [];
   }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Order Tracking</title>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
   <style>
      :root {
         --primary-color: #4361ee;
         --secondary-color: #3f37c9;
         --light-color: #f8f9fa;
         --dark-color: #212529;
         --border-radius: 8px;
         --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
         --transition: all 0.3s ease;
      }

      * {
         margin: 0;
         padding: 0;
         box-sizing: border-box;
      }

      body {
         font-family: 'Poppins', sans-serif;
         background-color: #f5f7fa;
         color: var(--dark-color);
         line-height: 1.6;
         padding: 20px;
      }

      .container {
         max-width: 800px;
         margin: 0 auto;
         background: white;
         padding: 30px;
         border-radius: var(--border-radius);
         box-shadow: var(--box-shadow);
      }

      h1 {
         color: var(--primary-color);
         margin-bottom: 30px;
         font-weight: 600;
         text-align: center;
         font-size: 2rem;
         position: relative;
         padding-bottom: 10px;
      }

      h1::after {
         content: '';
         position: absolute;
         bottom: 0;
         left: 50%;
         transform: translateX(-50%);
         width: 100px;
         height: 3px;
         background: var(--primary-color);
      }

      .filter-container {
         margin-bottom: 20px;
         display: flex;
         justify-content: flex-start;
      }

      .status-filter {
         padding: 8px 12px;
         font-size: 1rem;
         border-radius: var(--border-radius);
         border: 1px solid #ced4da;
         background-color: #fff;
         color: var(--dark-color);
         cursor: pointer;
         transition: var(--transition);
      }

      .status-filter:focus {
         outline: none;
         border-color: var(--primary-color);
         box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
      }

      .order-list {
         display: grid;
         gap: 15px;
      }

      .order-item {
         display: flex;
         justify-content: space-between;
         align-items: center;
         padding: 15px;
         background: white;
         border-radius: var(--border-radius);
         box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
         transition: var(--transition);
         flex-wrap: wrap;
      }

      .order-item:hover {
         transform: translateY(-2px);
         box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
      }

      .order-info {
         flex: 1;
      }

      .order-id {
         font-weight: 600;
         color: var(--primary-color);
      }

      .order-status {
         display: inline-block;
         padding: 3px 8px;
         border-radius: 12px;
         font-size: 0.8rem;
         font-weight: 500;
         text-transform: capitalize;
      }

      .status-pending {
         background-color: #fff3cd;
         color: #856404;
      }

      .status-processing {
         background-color: #cce5ff;
         color: #004085;
      }

      .status-shipped {
         background-color: #d4edda;
         color: #155724;
      }

      .status-delivered {
         background-color: #d1ecf1;
         color: #0c5460;
      }

      .track-btn {
         background-color: var(--primary-color);
         color: white;
         border: none;
         padding: 8px 15px;
         border-radius: var(--border-radius);
         cursor: pointer;
         transition: var(--transition);
         font-weight: 500;
      }

      .track-btn:hover {
         background-color: var(--secondary-color);
         transform: translateY(-1px);
      }

      .no-orders {
         text-align: center;
         color: #6c757d;
         padding: 20px;
      }

      @media (max-width: 768px) {
         .container {
            padding: 15px;
         }

         .order-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
         }
      }

      .progress-container {
         width: 100%;
         margin: 20px 0;
         position: relative;
      }

      .progress-bar {
         height: 4px;
         background-color: #e0e0e0;
         position: relative;
         border-radius: 2px;
      }

      .progress-fill {
         height: 100%;
         background-color: #4361ee;
         border-radius: 2px;
         transition: width 0.5s ease;
      }

      .progress-steps {
         display: flex;
         justify-content: space-between;
         margin-top: 15px;
         position: relative;
      }

      .progress-step {
         display: flex;
         flex-direction: column;
         align-items: center;
         position: relative;
         z-index: 1;
      }

      .step-icon {
         width: 24px;
         height: 24px;
         border-radius: 50%;
         background-color: #e0e0e0;
         display: flex;
         align-items: center;
         justify-content: center;
         color: white;
         font-size: 12px;
         margin-bottom: 5px;
      }

      .step-icon.active {
         background-color: #4361ee;
      }

      .step-icon.completed {
         background-color: #4cc9f0;
      }

      .step-label {
         font-size: 12px;
         color: #666;
         text-align: center;
         max-width: 80px;
      }

      .step-label.active {
         color: #4361ee;
         font-weight: 500;
      }

      .step-label.completed {
         color: #4cc9f0;
      }

      .tracking-details {
         width: 100%;
         display: none;
      }

      .tracking-number {
         margin-top: 20px;
         padding: 10px;
         background-color: #f5f5f5;
         border-radius: 4px;
         font-family: monospace;
         text-align: center;
         font-size: 14px;
      }

      .arrival-date {
         text-align: center;
         margin-bottom: 15px;
         font-weight: 500;
      }
   </style>
</head>

<body>
   <div class="container">
      <h1>Order Tracking</h1>

      <div class="filter-container">
         <select id="status-filter" class="status-filter" onchange="filterOrders()">
            <option value="all">All Orders</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
         </select>
      </div>

      <div class="order-list" id="order-list">
         <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
               <div class="order-item" data-status="<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                  <div class="order-info">
                     <span class="order-id">Order #<?php echo htmlspecialchars($order['id']); ?></span>
                     <span> - $<?php echo number_format($order['total_price'], 2); ?></span>
                     <span class="order-status status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                        <?php echo htmlspecialchars($order['status']); ?>
                     </span>
                  </div>
                  <button class="track-btn" onclick="trackOrder(<?php echo $order['id']; ?>, this)">
                     Track Order
                  </button>
                  <div class="tracking-details" id="tracking-details-<?php echo $order['id']; ?>">
                     <div class="progress-container">
                        <div class="progress-bar">
                           <div class="progress-fill" id="progress-fill-<?php echo $order['id']; ?>" style="width: 0%"></div>
                        </div>
                        <div class="progress-steps">
                           <div class="progress-step">
                              <div class="step-icon" id="step-1-<?php echo $order['id']; ?>">1</div>
                              <div class="step-label" id="label-1-<?php echo $order['id']; ?>">Order Processed</div>
                           </div>
                           <div class="progress-step">
                              <div class="step-icon" id="step-2-<?php echo $order['id']; ?>">2</div>
                              <div class="step-label" id="label-2-<?php echo $order['id']; ?>">Order Shipped</div>
                           </div>
                           <div class="progress-step">
                              <div class="step-icon" id="step-3-<?php echo $order['id']; ?>">3</div>
                              <div class="step-label" id="label-3-<?php echo $order['id']; ?>">In Transit</div>
                           </div>
                           <div class="progress-step">
                              <div class="step-icon" id="step-4-<?php echo $order['id']; ?>">4</div>
                              <div class="step-label" id="label-4-<?php echo $order['id']; ?>">Order Delivered</div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            <?php endforeach; ?>
         <?php else: ?>
            <p class="no-orders" id="no-orders">No orders found.</p>
         <?php endif; ?>
      </div>
   </div>
   <script>
      function trackOrder(orderId, button) {
         // Find the tracking details for this specific order
         const trackingDetails = document.getElementById(`tracking-details-${orderId}`);
         const isVisible = trackingDetails.style.display === 'block';

         // Hide all tracking details to ensure only one is visible at a time
         document.querySelectorAll('.tracking-details').forEach(detail => {
            detail.style.display = 'none';
         });

         // Toggle the visibility of the clicked order's tracking details
         trackingDetails.style.display = isVisible ? 'none' : 'block';

         // Get order data from PHP
         const orders = <?php echo json_encode($orders); ?>;
         const currentOrder = orders.find(order => order.id == orderId);

         // Map database status to progress stages
         const statusMap = {
            'pending': {
               progress: 25,
               activeStep: 1,
               label: 'Order Processed'
            },
            'processing': {
               progress: 25,
               activeStep: 1,
               label: 'Order Processed'
            },
            'shipped': {
               progress: 50,
               activeStep: 2,
               label: 'Order Shipped'
            },
            'cancelled': {
               progress: 75,
               activeStep: 3,
               label: 'Cancelled'
            },
            'delivered': {
               progress: 100,
               activeStep: 4,
               label: 'Order Delivered'
            }
         };

         // Default to first step if status not found
         const currentStatus = statusMap[currentOrder.status.toLowerCase()] || statusMap.pending;

         // Update label for step 3 based on status
         if (currentOrder.status.toLowerCase() === 'cancelled') {
            document.getElementById(`label-3-${orderId}`).textContent = 'Cancelled';
            document.getElementById(`step-3-${orderId}`).style.backgroundColor = '#dc3545'; // Red for cancelled
         } else {
            document.getElementById(`label-3-${orderId}`).textContent = 'In Transit';
            document.getElementById(`step-3-${orderId}`).style.backgroundColor = ''; // Reset color
         }

         // Update progress bar
         updateProgressBar(currentStatus, orderId);
      }

      function updateProgressBar(currentStatus, orderId) {
         // Update progress bar width
         document.getElementById(`progress-fill-${orderId}`).style.width = `${currentStatus.progress}%`;

         // Update step icons and labels
         for (let i = 1; i <= 4; i++) {
            const stepIcon = document.getElementById(`step-${i}-${orderId}`);
            const stepLabel = document.getElementById(`label-${i}-${orderId}`);

            // Reset all steps first
            stepIcon.classList.remove('active', 'completed');
            stepLabel.classList.remove('active', 'completed');

            if (i < currentStatus.activeStep) {
               // Completed steps
               stepIcon.classList.add('completed');
               stepLabel.classList.add('completed');
            } else if (i === currentStatus.activeStep) {
               // Current active step
               stepIcon.classList.add('active');
               stepLabel.classList.add('active');

               // Special handling for cancelled orders
               if (currentStatus.activeStep === 3 && currentStatus.label === 'Cancelled') {
                  stepIcon.style.backgroundColor = '#dc3545'; // Red color for cancelled
                  stepLabel.style.color = '#dc3545';
               }
            }
         }

         // Show rating option only if delivered (not for cancelled orders)
         if (currentStatus.activeStep === 4 && currentStatus.label === 'Order Delivered') {
            showRatingOption(orderId);
         }
      }

      function showRatingOption(orderId) {
         // Remove existing link if any
         const existingLink = document.querySelector(`#tracking-details-${orderId} a`);
         if (existingLink) existingLink.remove();

         // Create rating link
         const rateLink = document.createElement('a');
         rateLink.href = `supplier_rating.php?order_id=${orderId}`;
         rateLink.textContent = 'Rate this order';
         rateLink.style.display = 'block';
         rateLink.style.marginTop = '15px';
         rateLink.style.textAlign = 'center';
         rateLink.style.color = '#4361ee';
         rateLink.style.fontWeight = '500';

         document.getElementById(`tracking-details-${orderId}`).appendChild(rateLink);
      }

      function filterOrders() {
         const filterValue = document.getElementById('status-filter').value.toLowerCase();
         const orderItems = document.querySelectorAll('.order-item');
         const orderList = document.getElementById('order-list');
         let noOrdersMessage = document.getElementById('no-orders');

         let visibleOrders = 0;

         orderItems.forEach(item => {
            const status = item.getAttribute('data-status');
            if (filterValue === 'all' || status === filterValue) {
               item.style.display = 'flex';
               visibleOrders++;
            } else {
               item.style.display = 'none';
            }
         });

         // Remove existing no-orders message if it exists
         if (noOrdersMessage) {
            noOrdersMessage.remove();
         }

         // Create new no-orders message if no orders are visible
         if (visibleOrders === 0) {
            noOrdersMessage = document.createElement('p');
            noOrdersMessage.id = 'no-orders';
            noOrdersMessage.className = 'no-orders';
            noOrdersMessage.textContent = filterValue === 'all' ? 'No orders found.' : `No ${filterValue} orders found.`;
            orderList.appendChild(noOrdersMessage);
         }
      }

      // Initialize filter on page load
      document.addEventListener('DOMContentLoaded', filterOrders);
   </script>
</body>

</html>