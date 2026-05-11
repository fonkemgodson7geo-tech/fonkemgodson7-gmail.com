<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];
$message = '';
$error = '';

// CMDS Staff - 15 people with their rotation rules
$staffList = [
    ['name' => 'Abeng', 'position' => 'IDE Accoucheur', 'col' => 3],
    ['name' => 'Mayan', 'position' => 'Infirmière PEV', 'col' => 4],
    ['name' => 'Nloga', 'position' => 'Labo', 'col' => 5],
    ['name' => 'Favour', 'position' => 'Journée longue', 'col' => 6],
    ['name' => 'Wiltitz', 'position' => 'Infirmier', 'col' => 7],
    ['name' => 'Kadija', 'position' => 'Nuit', 'col' => 8],
    ['name' => 'Nyanze', 'position' => 'Après-midi', 'col' => 9],
    ['name' => 'Mvogo', 'position' => 'Garde', 'col' => 10],
    ['name' => 'Nagayena', 'position' => 'Matin', 'col' => 11],
    ['name' => 'Ndong', 'position' => 'Après-midi', 'col' => 12],
    ['name' => 'Zad', 'position' => 'Matin', 'col' => 13],
    ['name' => 'Florinda', 'position' => 'Après-midi', 'col' => 14],
    ['name' => 'Cathrine', 'position' => 'Mixte', 'col' => 15],
    ['name' => 'Abanda', 'position' => 'Nuit', 'col' => 16],
    ['name' => 'Saurel', 'position' => 'Journée', 'col' => 17],
];

function getShiftForStaff($staffName, $dayNum, $weekday) {
    switch($staffName) {
        case 'Abeng':
            return ($dayNum % 7 == 0) ? 'N' : (($dayNum % 2 == 0) ? 'A' : 'M');
        case 'Mayan':
            return ($weekday == 0) ? 'R' : 'M';
        case 'Nloga':
            return ($dayNum % 3 == 0) ? 'R' : (($dayNum % 2 == 0) ? 'A' : 'M');
        case 'Favour':
            return ($weekday == 0) ? 'R' : 'J';
        case 'Wiltitz':
            return ($dayNum % 6 == 0) ? 'R' : 'M';
        case 'Kadija':
            return ($dayNum % 4 == 0) ? 'N' : 'M';
        case 'Nyanze':
            return ($dayNum % 5 == 0) ? 'R' : 'A';
        case 'Mvogo':
            return ($dayNum % 3 == 0) ? 'G' : 'M';
        case 'Nagayena':
            return 'M';
        case 'Ndong':
            return (($weekday == 0) || ($weekday == 6)) ? 'R' : 'A';
        case 'Zad':
            return ($dayNum % 4 == 1) ? 'R' : 'M';
        case 'Florinda':
            return 'A';
        case 'Cathrine':
            return ($weekday == 0) ? '22h-8h' : '8h-22h';
        case 'Abanda':
            return ($dayNum % 2 == 0) ? 'REPOS' : '22h-8h';
        case 'Saurel':
            return ($dayNum % 3 == 0) ? 'REPOS' : '8h-22h';
        default:
            return '';
    }
}

