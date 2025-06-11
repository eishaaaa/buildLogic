<?php
session_start();
require_once 'db_connect.php'; // Your PDO database connection file

$stmt = $pdo->query("SELECT id, name, monthly_price, yearly_price, features, design_limit FROM pricing_plans");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php'; // Include your header file if needed
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pricing Plans</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    .plan-card {
      background: linear-gradient(145deg, #0056b3, #003f7f);
      border: 1px solid #2d3748;
      border-radius: 10px;
      color: #e2e8f0;
      transition: all 0.4s ease;
      transform: perspective(1px) translateZ(0);
      max-width: 380px;
      margin: 0 auto;
    }

    .plan-card:hover {
      transform: scale(1.05);
      box-shadow: 0 10px 20px rgba(40, 167, 69, 0.5);
      border-color: #28a745;
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
      }

      70% {
        box-shadow: 0 0 0 15px rgba(40, 167, 69, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
      }
    }

    .plan-title {
      color: #28a745;
      font-weight: bold;
    }

    .plan-price {
      font-size: 2rem;
      font-weight: bold;
    }

    .plan-duration {
      font-size: 1rem;
    }

    hr {
      border-color: #2d3748;
    }

    @media screen and (max-width: 768px) {
      .col-sm-4 {
        text-align: center;
        margin: 25px 0;
      }
    }

    .row {
      margin-top: 80px;
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="text-center">
      <h2>Pricing</h2>
      <h4>Choose a payment plan that works for you</h4>
      <?php if (isset($_SESSION['error'])): ?>
        <p style="color: red;"><?php echo htmlspecialchars($_SESSION['error']);
                                unset($_SESSION['error']); ?></p>
      <?php endif; ?>
      <?php if (isset($_SESSION['success'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_SESSION['success']);
                                  unset($_SESSION['success']); ?></p>
      <?php endif; ?>
    </div>

    <div class="row">
      <?php foreach ($plans as $plan): ?>
        <div class="col-sm-4 col-xs-12">
          <div class="card plan-card text-center p-4">
            <h2 class="plan-title"><?php echo htmlspecialchars($plan['name']); ?></h2>
            <h1 class="plan-price">Rs.<?php echo number_format($plan['monthly_price'], 2); ?>
              <span class="plan-duration">/Monthly</span>
            </h1>
            <hr>
            <p>
              <?php
              if ($plan['name'] === 'Basic') {
                echo 'This is for you that are beginning to explore floorplan designing';
              } elseif ($plan['name'] === 'Pro') {
                echo 'All Basic features plus';
              } else {
                echo 'Everything from Pro plus';
              }
              ?>
            </p>
            <ul class="list-unstyled mt-3 mb-4 text-start" style="text-align: left;">
              <?php
              // Parse features (assuming comma-separated in DB)
              $features = explode(',', $plan['features']);
              foreach ($features as $feature): ?>
                <li>✅ <?php echo htmlspecialchars(trim($feature)); ?></li>
              <?php endforeach; ?>
              <li>✅ Save up to <?php echo $plan['design_limit']; ?> designs</li>
            </ul>
            <form method="POST" action="subscribe.php">
              <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
              <button type="submit" class="btn btn-success btn-block">Select</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>

</html>
