<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];
$message = '';
$error = '';

$departments = [
    'doctor' => 'Doctors',
    'lab_doctor' => 'Lab Workers',
    'pharmacy_worker' => 'Pharmacy Workers',
    'intern' => 'Interns',
    'trainee' => 'Trainees',
];

$statusLabels = [
    'on_permission' => 'On Permission',
    'on_rest' => 'On Rest',
    'archived_deleted' => 'Archived (Deleted)',
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
        } elseif (count($users) < count($shiftTemplates)) {
            $missingWorkers = count($shiftTemplates) - count($users);
            $error = 'Cannot generate strict timetable for ' . $departments[$selectedDepartment] . '. Missing ' . $missingWorkers . ' worker(s) for unique daily shift coverage.';
        } else {
            $pdo->beginTransaction();

            $delete = $pdo->prepare('DELETE FROM shift_timetables WHERE worker_group = ? AND shift_date >= ? AND shift_date <= ?');
            $delete->execute([$selectedDepartment, $monthStart, $monthEnd]);

            $insert = $pdo->prepare('INSERT INTO shift_timetables (user_id, worker_group, shift_name, shift_date, start_at, end_at, generated_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

            $generated = 0;
            $workerCount = count($users);
            $shiftCount = count($shiftTemplates);
            $strictUniqueSameDay = $workerCount >= $shiftCount;

            foreach ($dates as $dayIndex => $date) {
                $dayAnchor = ($dayIndex * $shiftCount) % $workerCount;
                $usedWorkerIds = [];

                foreach ($shiftTemplates as $shiftIndex => $template) {
                    $candidateOffset = ($dayAnchor + $shiftIndex) % $workerCount;
                    $worker = null;

                    for ($seek = 0; $seek < $workerCount; $seek++) {
                        $nextOffset = ($candidateOffset + $seek) % $workerCount;
                        $nextWorker = $users[$nextOffset];
                        $nextWorkerId = (int)$nextWorker['id'];

                        if (!$strictUniqueSameDay || !isset($usedWorkerIds[$nextWorkerId])) {
                            $worker = $nextWorker;
                            break;
                        }
                    }

                    if ($worker === null) {
                        throw new RuntimeException('Unable to assign unique workers for all shift slots.');
                    }

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

                    $usedWorkerIds[(int)$worker['id']] = true;
                    $generated++;
                }
            }

            $pdo->commit();
            $message = 'Monthly timetable generated for ' . $departments[$selectedDepartment] . ' (' . $monthStart . ' to ' . $monthEnd . '). Shifts created: ' . $generated;
            if (!$strictUniqueSameDay) {
                $message .= ' Note: this department has fewer workers than daily shift slots, so some workers were assigned more than one shift on certain days.';
            }
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Timetable generation error: ' . $e->getMessage());
        $error = 'Could not generate timetable right now.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_day_status'])) {
    verifyCsrf();

    $statusDate = (string)($_POST['status_date'] ?? '');
    $statusType = (string)($_POST['status_type'] ?? '');
    $statusWorkerId = (int)($_POST['status_user_id'] ?? 0);
    $statusNoteInput = trim((string)($_POST['status_note'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $statusDate)) {
        $error = 'Choose a valid status date.';
    } elseif ($statusDate < $monthStart || $statusDate > $monthEnd) {
        $error = 'Status date must be inside the selected month window.';
    } elseif (!isset($statusLabels[$statusType])) {
        $error = 'Choose either On Permission or On Rest.';
    } else {
        try {
            $pdo = getDB();
            $users = timetableDepartmentUsers($pdo, $selectedDepartment);
            $validUserIds = array_map(static fn($w): int => (int)$w['id'], $users);

            if (!in_array($statusWorkerId, $validUserIds, true)) {
                $error = 'Select a valid worker from the chosen department.';
            } else {
                $pdo->beginTransaction();

                // Replace this worker's shift for the day with the selected day status.
                $delete = $pdo->prepare('DELETE FROM shift_timetables WHERE worker_group = ? AND user_id = ? AND shift_date = ?');
                $delete->execute([$selectedDepartment, $statusWorkerId, $statusDate]);

                $insert = $pdo->prepare('INSERT INTO shift_timetables (user_id, worker_group, shift_name, shift_date, start_at, end_at, generated_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $startAt = $statusDate . ' 00:00:00';
                $endAt = $statusDate . ' 23:59:59';
                $statusNote = $statusNoteInput !== '' ? $statusNoteInput : ('Marked as ' . $statusLabels[$statusType]);

                $insert->execute([
                    $statusWorkerId,
                    $selectedDepartment,
                    $statusType,
                    $statusDate,
                    $startAt,
                    $endAt,
                    (int)$user['id'],
                    $statusNote,
                ]);

                $pdo->commit();
                $message = $statusLabels[$statusType] . ' saved successfully for selected worker/date.';
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Timetable day status error: ' . $e->getMessage());
            $error = 'Could not save worker day status right now.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_timetable_entry'])) {
    verifyCsrf();

    $entryId = (int)($_POST['entry_id'] ?? 0);
    $editUserId = (int)($_POST['edit_user_id'] ?? 0);
    $editDate = (string)($_POST['edit_shift_date'] ?? '');
    $editShiftName = (string)($_POST['edit_shift_name'] ?? '');
    $editStartTime = (string)($_POST['edit_start_time'] ?? '');
    $editEndTime = (string)($_POST['edit_end_time'] ?? '');
    $editNote = trim((string)($_POST['edit_note'] ?? ''));

    if ($entryId <= 0) {
        $error = 'Invalid timetable entry selected for edit.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $editDate)) {
        $error = 'Choose a valid shift date.';
    } elseif ($editDate < $monthStart || $editDate > $monthEnd) {
        $error = 'Edited shift date must be inside the selected month window.';
    } elseif ($editUserId <= 0) {
        $error = 'Choose a valid worker.';
    } else {
        try {
            $pdo = getDB();
            $users = timetableDepartmentUsers($pdo, $selectedDepartment);
            $validUserIds = array_map(static fn($w): int => (int)$w['id'], $users);
            $allowedShiftNames = array_map(static fn($s): string => (string)$s['name'], timetableTemplateShifts($selectedDepartment));
            $allowedShiftNames = array_merge($allowedShiftNames, array_keys($statusLabels));

            if (!in_array($editUserId, $validUserIds, true)) {
                $error = 'Selected worker does not belong to this department.';
            } elseif (!in_array($editShiftName, $allowedShiftNames, true)) {
                $error = 'Selected shift type is not allowed for this department.';
            } else {
                if (isset($statusLabels[$editShiftName])) {
                    $editStartAt = $editDate . ' 00:00:00';
                    $editEndAt = $editDate . ' 23:59:59';
                } else {
                    if (!preg_match('/^\d{2}:\d{2}$/', $editStartTime) || !preg_match('/^\d{2}:\d{2}$/', $editEndTime)) {
                        $error = 'Start and end time must be valid HH:MM values.';
                    } else {
                        [$editStartAt, $editEndAt] = timetableShiftRange($editDate, $editStartTime, $editEndTime);
                    }
                }
            }

            if ($error === '') {
                $check = $pdo->prepare('SELECT id FROM shift_timetables WHERE id = ? AND worker_group = ? LIMIT 1');
                $check->execute([$entryId, $selectedDepartment]);
                if (!$check->fetchColumn()) {
                    $error = 'Timetable entry not found for this department.';
                } else {
                    $update = $pdo->prepare('UPDATE shift_timetables SET user_id = ?, shift_name = ?, shift_date = ?, start_at = ?, end_at = ?, note = ? WHERE id = ? AND worker_group = ?');
                    $update->execute([
                        $editUserId,
                        $editShiftName,
                        $editDate,
                        $editStartAt,
                        $editEndAt,
                        $editNote !== '' ? $editNote : null,
                        $entryId,
                        $selectedDepartment,
                    ]);
                    $message = 'Timetable entry updated successfully.';
                }
            }
        } catch (Throwable $e) {
            error_log('Timetable edit error: ' . $e->getMessage());
            $error = 'Could not update timetable entry right now.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_timetable_entry'])) {
    verifyCsrf();

    $entryId = (int)($_POST['entry_id'] ?? 0);
    if ($entryId <= 0) {
        $error = 'Invalid timetable entry selected for deletion.';
    } else {
        try {
            $pdo = getDB();
            $archive = $pdo->prepare('UPDATE shift_timetables SET shift_name = ?, note = ? WHERE id = ? AND worker_group = ? LIMIT 1');
            $archiveNote = 'Archived by admin #' . (int)$user['id'] . ' at ' . date('Y-m-d H:i:s');
            $archive->execute(['archived_deleted', $archiveNote, $entryId, $selectedDepartment]);

            if ($archive->rowCount() > 0) {
                $message = 'Timetable entry archived successfully. You can restore it from Archived Entries.';
            } else {
                $error = 'Timetable entry not found for this department.';
            }
        } catch (Throwable $e) {
            error_log('Timetable delete error: ' . $e->getMessage());
            $error = 'Could not delete timetable entry right now.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_timetable_entry'])) {
    verifyCsrf();

    $entryId = (int)($_POST['entry_id'] ?? 0);
    if ($entryId <= 0) {
        $error = 'Invalid archived entry selected for restore.';
    } else {
        try {
            $pdo = getDB();
            $templates = timetableTemplateShifts($selectedDepartment);
            $restoreName = isset($templates[0]['name']) ? (string)$templates[0]['name'] : 'day';
            $restore = $pdo->prepare('UPDATE shift_timetables SET shift_name = ?, note = ? WHERE id = ? AND worker_group = ? AND shift_name = ? LIMIT 1');
            $restore->execute([$restoreName, 'Restored from archive by admin #' . (int)$user['id'], $entryId, $selectedDepartment, 'archived_deleted']);

            if ($restore->rowCount() > 0) {
                $message = 'Archived timetable entry restored successfully.';
            } else {
                $error = 'Archived timetable entry not found for this department.';
            }
        } catch (Throwable $e) {
            error_log('Timetable restore error: ' . $e->getMessage());
            $error = 'Could not restore archived timetable entry right now.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hard_delete_archived_entry'])) {
    verifyCsrf();

    $entryId = (int)($_POST['entry_id'] ?? 0);
    if ($entryId <= 0) {
        $error = 'Invalid archived entry selected for permanent deletion.';
    } else {
        try {
            $pdo = getDB();
            $hardDelete = $pdo->prepare('DELETE FROM shift_timetables WHERE id = ? AND worker_group = ? AND shift_name = ? LIMIT 1');
            $hardDelete->execute([$entryId, $selectedDepartment, 'archived_deleted']);

            if ($hardDelete->rowCount() > 0) {
                $message = 'Archived timetable entry permanently deleted.';
            } else {
                $error = 'Archived timetable entry not found for this department.';
            }
        } catch (Throwable $e) {
            error_log('Timetable hard delete error: ' . $e->getMessage());
            $error = 'Could not permanently delete archived entry right now.';
        }
    }
}

$rows = [];
$archivedRows = [];
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT st.id, st.user_id AS timetable_user_id, st.shift_date, st.worker_group, st.shift_name, st.start_at, st.end_at, st.note,
                                  u.first_name, u.last_name, u.username, u.role
                           FROM shift_timetables st
                           JOIN users u ON st.user_id = u.id
                           WHERE st.worker_group = ? AND st.shift_date >= ? AND st.shift_date <= ? AND st.shift_name <> ?
                           ORDER BY st.shift_date ASC, st.start_at ASC, u.first_name ASC, u.last_name ASC, u.username ASC");
    $stmt->execute([$selectedDepartment, $monthStart, $monthEnd, 'archived_deleted']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $archivedStmt = $pdo->prepare("SELECT st.id, st.user_id AS timetable_user_id, st.shift_date, st.worker_group, st.shift_name, st.start_at, st.end_at, st.note,
                                          u.first_name, u.last_name, u.username, u.role
                                   FROM shift_timetables st
                                   JOIN users u ON st.user_id = u.id
                                   WHERE st.worker_group = ? AND st.shift_date >= ? AND st.shift_date <= ? AND st.shift_name = ?
                                   ORDER BY st.shift_date DESC, st.start_at DESC, u.first_name ASC, u.last_name ASC, u.username ASC");
    $archivedStmt->execute([$selectedDepartment, $monthStart, $monthEnd, 'archived_deleted']);
    $archivedRows = $archivedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Timetable fetch error: ' . $e->getMessage());
}

$editEntry = null;
$editEntryId = (int)($_GET['edit_id'] ?? 0);
if ($editEntryId > 0) {
    foreach ($rows as $row) {
        if ((int)$row['id'] === $editEntryId) {
            $editEntry = $row;
            break;
        }
    }
}

$staffingCount = 0;
$requiredPerDay = count(timetableTemplateShifts($selectedDepartment));
$staffingMissing = 0;
$staffingCanGuaranteeUnique = false;
$departmentUsers = [];

try {
    $pdo = getDB();
    $staffingUsers = timetableDepartmentUsers($pdo, $selectedDepartment);
    $departmentUsers = $staffingUsers;
    $staffingCount = count($staffingUsers);
    $staffingMissing = max(0, $requiredPerDay - $staffingCount);
    $staffingCanGuaranteeUnique = $staffingMissing === 0;
} catch (Throwable $e) {
    error_log('Timetable staffing check error: ' . $e->getMessage());
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
    <style>
        @media print {
            .no-print,
            nav,
            .alert,
            .btn,
            .form-control,
            .form-select {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }

            .table th,
            .table td {
                font-size: 12px;
                padding: 6px;
            }
        }
    </style>
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
    <p class="text-muted mb-4">Generate monthly schedules by department with one worker assigned per shift slot each day.</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($staffingCanGuaranteeUnique): ?>
        <div class="alert alert-success">
            <strong>Coverage check:</strong>
            <?php echo htmlspecialchars($departments[$selectedDepartment], ENT_QUOTES, 'UTF-8'); ?> has <?php echo (int)$staffingCount; ?> worker(s) for <?php echo (int)$requiredPerDay; ?> shift slot(s) per day.
            Unique same-day assignment is fully supported.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>Coverage check:</strong>
            <?php echo htmlspecialchars($departments[$selectedDepartment], ENT_QUOTES, 'UTF-8'); ?> has <?php echo (int)$staffingCount; ?> worker(s), but requires <?php echo (int)$requiredPerDay; ?> per day.
            Missing <?php echo (int)$staffingMissing; ?> worker(s) for strict no-repeat same-day coverage.
        </div>
    <?php endif; ?>

    <div class="card mb-4 no-print">
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
                    <button type="submit" name="generate_timetable" class="btn btn-danger w-100" <?php echo $staffingCanGuaranteeUnique ? '' : 'disabled'; ?> title="<?php echo $staffingCanGuaranteeUnique ? 'Generate monthly timetable' : 'Add more workers to meet daily shift coverage before generating'; ?>">
                        <i class="bi bi-calendar2-plus"></i> Generate
                    </button>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="w-100 mb-1">
                        <span class="badge <?php echo $staffingCanGuaranteeUnique ? 'bg-success' : 'bg-warning text-dark'; ?>">
                            Required per day: <?php echo (int)$requiredPerDay; ?> | Available: <?php echo (int)$staffingCount; ?>
                        </span>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-muted">Month window: <?php echo htmlspecialchars($monthStart, ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars($monthEnd, ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php if (!$staffingCanGuaranteeUnique): ?>
                        <div class="small text-danger mt-1">Generation is disabled until enough workers are available for unique same-day shift assignment.</div>
                    <?php endif; ?>
                    <ul class="small text-muted mt-2 mb-0">
                        <li>Pharmacy workers: 09:00-21:00 and 21:00-09:00 (1 worker per shift)</li>
                        <li>Lab workers: 09:00-17:00 and 17:00-21:00</li>
                        <li>Doctors: 09:00-15:00 and 15:00-21:00</li>
                        <li>Interns/Trainees: 09:00-15:00, 15:00-21:00, and 21:00-09:00</li>
                    </ul>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-header"><strong>Mark On Permission / On Rest</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?php echo csrfField(); ?>
                <input type="hidden" name="department" value="<?php echo htmlspecialchars($selectedDepartment, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="month_seed" value="<?php echo htmlspecialchars(substr($monthStart, 0, 7), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="col-md-4">
                    <label for="status_user_id" class="form-label">Worker</label>
                    <select class="form-select" id="status_user_id" name="status_user_id" required>
                        <option value="">Select worker</option>
                        <?php foreach ($departmentUsers as $w): ?>
                            <option value="<?php echo (int)$w['id']; ?>"><?php echo htmlspecialchars(trim((string)$w['first_name'] . ' ' . (string)$w['last_name']) . ' (' . (string)$w['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status_date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="status_date" name="status_date" min="<?php echo htmlspecialchars($monthStart, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($monthEnd, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="status_type" class="form-label">Status</label>
                    <select class="form-select" id="status_type" name="status_type" required>
                        <option value="on_permission">On Permission</option>
                        <option value="on_rest">On Rest</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="mark_day_status" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-person-lines-fill"></i> Save
                    </button>
                </div>

                <div class="col-12">
                    <label for="status_note" class="form-label">Note (optional)</label>
                    <input type="text" class="form-control" id="status_note" name="status_note" maxlength="255" placeholder="Reason or admin note">
                </div>
            </form>
        </div>
    </div>

    <?php if ($editEntry !== null): ?>
        <div class="card mb-4 no-print">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Edit Timetable Entry</strong>
                <a class="btn btn-sm btn-outline-secondary" href="timetable.php?department=<?php echo urlencode($selectedDepartment); ?>&month_seed=<?php echo urlencode(substr($monthStart, 0, 7)); ?>">Close</a>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($selectedDepartment, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="month_seed" value="<?php echo htmlspecialchars(substr($monthStart, 0, 7), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="entry_id" value="<?php echo (int)$editEntry['id']; ?>">

                    <div class="col-md-4">
                        <label for="edit_user_id" class="form-label">Worker</label>
                        <select class="form-select" id="edit_user_id" name="edit_user_id" required>
                            <?php foreach ($departmentUsers as $w): ?>
                                <option value="<?php echo (int)$w['id']; ?>" <?php echo (int)$w['id'] === (int)$editEntry['timetable_user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim((string)$w['first_name'] . ' ' . (string)$w['last_name']) . ' (' . (string)$w['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="edit_shift_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="edit_shift_date" name="edit_shift_date" min="<?php echo htmlspecialchars($monthStart, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($monthEnd, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$editEntry['shift_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label for="edit_shift_name" class="form-label">Shift</label>
                        <select class="form-select" id="edit_shift_name" name="edit_shift_name" required>
                            <?php foreach (timetableTemplateShifts($selectedDepartment) as $template): ?>
                                <option value="<?php echo htmlspecialchars((string)$template['name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string)$editEntry['shift_name'] === (string)$template['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$template['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                            <option value="on_permission" <?php echo (string)$editEntry['shift_name'] === 'on_permission' ? 'selected' : ''; ?>>On Permission</option>
                            <option value="on_rest" <?php echo (string)$editEntry['shift_name'] === 'on_rest' ? 'selected' : ''; ?>>On Rest</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label for="edit_start_time" class="form-label">Start</label>
                        <input type="time" class="form-control" id="edit_start_time" name="edit_start_time" value="<?php echo htmlspecialchars(date('H:i', strtotime((string)$editEntry['start_at'])), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-1">
                        <label for="edit_end_time" class="form-label">End</label>
                        <input type="time" class="form-control" id="edit_end_time" name="edit_end_time" value="<?php echo htmlspecialchars(date('H:i', strtotime((string)$editEntry['end_at'])), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-12">
                        <label for="edit_note" class="form-label">Note</label>
                        <input type="text" class="form-control" id="edit_note" name="edit_note" maxlength="255" value="<?php echo htmlspecialchars((string)($editEntry['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" name="update_timetable_entry" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo htmlspecialchars($departments[$selectedDepartment], ENT_QUOTES, 'UTF-8'); ?> Timetable</strong>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-dark no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                <span class="badge bg-secondary"><?php echo count($rows); ?> shifts</span>
            </div>
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
                            <th>Note</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="text-center text-muted">No timetable generated for selected department/month.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$r['shift_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($statusLabels[(string)$r['shift_name']] ?? (string)$r['shift_name']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('H:i', strtotime((string)$r['start_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['end_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(trim((string)$r['first_name'] . ' ' . (string)$r['last_name']) . ' (' . (string)$r['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$r['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="no-print">
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="timetable.php?department=<?php echo urlencode($selectedDepartment); ?>&month_seed=<?php echo urlencode(substr($monthStart, 0, 7)); ?>&edit_id=<?php echo (int)$r['id']; ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Archive this timetable entry? You can restore it later.');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selectedDepartment, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="month_seed" value="<?php echo htmlspecialchars(substr($monthStart, 0, 7), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="entry_id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" name="delete_timetable_entry" class="btn btn-sm btn-outline-danger">Archive</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4 no-print">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Archived Entries</strong>
            <span class="badge bg-warning text-dark"><?php echo count($archivedRows); ?> archived</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Worker</th>
                            <th>Role</th>
                            <th>Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$archivedRows): ?>
                        <tr><td colspan="5" class="text-center text-muted">No archived entries for selected department/month.</td></tr>
                    <?php else: ?>
                        <?php foreach ($archivedRows as $ar): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$ar['shift_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(trim((string)$ar['first_name'] . ' ' . (string)$ar['last_name']) . ' (' . (string)$ar['username'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$ar['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($ar['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Restore this archived entry?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selectedDepartment, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="month_seed" value="<?php echo htmlspecialchars(substr($monthStart, 0, 7), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="entry_id" value="<?php echo (int)$ar['id']; ?>">
                                            <button type="submit" name="restore_timetable_entry" class="btn btn-sm btn-outline-success">Restore</button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this archived entry? This cannot be undone.');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selectedDepartment, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="month_seed" value="<?php echo htmlspecialchars(substr($monthStart, 0, 7), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="entry_id" value="<?php echo (int)$ar['id']; ?>">
                                            <button type="submit" name="hard_delete_archived_entry" class="btn btn-sm btn-outline-danger">Delete Permanently</button>
                                        </form>
                                    </div>
                                </td>
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