function getCameroonHoliday($month, $day) {
    $holidays = [
        '1-1' => 'Nouvel An',
        '2-11' => 'Fête Jeunesse',
        '5-1' => 'Fête du Travail',
        '5-20' => 'Fête Nationale',
        '8-15' => 'Assomption',
        '12-25' => 'Noël'
    ];
    return $holidays["$month-$day"] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    verifyCsrf();
    
    $month = (int)($_POST['month'] ?? date('m'));
    $year = (int)($_POST['year'] ?? date('Y'));
    
    if ($month < 1 || $month > 12 || $year < 2024 || $year > 2030) {
        $error = 'Invalid month or year.';
    } else {
        try {
            $pdo = getDB();
            
            $startDate = new DateTime("$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01");
            $endDate = new DateTime($startDate->format('Y-m-t'));
            
            // Clear old timetable for this month/year
            $delete = $pdo->prepare('DELETE FROM shift_timetables WHERE strftime("%Y-%m", shift_date) = ?');
            $delete->execute([$startDate->format('Y-m')]);
            
            // Generate new timetable
            $insert = $pdo->prepare('INSERT INTO shift_timetables (user_id, worker_group, shift_name, shift_date, start_at, end_at, generated_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            
            $current = clone $startDate;
            $generated = 0;
            
            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');
                $dayNum = (int)$current->format('d');
                $weekday = (int)$current->format('w');
                
                // Check for holidays
                $holiday = getCameroonHoliday($month, $dayNum);
                if ($holiday) {
                    $current->modify('+1 day');
                    continue;
                }
                
                // Check for first Sunday (Inventaire)
                if ($weekday == 0 && $dayNum <= 7) {
                    $current->modify('+1 day');
                    continue;
                }
                
                // Generate shifts for each staff member
                foreach ($staffList as $staff) {
                    $shift = getShiftForStaff($staff['name'], $dayNum, $weekday);
                    
                    if ($shift) {
                        // Convert shift code to time range
                        $times = ['start' => '08:00', 'end' => '14:00'];
                        switch($shift) {
                            case 'M': $times = ['start' => '08:00', 'end' => '14:00']; break;
                            case 'A': $times = ['start' => '14:00', 'end' => '21:00']; break;
                            case 'N': $times = ['start' => '21:00', 'end' => '08:00']; break;
                            case 'J': $times = ['start' => '08:00', 'end' => '22:00']; break;
                            case 'G': $times = ['start' => '21:00', 'end' => '08:00']; break;
                            case 'R': $times = ['start' => '00:00', 'end' => '23:59']; break;
                            case '8h-22h': $times = ['start' => '08:00', 'end' => '22:00']; break;
                            case '22h-8h': $times = ['start' => '22:00', 'end' => '08:00']; break;
                        }
                        
                        $startAt = $dateStr . ' ' . $times['start'] . ':00';
                        $endAt = $dateStr . ' ' . $times['end'] . ':00';
                        
                        // Insert dummy user_id (0) - staff name stored in note
                        $insert->execute([0, 'cmds_staff', $shift, $dateStr, $startAt, $endAt, (int)$user['id'], $staff['name']]);
                        $generated++;
                    }
                }
                
                $current->modify('+1 day');
            }
            
            $message = "Timetable for " . $startDate->format('F Y') . " generated successfully! ($generated shifts)";
        } catch (Throwable $e) {
            error_log('Timetable generation error: ' . $e->getMessage());
            $error = 'Could not generate timetable: ' . $e->getMessage();
        }
    }
}

// Fetch timetable for current month
$currentMonth = date('Y-m');
$timetableData = [];
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM shift_timetables WHERE strftime('%Y-%m', shift_date) = ? ORDER BY shift_date ASC");
    $stmt->execute([$currentMonth]);
    $timetableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Timetable fetch error: ' . $e->getMessage());
}

$shiftColors = [
    'M' => '#ADD8E6',
    'A' => '#90EE90',
    'N' => '#00008B',
    'G' => '#191970',
    'J' => '#FFA500',
    'R' => '#D3D3D3',
    'REPOS' => '#DC143C',
    '8h-22h' => '#FFD700',
    '22h-8h' => '#800080'
];

