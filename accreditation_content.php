<?php
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accredited Content - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Accredited Information</h2>
        <p>This is secure, accredited content accessible only after verification.</p>
        <div class="alert alert-success">
            <h4>Clinic Accreditation Details</h4>
            <p><?php echo SITE_NAME; ?> is fully accredited and committed to providing high-quality healthcare services.</p>
            <ul>
                <li>Certified medical professionals</li>
                <li>State-of-the-art facilities</li>
                <li>Patient privacy and security</li>
                <li>24/7 emergency services</li>
            </ul>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    </div>
</body>
</html>