<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('admin');

$message = '';
$error = '';

function pagesEnsureTable(PDO $pdo): void {
    if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS custom_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL,
            is_published INTEGER NOT NULL DEFAULT 1,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(created_by) REFERENCES users(id)
        )');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS custom_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            content MEDIUMTEXT NOT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}

$title = trim((string)($_POST['title'] ?? ''));
$slug = trim((string)($_POST['slug'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));

try {
    $pdo = getDB();
    pagesEnsureTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        if (isset($_POST['create_page'])) {
            $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $slug));
            $slug = trim($slug, '-');
            if ($title === '' || $slug === '' || $content === '') {
                $error = 'Title, slug, and content are required.';
            } else {
                $insert = $pdo->prepare('INSERT INTO custom_pages (title, slug, content, is_published, created_by) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([$title, $slug, $content, isset($_POST['is_published']) ? 1 : 0, (int)$_SESSION['user']['id']]);
                $message = 'Page created successfully.';
                $title = $slug = $content = '';
            }
        }

        if (isset($_POST['delete_page'])) {
            $id = (int)($_POST['page_id'] ?? 0);
            if ($id > 0) {
                $del = $pdo->prepare('DELETE FROM custom_pages WHERE id = ?');
                $del->execute([$id]);
                $message = 'Page deleted successfully.';
            }
        }
    }

    $pages = $pdo->query('SELECT id, title, slug, is_published, created_at FROM custom_pages ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Manage pages error: ' . $e->getMessage());
    $error = 'Unable to load page manager.';
    $pages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Page Manager</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
    </div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header"><strong>Add New Page</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?php echo csrfField(); ?>
                <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Slug (example: about-us)</label><input class="form-control" name="slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="col-12"><label class="form-label">Content (HTML allowed)</label><textarea class="form-control" name="content" rows="8" required><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="is_published" name="is_published" checked><label class="form-check-label" for="is_published">Published</label></div>
                <div class="col-12"><button class="btn btn-primary" type="submit" name="create_page">Create Page</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>Existing Pages</strong></div>
        <div class="card-body">
            <?php if (!$pages): ?>
                <p class="text-muted mb-0">No pages created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>URL</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$page['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int)$page['is_published'] === 1 ? 'Published' : 'Draft'; ?></td>
                                <td><a href="../page.php?slug=<?php echo urlencode((string)$page['slug']); ?>" target="_blank">Open</a></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this page?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="page_id" value="<?php echo (int)$page['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_page">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
