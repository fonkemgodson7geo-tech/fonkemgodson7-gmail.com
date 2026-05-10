<?php
require_once 'config/config.php';
require_once 'includes/language.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function homeCsrfToken(): string {
    if (empty($_SESSION['home_csrf_token'])) {
        $_SESSION['home_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['home_csrf_token'];
}

function homeCsrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(homeCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyHomeCsrf(): bool {
    $submitted = (string)($_POST['csrf_token'] ?? '');
    return $submitted !== '' && hash_equals(homeCsrfToken(), $submitted);
}

$uploadMessage = '';
$uploadError = '';
$autoOpenUploadUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_home_file'])) {
    if (!verifyHomeCsrf()) {
        $uploadError = 'Request validation failed. Please try again.';
    } else {
        $selectedStaffSlug = trim((string)($_POST['staff_slug'] ?? ''));
        if ($selectedStaffSlug === '' || !isset($teamMembersBySlug[$selectedStaffSlug])) {
            $uploadError = 'Please select a valid staff member.';
        } elseif (!isset($_FILES['home_file']) || !is_array($_FILES['home_file'])) {
            $uploadError = 'Please choose a file to upload.';
        } else {
            $file = $_FILES['home_file'];
            $maxBytes = 5 * 1024 * 1024;
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $uploadDir = __DIR__ . '/uploads/staff_photos';

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $uploadError = 'Upload failed. Please try again.';
            } elseif (($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxBytes) {
                $uploadError = 'File must be between 1 byte and 5 MB.';
            } else {
                $originalName = (string)($file['name'] ?? '');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    $uploadError = 'Unsupported file type. Please upload a JPG, PNG, GIF, or WEBP image.';
                } else {
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $uploadError = 'Upload folder is not writable.';
                    } else {
                        $filename = $selectedStaffSlug . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
                        $target = $uploadDir . '/' . $filename;

                        if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                            $uploadError = 'Could not save uploaded file.';
                        } else {
                            $uploadMessage = 'Photo uploaded successfully for ' . $teamMembersBySlug[$selectedStaffSlug]['name'] . '.';
                            $autoOpenUploadUrl = 'uploads/staff_photos/' . rawurlencode($filename);
                            $teamPhotos[$selectedStaffSlug] = [
                                'name' => $filename,
                                'mtime' => (int)filemtime($target),
                                'url' => 'uploads/staff_photos/' . rawurlencode($filename),
                            ];
                        }
                    }
                }
            }
        }
    }
}

