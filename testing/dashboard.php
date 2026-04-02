<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'qa_tester', 'developer'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDB();
        
        if ($action === 'run_test') {
            $testType = $_POST['test_type'];
            $testName = $_POST['test_name'] ?? 'Automated Test';
            
            // Simulate running a test
            $startTime = microtime(true);
            
            // Run the actual test based on type
            $result = runAutomatedTest($testType);
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds
            
            // Save test result
            $stmt = $pdo->prepare("
                INSERT INTO test_results (test_name, test_type, status, duration_ms, results, run_by, run_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $testName,
                $testType,
                $result['status'],
                $duration,
                json_encode($result),
                $user['id']
            ]);
            
            $message = 'Test completed successfully!';
            
        } elseif ($action === 'create_test_case') {
            $title = $_POST['title'];
            $description = $_POST['description'];
            $testType = $_POST['test_type'];
            $priority = $_POST['priority'];
            
            $stmt = $pdo->prepare("
                INSERT INTO test_cases (title, description, test_type, priority, status, created_by, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            
            $stmt->execute([$title, $description, $testType, $priority, $user['id']]);
            
            $message = 'Test case created successfully!';
        }
        
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
    }
}

// Get test results
try {
    $pdo = getDB();
    
    // Recent test results
    $stmt = $pdo->prepare("
        SELECT tr.*, u.first_name, u.last_name
        FROM test_results tr
        JOIN users u ON tr.run_by = u.id
        ORDER BY tr.run_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test statistics
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_tests,
            SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed_tests,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_tests,
            AVG(duration_ms) as avg_duration
        FROM test_results 
        WHERE run_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Test cases
    $testCasesStmt = $pdo->query("
        SELECT tc.*, u.first_name, u.last_name
        FROM test_cases tc
        JOIN users u ON tc.created_by = u.id
        ORDER BY tc.created_at DESC
        LIMIT 5
    ");
    $testCases = $testCasesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $recentTests = [];
    $stats = ['total_tests' => 0, 'passed_tests' => 0, 'failed_tests' => 0, 'avg_duration' => 0];
    $testCases = [];
}

function runAutomatedTest($testType) {
    switch ($testType) {
        case 'database':
            // Test database connectivity and basic queries
            try {
                $pdo = getDB();
                
                // Test basic queries
                $pdo->query("SELECT 1");
                
                // Test patient table
                $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
                $patientCount = $stmt->fetchColumn();
                
                return [
                    'status' => 'passed',
                    'message' => 'Database connectivity and basic queries working',
                    'details' => ['patient_count' => $patientCount]
                ];
            } catch (Exception $e) {
                return [
                    'status' => 'failed',
                    'message' => 'Database test failed: ' . $e->getMessage()
                ];
            }
            
        case 'api':
            // Test API endpoints
            $endpoints = [
                'health' => '/api/health',
                'patients' => '/api/patients'
            ];
            
            $results = [];
            foreach ($endpoints as $name => $endpoint) {
                $url = 'http://' . $_SERVER['HTTP_HOST'] . $endpoint;
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => 'X-API-Key: test-key'
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                $results[$name] = $response ? 'success' : 'failed';
            }
            
            $allPassed = !in_array('failed', $results);
            
            return [
                'status' => $allPassed ? 'passed' : 'failed',
                'message' => $allPassed ? 'All API endpoints responding' : 'Some API endpoints failed',
                'details' => $results
            ];
            
        case 'security':
            // Basic security checks
            $issues = [];
            
            // Check for common security issues
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                $issues[] = 'HTTPS not enabled';
            }
            
            // Check file permissions (simplified)
            $configFile = '../config/config.php';
            if (file_exists($configFile) && is_readable($configFile)) {
                $issues[] = 'Config file should not be world-readable';
            }
            
            return [
                'status' => empty($issues) ? 'passed' : 'warning',
                'message' => empty($issues) ? 'Security checks passed' : 'Security issues found',
                'details' => $issues
            ];
            
        case 'performance':
            // Basic performance test
            $start = microtime(true);
            
            // Simulate some operations
            $pdo = getDB();
            for ($i = 0; $i < 10; $i++) {
                $pdo->query("SELECT COUNT(*) FROM patients");
            }
            
            $end = microtime(true);
            $duration = ($end - $start) * 1000;
            
            return [
                'status' => $duration < 500 ? 'passed' : 'warning',
                'message' => "Performance test completed in {$duration}ms",
                'details' => ['duration_ms' => $duration]
            ];
            
        default:
            return [
                'status' => 'failed',
                'message' => 'Unknown test type'
            ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing & QA Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Testing & QA</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="test_runner.php">Test Runner</a>
                <a class="nav-link" href="test_cases.php">Test Cases</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Testing & Quality Assurance</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Test Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Tests</h5>
                        <h3 class="text-primary"><?php echo $stats['total_tests']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Passed</h5>
                        <h3 class="text-success"><?php echo $stats['passed_tests']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Failed</h5>
                        <h3 class="text-danger"><?php echo $stats['failed_tests']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Avg Duration</h5>
                        <h3 class="text-info"><?php echo round($stats['avg_duration'], 1); ?>ms</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Run Tests -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Run Automated Tests</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="run_test">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="test_type" class="form-label">Test Type</label>
                                        <select class="form-select" id="test_type" name="test_type" required>
                                            <option value="">Select Test Type</option>
                                            <option value="database">Database Connectivity</option>
                                            <option value="api">API Endpoints</option>
                                            <option value="security">Security Checks</option>
                                            <option value="performance">Performance Test</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="test_name" class="form-label">Test Name (Optional)</label>
                                        <input type="text" class="form-control" id="test_name" name="test_name" placeholder="Custom test name">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Run Test</button>
                        </form>
                    </div>
                </div>

                <!-- Recent Test Results -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Test Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Run By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTests as $test): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                            <td><?php echo ucfirst($test['test_type']); ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = 'secondary';
                                                if ($test['status'] === 'passed') {
                                                    $badgeClass = 'success';
                                                } elseif ($test['status'] === 'failed') {
                                                    $badgeClass = 'danger';
                                                } elseif ($test['status'] === 'warning') {
                                                    $badgeClass = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($test['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $test['duration_ms']; ?>ms</td>
                                            <td><?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?></td>
                                            <td><?php echo date('M d, H:i', strtotime($test['run_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Create Test Case -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Create Test Case</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_test_case">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="test_type" class="form-label">Type</label>
                                <select class="form-select" id="test_type" name="test_type" required>
                                    <option value="unit">Unit Test</option>
                                    <option value="integration">Integration Test</option>
                                    <option value="functional">Functional Test</option>
                                    <option value="security">Security Test</option>
                                    <option value="performance">Performance Test</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Create Test Case</button>
                        </form>
                    </div>
                </div>

                <!-- Test Cases -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Test Cases</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($testCases as $case): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($case['title']); ?></h6>
                                        <small>
                                            <?php
                                            $priorityClass = 'secondary';
                                            if ($case['priority'] === 'low') {
                                                $priorityClass = 'secondary';
                                            } elseif ($case['priority'] === 'medium') {
                                                $priorityClass = 'info';
                                            } elseif ($case['priority'] === 'high') {
                                                $priorityClass = 'warning';
                                            } elseif ($case['priority'] === 'critical') {
                                                $priorityClass = 'danger';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $priorityClass; ?>">
                                                <?php echo ucfirst($case['priority']); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars(substr($case['description'], 0, 50)) . '...'; ?></p>
                                    <small class="text-muted">
                                        <?php echo ucfirst($case['test_type']); ?> • 
                                        By <?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>