$shiftTextColors = [
    'N' => '#FFFFFF',
    'G' => '#FFFFFF',
    '22h-8h' => '#FFFFFF',
    'REPOS' => '#FFFFFF'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Timetable - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        body { background: #f5f7fa; }
        .timetable-container { max-width: 1400px; margin: 2rem auto; }
        .timetable-header { background: linear-gradient(135deg, #0b2b3b 0%, #1a4a62 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .timetable-header h1 { font-size: 2rem; font-weight: 600; }
        .timetable-header p { font-size: 0.95rem; opacity: 0.9; }
        .controls { background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-responsive { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
        .timetable { margin: 0; }
        .timetable thead { background: #0b2b3b; color: white; }
        .timetable th { padding: 1rem; font-weight: 600; border: 1px solid #ddd; }
        .timetable td { padding: 0.75rem; border: 1px solid #ddd; text-align: center; font-size: 0.9rem; }
        .shift-cell { cursor: pointer; transition: all 0.2s; border-radius: 4px; font-weight: 500; }
        .shift-cell:hover { transform: scale(1.05); }
        .legend { margin-top: 2rem; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .legend-item { display: inline-block; margin-right: 2rem; margin-bottom: 0.5rem; }
        .legend-color { display: inline-block; width: 20px; height: 20px; border-radius: 4px; margin-right: 8px; vertical-align: middle; }
        .alert { border-radius: 12px; }
        @media print {
            .controls, .timetable-header { display: none; }
            .table-responsive { box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="timetable-container">
    <div class="timetable-header">
        <h1><i class="bi bi-calendar3"></i> Staff Timetable Management</h1>
        <p>CENTRE MEDICAL DONS DE SOINS - Auto-Rotation System V4.0</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="controls">
        <h5><i class="bi bi-gear"></i> Generate Timetable</h5>
        <form method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            
            <div class="col-md-3">
                <label for="month" class="form-label">Month</label>
                <select name="month" id="month" class="form-select" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m == date('m') ? 'selected' : ''); ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select name="year" id="year" class="form-select" required>
                    <?php for ($y = 2024; $y <= 2030; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == date('Y') ? 'selected' : ''); ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <button type="submit" name="generate_timetable" class="btn btn-primary">
                    <i class="bi bi-lightning"></i> Generate Timetable
                </button>
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </form>
    </div>
    
    <div class="table-responsive">
        <table class="timetable table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <?php foreach ($staffList as $staff): ?>
                        <th><?php echo htmlspecialchars($staff['name']); ?></th>
                    <?php endforeach; ?>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (!empty($timetableData)) {
                    $grouped = [];
                    foreach ($timetableData as $row) {
                        $date = $row['shift_date'];
                        if (!isset($grouped[$date])) {
                            $grouped[$date] = [];
                        }
                        $grouped[$date][$row['note']] = $row['shift_name'];
                    }
                    
                    foreach ($grouped as $date => $shifts) {
                        $dateObj = new DateTime($date);
                        echo '<tr>';
                        echo '<td><strong>' . $dateObj->format('d-M-Y') . '</strong></td>';
                        echo '<td>' . $dateObj->format('D') . '</td>';
                        
                        foreach ($staffList as $staff) {
                            $shiftCode = $shifts[$staff['name']] ?? '—';
                            $bgColor = $shiftColors[$shiftCode] ?? '#FFFFFF';
                            $textColor = $shiftTextColors[$shiftCode] ?? '#000000';
                            echo '<td style="background-color: ' . $bgColor . '; color: ' . $textColor . ';" class="shift-cell">' . htmlspecialchars($shiftCode) . '</td>';
                        }
                        
                        echo '<td>—</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="' . (2 + count($staffList) + 1) . '" class="text-muted text-center py-4">No timetable generated yet. Select month and year above.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="legend">
        <h6><i class="bi bi-palette"></i> Shift Codes Legend</h6>
        <div class="legend-item"><span class="legend-color" style="background: #ADD8E6;"></span> <strong>M</strong> = Matin (8h-14h)</div>
        <div class="legend-item"><span class="legend-color" style="background: #90EE90;"></span> <strong>A</strong> = Après-midi (14h-21h)</div>
        <div class="legend-item"><span class="legend-color" style="background: #00008B; border: 1px solid #ccc;"></span> <strong>N</strong> = Nuit (21h-8h)</div>
        <div class="legend-item"><span class="legend-color" style="background: #FFA500;"></span> <strong>J</strong> = Journée (8h-22h)</div>
        <div class="legend-item"><span class="legend-color" style="background: #191970; border: 1px solid #ccc;"></span> <strong>G</strong> = Garde (21h-8h)</div>
        <div class="legend-item"><span class="legend-color" style="background: #D3D3D3;"></span> <strong>R</strong> = Repos</div>
        <div class="legend-item"><span class="legend-color" style="background: #DC143C; border: 1px solid #ccc;"></span> <strong>REPOS</strong> = Congé</div>
        <div class="legend-item"><span class="legend-color" style="background: #FFD700;"></span> <strong>8h-22h</strong> = Journée</div>
        <div class="legend-item"><span class="legend-color" style="background: #800080; border: 1px solid #ccc;"></span> <strong>22h-8h</strong> = Nuit longue</div>
    </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
