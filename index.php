<?php
session_start();
include('assets/lib/openconn.php');

// Update last activity for logged-in user
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $currentTime = date('Y-m-d H:i:s');
    $updateQuery = "UPDATE users SET last_activity = ? WHERE user_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $currentTime, $userId);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('assets/includes/link-css.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Connector - Save Lives Through Donation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #E60026;
            --secondary-color: #1F2A44;
            --text-color: #2D3748;
            --accent-color: #FEE2E2;
            --white: #FFFFFF;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
            line-height: 1.7;
            background: var(--white);
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #730a0d 0%, #af1d1d 100%);
            color: var(--white);
            padding: 50px 0;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            opacity: 0.2;
        }

        .hero-content {
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            animation: fadeInUp 1s ease-out;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 1.25rem;
            font-weight: 300;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            color: #fff;
        }

        .hero-buttons .btn {
            padding: 14px 32px;
            font-size: 1.1rem;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: var(--white);
            color: var(--primary-color);
            border: none;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            background: var(--accent-color);
            color: var(--primary-color);
            transform: translateY(-4px);
        }

        .btn-outline-light {
            border: 2px solid var(--white);
            color: var(--white);
            background: transparent;
        }

        .btn-outline-light:hover {
            background: var(--white);
            color: var(--primary-color);
            transform: translateY(-4px);
        }

        /* Purpose Section */
        .purpose-section {
            padding: 100px 0;
            background: var(--accent-color);
        }

        .purpose-section h2 {
            font-size: 2.8rem;
            font-weight: 600;
            color: var(--secondary-color);
            text-align: center;
            margin-bottom: 4rem;
            animation: fadeIn 1s ease-out;
        }

        .purpose-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            transition: all 0.4s ease;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .purpose-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .purpose-card i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .purpose-card:hover i {
            transform: scale(1.2);
        }

        .purpose-card h3 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .purpose-card p {
            font-size: 1rem;
            color: #4B5563;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #374151 100%);
            color: var(--white);
            padding: 80px 0;
            text-align: center;
            position: relative;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            animation: fadeIn 1s ease-out;
        }

        .cta-section .btn {
            padding: 14px 40px;
            font-size: 1.2rem;
            border-radius: 50px;
            background: var(--primary-color);
            color: var(--white);
            font-weight: 500;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .cta-section .btn:hover {
            background: var(--white);
            color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .hero-content h1 {
                font-size: 2.8rem;
            }

            .hero-content p {
                font-size: 1.1rem;
            }

            .purpose-section h2 {
                font-size: 2.3rem;
            }

            .cta-section h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }

            .hero-content h1 {
                font-size: 2.2rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .hero-buttons .btn {
                padding: 12px 24px;
                font-size: 1rem;
                margin: 8px;
            }

            .purpose-card {
                padding: 2rem;
            }

            .purpose-card h3 {
                font-size: 1.4rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                min-height: 80vh;
                padding: 60px 0;
            }

            .hero-content h1 {
                font-size: 1.8rem;
            }

            .hero-content p {
                font-size: 0.9rem;
            }

            .hero-buttons .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
            }

            .purpose-section {
                padding: 60px 0;
            }

            .purpose-section h2 {
                font-size: 1.8rem;
            }

            .cta-section {
                padding: 60px 0;
            }

            .cta-section h2 {
                font-size: 1.6rem;
            }

            .cta-section .btn {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <?php include('assets/includes/preloader.php'); ?>

    <!-- Scroll to top -->
    <?php include('assets/includes/scroll-to-top.php'); ?>

    <!-- Header -->
    <?php include('assets/includes/header.php'); ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1>Be a Lifesaver with Blood Connector</h1>
                <p>Join our mission to connect blood donors with those in need, creating a community that saves lives through compassion and action.</p>
                <div class="hero-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile" class="btn btn-primary">Profile</a>
                <?php else: ?>
                    <a href="sign-up" class="btn btn-primary">Sign Up Now</a>
                    <a href="sign-in" class="btn btn-outline-light">Sign In</a>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Purpose Section -->
    <section class="purpose-section">
        <div class="container">
            <h2>Why Blood Connector?</h2>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="purpose-card">
                        <i class="fas fa-heartbeat"></i>
                        <h3>Life-Saving Impact</h3>
                        <p>Facilitate timely blood donations to help patients in critical need, making a direct impact on lives.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="purpose-card">
                        <i class="fas fa-users"></i>
                        <h3>Community Driven</h3>
                        <p>Build a network of donors and recipients united by the shared goal of saving lives.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="purpose-card">
                        <i class="fas fa-bullhorn"></i>
                        <h3>Raise Awareness</h3>
                        <p>Educate communities about the importance of blood donation to inspire more people to contribute.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Make a Difference?</h2>
            <a href="sign-up" class="btn">Join the Movement</a>
        </div>
    </section>

    <!-- Footer -->
    <?php include('assets/includes/footer.php'); ?>

    <!-- Javascript Files -->
    <?php include('assets/includes/link-js.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll for buttons
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>