$recentUploads = [];
$publicUploadDir = __DIR__ . '/uploads/home_uploads';
if (is_dir($publicUploadDir)) {
    $entries = scandir($publicUploadDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $publicUploadDir . '/' . $entry;
        if (is_file($full)) {
            $recentUploads[] = ['name' => $entry, 'mtime' => (int)filemtime($full)];
        }
    }
    usort($recentUploads, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    $recentUploads = array_slice($recentUploads, 0, 6);
}

$teamMembers = [
    ['slug' => 'dr-tata-asscada', 'name' => 'Dr TATA ASSCADA', 'title' => 'Chief of Clinic, BSc MLS', 'description' => 'Promoteur et Fondateur'],
    ['slug' => 'dr-abeng-bernard-ide', 'name' => 'Dr ABENG BERNARD IDE', 'title' => 'Accoucheur, Chef Personnel', 'description' => 'Head of Maternal Care'],
    ['slug' => 'fonkem-catherine', 'name' => 'FONKEM CATHERINE', 'title' => 'SRN Pharmacy Major', 'description' => 'Pharmacy Operations Leader'],
    ['slug' => 'mayang-marguerite', 'name' => 'MAYANG MARGUERITE', 'title' => 'Infirmière, PF Vaccination', 'description' => 'Vaccination and Patient Care'],
    ['slug' => 'dr-nong-ernest', 'name' => 'Dr NONG ERNEST', 'title' => 'Senior Lab Tech', 'description' => 'Laboratory Diagnostics Lead'],
    ['slug' => 'safouratou-zad', 'name' => 'Safouratou Zad', 'title' => 'TMS, Ass Lab Major', 'description' => 'Assistant Laboratory Manager'],
    ['slug' => 'abanda-christel', 'name' => 'Abanda Christel', 'title' => 'Aid Soignante, Assistant Caissier et Pharmacy', 'description' => 'Patient support and pharmacy assistant'],
];

$teamMembersBySlug = [];
foreach ($teamMembers as $member) {
    $teamMembersBySlug[$member['slug']] = $member;
}

$teamPhotoDir = __DIR__ . '/uploads/staff_photos';
$teamPhotos = [];
if (is_dir($teamPhotoDir)) {
    $entries = scandir($teamPhotoDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $teamPhotoDir . '/' . $entry;
        if (!is_file($full)) {
            continue;
        }
        if (!preg_match('/^([a-z0-9_-]+)_.*\.(jpg|jpeg|png|gif|webp)$/i', $entry, $matches)) {
            continue;
        }
        $slug = strtolower($matches[1]);
        if (!isset($teamMembersBySlug[$slug])) {
            continue;
        }
        $mtime = (int)filemtime($full);
        if (!isset($teamPhotos[$slug]) || $mtime > $teamPhotos[$slug]['mtime']) {
            $teamPhotos[$slug] = [
                'name' => $entry,
                'mtime' => $mtime,
                'url' => 'uploads/staff_photos/' . rawurlencode($entry),
            ];
        }
    }
}

$siteLogoUrl = trim((string)SITE_LOGO_URL);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = SITE_URL !== '' ? SITE_URL : ($host !== '' ? ($scheme . '://' . $host) : '');
$canonicalUrl = $baseUrl !== '' ? $baseUrl . '/' : '';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(appLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SITE_NAME; ?> — trusted care delivery engineered for global reliability. Patient, doctor and admin portals with live system health monitoring.">
    <?php if ($canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> | Connected Care Network">
    <meta property="og:description" content="Trusted care platform for patients, doctors, and administrators.">
    <title><?php echo SITE_NAME; ?> | Connected Care Network</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-0: #f3f8fb;
            --bg-1: #e7f1f7;
            --ink-0: #152638;
            --ink-1: #35546d;
            --card: rgba(255, 255, 255, 0.8);
            --card-strong: #ffffff;
            --line: rgba(21, 38, 56, 0.12);
            --teal: #0fb39d;
            --teal-dark: #0a8b7b;
            --amber: #ffb347;
            --coral: #ff7f66;
            --ok: #27bf8a;
            --warn: #e1962f;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ink-0);
            background:
                radial-gradient(900px 400px at 10% -5%, rgba(15, 179, 157, 0.2), transparent 60%),
                radial-gradient(900px 400px at 95% 0%, rgba(255, 127, 102, 0.2), transparent 62%),
                linear-gradient(180deg, var(--bg-0), var(--bg-1));
            background-attachment: fixed;
        }

        .page {
            max-width: 1220px;
            margin: 0 auto;
            padding: 1.25rem 1rem 4rem;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.8rem;
            align-items: center;
            padding: 0.6rem 0;
            margin-bottom: 1.3rem;
            animation: rise 700ms ease-out;
        }
        .brand-wrap {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
        }
        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            object-fit: cover;
            display: block;
        }
        .brand-logo-fallback {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--ink-1);
            letter-spacing: 0.08em;
            background: #fff;
        }

        .brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .topnav {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .chip-link {
            text-decoration: none;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.75);
            color: var(--ink-0);
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
            font-size: 0.84rem;
            font-weight: 600;
            transition: all 180ms ease;
        }

        .chip-link:hover {
            transform: translateY(-1px);
            border-color: rgba(10, 139, 123, 0.35);
            background: #fff;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
            background: rgba(255, 255, 255, 0.8);
            font-size: 0.84rem;
            color: var(--ink-1);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--warn);
            animation: pulse-warn 1.6s infinite;
        }

        .dot.ok {
            background: var(--ok);
            animation: pulse-ok 1.6s infinite;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 0.95rem;
            margin-bottom: 1rem;
        }

        .hero-main,
        .hero-side {
            border: 1px solid var(--line);
            border-radius: 22px;
            background: linear-gradient(155deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.78));
            box-shadow: 0 20px 45px rgba(20, 45, 68, 0.11);
            animation: rise 850ms ease-out;
        }

        .hero-main {
            padding: 2.1rem;
            position: relative;
            overflow: hidden;
        }

        .hero-main::after {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(15, 179, 157, 0.17), rgba(15, 179, 157, 0));
            right: -80px;
            top: -80px;
            pointer-events: none;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            border: 1px solid rgba(15, 179, 157, 0.28);
            padding: 0.35rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--teal-dark);
            background: rgba(15, 179, 157, 0.12);
        }

        .hero-main h1 {
            margin: 0 0 0.7rem;
            font-family: 'Outfit', sans-serif;
            font-size: clamp(1.85rem, 4vw, 3rem);
            line-height: 1.08;
            letter-spacing: -0.02em;
        }

        .hero-main p {
            margin: 0;
            color: var(--ink-1);
            max-width: 760px;
            font-size: 1.01rem;
            line-height: 1.68;
        }

        .actions {
            margin-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .btn {
            text-decoration: none;
            color: #09322d;
            border-radius: 12px;
            padding: 0.72rem 1.05rem;
            font-weight: 700;
            font-size: 0.92rem;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(15, 37, 55, 0.15);
        }

        .btn.primary {
            background: linear-gradient(90deg, #14c8ac, #7fe3cf);
        }

        .btn.warm {
            background: linear-gradient(90deg, #ffd091, #ffb287);
            color: #50280c;
        }

        .hero-highlights {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem;
            margin-top: 1.1rem;
        }

        .hl {
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.66);
            border-radius: 12px;
            padding: 0.65rem 0.7rem;
            font-size: 0.82rem;
            color: var(--ink-1);
        }

        .hl strong {
            display: block;
            color: var(--ink-0);
            font-size: 0.97rem;
            margin-bottom: 0.1rem;
        }

        .hero-side {
            padding: 1.2rem;
            display: grid;
            gap: 0.75rem;
        }

        .quick-tile {
            border-radius: 14px;
            padding: 0.95rem;
            border: 1px solid var(--line);
            background: #fff;
        }

        .quick-tile .k {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-1);
        }

        .quick-tile .v {
            margin-top: 0.2rem;
            font-weight: 800;
            font-size: 1.1rem;
            font-family: 'Outfit', sans-serif;
        }

        .time-grid {
            display: grid;
            gap: 0.45rem;
        }

        .time-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed var(--line);
            padding: 0.33rem 0;
            font-size: 0.9rem;
        }

        .time-row:last-child {
            border-bottom: 0;
        }

        .time-row span {
            color: var(--ink-1);
        }

        .metrics {
            margin-top: 1.4rem;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .metric {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.76);
            padding: 0.9rem 1rem;
            animation: rise 1000ms ease-out;
        }

        .metric .label {
            font-size: 0.78rem;
            color: var(--ink-1);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .metric .value {
            font-size: 1.05rem;
            font-weight: 800;
            margin-top: 0.3rem;
        }

        .grid { margin-top: 1.2rem; display: grid; grid-template-columns: 2fr 1fr; gap: 0.9rem; }

        .panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.76);
            padding: 1.1rem 1.3rem;
        }

        .panel h2 {
            margin: 0 0 0.7rem;
            font-size: 1.03rem;
            font-family: 'Outfit', sans-serif;
        }

        .feature-list {
            margin: 0;
            padding-left: 1.1rem;
            color: var(--ink-1);
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .portal-wrap {
            margin-top: 1rem;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--card-strong);
            padding: 1.2rem;
            box-shadow: 0 20px 45px rgba(20, 45, 68, 0.11);
        }

        .portal-head {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-bottom: 0.85rem;
            align-items: center;
        }

        .portal-head h2 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.22rem;
        }

        .portal-head p {
            margin: 0;
            color: var(--ink-1);
            font-size: 0.92rem;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.7rem;
        }

        .portal-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 0.9rem;
            background: linear-gradient(160deg, #ffffff, #f4fbff);
            display: grid;
            gap: 0.5rem;
        }

        .portal-card h3 {
            margin: 0;
            font-size: 0.98rem;
            font-family: 'Outfit', sans-serif;
        }

        .portal-card p {
            margin: 0;
            font-size: 0.86rem;
            color: var(--ink-1);
            min-height: 2.35rem;
        }

        .portal-actions {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .mini-btn {
            text-decoration: none;
            border-radius: 10px;
            padding: 0.45rem 0.62rem;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .mini-btn.login {
            background: linear-gradient(90deg, #16c0a5, #93e9d9);
            color: #073830;
        }

        .mini-btn.alt {
            background: #eef5fb;
            color: #2f4f68;
            border: 1px solid var(--line);
        }

        .upload-panel {
            margin-top: 1rem;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.88);
            padding: 1rem;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 0.9rem;
        }

        .upload-grid .cardish {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            padding: 0.9rem;
        }

        .upload-note {
            color: var(--ink-1);
            font-size: 0.9rem;
        }

        .upload-list {
            margin: 0;
            padding-left: 1rem;
        }

        .upload-list li {
            margin-bottom: 0.3rem;
            color: var(--ink-1);
            font-size: 0.9rem;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.95rem;
            margin-top: 1rem;
        }

        .service-card,
        .team-card,
        .map-panel,
        .about-panel,
        .contact-panel {
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 1.2rem;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 18px 40px rgba(20, 45, 68, 0.08);
        }

        .section-heading {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            margin-bottom: 0.9rem;
        }

        .search-panel {
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 1.3rem 1.35rem;
            background: rgba(255, 255, 255, 0.92);
            margin-bottom: 1.5rem;
            box-shadow: 0 18px 40px rgba(20, 45, 68, 0.08);
        }

        .search-inline-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .search-inline-form input {
            width: 100%;
            padding: 1rem 1.1rem;
            border-radius: 18px;
            border: 1px solid var(--line);
            outline: none;
            font-size: 0.96rem;
            color: var(--ink-1);
            background: #f8fbfd;
        }

        .search-inline-form button {
            padding: 0 1.4rem;
            border-radius: 18px;
            border: none;
            background: linear-gradient(135deg, #0fb39d, #23c6d1);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            min-width: 110px;
        }

        .service-card h3,
        .team-card h3 {
            margin: 0 0 0.65rem;
            font-size: 1rem;
        }

        .service-card p,
        .team-card p,
        .about-panel p,
        .contact-panel p {
            margin: 0;
            line-height: 1.75;
            color: var(--ink-1);
            font-size: 0.96rem;
        }

        .service-card {
            min-height: 170px;
        }

        .service-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: #fff;
            margin-bottom: 0.85rem;
            font-size: 1.2rem;
            background: linear-gradient(135deg, #0fb39d, #23c6d1);
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .team-card {
            display: grid;
            gap: 0.85rem;
        }

        .team-photo {
            width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 18px;
            background: linear-gradient(180deg, #eef7fb, #ffffff);
            display: grid;
            place-items: center;
            color: var(--ink-1);
            font-weight: 700;
            text-align: center;
            padding: 1rem;
            overflow: hidden;
        }

        .team-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 18px;
        }

        .team-photo strong {
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
            margin-top: 1rem;
        }

        .contact-panel {
            display: grid;
            gap: 0.85rem;
        }

        .contact-panel strong {
            display: block;
            font-size: 1rem;
            margin-bottom: 0.35rem;
        }

        .contact-number {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--line);
            background: #fff;
            font-weight: 700;
            color: var(--ink-0);
        }

        .map-panel iframe {
            width: 100%;
            min-height: 320px;
            border: 0;
            border-radius: 18px;
        }

        .photo-upload-info {
            margin-top: 1rem;
            background: rgba(15, 179, 157, 0.08);
            padding: 0.95rem 1rem;
            border-radius: 16px;
            color: var(--ink-1);
            font-size: 0.95rem;
        }

        .photo-upload-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .photo-upload-row label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 700;
            color: var(--ink-0);
        }

        .photo-upload-row input[type="text"],
        .photo-upload-row input[type="file"] {
            width: 100%;
            padding: 0.85rem 0.95rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            font-size: 0.95rem;
        }

        .photo-upload-row button {
            margin-top: 1rem;
            width: fit-content;
            border: 0;
            border-radius: 14px;
            padding: 0.85rem 1.1rem;
            background: linear-gradient(90deg, #14c8ac, #7fe3cf);
            color: #0b2f2a;
            font-weight: 700;
            cursor: pointer;
        }

        .hero-info { margin-top: 1rem; display: grid; gap: 0.9rem; }

        .hero-badge {
            width: fit-content;
            border-radius: 18px;
            padding: 0.65rem 0.9rem;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(15, 179, 157, 0.18);
            color: var(--ink-1);
            font-weight: 700;
        }

        .hero-copy p { max-width: 720px; }

        .about-panel ul,
        .feature-list {
            padding-left: 1.2rem;
            margin: 0;
            color: var(--ink-1);
            line-height: 1.8;
        }

        .about-panel ul li {
            margin-bottom: 0.85rem;
        }

        .feature-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1rem;
            border-radius: 999px;
            background: rgba(15, 179, 157, 0.12);
            color: var(--teal-dark);
            font-weight: 700;
            margin-top: 1.25rem;
        }

        .hero-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.35rem;
        }

        @media (max-width: 920px) {
            .service-grid,
            .team-grid,
            .contact-grid,
            .photo-upload-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .brand { font-size: 1rem; }
            .hero-copy h1 { font-size: clamp(2rem, 5vw, 2.4rem); }
            .hero-copy p { font-size: 0.98rem; }
        }

        @keyframes pulse-warn {
            0%,100% { box-shadow: 0 0 0 0 rgba(255,203,102,0.55); }
            70%      { box-shadow: 0 0 0 8px rgba(255,203,102,0); }
        }
        @keyframes pulse-ok {
            0%,100% { box-shadow: 0 0 0 0 rgba(56,211,159,0.55); }
            70%      { box-shadow: 0 0 0 8px rgba(56,211,159,0); }
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 900px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .metrics { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .grid    { grid-template-columns: 1fr; }

            .portal-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .upload-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .hero-main {
                padding: 1.3rem;
            }

            .hero-highlights {
                grid-template-columns: 1fr;
            }

            .actions { flex-direction: column; }
            .btn { text-align: center; }

            .portal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="page">

    <header class="topbar">
        <div class="brand-wrap">
            <?php if ($siteLogoUrl !== ''): ?>
                <img
                    src="<?php echo htmlspecialchars($siteLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> logo"
                    class="brand-logo"
                >
            <?php else: ?>
                <span class="brand-logo-fallback" aria-hidden="true">LOGO</span>
            <?php endif; ?>
            <div class="brand"><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <nav class="topnav" aria-label="Quick links">
            <a class="chip-link" href="#portal">Sign In</a>
            <a class="chip-link" href="patient/register.php">Register Patient</a>
            <a class="chip-link" href="doctor/register.php">Register Doctor</a>
            <a class="chip-link" href="staff/register.php">Register Staff</a>
            <a class="chip-link" href="public_communications.php">Communications</a>
            <a class="chip-link" href="pages.php">Pages</a>
            <a class="chip-link" href="search.php">Search</a>
            <a class="chip-link" href="accreditation.php">Accreditation</a>
            <a class="chip-link" href="?lang=en">English</a>
            <a class="chip-link" href="?lang=fr">Francais</a>
        </nav>
        <div class="status-pill" id="statusPill" role="status" aria-live="polite">
            <span class="dot" id="statusDot"></span>
            <span id="statusText">Checking live service status&hellip;</span>
        </div>
    </header>

    <section class="hero-grid">
        <article class="hero-main hero-copy">
            <span class="eyebrow"><?php echo appT('CENTRE MÉDICAL DONS DE SOINS', 'CENTRE MÉDICAL DONS DE SOINS'); ?></span>
            <h1><?php echo appT('Empathy, love and care are our dignity.', 'Empathie, amour et soins sont notre dignité.'); ?></h1>
            <p>
                <?php echo appT('Located in the Central Region of Cameroon, Upper Sanaga Division, Mbandjock, just a few meters from SOSUCAM sugar company at Camp Alangerbault.', 'Situé dans la Région du Centre du Cameroun, Division du Haut Sanaga, Mbandjock, à quelques mètres de la sucrerie SOSUCAM au Camp Alangerbault.'); ?> 
                <?php echo appT('Our clinic delivers compassionate patient care, modern diagnostics, and a trusted medical environment for families in the region.', 'Notre clinique offre des soins compatissants, des diagnostics modernes et un environnement médical de confiance pour les familles de la région.'); ?>
            </p>
            <div class="actions">
                <a href="#portal" class="btn primary"><?php echo appT('Access Portals', 'Accéder aux portails'); ?></a>
                <a href="patient/register.php" class="btn warm"><?php echo appT('Register a Patient', 'Inscrire un patient'); ?></a>
            </div>
            <div class="hero-highlights">
                <div class="hl"><strong><?php echo appT('CHAMPIONING CARE', 'SOINS DEPUIS LE COEUR'); ?></strong><?php echo appT(' Experienced clinical leadership in a modern facility.', ' Un leadership clinique expérimenté dans un établissement moderne.'); ?></div>
                <div class="hl"><strong><?php echo appT('MULTILINGUAL SUPPORT', 'SUPPORT MULTILINGUE'); ?></strong><?php echo appT(' English and French communication at every step.', ' Communication en anglais et en français à chaque étape.'); ?></div>
                <div class="hl"><strong><?php echo appT('FAST ACCESS', 'ACCÈS RAPIDE'); ?></strong><?php echo appT(' Close to SOSUCAM, with clear directions and easy referrals.', ' Près de SOSUCAM, avec des directions claires et des renvois faciles.'); ?></div>
            </div>
        </article>

        <aside class="hero-side">
            <div class="quick-tile">
                <div class="k"><?php echo appT('Clinic Motto', 'Devise'); ?></div>
                <div class="v"><?php echo appT('EMPATHY, LOVE AND CARE OUR DIGNITY', 'EMPATHIE, AMOUR ET SOINS NOTRE DIGNITÉ'); ?></div>
            </div>
            <div class="quick-tile">
                <div class="k"><?php echo appT('Contact', 'Contact'); ?></div>
                <div class="v"><?php echo htmlspecialchars(PAYMENT_NUMBER . ' / ' . CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="quick-tile">
                <div class="k"><?php echo appT('Location', 'Emplacement'); ?></div>
                <div class="v"><?php echo appT('Camp Alangerbault, Mbandjock', 'Camp Alangerbault, Mbandjock'); ?></div>
            </div>
        </aside>
    </section>

    <section class="search-panel" id="system-search">
        <div class="section-heading"><?php echo appT('Search the clinic system', 'Rechercher dans le système de la clinique'); ?></div>
        <p><?php echo appT('Quickly find staff, patients, drugs, groups, pages, and reports from one search box.', 'Trouvez rapidement du personnel, des patients, des médicaments, des groupes, des pages et des rapports depuis une seule barre de recherche.'); ?></p>
        <form action="search.php" method="get" class="search-inline-form">
            <input type="search" name="q" placeholder="<?php echo appT('Search by name, drug, group, page, report…', 'Rechercher par nom, médicament, groupe, page, rapport…'); ?>" aria-label="Search the system">
            <button type="submit"><?php echo appT('Search', 'Rechercher'); ?></button>
        </form>
    </section>

    <section class="about-panel" id="about">
        <div class="section-heading"><?php echo appT('About the Clinic', 'À propos de la clinique'); ?></div>
        <p><?php echo appT('Centre Medical Dons de Soins brings expert care to the Central Region of Cameroon. Located in the Upper Sanaga Division, our facility is built to serve patients with fast access, experienced clinicians, and trusted pharmacy and laboratory services.', 'Le Centre Médical Dons de Soins apporte une expertise médicale à la Région du Centre du Cameroun. Situé dans la Division du Haut Sanaga, notre établissement est conçu pour servir les patients avec un accès rapide, des cliniciens expérimentés et des services pharmaceutiques et de laboratoire fiables.'); ?></p>
        <ul>
            <li><?php echo appT('Our team includes highly trained doctors, nurses, lab technicians, and pharmacy specialists.', 'Notre équipe comprend des médecins, des infirmières, des techniciens de laboratoire et des spécialistes en pharmacie hautement qualifiés.'); ?></li>
            <li><?php echo appT('We provide emergency response, maternal health, lab diagnostics, vaccination, and continuous care.', 'Nous fournissons des services d’urgence, de santé maternelle, de diagnostics de laboratoire, de vaccination et de soins continus.'); ?></li>
            <li><?php echo appT('The clinic is just a few meters away from SOSUCAM sugar company at Camp Alangerbault in Mbandjock.', 'La clinique se trouve à quelques mètres de la sucrerie SOSUCAM au Camp Alangerbault à Mbandjock.'); ?></li>
        </ul>
    </section>

    <section class="service-card" id="services">
        <div class="section-heading"><?php echo appT('Our Hospital Services', 'Nos services hospitaliers'); ?></div>
        <div class="service-grid">
            <article class="service-card">
                <div class="service-icon">🏥</div>
                <h3><?php echo appT('Emergency Care', 'Soins d’urgence'); ?></h3>
                <p><?php echo appT('Rapid response and stabilization for urgent medical needs.', 'Réponse rapide et stabilisation pour les besoins médicaux urgents.'); ?></p>
            </article>
            <article class="service-card">
                <div class="service-icon">🤱</div>
                <h3><?php echo appT('Maternal Health', 'Santé maternelle'); ?></h3>
                <p><?php echo appT('Antenatal, delivery, and postnatal support from specialist staff.', 'Soutien prénatal, accouchement et postnatal par un personnel spécialisé.'); ?></p>
            </article>
            <article class="service-card">
                <div class="service-icon">💊</div>
                <h3><?php echo appT('Pharmacy Services', 'Services pharmaceutiques'); ?></h3>
                <p><?php echo appT('Secure medication dispensing and patient counseling.', 'Dispensation sécurisée des médicaments et conseils aux patients.'); ?></p>
            </article>
            <article class="service-card">
                <div class="service-icon">🧪</div>
                <h3><?php echo appT('Laboratory Diagnostics', 'Diagnostics de laboratoire'); ?></h3>
                <p><?php echo appT('Accurate testing and fast results from our lab team.', 'Tests précis et résultats rapides de notre équipe de laboratoire.'); ?></p>
            </article>
            <article class="service-card">
                <div class="service-icon">💉</div>
                <h3><?php echo appT('Vaccination', 'Vaccination'); ?></h3>
                <p><?php echo appT('Carefully managed vaccination services for all age groups.', 'Services de vaccination soigneusement gérés pour tous les âges.'); ?></p>
            </article>
            <article class="service-card">
                <div class="service-icon">🩺</div>
                <h3><?php echo appT('Clinical Consultations', 'Consultations cliniques'); ?></h3>
                <p><?php echo appT('Doctor appointments, follow-up care, and specialist referrals.', 'Rendez-vous médicaux, soins de suivi et orientation vers des spécialistes.'); ?></p>
            </article>
        </div>
    </section>

    <section class="about-panel" id="team">
        <div class="section-heading"><?php echo appT('Clinical Leadership & Team', 'Leadership clinique et équipe'); ?></div>
        <div class="team-grid">
            <?php foreach ($teamMembers as $member): ?>
                <article class="team-card">
                    <div class="team-photo">
                        <?php if (!empty($teamPhotos[$member['slug']])): ?>
                            <img src="<?php echo htmlspecialchars($teamPhotos[$member['slug']]['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?> photo">
                        <?php else: ?>
                            <strong><?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($member['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($member['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!empty($teamPhotos[$member['slug']])): ?>
                        <p style="margin-top:0.75rem;font-size:0.92rem;color:var(--ink-1);">
                            <?php echo appT('Profile photo uploaded.', 'Photo de profil téléchargée.'); ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="photo-upload-info">
            <?php echo appT('Upload a profile photo for any doctor or staff member and use the name field to identify the photo.', 'Téléchargez une photo de profil pour un médecin ou un membre du personnel et utilisez le champ de nom pour identifier la photo.'); ?>
        </div>
    </section>

    <section class="map-panel" id="location">
        <div class="section-heading"><?php echo appT('Clinic Location', 'Emplacement de la clinique'); ?></div>
        <iframe
            src="https://www.google.com/maps?q=Camp+Alangerbault+Mbandjock+Cameroon&output=embed"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
        <p><?php echo appT('The clinic is situated a few meters from SOSUCAM sugar company at Camp Alangerbault, making it easy to find while providing a calm and accessible care environment.', 'La clinique est située à quelques mètres de la sucrerie SOSUCAM au Camp Alangerbault, ce qui la rend facile à trouver tout en offrant un environnement de soins calme et accessible.'); ?></p>
    </section>

    <section class="upload-panel" id="home-upload">
        <div class="portal-head" style="margin-bottom:0.75rem;">
            <h2><?php echo appT('Team Photo Upload', 'Téléchargement de photos de l’équipe'); ?></h2>
            <p><?php echo appT('Upload clinician photos and associate them with doctors and nurses listed above.', 'Téléchargez des photos de cliniciens et associez-les aux médecins et infirmières listés ci-dessus.'); ?></p>
        </div>

        <?php if ($uploadMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($uploadMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($uploadError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="upload-grid">
            <div class="cardish">
                <form method="post" enctype="multipart/form-data">
                    <?php echo homeCsrfField(); ?>
                    <div class="photo-upload-row">
                        <div>
                            <label for="staff_slug"><?php echo appT('Select Staff Profile', 'Sélectionner le profil du personnel'); ?></label>
                            <select id="staff_slug" name="staff_slug" required>
                                <option value=""><?php echo appT('Choose a team member', 'Choisir un membre de l’équipe'); ?></option>
                                <?php foreach ($teamMembers as $member): ?>
                                    <option value="<?php echo htmlspecialchars($member['slug'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($member['name'] . ' — ' . $member['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="home_file"><?php echo appT('Choose Photo', 'Choisir la photo'); ?></label>
                            <input type="file" id="home_file" name="home_file" accept="image/*" required>
                        </div>
                    </div>
                    <p class="upload-note"><?php echo appT('Upload a profile photo for the selected staff member. Image files only, up to 5 MB.', 'Téléchargez une photo de profil pour le membre du personnel sélectionné. Fichiers image seulement, jusqu’à 5 Mo.'); ?></p>
                    <button type="submit" name="upload_home_file"><?php echo appT('Upload Photo', 'Télécharger la photo'); ?></button>
                </form>
            </div>
            <div class="cardish">
                <h3 style="margin-top:0;"><?php echo appT('Team Photos', 'Photos de l’équipe'); ?></h3>
                <?php if (empty($teamPhotos)): ?>
                    <p class="upload-note"><?php echo appT('No team photos uploaded yet.', 'Aucune photo de l’équipe téléchargée pour le moment.'); ?></p>
                <?php else: ?>
                    <ul class="upload-list">
                        <?php foreach ($teamMembers as $member): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($teamPhotos[$member['slug']])): ?>
                                    — <a href="<?php echo htmlspecialchars($teamPhotos[$member['slug']]['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo appT('View photo', 'Voir la photo'); ?></a>
                                <?php else: ?>
                                    — <span class="upload-note"><?php echo appT('No photo yet', 'Pas encore de photo'); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="metrics" role="list">
        <article class="metric" role="listitem">
            <div class="label"><?php echo appT('Service Status', 'Statut du service'); ?></div>
            <div class="value" id="metricStatus"><?php echo appT('Syncing…', 'Synchronisation…'); ?></div>
        </article>
        <article class="metric" role="listitem">
            <div class="label"><?php echo appT('Languages', 'Langues'); ?></div>
            <div class="value"><?php echo appT('English & Français', 'Anglais & Français'); ?></div>
        </article>
        <article class="metric" role="listitem">
            <div class="label"><?php echo appT('Emergency Contact', 'Contact d’urgence'); ?></div>
            <div class="value"><?php echo htmlspecialchars(PAYMENT_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="metric" role="listitem">
            <div class="label"><?php echo appT('Support', 'Support'); ?></div>
            <div class="value"><?php echo htmlspecialchars(CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    </section>

    <div class="grid">
        <section class="panel">
            <h2><?php echo appT('Hospital Features', 'Fonctionnalités de l’hôpital'); ?></h2>
            <ul class="feature-list">
                <li><?php echo appT('24/7 emergency readiness with professional clinical coordination.', 'Disponibilité 24h/24 et 7j/7 avec coordination clinique professionnelle.'); ?></li>
                <li><?php echo appT('Dedicated maternity and vaccination services led by experienced staff.', 'Services dédiés maternité et vaccination dirigés par un personnel expérimenté.'); ?></li>
                <li><?php echo appT('Modern lab technology and secure pharmacy workflows.', 'Technologie de laboratoire moderne et processus pharmaceutiques sécurisés.'); ?></li>
                <li><?php echo appT('Patient registration, appointments, and medical records online.', 'Enregistrement des patients, rendez-vous et dossiers médicaux en ligne.'); ?></li>
            </ul>
        </section>
        <aside class="panel">
            <h2><?php echo appT('Contact & Access', 'Contact et accès'); ?></h2>
            <ul class="feature-list">
                <li><?php echo sprintf(appT('Phone & WhatsApp: %s', 'Téléphone & WhatsApp : %s'), htmlspecialchars(PAYMENT_NUMBER . ' / ' . CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8')); ?></li>
                <li><?php echo appT('Open daily for consultations and urgent care.', 'Ouvert tous les jours pour consultations et soins urgents.'); ?></li>
                <li><?php echo appT('Conveniently located near SOSUCAM sugar company.', 'Situé à proximité de la sucrerie SOSUCAM.'); ?></li>
            </ul>
        </aside>
    </div>

    <p class="foot">
        <?php echo appT('Administration and clinic operations are fully integrated.', 'L’administration et les opérations cliniques sont entièrement intégrées.'); ?>
        <br>
        <?php echo appT('Last checked:', 'Dernière vérification :'); ?> <span id="healthTimestamp">pending&hellip;</span>
    </p>

</main>

    <section class="portal-wrap" id="portal">
        <div class="portal-head">
            <h2>Login Access On Home Page</h2>
            <p>All sign in options stay right here on the homepage, exactly as requested.</p>
        </div>
        <div class="portal-grid">
            <article class="portal-card">
                <h3>Patient Portal</h3>
                <p>Appointments, records, and payments in one secure space.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="patient/login.php">Patient Sign In</a>
                    <a class="mini-btn alt" href="patient/register.php">Register</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Doctor Portal</h3>
                <p>Consultations, shift attendance, and care updates.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="doctor/login.php">Doctor Sign In</a>
                    <a class="mini-btn alt" href="doctor/register.php">Create Account</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Staff Portal</h3>
                <p>Operational workflows, reporting, and communications.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="staff/login.php">Staff Sign In</a>
                    <a class="mini-btn alt" href="staff/register.php">Create Account</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Intern Portal</h3>
                <p>Guided access for internship and supervision tasks.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="intern/login.php">Intern Sign In</a>
                    <a class="mini-btn alt" href="intern/register.php">Create Account</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Trainee Portal</h3>
                <p>Shift activities and learning operations in one place.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="trainee/login.php">Trainee Sign In</a>
                    <a class="mini-btn alt" href="trainee/register.php">Create Account</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Admin Portal</h3>
                <p>Governance, page management, and system oversight.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="admin/login.php">Admin Sign In</a>
                    <a class="mini-btn alt" href="accreditation.php">Accreditation</a>
                </div>
            </article>
        </div>
    </section>

    <section class="upload-panel" id="home-upload">
        <div class="portal-head" style="margin-bottom:0.75rem;">
            <h2>Upload Pictures and Files</h2>
            <p>Share clinic pictures, documents, or media directly from the home page.</p>
        </div>

        <?php if ($uploadMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($uploadMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($uploadError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="upload-grid">
            <div class="cardish">
                <form method="post" enctype="multipart/form-data">
                    <?php echo homeCsrfField(); ?>
                    <div class="mb-2">
                        <label for="home_file" class="form-label"><strong>Select File</strong></label>
                        <input class="form-control" type="file" id="home_file" name="home_file" required>
                    </div>
                    <p class="upload-note">Allowed: JPG, JPEG, PNG, GIF, WEBP, PDF, DOC, DOCX, TXT. Max size: 5 MB.</p>
                    <button class="btn btn-primary" type="submit" name="upload_home_file">Upload</button>
                </form>
            </div>
            <div class="cardish">
                <h3 style="margin-top:0;">Recent Uploads</h3>
                <?php if (!$recentUploads): ?>
                    <p class="upload-note">No files uploaded yet.</p>
                <?php else: ?>
                    <ul class="upload-list">
                        <?php foreach ($recentUploads as $item): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars('uploads/home_uploads/' . $item['name'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="metrics" role="list">
        <article class="metric" role="listitem">
            <div class="label">Platform Status</div>
            <div class="value" id="metricStatus">Syncing&hellip;</div>
        </article>
        <article class="metric" role="listitem">
            <div class="label">Languages</div>
            <div class="value">English &amp; Français</div>
        </article>
        <article class="metric" role="listitem">
            <div class="label">Payment Hotline</div>
            <div class="value"><?php echo htmlspecialchars(PAYMENT_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="metric" role="listitem">
            <div class="label">Customer Service</div>
            <div class="value"><?php echo htmlspecialchars(CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    </section>

    <div class="grid">
        <section class="panel">
            <h2>Why This Experience Feels Captivating</h2>
            <ul class="feature-list">
                <li>Editorial hero composition with layered gradients and cards for visual depth.</li>
                <li>All role logins available on home page without navigation friction.</li>
                <li>Realtime operational status connected to a live health endpoint.</li>
                <li>Clear call paths for registration, accreditation, and public communications.</li>
                <li>Mobile-first responsive behavior for teams and patients on the move.</li>
            </ul>
        </section>
        <aside class="panel">
            <h2>Public Access</h2>
            <ul class="feature-list">
                <li><a href="public_communications.php">Public Communications Board</a></li>
                <li><a href="sitemap.php">Site Map</a></li>
                <li><a href="accreditation.php">Accreditation and Compliance</a></li>
            </ul>
        </aside>
    </div>

    <p class="foot">
        Health endpoint: <strong>/api/health.php</strong> &middot;
        Last checked: <span id="healthTimestamp">pending&hellip;</span>
    </p>

</main>

<script>
(function () {
    'use strict';

    /* ── Live health check ── */
    async function loadHealth() {
        const dot        = document.getElementById('statusDot');
        const text       = document.getElementById('statusText');
        const metricEl   = document.getElementById('metricStatus');
        const tsEl       = document.getElementById('healthTimestamp');

        try {
            const res     = await fetch('api/health.php', { cache: 'no-store' });
            const payload = await res.json();
            const ok      = payload.status === 'operational';

            dot.classList.toggle('ok', ok);
            text.textContent      = ok ? 'All core services operational'
                                       : 'Service degraded — monitoring in progress';
            metricEl.textContent  = ok ? 'Operational' : 'Degraded';
            tsEl.textContent      = payload.generated_at || new Date().toISOString();
        } catch (_) {
            text.textContent     = 'Status unavailable — retrying automatically';
            metricEl.textContent = 'Unknown';
            tsEl.textContent     = new Date().toISOString();
        }
    }

    /* ── Live global clock ── */
    const zones = {
        timeDouala : 'Africa/Douala',
        timeUtc    : 'UTC',
        timeNy     : 'America/New_York',
        timeLondon : 'Europe/London',
        timeTokyo  : 'Asia/Tokyo',
    };
    const fmts = {};
    for (const [id, tz] of Object.entries(zones)) {
        fmts[id] = new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: tz
        });
    }

    function updateClock() {
        const now = new Date();
        for (const [id, fmt] of Object.entries(fmts)) {
            const el = document.getElementById(id);
            if (el) el.textContent = fmt.format(now);
        }
    }

    loadHealth();
    updateClock();
    setInterval(loadHealth, 60000);
    setInterval(updateClock, 1000);
}());

<?php if ($autoOpenUploadUrl !== ''): ?>
window.addEventListener('load', function () {
    window.open('<?php echo htmlspecialchars($autoOpenUploadUrl, ENT_QUOTES, 'UTF-8'); ?>', '_blank', 'noopener');
});
<?php endif; ?>
</script>
</body>
</html>