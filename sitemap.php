<?php
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/xml; charset=UTF-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = SITE_URL !== '' ? SITE_URL : ($host !== '' ? ($scheme . '://' . $host) : '');

if ($baseUrl === '') {
    http_response_code(500);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<error>Unable to resolve site URL. Set SITE_URL in environment.</error>\n";
    exit;
}

$pages = [
    'index.php' => 'daily',
    'accreditation.php' => 'weekly',
    'accreditation_content.php' => 'weekly',
    'api/health.php' => 'weekly',
    '404.php' => 'monthly',
    '500.php' => 'monthly',
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($pages as $path => $changefreq) {
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($filePath)) {
        continue;
    }

    $lastmod = gmdate('Y-m-d', filemtime($filePath));
    $loc = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>{$changefreq}</changefreq>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
