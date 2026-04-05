<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];
$message = '';
$error = '';

$departments = [
    'doctor' => 'Doctors',
    'lab_doctor' => 'Lab Doctors',
    'pharmacy_worker' => 'Pharmacy Workers',
    'intern' => 'Interns',
    'trainee' => 'Trainees',
];

function timetableMonthBounds(string $seedMonth): array {
    $dt = DateTime::createFromFormat('Y-m', $seedMonth);
    if (!$dt) {
        $dt = new DateTime('first day of this month');
    }
    $start = clone $dt;
    $start->modify('first day of this month');
    $end = clone $dt;
    $end->modify('last day of this month');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function timetableMonthDates(string $monthStart, string $monthEnd): array {
    $dates = [];
    $start = DateTime::createFromFormat('Y-m-d', $monthStart);
    $end = DateTime::createFromFormat('Y-m-d', $monthEnd);
    if (!$start || !$end) {
        return $dates;
    }

    while ($start <= $end) {
        $dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }

    return $dates;
}

function timetableDepartmentUsers(PDO $pdo, string $department): array {
    switch ($department) {
        case 'doctor':
            $stmt = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'doctor' ORDER BY first_name, last_name, username");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'lab_doctor':
            $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.username
                                 FROM users u
                                 JOIN doctors d ON d.user_id = u.id
                                 WHERE u.role = 'doctor' AND lower(COALESCE(d.specialization, '')) LIKE '%lab%'
                                 ORDER BY u.first_name, u.last_name, u.username");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'pharmacy_worker':
            $stmt = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role IN ('staff', 'doctor', 'pharmacist') ORDER BY first_name, last_name, username");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'intern':
            $stmt = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'intern' ORDER BY first_name, last_name, username");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'trainee':
            $stmt = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'trainee' ORDER BY first_name, last_name, username");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        default:
            return [];
    }
}

function timetableDateTime(string $date, string $time): string {
    $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    return $dt ? $dt->format('Y-m-d H:i:s') : ($date . ' 09:00:00');
}

function timetableTemplateShifts(string $department): array {
    switch ($department) {
        case 'doctor':
            return [
                ['name' => 'morning', 'start' => '09:00', 'end' => '15:00'],
                ['name' => 'evening', 'start' => '15:00', 'end' => '21:00'],
            ];
        case 'pharmacy_worker':
            return [
                ['name' => 'day', 'start' => '09:00', 'end' => '21:00'],
                ['name' => 'night', 'start' => '21:00', 'end' => '09:00'],
            ];
        case 'lab_doctor':
            return [
                ['name' => 'day', 'start' => '09:00', 'end' => '17:00'],
                ['name' => 'evening', 'start' => '17:00', 'end' => '21:00'],
            ];
        case 'intern':
        case 'trainee':
            return [
                ['name' => 'morning', 'start' => '09:00', 'end' => '15:00'],
                ['name' => 'evening', 'start' => '15:00', 'end' => '21:00'],
                ['name' => 'night', 'start' => '21:00', 'end' => '09:00'],
            ];
        default:
            return [];
    }
}

function timetableShiftRange(string $date, string $startTime, string $endTime): array {
    $start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $startTime);
    $end = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $endTime);
    if (!$start || !$end) {
        return [timetableDateTime($date, '09:00'), timetableDateTime($date, '15:00')];
    }
    if ($end <= $start) {
        $end->modify('+1 day');
    }
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function timetableIsLabRestDay(string $date, int $workerIndex): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt) {
        return false;
    }
    $weekOfMonth = (int)ceil((int)$dt->format('j') / 7);
    $restDay = (($workerIndex + $weekOfMonth - 1) % 7) + 1; // 1=Mon ... 7=Sun
    return (int)$dt->format('N') === $restDay;
}

$selectedDepartment = (string)($_POST['department'] ?? ($_GET['department'] ?? 'doctor'));
if (!isset($departments[$selectedDepartment])) {
    $selectedDepartment = 'doctor';
}

