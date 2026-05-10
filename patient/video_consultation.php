<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('patient');

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Consultation - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7fb; }
        .navbar { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important; }
        .hero { max-width: 920px; margin: 2.5rem auto 1.5rem; padding: 2.5rem 2rem; background: white; border-radius: 18px; box-shadow: 0 18px 40px rgba(15, 45, 75, 0.08); }
        .hero .title { font-size: 2rem; font-weight: 800; margin-bottom: 0.8rem; }
        .hero p { color: #4b5c7d; line-height: 1.7; }
        .feature-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top: 1.75rem; }
        .feature-card { background: #f8fbff; border-radius: 16px; padding: 1.35rem; border: 1px solid #e2edf7; }
        .feature-card h3 { font-size: 1.1rem; margin-bottom: 0.6rem; }
        .feature-card p { margin: 0; color: #5a6b84; }
        .cta-panel { margin-top: 2rem; text-align: center; }
        .cta-panel .btn-primary { padding: 0.95rem 2rem; font-size: 1rem; border-radius: 999px; }
        .note-box { margin-top: 1.5rem; padding: 1.25rem 1.35rem; border-radius: 14px; background: #eef7ff; border: 1px solid #d7e9ff; color: #1f425f; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-heart-pulse"></i> <?php echo SITE_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="appointments.php">Appointments</a>
                <a class="nav-link" href="records.php">Medical Records</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <div class="hero">
        <div class="title"><i class="bi bi-camera-video-fill"></i> Online Video Consultation</div>
        <p>Book a secure online consultation with one of our doctors from the comfort of home. Choose "Video Consultation" when booking and join the appointment via the secure link provided in your patient portal and email.</p>
        <div class="feature-grid">
            <div class="feature-card">
                <h3><i class="bi bi-clock-history"></i> Flexible Scheduling</h3>
                <p>Select a convenient date and time for your video consultation and let our doctors know your concern in advance.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-shield-lock"></i> Secure & Private</h3>
                <p>Your consultation is confidential and takes place through a secure online meeting link shared only with you and your doctor.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-people-fill"></i> Expert Care</h3>
                <p>Connect with qualified medical professionals who can diagnose, advise, and follow up on your health needs remotely.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-phone-fill"></i> No Travel Needed</h3>
                <p>Save time and avoid travel by consulting online for non-emergency symptoms, follow-ups, and medication reviews.</p>
            </div>
        </div>

        <div class="cta-panel">
            <a href="book_appointment.php?service_type=Video+Consultation" class="btn btn-primary"><i class="bi bi-play-circle"></i> Book Video Consultation Now</a>
        </div>

        <div class="note-box">
            <strong>Note:</strong> After booking, you will receive appointment confirmation and the video link in your patient portal. Ensure your device has camera and microphone access for the consultation.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
