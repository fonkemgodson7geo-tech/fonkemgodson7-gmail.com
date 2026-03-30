<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SESSION['user']['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];

// Handle confirm logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (($_POST['action'] ?? null) === 'confirm') {
        logout();
        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Confirmation - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .confirmation-card {
            max-width: 400px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            border: none;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon-container {
            text-align: center;
            padding: 30px 20px 20px;
        }
        
        .logout-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 10px;
        }
        
        .confirmation-text {
            color: #6c757d;
            font-size: 0.95rem;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .btn-logout {
            flex: 1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-confirm-logout {
            background: #dc3545;
            color: white;
        }
        
        .btn-confirm-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-cancel-logout {
            background: #e9ecef;
            color: #495057;
        }
        
        .btn-cancel-logout:hover {
            background: #dee2e6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .user-info-text {
            font-size: 0.9rem;
            color: #495057;
        }
        
        .user-name {
            font-weight: 600;
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="confirmation-card card">
        <div class="icon-container">
            <div class="logout-icon">
                <i class="bi bi-box-arrow-right"></i>
            </div>
            <h2 class="card-title">Confirm Logout</h2>
        </div>
        
        <div class="card-body">
            <div class="user-info">
                <div class="user-info-text">
                    Currently logged in as:
                    <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            
            <p class="confirmation-text">
                Are you sure you want to logout? You will need to log in again to access your account.
            </p>
            
            <form method="post" class="w-100">
                <?php echo csrfField(); ?>
                <div class="button-group">
                    <button type="submit" name="action" value="confirm" class="btn btn-logout btn-confirm-logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                    <button type="button" onclick="window.history.back();" class="btn btn-logout btn-cancel-logout">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
