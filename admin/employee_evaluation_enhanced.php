<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$message = '';
$error = '';
$pdo = getDB();

// Handle form submission via AJAX or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $empName = trim($_POST['empName'] ?? '');
    $empId = trim($_POST['empId'] ?? '');
    $dept = trim($_POST['dept'] ?? '');
    $period = trim($_POST['period'] ?? '');
    $evaluator = trim($_POST['evaluator'] ?? '');
    
    $assiduity = (float)($_POST['assiduity'] ?? 0);
    $punctuality = (float)($_POST['punctuality'] ?? 0);
    $productivity = (float)($_POST['productivity'] ?? 0);
    $illnessMgmt = (float)($_POST['illnessMgmt'] ?? 0);
    
    $illCert = (int)($_POST['illCert'] ?? 0);
    $illUncert = (int)($_POST['illUncert'] ?? 0);
    $permApproved = (int)($_POST['permApproved'] ?? 0);
    $permUnapproved = (int)($_POST['permUnapproved'] ?? 0);
    $unauthAbsence = (int)($_POST['unauthAbsence'] ?? 0);
    
    $sanctionsCount = (int)($_POST['sanctionsCount'] ?? 0);
    $suspensionDays = (int)($_POST['suspensionDays'] ?? 0);
    $queryLetter = trim($_POST['queryLetter'] ?? '');
    $discNote = trim($_POST['discNote'] ?? '');
    
    $punchAccuracy = (int)($_POST['punchAccuracy'] ?? 0);
    $buddyPunch = (int)($_POST['buddyPunch'] ?? 0);
    $deployFlex = (int)($_POST['deployFlex'] ?? 0);
    $commitPct = (int)($_POST['commitPct'] ?? 0);
    $overallScore = (float)($_POST['overallScore'] ?? 0);
    
    try {
        // Find or create employee by name
        $stmt = $pdo->prepare('SELECT id FROM users WHERE (first_name || " " || last_name) LIKE ? OR username LIKE ? LIMIT 1');
        $stmt->execute(["%$empName%", "%$empId%"]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            $error = 'Employee not found in system.';
        } else {
            $employee_id = $employee['id'];
            
            // Insert evaluation
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO employee_evaluations 
                (employee_id, evaluation_date, evaluated_by, assiduity_rating, punctuality_rating, 
                 productivity_rating, illness_days, permission_days, absence_days, sanctions, 
                 suspension, query_letter, overall_rating, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
            $notes = "Dept: $dept | Period: $period | Punch accuracy: $punchAccuracy% | Deployment: $deployFlex/5 | Commit: $commitPct%";
            
            $stmt->execute([
                $employee_id,
                date('Y-m-d'),
                $_SESSION['user']['id'],
                $assiduity,
                $punctuality,
                $productivity,
                $illCert + $illUncert,
                $permApproved + $permUnapproved,
                $unauthAbsence,
                $sanctionsCount > 0 ? "Yes ($sanctionsCount)" : 'No',
                $suspensionDays,
                $queryLetter,
                $overallScore,
                $notes
            ]);
            
            $message = "✅ Evaluation for {$empName} ({$empId}) saved successfully with overall score {$overallScore}/5";
        }
    } catch (Exception $e) {
        $error = 'Error saving evaluation: ' . $e->getMessage();
    }
}

