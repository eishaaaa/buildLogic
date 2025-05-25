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
      /* NEW */
      margin: 0 auto;
      /* Center it horizontally */
    }

    .plan-card:hover {
      transform: scale(1.05);
      /* Slight zoom */
      box-shadow: 0 10px 20px rgba(40, 167, 69, 0.5);
      /* Green glow */
      border-color: #28a745;
      animation: pulse 1.5s infinite;
    }

    /* Smooth pulsing animation */
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
      /* Space between "Choose Your Plan" and the cards */
    }
  </style>
</head>

<body>

  <!-- Container (Pricing Section) -->
  <div class="container-fluid">
    <div class="text-center">
      <h2>Pricing</h2>
      <h4>Choose a payment plan that works for you</h4>
    </div>

    <div class="row">
      <!-- Basic Plan -->
      <div class="col-sm-4 col-xs-12">
        <div class="card plan-card text-center p-4">
          <h2 class="plan-title">Free</h2>
          <h1 class="plan-price">$0<span class="plan-duration">/Forever</span></h1>
          <hr>
          <p>This is for you that are beginning to explore floorplan designing</p>
          <ul class="list-unstyled mt-3 mb-4 text-start" style="text-align: left;">
            <li>✅ Limited templates (2–3 floorplan styles)</li>
            <li>✅ Adjust room sizes only</li>
            <li>✅ Edit basic elements (rectangles only)</li>
            <li>✅ Max 1 project saved</li>
            <li>✅ Watermark on export</li>
            <li>✅ High-quality PDF export</li>
          </ul>
          <form method="POST" action="subscribe.php">
            <input type="hidden" name="package" value="Free">
            <button type="submit" class="btn btn-success btn-block">Select</button>
          </form>
        </div>
      </div>
      <!-- Pro Plan -->
      <div class="col-sm-4 col-xs-12">
        <div class="card plan-card text-center p-4">
          <h2 class="plan-title">Pro</h2>
          <h1 class="plan-price">Rs.750<span class="plan-duration">/Monthly</span></h1>
          <hr>
          <p>All basic features plus</p>
          <ul class="list-unstyled mt-3 mb-4 text-start" style="text-align: left;">
            <li>✅ Full design edit access (add rooms, walls, circles, etc.) </li>
            <li>✅ Upload custom floorplan backgrounds </li>
            <li>✅ Save up to 20 projects</li>
            <li>✅ Access premium furniture & decor elements</li>
            <li>✅ High-quality PDF export</li>
          </ul>
          <form method="POST" action="subscribe.php">
            <input type="hidden" name="package" value="Pro">
            <button type="submit" class="btn btn-success btn-block">Select</button>
          </form>
        </div>
      </div>
      <!-- Premium Plan -->
      <div class="col-sm-4 col-xs-12">
        <div class="card plan-card text-center p-4">
          <h2 class="plan-title">Premium</h2>
          <h1 class="plan-price">Rs.1000<span class="plan-duration">/monthly</span></h1>
          <hr>
          <p>Everything from Pro plus</p>
          <ul class="list-unstyled mt-3 mb-4 text-start" style="text-align: left;">
            <li>✅ Team collaboration (invite others to edit)</li>
            <li>✅ Unlimited project saves</li>
            <li>✅ Custom branding (user can add their logo)</li>
            <li>✅ Advanced measurements (dimension lines, scale setting)</li>
            <li>✅ Priority support</li>
            <li>✅ Import external 3D content </li>
          </ul>
          <form method="POST" action="subscribe.php">
            <input type="hidden" name="package" value="Premium">
            <button type="submit" class="btn btn-success btn-block">Select</button>
          </form>
        </div>
      </div>
    </div>
  </div>

</body>

</html>