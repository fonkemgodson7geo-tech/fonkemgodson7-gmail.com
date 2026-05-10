<?php
require_once 'config/config.php';

$pages = [];
$error = '';

try {
    $pdo = null;
    if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
        $pdo = new PDO('sqlite:' . DB_FILE);
    } else {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('SELECT title, slug, created_at FROM custom_pages WHERE is_published = 1 ORDER BY created_at DESC');
    $stmt->execute();
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Public pages listing error: ' . $e->getMessage());
    $error = 'Unable to load public pages right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pages - <?php echo SITE_NAME; ?></title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f8fb; color: #10273e; font-family: 'Plus Jakarta Sans', sans-serif; }
        .page-wrapper { max-width: 980px; margin: 2.5rem auto; padding: 0 1rem; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 2.2rem; margin-bottom: 0.65rem; }
        .page-header p { color: #4f6272; line-height: 1.7; }
        .page-card { border: 1px solid #d8e2ed; border-radius: 18px; background: #fff; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 24px 48px rgba(15, 45, 75, 0.06); }
        .page-card h2 { margin-bottom: 0.45rem; font-size: 1.15rem; }
        .page-card p { margin: 0; color: #5e718c; }
        .page-link { color: #0f8e74; text-decoration: none; font-weight: 600; }
        .page-link:hover { text-decoration: underline; }
        .alert { border-radius: 16px; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <header class="page-header">
        <h1>Public Pages</h1>
        <p>Browse our published pages for clinic news, services, policies, and important public announcements.</p>
        <p><a href="index.php" class="page-link">← Back to home</a></p>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif (!$pages): ?>
        <div class="alert alert-info">No public pages are available yet. Please check back later.</div>
    <?php else: ?>
        <?php foreach ($pages as $page): ?>
            <article class="page-card">
                <h2><a class="page-link" href="page.php?slug=<?php echo urlencode((string)$page['slug']); ?>"><?php echo htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8'); ?></a></h2>
                <p>Published: <?php echo htmlspecialchars(date('F j, Y', strtotime((string)$page['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
