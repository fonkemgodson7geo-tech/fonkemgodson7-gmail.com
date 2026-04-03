<?php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function pageCsrfToken(): string {
    if (empty($_SESSION['public_csrf_token'])) {
        $_SESSION['public_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['public_csrf_token'];
}

function verifyPageCsrf(): bool {
    $submitted = (string)($_POST['csrf_token'] ?? '');
    return $submitted !== '' && hash_equals(pageCsrfToken(), $submitted);
}

function pageGetDB(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    return $pdo;
}

function ensurePublicCommunicationsTable(PDO $pdo): void {
    if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS public_communications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_name TEXT NOT NULL,
                contact TEXT,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                image_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS public_communications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                author_name VARCHAR(120) NOT NULL,
                contact VARCHAR(180) NULL,
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                image_path VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

$message = '';
$error = '';
$posts = [];

$authorName = trim((string)($_POST['author_name'] ?? ''));
$contact = trim((string)($_POST['contact'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$body = trim((string)($_POST['message'] ?? ''));

try {
    $pdo = pageGetDB();
    ensurePublicCommunicationsTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
        if (!verifyPageCsrf()) {
            $error = 'Request validation failed. Please refresh and try again.';
        } elseif ($authorName === '' || $title === '' || $body === '') {
            $error = 'Name, title, and message are required.';
        } else {
            $imagePath = null;
            if (isset($_FILES['image_file']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['image_file'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $error = 'Image upload failed. Please try again.';
                } else {
                    $tmp = (string)$file['tmp_name'];
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];

                    if (!isset($allowed[$mime])) {
                        $error = 'Only JPG, PNG, and WEBP images are allowed.';
                    } elseif ((int)($file['size'] ?? 0) > MAX_FILE_SIZE) {
                        $error = 'Image is too large.';
                    } else {
                        $uploadDir = __DIR__ . '/uploads/public_communications';
                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                            $error = 'Upload folder is not available.';
                        } else {
                            $filename = 'comm_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                            $target = $uploadDir . '/' . $filename;
                            if (!move_uploaded_file($tmp, $target)) {
                                $error = 'Could not store uploaded image.';
                            } else {
                                $imagePath = 'uploads/public_communications/' . $filename;
                            }
                        }
                    }
                }
            }

            if ($error === '') {
                $insert = $pdo->prepare('INSERT INTO public_communications (author_name, contact, title, message, image_path) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([$authorName, $contact !== '' ? $contact : null, $title, $body, $imagePath]);
                $message = 'Communication published successfully.';
                $authorName = '';
                $contact = '';
                $title = '';
                $body = '';
            }
        }
    }

    $list = $pdo->query('SELECT id, author_name, contact, title, message, image_path, created_at FROM public_communications ORDER BY created_at DESC LIMIT 20');
    $posts = $list->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Public communications page error: ' . $e->getMessage());
    $error = 'Unable to load communications right now.';
}

$featuredPost = $posts[0] ?? null;
$otherPosts = array_slice($posts, 1);
$postCount = count($posts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Communications - <?php echo SITE_NAME; ?></title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --page-bg: #f6f9f8;
            --surface: #ffffff;
            --surface-alt: #eef5f3;
            --ink: #12322b;
            --muted: #5d726b;
            --accent: #0c8f77;
            --accent-2: #f2a65a;
            --line: #d8e4e0;
            --shadow: 0 22px 45px rgba(11, 56, 47, 0.12);
            --radius-xl: 24px;
            --radius-lg: 18px;
            --radius-md: 12px;
        }

        body {
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 450px at 12% -10%, rgba(12, 143, 119, 0.15), transparent 70%),
                radial-gradient(850px 360px at 92% 0%, rgba(242, 166, 90, 0.18), transparent 72%),
                linear-gradient(180deg, #f8fcfb 0%, var(--page-bg) 100%);
            min-height: 100vh;
        }

        .hero-wrap {
            border-radius: var(--radius-xl);
            background:
                linear-gradient(130deg, rgba(12, 143, 119, 0.96), rgba(18, 50, 43, 0.95)),
                repeating-linear-gradient(45deg, rgba(255,255,255,0.06), rgba(255,255,255,0.06) 2px, transparent 2px, transparent 14px);
            box-shadow: var(--shadow);
            padding: 2rem;
            color: #f6fffc;
            position: relative;
            overflow: hidden;
        }

        .hero-wrap::after {
            content: '';
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            right: -80px;
            top: -70px;
            background: rgba(255, 255, 255, 0.1);
        }

        .brand-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            padding: 0.35rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(1.8rem, 3.6vw, 2.9rem);
            font-weight: 800;
            line-height: 1.12;
            margin: 0.95rem 0 0.6rem;
            max-width: 18ch;
        }

        .hero-sub {
            color: rgba(246, 255, 252, 0.88);
            max-width: 58ch;
            margin-bottom: 1.2rem;
        }

        .hero-stat {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 14px;
            padding: 0.85rem 1rem;
            min-width: 140px;
        }

        .hero-stat .value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
        }

        .hero-stat .label {
            color: rgba(246, 255, 252, 0.85);
            font-size: 0.82rem;
            margin-top: 0.25rem;
        }

        .mag-shell {
            margin-top: 1.4rem;
        }

        .card-glass {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: 0 16px 30px rgba(13, 64, 53, 0.08);
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.8rem;
        }

        .section-head h3 {
            margin: 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.5rem;
        }

        .featured-post {
            padding: 1.3rem;
            animation: floatIn 0.62s ease-out both;
        }

        .featured-meta {
            color: var(--muted);
            font-size: 0.88rem;
            margin-top: 0.45rem;
        }

        .featured-title {
            margin-top: 0.65rem;
            margin-bottom: 0.7rem;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(1.45rem, 2.4vw, 2.05rem);
            line-height: 1.25;
        }

        .featured-image {
            border-radius: var(--radius-md);
            overflow: hidden;
            margin-top: 1rem;
            border: 1px solid var(--line);
            background: var(--surface-alt);
        }

        .featured-image img {
            width: 100%;
            max-height: 360px;
            object-fit: cover;
            display: block;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .news-card {
            padding: 1rem;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #fbfefd 100%);
            animation: floatIn 0.65s ease-out both;
        }

        .news-card:nth-child(2) { animation-delay: 0.06s; }
        .news-card:nth-child(3) { animation-delay: 0.1s; }
        .news-card:nth-child(4) { animation-delay: 0.14s; }

        .news-card h5 {
            margin: 0.5rem 0 0.45rem;
            font-size: 1.02rem;
            line-height: 1.35;
            font-weight: 800;
        }

        .news-card p {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 0.93rem;
        }

        .news-meta {
            color: var(--muted);
            font-size: 0.82rem;
        }

        .mini-thumb {
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.75rem;
            border: 1px solid var(--line);
        }

        .mini-thumb img {
            width: 100%;
            height: 140px;
            object-fit: cover;
        }

        .sidebar-wrap {
            display: grid;
            gap: 1rem;
        }

        .publish-card {
            padding: 1.2rem;
            animation: floatIn 0.7s ease-out both;
        }

        .publish-card h4 {
            margin: 0 0 0.8rem;
            font-family: 'Playfair Display', Georgia, serif;
        }

        .form-label {
            font-size: 0.86rem;
            font-weight: 700;
            color: #304f47;
        }

        .form-control {
            border-radius: 12px;
            border-color: #d4e3de;
            padding: 0.62rem 0.78rem;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(12, 143, 119, 0.18);
        }

        .btn-home,
        .btn-publish {
            border-radius: 999px;
            font-weight: 700;
            padding: 0.56rem 1rem;
        }

        .btn-home {
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #f6fffc;
        }

        .btn-home:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .btn-publish {
            border: none;
            color: #fff;
            background: linear-gradient(120deg, #0c8f77, #0b6f5d);
            box-shadow: 0 12px 22px rgba(12, 143, 119, 0.26);
        }

        .btn-publish:hover {
            filter: brightness(1.05);
            color: #fff;
        }

        .info-card {
            padding: 1rem;
            background: linear-gradient(180deg, #fff8ee 0%, #fffef9 100%);
            border: 1px solid #f4dfbf;
        }

        .info-card h6 {
            font-weight: 800;
            margin-bottom: 0.35rem;
        }

        .tag-line {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #18493f;
            border: 1px solid #cfe2dc;
            background: #f6fcfa;
            border-radius: 999px;
            padding: 0.25rem 0.65rem;
        }

        .alert {
            border: none;
            border-radius: 12px;
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 991px) {
            .hero-wrap {
                padding: 1.35rem;
            }

            .hero-title {
                max-width: none;
            }
        }
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">
    <section class="hero-wrap mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 position-relative" style="z-index:1;">
            <div>
                <span class="brand-chip"><i class="bi bi-heart-pulse"></i> Community Bulletin</span>
                <h1 class="hero-title">Hospital Newsroom, Announcements, and Public Updates</h1>
                <p class="hero-sub mb-0">A clear and trusted space for patients, families, and partners to follow new services, emergency notices, and care communication from our team.</p>
            </div>
            <a href="index.php" class="btn btn-home"><i class="bi bi-arrow-left"></i> Back Home</a>
        </div>
        <div class="d-flex flex-wrap gap-2 gap-sm-3 mt-3 position-relative" style="z-index:1;">
            <div class="hero-stat">
                <div class="value"><?php echo htmlspecialchars((string)$postCount, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="label">Recent Posts</div>
            </div>
            <div class="hero-stat">
                <div class="value">24/7</div>
                <div class="label">Public Service Desk</div>
            </div>
            <div class="hero-stat">
                <div class="value">Verified</div>
                <div class="label">Clinical Updates</div>
            </div>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert alert-success mb-3"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row g-4 mag-shell">
        <div class="col-lg-8">
            <div class="section-head">
                <h3>Latest Story</h3>
                <span class="text-muted small"><i class="bi bi-newspaper"></i> Editorial layout</span>
            </div>

            <?php if ($featuredPost): ?>
                <article class="card-glass featured-post">
                    <div class="featured-meta">
                        By <?php echo htmlspecialchars((string)$featuredPost['author_name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($featuredPost['contact'])): ?>
                            | <?php echo htmlspecialchars((string)$featuredPost['contact'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                        | <?php echo htmlspecialchars((string)$featuredPost['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <h2 class="featured-title"><?php echo htmlspecialchars((string)$featuredPost['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars((string)$featuredPost['message'], ENT_QUOTES, 'UTF-8')); ?></p>

                    <?php if (!empty($featuredPost['image_path'])): ?>
                        <a class="featured-image" href="<?php echo htmlspecialchars((string)$featuredPost['image_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo htmlspecialchars((string)$featuredPost['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Featured communication image">
                        </a>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <div class="card-glass p-4">
                    <h5 class="mb-2">No communication published yet</h5>
                    <p class="text-muted mb-0">Use the publishing form to share your first announcement with the community.</p>
                </div>
            <?php endif; ?>

            <?php if ($otherPosts): ?>
                <div class="section-head mt-4 mb-2">
                    <h3>More Updates</h3>
                    <span class="text-muted small">WordPress-style cards</span>
                </div>
                <div class="news-grid">
                    <?php foreach ($otherPosts as $post): ?>
                        <article class="news-card">
                            <div class="news-meta">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars((string)$post['author_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <span class="mx-1">|</span>
                                <?php echo htmlspecialchars((string)$post['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <h5><?php echo htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                            <p><?php echo htmlspecialchars(mb_strimwidth((string)$post['message'], 0, 190, '...'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (!empty($post['image_path'])): ?>
                                <a class="mini-thumb d-block" href="<?php echo htmlspecialchars((string)$post['image_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo htmlspecialchars((string)$post['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Communication image">
                                </a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="sidebar-wrap">
                <div class="card-glass publish-card" id="publish-form">
                    <h4><i class="bi bi-pencil-square"></i> Publish Communique</h4>
                    <form method="post" enctype="multipart/form-data" class="row g-3" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(pageCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="col-12">
                            <label class="form-label" for="author_name">Your Name</label>
                            <input class="form-control" id="author_name" name="author_name" value="<?php echo htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="contact">Contact (email or phone)</label>
                            <input class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="title">Title</label>
                            <input class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="message">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="image_file">Upload Cover Image</label>
                            <input class="form-control" type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <div class="col-12 d-grid">
                            <button class="btn btn-publish" type="submit" name="publish"><i class="bi bi-send"></i> Publish Update</button>
                        </div>
                    </form>
                </div>

                <div class="card-glass info-card">
                    <h6><i class="bi bi-shield-check"></i> Publishing Guidelines</h6>
                    <p class="text-muted small mb-2">Keep messages factual, concise, and patient-friendly. Use clear titles and avoid sensitive personal information.</p>
                    <div class="tag-line">
                        <span class="tag">Emergency Notice</span>
                        <span class="tag">Vaccination</span>
                        <span class="tag">Service Hours</span>
                        <span class="tag">Community Program</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
