<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'translator'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

$currentLang = $_GET['lang'] ?? 'en';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDB();
        
        if ($action === 'add_translation') {
            $key = $_POST['translation_key'];
            $language = $_POST['language'];
            $translation = $_POST['translation'];
            
            $stmt = $pdo->prepare("
                INSERT INTO translations (translation_key, language_code, translation_text, created_by, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                translation_text = VALUES(translation_text),
                updated_by = VALUES(created_by),
                updated_at = NOW()
            ");
            
            $stmt->execute([$key, $language, $translation, $user['id']]);
            
            $message = 'Translation added/updated successfully!';
            
        } elseif ($action === 'add_language') {
            $code = $_POST['language_code'];
            $name = $_POST['language_name'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO languages (code, name, is_active, created_by, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$code, $name, $isActive, $user['id']]);
            
            $message = 'Language added successfully!';
            
        } elseif ($action === 'export_translations') {
            $language = $_POST['export_language'];
            
            $stmt = $pdo->prepare("SELECT translation_key, translation_text FROM translations WHERE language_code = ?");
            $stmt->execute([$language]);
            $translations = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $filename = "translations_{$language}_" . date('Y-m-d') . '.json';
            $filepath = '../exports/' . $filename;
            
            if (!is_dir('../exports')) {
                mkdir('../exports', 0755, true);
            }
            
            file_put_contents($filepath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $message = "Translations exported to {$filename}";
        }
        
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
    }
}

// Get available languages
try {
    $pdo = getDB();
    
    $langStmt = $pdo->query("SELECT * FROM languages ORDER BY name");
    $languages = $langStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get translations for current language
    $transStmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name
        FROM translations t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.language_code = ?
        ORDER BY t.translation_key
    ");
    $transStmt->execute([$currentLang]);
    $translations = $transStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get translation keys that need translation
    $keysStmt = $pdo->query("SELECT DISTINCT translation_key FROM translations ORDER BY translation_key");
    $allKeys = $keysStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Check missing translations for current language
    $missingStmt = $pdo->prepare("
        SELECT translation_key 
        FROM translations 
        WHERE language_code = 'en' 
        AND translation_key NOT IN (
            SELECT translation_key FROM translations WHERE language_code = ?
        )
    ");
    $missingStmt->execute([$currentLang]);
    $missingTranslations = $missingStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $languages = [];
    $translations = [];
    $allKeys = [];
    $missingTranslations = [];
}

// Translation helper function
function __($key, $lang = null) {
    static $translations = [];
    
    $lang = $lang ?? 'en';
    
    if (!isset($translations[$lang])) {
        // Load translations from database (simplified - in real app, cache this)
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT translation_key, translation_text FROM translations WHERE language_code = ?");
            $stmt->execute([$lang]);
            $translations[$lang] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $translations[$lang] = [];
        }
    }
    
    return $translations[$lang][$key] ?? $key;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internationalization - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - i18n</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Internationalization & Localization</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Language Selector -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Current Language: 
                    <?php 
                    $currentLangName = 'English';
                    foreach ($languages as $lang) {
                        if ($lang['code'] === $currentLang) {
                            $currentLangName = $lang['name'];
                            break;
                        }
                    }
                    echo htmlspecialchars($currentLangName);
                    ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($languages as $lang): ?>
                        <div class="col-md-2 mb-2">
                            <a href="?lang=<?php echo $lang['code']; ?>" class="btn btn-outline-primary <?php echo $lang['code'] === $currentLang ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($lang['name']); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Translations List -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Translations</h5>
                        <span class="badge bg-info"><?php echo count($translations); ?> translations</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($missingTranslations)): ?>
                            <div class="alert alert-warning">
                                <strong>Missing Translations:</strong> <?php echo count($missingTranslations); ?> keys need translation.
                                <a href="#add-translation" class="alert-link">Add them now</a>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Key</th>
                                        <th>Translation</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($translations as $trans): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($trans['translation_key']); ?></code></td>
                                            <td><?php echo htmlspecialchars($trans['translation_text']); ?></td>
                                            <td><?php echo $trans['updated_at'] ? date('M d, Y', strtotime($trans['updated_at'])) : date('M d, Y', strtotime($trans['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editTranslation('<?php echo $trans['translation_key']; ?>', '<?php echo addslashes($trans['translation_text']); ?>')">
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Translation -->
                <div class="card" id="add-translation">
                    <div class="card-header">
                        <h5>Add/Edit Translation</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="add_translation">
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="translation_key" class="form-label">Translation Key</label>
                                        <input type="text" class="form-control" id="translation_key" name="translation_key" required>
                                        <div class="form-text">Use lowercase with underscores (e.g., welcome_message)</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="language" class="form-label">Language</label>
                                        <select class="form-select" id="language" name="language" required>
                                            <?php foreach ($languages as $lang): ?>
                                                <option value="<?php echo $lang['code']; ?>" <?php echo $lang['code'] === $currentLang ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($lang['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="translation" class="form-label">Translation Text</label>
                                        <input type="text" class="form-control" id="translation" name="translation" required>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Translation</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Add Language -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Add New Language</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="add_language">
                            
                            <div class="mb-3">
                                <label for="language_code" class="form-label">Language Code</label>
                                <input type="text" class="form-control" id="language_code" name="language_code" placeholder="en" required>
                                <div class="form-text">ISO 639-1 code (e.g., en, fr, es)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="language_name" class="form-label">Language Name</label>
                                <input type="text" class="form-control" id="language_name" name="language_name" placeholder="English" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Add Language</button>
                        </form>
                    </div>
                </div>

                <!-- Export Translations -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Export Translations</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="export_translations">
                            
                            <div class="mb-3">
                                <label for="export_language" class="form-label">Language</label>
                                <select class="form-select" id="export_language" name="export_language" required>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?php echo $lang['code']; ?>">
                                            <?php echo htmlspecialchars($lang['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-info">Export to JSON</button>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h5>Translation Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Total Languages:</strong>
                            <span class="float-end"><?php echo count($languages); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Active Languages:</strong>
                            <span class="float-end"><?php echo count(array_filter($languages, fn($l) => $l['is_active'])); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Translation Keys:</strong>
                            <span class="float-end"><?php echo count($allKeys); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Missing (<?php echo $currentLangName; ?>):</strong>
                            <span class="float-end"><?php echo count($missingTranslations); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Completion Rate:</strong>
                            <span class="float-end">
                                <?php 
                                $totalKeys = count($allKeys);
                                $translatedKeys = count($translations);
                                $rate = $totalKeys > 0 ? round(($translatedKeys / $totalKeys) * 100) : 0;
                                echo $rate . '%';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editTranslation(key, text) {
            document.getElementById('translation_key').value = key;
            document.getElementById('translation').value = text;
            document.getElementById('add-translation').scrollIntoView();
        }
    </script>
</body>
</html>