// Get list of employees for reference
$employees = $pdo->query("SELECT id, username, first_name, last_name, role FROM users WHERE role != 'patient' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Employee Evaluation System | Punch · Deploy · Commit</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #e9f0f5;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            padding: 2rem 1rem;
        }

        .evaluation-container {
            max-width: 1280px;
            margin: 0 auto;
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .eval-header {
            background: linear-gradient(135deg, #0b2b3b 0%, #1a4a62 100%);
            color: white;
            padding: 1.8rem 2rem;
        }

        .eval-header h1 {
            font-weight: 600;
            font-size: 1.9rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .eval-header h1 small {
            font-size: 0.85rem;
            font-weight: 400;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 40px;
        }

        .sub {
            margin-top: 10px;
            opacity: 0.85;
            font-size: 0.95rem;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-panel {
            padding: 2rem;
        }

        .two-col-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .info-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 1.2rem 1.5rem;
            border: 1px solid #e2edf2;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .info-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f3b4c;
            border-left: 5px solid #2c8fbb;
            padding-left: 12px;
            margin-bottom: 1rem;
        }

        .input-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            align-items: center;
            gap: 10px;
        }

        .input-row label {
            width: 140px;
            font-weight: 500;
            color: #1e4a62;
        }

        .input-row input, .input-row select {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #cbdde6;
            border-radius: 14px;
            font-size: 0.9rem;
            background: white;
        }

        .rating-section {
            margin: 28px 0 32px;
        }

        .rating-section h2 {
            font-size: 1.5rem;
            margin-bottom: 16px;
            color: #0b3b4f;
            border-bottom: 3px solid #bdd9e8;
            display: inline-block;
            padding-bottom: 5px;
        }

        .criteria-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .criteria-table th, .criteria-table td {
            border: 1px solid #e0edf3;
            padding: 12px 14px;
            vertical-align: top;
        }

        .criteria-table th {
            background: #eef4f8;
            font-weight: 600;
            color: #1c5a72;
        }

        select.rating-select {
            width: 100%;
            padding: 6px;
            border-radius: 30px;
            border: 1px solid #bcd0db;
            background: #fff;
            font-weight: 500;
        }

        textarea.comment {
            width: 100%;
            border-radius: 16px;
            border: 1px solid #cbdde6;
            padding: 8px;
            font-size: 0.8rem;
            resize: vertical;
        }

        .absence-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            background: #fefdf7;
            padding: 18px;
            border-radius: 24px;
            border: 1px solid #ffe6c7;
            margin: 20px 0;
        }

        .absence-item {
            flex: 1;
            min-width: 130px;
        }

        .absence-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #a5530e;
        }

        .absence-item input {
            width: 100%;
            padding: 8px;
            border-radius: 40px;
            border: 1px solid #f1cfb0;
            text-align: center;
        }

        .disciplinary-card {
            background: #fff7f0;
            padding: 18px;
            border-radius: 24px;
            margin-top: 20px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 40px;
            margin-bottom: 20px;
        }

        button {
            padding: 12px 26px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary {
            background: #1f6e8c;
            color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .btn-primary:hover {
            background: #0e4b63;
            transform: scale(0.98);
        }

        .btn-secondary {
            background: #dbe9f0;
            color: #1e4a62;
        }

        .result-area {
            background: #f0f7fb;
            border-radius: 24px;
            padding: 20px;
            margin-top: 24px;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 0.85rem;
            border: 1px solid #cce3ed;
            display: none;
        }

        @media (max-width: 700px) {
            .form-panel {
                padding: 1rem;
            }
            .input-row label {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="evaluation-container">
    <div class="eval-header">
        <h1>🏥 Employee Performance & Conduct Evaluation <small>Hospital System</small></h1>
        <div class="sub">🔹 Punch · Deploy · Commit 🔹 | Assiduity · Punctuality · Productivity · Absence Tracking · Disciplinary Log</div>
    </div>

    <div class="form-panel">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form id="evaluationForm" method="post">
            <?php echo csrfField(); ?>
            
            <div class="two-col-grid">
                <div class="info-card">
                    <h3>👤 Employee & Punch data</h3>
                    <div class="input-row">
                        <label>Full name</label>
                        <input type="text" id="empName" name="empName" placeholder="e.g., Sarah Johnson">
                    </div>
                    <div class="input-row">
                        <label>Employee ID / Badge</label>
                        <input type="text" id="empId" name="empId" placeholder="HOS-2134">
                    </div>
                    <div class="input-row">
                        <label>Department</label>
                        <input type="text" id="dept" name="dept" value="Nursing / Emergency / Admin">
                    </div>
                    <div class="input-row">
                        <label>Review period</label>
                        <input type="text" id="period" name="period" value="Q2 2026 (Jan - Mar)">
                    </div>
                    <div class="input-row">
                        <label>Evaluator</label>
                        <input type="text" id="evaluator" name="evaluator" placeholder="HR / Supervisor">
                    </div>
                </div>
                <div class="info-card">
                    <h3>⏱️ Punch compliance & Deployment</h3>
                    <div class="input-row">
                        <label>Punch accuracy (%)</label>
                        <input type="number" id="punchAccuracy" name="punchAccuracy" value="98" step="1" min="0" max="100">
                    </div>
                    <div class="input-row">
                        <label>Buddy punching incidents</label>
                        <input type="number" id="buddyPunch" name="buddyPunch" value="0" min="0">
                    </div>
                    <div class="input-row">
                        <label>Deployment flexibility (1-5)</label>
                        <select id="deployFlex" name="deployFlex">
                            <option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option>
                        </select>
                    </div>
                    <div class="input-row">
                        <label>Commitment (task completion %)</label>
                        <input type="number" id="commitPct" name="commitPct" value="95" step="1" min="0" max="100">
                    </div>
                </div>
            </div>

            <div class="rating-section">
                <h2>📋 Core performance metrics</h2>
                <table class="criteria-table" id="ratingTable">
                    <thead>
                        <tr><th>Criteria</th><th>Rating (1–5)</th><th>Comments / Evidence</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Assiduity</strong> (Diligence / sustained engagement)</td>
                            <td><select class="rating-select" name="assiduity"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select></td>
                            <td><textarea class="comment" placeholder="Observed consistency..."></textarea></td>
                        </tr>
                        <tr>
                            <td><strong>Punctuality</strong> (based on punch records)</td>
                            <td><select class="rating-select" name="punctuality"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select></td>
                            <td><textarea class="comment" placeholder="Late arrivals, early departures..."></textarea></td>
                        </tr>
                        <tr>
                            <td><strong>Productivity</strong> (output vs expected)</td>
                            <td><select class="rating-select" name="productivity"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select></td>
                            <td><textarea class="comment" placeholder="Tasks completed / efficiency"></textarea></td>
                        </tr>
                        <tr>
                            <td><strong>Illness management</strong> (pattern, certification)</td>
                            <td><select class="rating-select" name="illnessMgmt"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select></td>
                            <td><textarea class="comment" placeholder="Frequent vs occasional sick leave..."></textarea></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="absence-grid">
                <div class="absence-item"><label>🤒 Illness days (certified)</label><input type="number" id="illCert" name="illCert" value="2" min="0"></div>
                <div class="absence-item"><label>🤧 Illness days (uncertified)</label><input type="number" id="illUncert" name="illUncert" value="1" min="0"></div>
                <div class="absence-item"><label>✅ Permission (approved leave)</label><input type="number" id="permApproved" name="permApproved" value="3" min="0"></div>
                <div class="absence-item"><label>⚠️ Permission (unapproved)</label><input type="number" id="permUnapproved" name="permUnapproved" value="0" min="0"></div>
                <div class="absence-item"><label>🚫 Absence (unauthorised)</label><input type="number" id="unauthAbsence" name="unauthAbsence" value="0" min="0"></div>
            </div>

            <div class="disciplinary-card">
                <h3 style="color:#ac4e2b;">⚖️ Disciplinary & Query tracking</h3>
                <div class="two-col-grid" style="margin-top: 6px;">
                    <div><label>📌 Sanctions (verbal/written)</label><input type="number" id="sanctionsCount" name="sanctionsCount" value="0" min="0" style="width:80px; margin-left:12px;"></div>
                    <div><label>⛔ Suspension (days)</label><input type="number" id="suspensionDays" name="suspensionDays" value="0" min="0" style="width:80px; margin-left:12px;"></div>
                    <div><label>📄 Query letter issued</label><select id="queryLetter" name="queryLetter"><option value="No">No</option><option value="Yes (1)">Yes (1)</option><option value="Yes (2+)">Yes (2 or more)</option></select></div>
                    <div><label>📎 Additional notes</label><input type="text" id="discNote" name="discNote" placeholder="e.g., pending investigation"></div>
                </div>
            </div>

            <input type="hidden" id="overallScore" name="overallScore" value="3">

            <div class="action-buttons">
                <button type="button" class="btn-secondary" id="resetBtn">Reset to demo</button>
                <button type="button" class="btn-primary" id="generateEvalBtn">📊 Generate complete evaluation sheet</button>
            </div>

            <div id="resultOutput" class="result-area"></div>

            <div style="font-size:13px; text-align:center; margin-top:25px; color:#6f8f9f;">
                ✅ Commit plan & final approval section included in report below
            </div>
        </form>
    </div>
</div>

<script>
    function getCoreRatings() {
        const selects = document.querySelectorAll('#ratingTable select.rating-select');
        let ratings = {};
        selects.forEach((sel, idx) => {
            let names = ['assiduity', 'punctuality', 'productivity', 'illnessMgmt'];
            ratings[names[idx]] = parseInt(sel.value, 10);
        });
        return ratings;
    }

    function getAbsenceScores() {
        let illCert = parseInt(document.getElementById('illCert').value) || 0;
        let illUncert = parseInt(document.getElementById('illUncert').value) || 0;
        let permUnapproved = parseInt(document.getElementById('permUnapproved').value) || 0;
        let unauth = parseInt(document.getElementById('unauthAbsence').value) || 0;
        
        let totalIllWeight = illCert + (illUncert * 1.5);
        let illnessRating = totalIllWeight > 10 ? 1 : totalIllWeight > 7 ? 2 : totalIllWeight > 4 ? 3 : totalIllWeight > 2 ? 4 : 5;
        
        let absencePenalty = unauth + permUnapproved;
        let attendanceRating = absencePenalty >= 5 ? 1 : absencePenalty >= 3 ? 2 : absencePenalty >= 2 ? 3 : absencePenalty >= 1 ? 4 : 5;
        
        return { illnessRating, attendanceRating, illCert, illUncert, permUnapproved, unauth };
    }

    function getDisciplinaryScore() {
        let sanctions = parseInt(document.getElementById('sanctionsCount').value) || 0;
        let suspension = parseInt(document.getElementById('suspensionDays').value) || 0;
        let queryVal = document.getElementById('queryLetter').value;
        let totalPenalty = sanctions + (suspension>0? suspension/2:0) + (queryVal !== 'No' ? 1 : 0);
        if (totalPenalty === 0) return 5;
        if (totalPenalty <= 1) return 4;
        if (totalPenalty <= 2) return 3;
        if (totalPenalty <= 4) return 2;
        return 1;
    }

    function generateEvaluation() {
        let empName = document.getElementById('empName').value || "Not specified";
        let empId = document.getElementById('empId').value || "N/A";
        let dept = document.getElementById('dept').value || "General";
        let period = document.getElementById('period').value || "current period";
        let evaluator = document.getElementById('evaluator').value || "Supervisor";
        
        let punchAcc = parseInt(document.getElementById('punchAccuracy').value) || 100;
        let buddyPunch = parseInt(document.getElementById('buddyPunch').value) || 0;
        let deployFlex = parseInt(document.getElementById('deployFlex').value);
        let commitPct = parseInt(document.getElementById('commitPct').value) || 0;
        
        let punchRating = buddyPunch > 0 ? 2 : punchAcc < 85 ? 2 : punchAcc < 95 ? 3 : punchAcc >= 98 ? 5 : 4;
        let commitRating = commitPct >= 95 ? 5 : commitPct >= 85 ? 4 : commitPct >= 75 ? 3 : commitPct >= 60 ? 2 : 1;
        
        let core = getCoreRatings();
        let assiduity = core.assiduity || 3;
        let punctuality = core.punctuality || 3;
        let productivity = core.productivity || 3;
        let illnessMgmtRating = core.illnessMgmt || 3;
        
        let absenceObj = getAbsenceScores();
        let discScore = getDisciplinaryScore();
        
        let overall = (assiduity + punctuality + productivity + punchRating + deployFlex + commitRating + illnessMgmtRating + absenceObj.attendanceRating + discScore) / 9;
        let finalOverall = Math.round(overall * 10) / 10;
        
        document.getElementById('overallScore').value = finalOverall;
        
        let resultHTML = `
            <div style="font-family: monospace; background:#fff; padding:12px; border-radius:18px;">
            <h3>📄 HOSPITAL STAFF EVALUATION REPORT — ${empName} (${empId})</h3>
            <p><strong>Department:</strong> ${dept} &nbsp;|&nbsp; <strong>Period:</strong> ${period} &nbsp;|&nbsp; <strong>Evaluator:</strong> ${evaluator}</p>
            <hr>
            <h4>🔹 Punch, Deployment & Commitment </h4>
            <ul>
                <li>Punch accuracy: ${punchAcc}% | Buddy punch incidents: ${buddyPunch} → <strong>Punch Rating: ${punchRating}/5</strong></li>
                <li>Deployment flexibility: ${deployFlex}/5 &nbsp;|&nbsp; Commitment (task completion): ${commitPct}% → <strong>Commitment rating: ${commitRating}/5</strong></li>
            </ul>
            <h4>📌 Core performance (1-5):</h4>
            <ul>
                <li>Assiduity: ${assiduity}/5</li>
                <li>Punctuality: ${punctuality}/5</li>
                <li>Productivity: ${productivity}/5</li>
                <li>Illness mgmt (self-reported rating): ${illnessMgmtRating}/5</li>
            </ul>
            <h4>📆 Absence & Leave tracking</h4>
            <ul>
                <li>Illness (certified): ${absenceObj.illCert} days | Uncertified: ${absenceObj.illUncert} days</li>
                <li>Unapproved permission: ${absenceObj.permUnapproved} | Unauthorised absence: ${absenceObj.unauth}</li>
                <li><strong>Attendance behaviour score (auto): ${absenceObj.attendanceRating}/5</strong></li>
                <li><strong>Illness pattern score (auto): ${absenceObj.illnessRating}/5</strong></li>
            </ul>
            <h4>⚠️ Sanctions, suspension, query letters</h4>
            <ul>
                <li>Sanctions count: ${document.getElementById('sanctionsCount').value} | Suspension days: ${document.getElementById('suspensionDays').value} | Query letter issued: ${document.getElementById('queryLetter').value}</li>
                <li><strong>Disciplinary standing score: ${discScore}/5</strong></li>
                <li>Notes: ${document.getElementById('discNote').value || '—'}</li>
            </ul>
            <h4>🏆 OVERALL PERFORMANCE SCORE: <span style="background:#0f3b4c; color:white; padding:4px 14px; border-radius:40px;">${finalOverall} / 5</span></h4>
            <p><strong>Commitment plan (signed below):</strong><br>
            ➤ IMPROVEMENT COMMITMENT: 
            <br>- Punctuality improvement needed? ${punctuality <= 2 ? 'Yes - revise shift start discipline' : 'Satisfactory'} 
            <br>- Productivity action: ${productivity <= 2 ? 'Additional training / workload adjustment' : 'Maintain current KPI'}
            <br>- Attendance & absence plan: ${absenceObj.unauth > 0 ? 'Strict adherence to permission process' : 'No pattern observed'}
            <br>- Signature of employee & supervisor required below.</p>
            <hr>
            <div style="display:flex; justify-content:space-between; margin-top:18px;">
                <div>_________<br>Employee signature</div>
                <div>_________<br>Supervisor / HR signature</div>
                <div>Date: ${new Date().toLocaleDateString()}</div>
            </div>
            <p style="font-size:11px; margin-top:12px;">✅ Generated by Hospital Evaluation System — includes assiduity, punctuality, productivity, illness, permission, absence, sanctions, suspension, query letter, punch, deploy & commit.</p>
            </div>
        `;
        
        let resultDiv = document.getElementById('resultOutput');
        resultDiv.innerHTML = resultHTML;
        resultDiv.style.display = 'block';
        resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    function resetDemo() {
        document.getElementById('empName').value = "Dr. Elena Vasquez";
        document.getElementById('empId').value = "MD-8821";
        document.getElementById('dept').value = "Emergency Medicine";
        document.getElementById('period').value = "March 2026 - June 2026";
        document.getElementById('evaluator').value = "HR Director";
        document.getElementById('punchAccuracy').value = 96;
        document.getElementById('buddyPunch').value = 0;
        document.getElementById('deployFlex').value = 4;
        document.getElementById('commitPct').value = 92;
        
        let selects = document.querySelectorAll('#ratingTable select.rating-select');
        selects[0].value = "3"; // assiduity
        selects[1].value = "4"; // punctuality
        selects[2].value = "4"; // productivity
        selects[3].value = "3"; // illness mgmt
        
        document.getElementById('illCert').value = 1;
        document.getElementById('illUncert').value = 0;
        document.getElementById('permApproved').value = 2;
        document.getElementById('permUnapproved').value = 0;
        document.getElementById('unauthAbsence').value = 0;
        document.getElementById('sanctionsCount').value = 0;
        document.getElementById('suspensionDays').value = 0;
        document.getElementById('queryLetter').value = "No";
        document.getElementById('discNote').value = "No active disciplinary record";
        document.getElementById('resultOutput').style.display = 'none';
    }
    
    document.getElementById('generateEvalBtn').addEventListener('click', generateEvaluation);
    document.getElementById('resetBtn').addEventListener('click', resetDemo);
    window.onload = () => { resetDemo(); };
</script>
</body>
</html>