<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'doctor'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDB();
        
        if ($action === 'generate_suggestion') {
            $patientId = $_POST['patient_id'];
            $context = $_POST['context'];
            $suggestionType = $_POST['suggestion_type'];
            
            // Get patient data for context
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       COUNT(a.id) as appointment_count,
                       MAX(a.appointment_date) as last_appointment
                FROM patients p
                LEFT JOIN appointments a ON p.id = a.patient_id
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent consultations
            $stmt = $pdo->prepare("
                SELECT * FROM consultations 
                WHERE patient_id = ? 
                ORDER BY consultation_date DESC 
                LIMIT 3
            ");
            $stmt->execute([$patientId]);
            $recentConsultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate AI suggestion based on type
            $suggestion = generateAiSuggestion($suggestionType, $patient, $recentConsultations, $context);
            
            // Save suggestion
            $confidence = rand(70, 95); // Simulated confidence score
            $contextData = json_encode(['patient' => $patient, 'consultations' => $recentConsultations, 'context' => $context]);

            try {
                // Newer schema (SQLite setup)
                $stmt = $pdo->prepare("\
                    INSERT INTO ai_suggestions (patient_id, doctor_id, suggestion_type, context_data, suggestion_text, confidence_score)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $patientId,
                    $user['id'],
                    $suggestionType,
                    $contextData,
                    $suggestion,
                    $confidence
                ]);
            } catch (PDOException $e) {
                // Legacy schema fallback (MySQL install.sql)
                $stmt = $pdo->prepare("\
                    INSERT INTO ai_suggestions (patient_id, suggestion_type, suggestion_text, confidence_score, suggested_by, accepted)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $patientId,
                    $suggestionType,
                    $suggestion,
                    $confidence,
                    (string)($user['username'] ?? 'system')
                ]);
            }
            
            $message = 'AI suggestion generated successfully!';
            
        } elseif ($action === 'apply_suggestion') {
            $suggestionId = $_POST['suggestion_id'];
            
            // Mark suggestion as applied
            try {
                $stmt = $pdo->prepare("UPDATE ai_suggestions SET applied_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$suggestionId]);
            } catch (PDOException $e) {
                $stmt = $pdo->prepare("UPDATE ai_suggestions SET accepted = 1 WHERE id = ?");
                $stmt->execute([$suggestionId]);
            }
            
            $message = 'Suggestion applied successfully!';
            
        } elseif ($action === 'dismiss_suggestion') {
            $suggestionId = $_POST['suggestion_id'];
            
            // Mark suggestion as dismissed
            try {
                $stmt = $pdo->prepare("UPDATE ai_suggestions SET dismissed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$suggestionId]);
            } catch (PDOException $e) {
                $stmt = $pdo->prepare("DELETE FROM ai_suggestions WHERE id = ?");
                $stmt->execute([$suggestionId]);
            }
            
            $message = 'Suggestion dismissed.';
        }
        
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
    }
}

