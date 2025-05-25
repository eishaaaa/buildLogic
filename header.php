<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Home Design Tool</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
    <style>
        .site-logo {
            width: 180px;
            height: 40px;
            object-fit: contain;
            border-radius: 0;
        }

        .header-site-info h1 {
            font-family: Allerta, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            margin: 0;
            color: white;
        }



        .btn-personId {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            color: white;
        }

        .btn-personId:hover {
            background: linear-gradient(45deg, #1e7e34, #155d27);
            transform: scale(1.05);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
        }

        header {
            background-color: #4CAF50;
            color: white;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }

        /* Hero Section */
        .hero {
            position: relative;
            height: 300px;
            overflow: hidden;
            background-color: #e0e0e0;
            border-radius: 20px;
            padding: 20px;
            margin: 20px auto;
            max-width: 90%;
        }

        .swiper {
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }

        .swiper-slide {
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
        }

        .hero h1 {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-align: center;
            font-size: 2.8rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            opacity: 0;
            transition: opacity 0.5s, transform 0.5s;
        }

        /* Fade Animations */
        .fadeInUp {
            opacity: 1;
            transform: translate(-50%, -50%);
            animation: fadeInUp 1s forwards;
        }

        .fadeOutDown {
            opacity: 0;
            transform: translate(-50%, 20%);
            animation: fadeOutDown 0.5s forwards;
        }

        @keyframes fadeInUp {
            0% {
                transform: translate(-50%, -60%);
                opacity: 0;
            }

            100% {
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }

        @keyframes fadeOutDown {
            0% {
                transform: translate(-50%, -50%);
                opacity: 1;
            }

            100% {
                transform: translate(-50%, 20%);
                opacity: 0;
            }
        }

        /* Swiper Navigation Arrows */
        .swiper-button-next,
        .swiper-button-prev {
            color: white;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 14px;
        }

        .feature-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            text-align: center;
            margin-bottom: 20px;
        }

        .feature-card:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .feature-icon {
            width: 120px;
            height: 80px;
            margin-bottom: 15px;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #0056b3, #003f7f);
            transform: scale(1.05);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
        }

        .hidden-button {
            display: none;
        }

        .btn-custom {
            font-size: 16px;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 25px;
            border: none;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .hero {

            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .hero h1 {
            font-size: 2.8rem;
            font-weight: bold;
        }

        .gallery-item img {
            border-radius: 15px;
            transition: transform 0.3s;
        }

        .gallery-item img:hover {
            transform: scale(1.05);
        }

        .faq-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background-color: #f8f9fa;
        }

        .faq-image {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .faq-image img {
            width: 80%;
            max-width: 400px;
        }

        .faq-content {
            flex: 1;
            padding: 20px;
        }

        .faq-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .faq-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .faq-item {
            background-color: #0e2a47;
            color: white;
            margin-bottom: 10px;
            border-radius: 5px;
            overflow: hidden;
        }

        .faq-question {
            background-color: #0e2a47;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-size: 16px;
            border: none;
            width: 100%;
            text-align: left;
        }

        .faq-question:hover {
            background-color: #0c1e34;
        }

        .faq-answer {
            background-color: white;
            color: black;
            padding: 15px;
            display: none;
        }

        .faq-question .icon {
            font-size: 18px;
        }

        .social-icons {
            margin-top: 10px;
        }

        .social-icons a {
            font-size: 28px;
            /* Icon size */
            margin: 0 15px;
            text-decoration: none;
            transition: transform 0.3s ease-in-out;
        }

        /* Brand Colors */

        /* Testimonials Section */
        .testimonials {
            background-color: #343a40;
            padding: 60px 0;
        }

        .testimonial-name {
            font-weight: bold;
            margin-top: 15px;
        }

        .testimonial-role {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .testimonial-text {
            font-style: italic;
            font-size: 1rem;
        }

        .custom-arrow {
            color: black;
            font-size: 1.5rem;
            background: none;
            border: none;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            cursor: pointer;
        }

        .carousel-control-prev {
            left: -30px;
        }

        .carousel-control-next {
            right: -30px;
        }

        /* Styling indicators */
        .carousel-indicators button {
            background-color: black;
        }

        /* Testimonial Card Styling */
        .testimonial-card {
            max-width: 100%;
            border-radius: 20px;
            transition: 0.3s ease-in-out;
        }

        .testimonial-card:hover {
            transform: scale(1.02);
        }

        .testimonial-header img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border: 3px solid #007bff;
            padding: 5px;
        }

        footer {
            background-color: #e0e0e0;
            color: #333;
            padding: 40px 0;
        }

        footer a {
            color: #007bff;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        .footer {
            background-color: #f1f1f1;
            /* light grey */
            color: #333333;
            padding: 60px 0 30px;
            font-family: 'Segoe UI', sans-serif;
        }

        .footer h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #222222;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }

        .footer-links i {
            margin-right: 10px;
            color: #ff6f00;
        }

        .footer-links a {
            text-decoration: none;
            color: #444444;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: #ff6f00;
        }

        .footer-bottom {
            margin-top: 40px;
            border-top: 1px solid #ccc;
            padding-top: 20px;
            font-size: 14px;
        }

        .instagram {
            color: #E4405F;
            /* Instagram Pink-Red */
        }

        .facebook {
            color: #1877F2;
            /* Facebook Blue */
        }

        .pinterest {
            color: #E60023;
            /* Pinterest Red */
        }

        /* Hover Effects */
        .instagram:hover {
            color: #C13584;
            /* Instagram Deep Pink */
            transform: scale(1.2);
        }

        .facebook:hover {
            color: #0E5A99;
            /* Facebook Dark Blue */
            transform: scale(1.2);
        }

        .pinterest:hover {
            color: #BD081C;
            /* Pinterest Dark Red */
            transform: scale(1.2);
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: auto;

        }

        .auth-container fieldset {
            border: none;
        }

        h3 {
            font-weight: 600;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
        }

        .btn-block {
            padding: 10px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
        }

        .text-muted {
            font-size: 0.9rem;
        }

        .gap-1 {
            gap: 0.5rem;
        }

        .gap-2 {
            gap: 1rem;
        }
    </style>
</head>

<body>
    <header>
        <div class="container-fluid d-flex justify-content-between  py-3">

            <div class="d-flex align-items-center">
                <img src="images/logo.png" alt="logo" class="site-logo me-2">
                <div class="header-site-info">
                    <h1 class="h4 mb-0">BrickLogic</h1>
                </div>
            </div>

            <!-- Right: Navigation + Auth Buttons -->
            <div class="d-flex align-items-center">
                <nav class="navbar navbar-expand-md navbar-light p-0">
                    <div class="container-fluid p-0">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav me-3">
                                <li class="nav-item"><a class="nav-link text-white" href="index.php#features">Features</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="index.php#gallery">Gallery</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="pricing.php">Pricing</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="dashbord.php">Dashboard</a></li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <?php if (isset($_SESSION['email']) && isset($_SESSION['name'])): ?>
                    <a class="btn btn-custom btn-login dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-hidden="true" title="User Menu">
                        <?php echo htmlspecialchars(substr($_SESSION['name'], 0, 1)); ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                <?php else: ?>
                    <a href="login.php" class="btn btn-custom btn-login" title="Login">
                        <i class="fa fa-user" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>

        </div>
        </div>
    </header>
</body>

</html>