$monthSeed = (string)($_POST['month_seed'] ?? ($_GET['month_seed'] ?? date('Y-m')));
[$monthStart, $monthEnd] = timetableMonthBounds($monthSeed);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    verifyCsrf();

    try {
        $pdo = getDB();
        $users = timetableDepartmentUsers($pdo, $selectedDepartment);
        $dates = timetableMonthDates($monthStart, $monthEnd);
        $shiftTemplates = timetableTemplateShifts($selectedDepartment);

        if (!$users) {
            $error = 'No workers found for selected department.';
        } elseif (!$dates) {
            $error = 'No dates available for selected month.';
        } elseif (!$shiftTemplates) {
            $error = 'No shift template found for selected department.';
        } else {
            $pdo->beginTransaction();

            $delete = $pdo->prepare('DELETE FROM shift_timetables WHERE worker_group = ? AND shift_date >= ? AND shift_date <= ?');
            $delete->execute([$selectedDepartment, $monthStart, $monthEnd]);

            $insert = $pdo->prepare('INSERT INTO shift_timetables (user_id, worker_group, shift_name, shift_date, start_at, end_at, generated_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

            $generated = 0;
            foreach ($users as $workerIndex => $worker) {
                foreach ($dates as $dayIndex => $date) {
                    if ($selectedDepartment === 'lab_doctor' && timetableIsLabRestDay($date, (int)$workerIndex)) {
                        continue;
                    }

                    $shiftIndex = ($workerIndex + $dayIndex) % count($shiftTemplates);
                    $template = $shiftTemplates[$shiftIndex];
                    [$startAt, $endAt] = timetableShiftRange($date, (string)$template['start'], (string)$template['end']);

                    $insert->execute([
                        (int)$worker['id'],
                        $selectedDepartment,
                        (string)$template['name'],
                        $date,
                        $startAt,
                        $endAt,
                        (int)$user['id'],
                        'Auto-generated monthly timetable',
                    ]);
                    $generated++;
                }
            }

            $pdo->commit();
            $message = 'Monthly timetable generated for ' . $departments[$selectedDepartment] . ' (' . $monthStart . ' to ' . $monthEnd . '). Shifts created: ' . $generated;
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Timetable generation error: ' . $e->getMessage());
        $error = 'Could not generate timetable right now.';
    }
}

$rows = [];
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT st.shift_date, st.worker_group, st.shift_name, st.start_at, st.end_at,
                                  u.first_name, u.last_name, u.username, u.role
                           FROM shift_timetables st
                           JOIN users u ON st.user_id = u.id
                           WHERE st.worker_group = ? AND st.shift_date >= ? AND st.shift_date <= ?
                           ORDER BY st.shift_date ASC, u.first_name ASC, u.last_name ASC, u.username ASC");
    $stmt->execute([$selectedDepartment, $monthStart, $monthEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Timetable fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Working Timetable - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_users.php">Manage Users</a>
            <a class="nav-link active" href="timetable.php">Timetable</a>
            <a class="nav-link" href="operations_records.php">Operations</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2 class="mb-3">Department Monthly Timetable</h2>
    <p class="text-muted mb-4">Generate monthly schedules by department using fixed shift templates for doctors, pharmacy workers, lab doctors, interns, and trainees.</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Generate Monthly Timetable</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?php echo csrfField(); ?>
                <div class="col-md-3">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department" required>
                        <?php foreach ($departments as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDepartment === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="month_seed" class="form-label">Month</label>
                    <input type="month" class="form-control" id="month_seed" name="month_seed" value="<?php echo htmlspecialchars(substr($monthStart, 0, 7), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="generate_timetable" class="btn btn-danger w-100">
                        <i class="bi bi-calendar2-plus"></i> Generate
                    </button>
                </div>
                <div class="col-12">
                    <small class="text-muted">Month window: <?php echo htmlspecialchars($monthStart, ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars($monthEnd, ENT_QUOTES, 'UTF-8'); ?></small>
                    <ul class="small text-muted mt-2 mb-0">
                        <li>Doctors: 09:00-15:00 and 15:00-21:00 (Mon-Sun)</li>
                        <li>Pharmacy workers: 09:00-21:00 and 21:00-09:00</li>
                        <li>Lab doctors: 09:00-17:00 and 17:00-21:00 with one rest day per week</li>
                        <li>Interns/Trainees: 09:00-15:00, 15:00-21:00, and 21:00-09:00</li>
                    </ul>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo htmlspecialchars($departments[$selectedDepartment], ENT_QUOTES, 'UTF-8'); ?> Timetable</strong>
            <span class="badge bg-secondary"><?php echo count($rows); ?> shifts</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Worker</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="text-center text-muted">No timetable generated for selected department/month.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$r['shift_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$r['shift_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('H:i', strtotime((string)$r['start_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['end_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(trim((string)$r['first_name'] . ' ' . (string)$r['last_name']) . ' (' . (string)$r['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$r['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
