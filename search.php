<?php
require_once 'config/config.php';
require_once 'includes/language.php';
require_once 'includes/auth.php';

$q = trim((string)($_GET['q'] ?? ''));
$searchError = '';
$searchResults = [];
$resultCount = 0;

function escapeSearchTerm(string $term): string {
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
}

function searchTable(PDO $pdo, string $sql, array $params): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($q !== '') {
    try {
        $pdo = getDB();
        $pattern = escapeSearchTerm($q);

        $users = searchTable($pdo,
            'SELECT id, username, first_name, last_name, role, email FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR role LIKE ? ORDER BY first_name LIMIT 25',
            [$pattern, $pattern, $pattern, $pattern, $pattern]
        );
        foreach ($users as $row) {
            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $row['username'];
            $searchResults[] = [
                'category' => 'Person',
                'title' => $fullName,
                'subtitle' => 'User role: ' . ($row['role'] ?? 'staff'),
                'url' => 'admin/manage_users.php',
                'note' => 'Search users in the system',
            ];
        }

        $patients = searchTable($pdo,
            'SELECT p.id, p.medical_record_number, u.first_name, u.last_name, u.username FROM patients p LEFT JOIN users u ON u.id = p.user_id WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR p.medical_record_number LIKE ? ORDER BY u.first_name LIMIT 25',
            [$pattern, $pattern, $pattern, $pattern]
        );
        foreach ($patients as $row) {
            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $row['username'];
            $searchResults[] = [
                'category' => 'Patient',
                'title' => $fullName ?: 'Patient #' . ($row['medical_record_number'] ?? 'N/A'),
                'subtitle' => 'Medical record: ' . ($row['medical_record_number'] ?? 'unknown'),
                'url' => 'admin/manage_users.php',
                'note' => 'Patient record search results',
            ];
        }

        $doctors = searchTable($pdo,
            'SELECT d.id, u.first_name, u.last_name, d.specialization FROM doctors d LEFT JOIN users u ON u.id = d.user_id WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR d.specialization LIKE ? ORDER BY u.first_name LIMIT 25',
            [$pattern, $pattern, $pattern]
        );
        foreach ($doctors as $row) {
            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Doctor';
            $searchResults[] = [
                'category' => 'Doctor',
                'title' => $fullName,
                'subtitle' => 'Specialization: ' . ($row['specialization'] ?? 'General'),
                'url' => 'doctor/dashboard.php',
                'note' => 'Search doctors by name or specialty',
            ];
        }

        if (function_exists('searchTable')) {
            // Groups
            try {
                $groups = searchTable($pdo,
                    'SELECT id, name, description FROM patient_groups WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 25',
                    [$pattern, $pattern]
                );
                foreach ($groups as $row) {
                    $searchResults[] = [
                        'category' => 'Group',
                        'title' => $row['name'],
                        'subtitle' => trim((string)$row['description']),
                        'url' => 'admin/manage_groups.php',
                        'note' => 'Patient group match',
                    ];
                }
            } catch (Throwable $e) {
                // Ignore missing table.
            }
        }

        try {
            $pages = searchTable($pdo,
                'SELECT id, title, slug FROM custom_pages WHERE title LIKE ? OR slug LIKE ? OR content LIKE ? ORDER BY title LIMIT 25',
                [$pattern, $pattern, $pattern]
            );
            foreach ($pages as $row) {
                $searchResults[] = [
                    'category' => 'Page',
                    'title' => $row['title'],
                    'subtitle' => 'Page slug: ' . ($row['slug'] ?? ''),
                    'url' => 'page.php?slug=' . urlencode($row['slug']),
                    'note' => 'Custom page search result',
                ];
            }
        } catch (Throwable $e) {
            // Ignore missing pages table.
        }

        try {
            $reports = searchTable($pdo,
                'SELECT id, report_name, report_type FROM reports WHERE report_name LIKE ? OR report_type LIKE ? ORDER BY report_name LIMIT 25',
                [$pattern, $pattern]
            );
            foreach ($reports as $row) {
                $searchResults[] = [
                    'category' => 'Report',
                    'title' => $row['report_name'],
                    'subtitle' => 'Type: ' . ($row['report_type'] ?? 'report'),
                    'url' => 'reports/dashboard.php',
                    'note' => 'Report search result',
                ];
            }
        } catch (Throwable $e) {
            // Ignore missing reports table.
        }

        try {
            $drugs = searchTable($pdo,
                'SELECT id, medication_name, generic_name, dosage_form FROM pharmacy_inventory WHERE medication_name LIKE ? OR generic_name LIKE ? OR dosage_form LIKE ? ORDER BY medication_name LIMIT 25',
                [$pattern, $pattern, $pattern]
            );
            foreach ($drugs as $row) {
                $searchResults[] = [
                    'category' => 'Drug',
                    'title' => $row['medication_name'],
                    'subtitle' => trim((string)$row['generic_name']) . ' • ' . trim((string)$row['dosage_form']),
                    'url' => 'pharmacy/inventory.php',
                    'note' => 'Medication search result',
                ];
            }
        } catch (Throwable $e) {
            // Ignore missing pharmacy table.
        }

        $resultCount = count($searchResults);
    } catch (Throwable $e) {
        error_log('Search error: ' . $e->getMessage());
        $searchError = 'Unable to perform search at this time. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(appLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Search | <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; font-family:'Plus Jakarta Sans',sans-serif; background:#eef4f9; color:#10273e; }
        .page { max-width:1120px; margin:0 auto; padding:2rem 1rem 3rem; }
        h1 { margin:0 0 0.5rem; font-family:'Outfit',sans-serif; font-size:2rem; }
        .search-form { display:flex; flex-wrap:wrap; gap:0.75rem; margin:1.5rem 0 1rem; }
        .search-form input { flex:1 1 320px; padding:0.95rem 1rem; border:1px solid #c8d5df; border-radius:14px; font-size:1rem; }
        .search-form button { border:0; border-radius:14px; background:#0fb39d; color:#fff; font-weight:700; padding:0.95rem 1.2rem; cursor:pointer; }
        .search-summary { margin:1rem 0 0.5rem; font-size:0.95rem; color:#4c687d; }
        .result-list { list-style:none; padding:0; margin:0; display:grid; gap:1rem; }
        .result-card { border:1px solid #cbd6e0; border-radius:18px; background:#fff; padding:1.2rem; box-shadow:0 12px 30px rgba(15, 51, 75, 0.06); }
        .result-card a { color:#0d1a2a; text-decoration:none; display:block; }
        .result-card a:hover { text-decoration:underline; }
        .result-category { display:inline-flex; align-items:center; gap:0.45rem; font-size:0.82rem; letter-spacing:0.03em; text-transform:uppercase; color:#12786a; font-weight:700; }
        .result-title { margin:0.45rem 0 0.35rem; font-size:1.05rem; }
        .result-subtitle { margin:0; color:#5c7283; font-size:0.95rem; }
        .result-note { margin-top:0.75rem; color:#6f8699; font-size:0.9rem; }
        .alert { padding:1rem 1.2rem; border-radius:14px; margin:1rem 0; border:1px solid #d7e2e9; background:#f7fbff; color:#0e314d; }
        .page-link { display:inline-flex; align-items:center; gap:0.5rem; font-size:0.92rem; color:#0f8e74; }
    </style>
</head>
<body>
<div class="page">
    <header>
        <h1><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> Search</h1>
        <p>Search names, drugs, groups, pages, reports and more across the clinic system.</p>
    </header>
    <form class="search-form" action="search.php" method="get" role="search">
        <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search staff, patients, drugs, groups, pages, reports…" aria-label="Search the system">
        <button type="submit">Search</button>
    </form>

    <?php if ($searchError): ?>
        <div class="alert"><?php echo htmlspecialchars($searchError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($q === ''): ?>
        <div class="alert">Enter a name, drug, group, page title, report name, or other term to find results across the system.</div>
    <?php else: ?>
        <div class="search-summary"><?php echo $resultCount; ?> result<?php echo $resultCount === 1 ? '' : 's'; ?> for <strong><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php if ($resultCount === 0): ?>
            <div class="alert">No matching records were found. Try a shorter name or keyword.</div>
        <?php else: ?>
            <ul class="result-list">
                <?php foreach ($searchResults as $item): ?>
                    <li class="result-card">
                        <div class="result-category"><?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <h2 class="result-title"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        </a>
                        <p class="result-subtitle"><?php echo htmlspecialchars($item['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="result-note"><?php echo htmlspecialchars($item['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <p class="page-link"><a href="index.php">Back to home</a></p>
</div>
</body>
</html>