// Get recent suggestions
try {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("\
            SELECT s.*, u.first_name, u.last_name
            FROM ai_suggestions s
            JOIN patients p ON s.patient_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE s.doctor_id = ? AND s.applied_at IS NULL AND s.dismissed_at IS NULL
            ORDER BY s.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user['id']]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("\
            SELECT s.*, u.first_name, u.last_name
            FROM ai_suggestions s
            JOIN patients p ON s.patient_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE (s.suggested_by = ? OR s.suggested_by IS NULL) AND (s.accepted = 0 OR s.accepted IS NULL)
            ORDER BY s.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([(string)($user['username'] ?? '')]);
    }
    $recentSuggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get patients for dropdown
    $patientsStmt = $pdo->query("\
        SELECT p.id, u.first_name, u.last_name
        FROM patients p
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY u.first_name, u.last_name
    ");
    $patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $recentSuggestions = [];
    $patients = [];
}

function generateAiSuggestion($type, $patient, $consultations, $context) {
    switch ($type) {
        case 'diagnosis':
            $suggestions = [
                "Based on patient history and symptoms, consider differential diagnosis of hypertension. Recommend blood pressure monitoring and lifestyle modifications.",
                "Patient presents with respiratory symptoms. Consider chest X-ray and pulmonary function tests to rule out pneumonia or COPD.",
                "Elevated blood glucose levels suggest possible diabetes mellitus. Recommend HbA1c testing and nutritional counseling.",
                "Joint pain and stiffness may indicate rheumatoid arthritis. Consider ESR, CRP, and rheumatology referral."
            ];
            return $suggestions[array_rand($suggestions)];
            
        case 'treatment':
            $suggestions = [
                "For this patient's condition, consider starting with conservative management including NSAIDs and physical therapy before considering surgical options.",
                "Antibiotic therapy recommended based on clinical presentation. Consider amoxicillin 500mg TID for 7-10 days.",
                "Patient may benefit from beta-blocker therapy for blood pressure control. Start with low dose and titrate based on response.",
                "Recommend lifestyle modifications including diet, exercise, and smoking cessation counseling as first-line therapy."
            ];
            return $suggestions[array_rand($suggestions)];
            
        case 'followup':
            $suggestions = [
                "Schedule follow-up appointment in 2 weeks to assess treatment response and adjust therapy as needed.",
                "Recommend 3-month follow-up for chronic condition monitoring and medication review.",
                "Patient requires close monitoring. Schedule weekly check-ins until symptoms stabilize.",
                "Long-term follow-up recommended. Schedule 6-month appointment for comprehensive review."
            ];
            return $suggestions[array_rand($suggestions)];
            
        case 'prevention':
            $suggestions = [
                "Recommend annual influenza vaccination and pneumococcal vaccine for age-appropriate prevention.",
                "Counsel patient on smoking cessation and provide nicotine replacement therapy resources.",
                "Advise regular exercise (150 minutes/week) and Mediterranean diet for cardiovascular prevention.",
                "Recommend cancer screening based on age and risk factors: colonoscopy every 10 years, mammogram annually."
            ];
            return $suggestions[array_rand($suggestions)];
            
        default:
            return "General recommendation: Continue monitoring patient condition and adjust treatment plan based on clinical response.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Clinical Assistant - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - AI Assistant</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="suggestions.php">Suggestions</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>AI Clinical Decision Support</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Generate New Suggestion -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Generate AI Suggestion</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="generate_suggestion">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="patient_id" class="form-label">Patient</label>
                                        <select class="form-select" id="patient_id" name="patient_id" required>
                                            <option value="">Select Patient</option>
                                            <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>">
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="suggestion_type" class="form-label">Suggestion Type</label>
                                        <select class="form-select" id="suggestion_type" name="suggestion_type" required>
                                            <option value="">Select Type</option>
                                            <option value="diagnosis">Diagnosis Support</option>
                                            <option value="treatment">Treatment Recommendation</option>
                                            <option value="followup">Follow-up Planning</option>
                                            <option value="prevention">Prevention Counseling</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="context" class="form-label">Clinical Context</label>
                                <textarea class="form-control" id="context" name="context" rows="3" placeholder="Enter symptoms, test results, or other clinical information..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Generate Suggestion</button>
                        </form>
                    </div>
                </div>

                <!-- Recent Suggestions -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent AI Suggestions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentSuggestions)): ?>
                            <p class="text-muted">No recent suggestions available.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recentSuggestions as $suggestion): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($suggestion['first_name'] . ' ' . $suggestion['last_name']); ?> - 
                                                <?php echo ucfirst(str_replace('_', ' ', $suggestion['suggestion_type'])); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('M d, H:i', strtotime($suggestion['created_at'])); ?>
                                                <span class="badge bg-info"><?php echo $suggestion['confidence_score']; ?>%</span>
                                            </small>
                                        </div>
                                        <p class="mb-2"><?php echo htmlspecialchars($suggestion['suggestion_text']); ?></p>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display: inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="apply_suggestion">
                                                <input type="hidden" name="suggestion_id" value="<?php echo $suggestion['id']; ?>">
                                                <button type="submit" class="btn btn-success">Apply</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="dismiss_suggestion">
                                                <input type="hidden" name="suggestion_id" value="<?php echo $suggestion['id']; ?>">
                                                <button type="submit" class="btn btn-secondary">Dismiss</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- AI Analytics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>AI Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Suggestions Generated Today:</strong>
                            <span class="float-end"><?php
                                try {
                                    $pdo = getDB();
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_suggestions WHERE DATE(created_at) = DATE('now') AND doctor_id = ?");
                                        $stmt->execute([$user['id']]);
                                    } catch (PDOException $e) {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_suggestions WHERE DATE(created_at) = CURDATE() AND (suggested_by = ? OR suggested_by IS NULL)");
                                        $stmt->execute([(string)($user['username'] ?? '')]);
                                    }
                                    echo $stmt->fetchColumn();
                                } catch (PDOException $e) {
                                    echo 'N/A';
                                }
                            ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Suggestions Applied:</strong>
                            <span class="float-end"><?php
                                try {
                                    $pdo = getDB();
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_suggestions WHERE applied_at IS NOT NULL AND doctor_id = ?");
                                        $stmt->execute([$user['id']]);
                                    } catch (PDOException $e) {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_suggestions WHERE accepted = 1 AND (suggested_by = ? OR suggested_by IS NULL)");
                                        $stmt->execute([(string)($user['username'] ?? '')]);
                                    }
                                    echo $stmt->fetchColumn();
                                } catch (PDOException $e) {
                                    echo 'N/A';
                                }
                            ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Average Confidence:</strong>
                            <span class="float-end"><?php
                                try {
                                    $pdo = getDB();
                                    try {
                                        $stmt = $pdo->prepare("SELECT AVG(confidence_score) FROM ai_suggestions WHERE doctor_id = ?");
                                        $stmt->execute([$user['id']]);
                                    } catch (PDOException $e) {
                                        $stmt = $pdo->prepare("SELECT AVG(confidence_score) FROM ai_suggestions WHERE (suggested_by = ? OR suggested_by IS NULL)");
                                        $stmt->execute([(string)($user['username'] ?? '')]);
                                    }
                                    $avg = $stmt->fetchColumn();
                                    echo $avg ? round($avg) . '%' : 'N/A';
                                } catch (PDOException $e) {
                                    echo 'N/A';
                                }
                            ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="quickSuggestion('diagnosis')">
                                Quick Diagnosis Check
                            </button>
                            <button class="btn btn-outline-primary" onclick="quickSuggestion('treatment')">
                                Treatment Recommendation
                            </button>
                            <button class="btn btn-outline-primary" onclick="quickSuggestion('prevention')">
                                Prevention Counseling
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function quickSuggestion(type) {
            document.getElementById('suggestion_type').value = type;
            document.getElementById('patient_id').focus();
        }
    </script>
</body>
</html>