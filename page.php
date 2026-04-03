<?php
require_once 'config/config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$page = null;

if ($slug !== '') {
    try {
        $pdo = null;
        if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
            $pdo = new PDO('sqlite:' . DB_FILE);
        } else {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare('SELECT title, content, is_published FROM custom_pages WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($candidate && (int)$candidate['is_published'] === 1) {
            $page = $candidate;
        }
    } catch (Throwable $e) {
        error_log('Public page render error: ' . $e->getMessage());
    }
}

if (!$page) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['title'] ?? 'Page Not Found', ENT_QUOTES, 'UTF-8'); ?> - <?php echo SITE_NAME; ?></title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">
    <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">Back Home</a>
    <?php if (!$page): ?>
        <div class="alert alert-warning">Page not found.</div>
    <?php else: ?>
        <article class="card shadow-sm">
            <div class="card-body">
                <h1><?php echo htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <hr>
                <div><?php echo (string)$page['content']; ?></div>
            </div>
        </article>
    <?php endif; ?>
</div>
</body>
</html>
