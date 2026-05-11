<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();
$user = $_SESSION['user'];
$message = '';
$error = '';

function formatUserLabel(array $user): string {
    $name = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($user['username'] ?? ''));
    }
    return $name !== '' ? $name . ' (' . ($user['role'] ?? 'user') . ')' : ($user['username'] ?? 'Unknown');
}

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$pdo = getDB();

function loadSchedulerData(PDO $pdo): array {
    $staffStmt = $pdo->query("SELECT id, username, first_name, last_name, role FROM users WHERE role != 'patient' ORDER BY first_name, last_name, username");
    $groupsStmt = $pdo->query("SELECT id, name, description FROM scheduler_groups ORDER BY name");
    $teamsStmt = $pdo->query("SELECT t.id, t.group_id, t.name, t.description, t.lead_id, g.name AS group_name, u.first_name, u.last_name, u.username FROM scheduler_teams t LEFT JOIN scheduler_groups g ON t.group_id = g.id LEFT JOIN users u ON t.lead_id = u.id ORDER BY g.name, t.name");
    $membersStmt = $pdo->query("SELECT tm.id, tm.team_id, tm.user_id, tm.role, tm.joined_at, t.name AS team_name, g.name AS group_name, u.first_name, u.last_name, u.username FROM scheduler_team_members tm JOIN scheduler_teams t ON tm.team_id = t.id JOIN scheduler_groups g ON t.group_id = g.id JOIN users u ON tm.user_id = u.id ORDER BY g.name, t.name, u.first_name, u.last_name");
    $schedulesStmt = $pdo->query("SELECT s.id, s.team_id, s.day_of_week, s.shift_type, s.start_time, s.end_time, s.location, t.name AS team_name, g.name AS group_name FROM scheduler_schedules s JOIN scheduler_teams t ON s.team_id = t.id JOIN scheduler_groups g ON t.group_id = g.id ORDER BY g.name, t.name, s.day_of_week, s.start_time");

    return [
        'staff' => $staffStmt->fetchAll(PDO::FETCH_ASSOC),
        'groups' => $groupsStmt->fetchAll(PDO::FETCH_ASSOC),
        'teams' => $teamsStmt->fetchAll(PDO::FETCH_ASSOC),
        'members' => $membersStmt->fetchAll(PDO::FETCH_ASSOC),
        'schedules' => $schedulesStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        if (isset($_POST['create_group'])) {
            $name = trim((string)($_POST['group_name'] ?? ''));
            $description = trim((string)($_POST['group_description'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Please enter a scheduler group name.');
            }
            $insert = $pdo->prepare('INSERT INTO scheduler_groups (name, description, created_by) VALUES (?, ?, ?)');
            $insert->execute([$name, $description, (int)$user['id']]);
            $message = 'Scheduler group saved successfully.';
        } elseif (isset($_POST['create_team'])) {
            $groupId = (int)($_POST['team_group_id'] ?? 0);
            $name = trim((string)($_POST['team_name'] ?? ''));
            $description = trim((string)($_POST['team_description'] ?? ''));
            $leadId = (int)($_POST['team_lead_id'] ?? 0);
            if ($groupId <= 0 || $name === '') {
                throw new RuntimeException('Please select a group and provide a team name.');
            }
            $insert = $pdo->prepare('INSERT INTO scheduler_teams (group_id, name, description, lead_id, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $insert->execute([$groupId, $name, $description, $leadId > 0 ? $leadId : null]);
            $message = 'Scheduler team created successfully.';
        } elseif (isset($_POST['create_member'])) {
            $teamId = (int)($_POST['member_team_id'] ?? 0);
            $userId = (int)($_POST['member_user_id'] ?? 0);
            $role = trim((string)($_POST['member_role'] ?? ''));
            if ($teamId <= 0 || $userId <= 0) {
                throw new RuntimeException('Please choose a team and staff member.');
            }
            $insert = $pdo->prepare('INSERT INTO scheduler_team_members (team_id, user_id, role) VALUES (?, ?, ?)');
            $insert->execute([$teamId, $userId, $role]);
            $message = 'Team member assigned successfully.';
        } elseif (isset($_POST['create_schedule'])) {
            $teamId = (int)($_POST['schedule_team_id'] ?? 0);
            $dayOfWeek = (int)($_POST['schedule_day_of_week'] ?? -1);
            $shiftType = trim((string)($_POST['schedule_shift_type'] ?? ''));
            $startTime = trim((string)($_POST['schedule_start_time'] ?? ''));
            $endTime = trim((string)($_POST['schedule_end_time'] ?? ''));
            $location = trim((string)($_POST['schedule_location'] ?? ''));
            if ($teamId <= 0 || $dayOfWeek < 0 || $dayOfWeek > 6 || $shiftType === '') {
                throw new RuntimeException('Please select a team, day of week and shift type.');
            }
            $insert = $pdo->prepare('INSERT INTO scheduler_schedules (team_id, day_of_week, shift_type, start_time, end_time, location, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$teamId, $dayOfWeek, $shiftType, $startTime ?: null, $endTime ?: null, $location, (int)$user['id']]);
            $message = 'Schedule block created successfully.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$data = loadSchedulerData($pdo);
$staffUsers = $data['staff'];
$groups = $data['groups'];
$teams = $data['teams'];
$members = $data['members'];
$schedules = $data['schedules'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Scheduler - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 14px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, 0.06);
        }
        .page-title {
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .form-card {
            margin-bottom: 1.5rem;
        }
        .table-small th,
        .table-small td {
            vertical-align: middle;
            padding: 0.65rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-calendar2-check"></i> Staff Scheduler</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                    <a class="nav-link active" href="scheduler.php">Scheduler</a>
                    <a class="nav-link" href="timetable.php">Timetable</a>
                    <a class="nav-link" href="manage_users.php">Users</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="page-title">Staff Scheduler</h1>
                <p class="text-muted">Create groups, teams, and schedules for your clinical staff.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo nl2br(htmlspecialchars($error, ENT_QUOTES, 'UTF-8')); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card form-card p-4 mb-4">
                    <h4 class="mb-3">New Scheduler Group</h4>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label">Group name</label>
                            <input type="text" name="group_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="group_description" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                    </form>
                </div>

                <div class="card form-card p-4 mb-4">
                    <h4 class="mb-3">New Scheduler Team</h4>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label">Group</label>
                            <select name="team_group_id" class="form-select" required>
                                <option value="">Select group</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo (int)$group['id']; ?>"><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team name</label>
                            <input type="text" name="team_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team lead</label>
                            <select name="team_lead_id" class="form-select">
                                <option value="">No lead</option>
                                <?php foreach ($staffUsers as $staff): ?>
                                    <option value="<?php echo (int)$staff['id']; ?>"><?php echo htmlspecialchars(formatUserLabel($staff), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="team_description" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" name="create_team" class="btn btn-primary">Create Team</button>
                    </form>
                </div>

                <div class="card form-card p-4 mb-4">
                    <h4 class="mb-3">Assign Team Member</h4>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label">Team</label>
                            <select name="member_team_id" class="form-select" required>
                                <option value="">Select team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo (int)$team['id']; ?>"><?php echo htmlspecialchars($team['group_name'] . ' / ' . $team['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff member</label>
                            <select name="member_user_id" class="form-select" required>
                                <option value="">Select staff</option>
                                <?php foreach ($staffUsers as $staff): ?>
                                    <option value="<?php echo (int)$staff['id']; ?>"><?php echo htmlspecialchars(formatUserLabel($staff), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team role</label>
                            <input type="text" name="member_role" class="form-control" placeholder="e.g. Nurse, Pharmacist, Support">
                        </div>
                        <button type="submit" name="create_member" class="btn btn-primary">Assign Member</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card form-card p-4 mb-4">
                    <h4 class="mb-3">New Team Schedule</h4>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label">Team</label>
                            <select name="schedule_team_id" class="form-select" required>
                                <option value="">Select team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo (int)$team['id']; ?>"><?php echo htmlspecialchars($team['group_name'] . ' / ' . $team['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Day of week</label>
                                <select name="schedule_day_of_week" class="form-select" required>
                                    <option value="">Select day</option>
                                    <?php foreach ($dayNames as $index => $dayName): ?>
                                        <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift type</label>
                                <input type="text" name="schedule_shift_type" class="form-control" placeholder="e.g. Morning, Night" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start time</label>
                                <input type="time" name="schedule_start_time" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End time</label>
                                <input type="time" name="schedule_end_time" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="schedule_location" class="form-control" placeholder="e.g. Ward A, Pharmacy">
                        </div>
                        <button type="submit" name="create_schedule" class="btn btn-primary">Save Schedule</button>
                    </form>
                </div>

                <div class="card p-4 mb-4">
                    <h4 class="mb-3">Scheduler summary</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-small mb-0">
                            <thead>
                                <tr>
                                    <th>Groups</th>
                                    <th>Teams</th>
                                    <th>Members</th>
                                    <th>Schedules</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo count($groups); ?></td>
                                    <td><?php echo count($teams); ?></td>
                                    <td><?php echo count($members); ?></td>
                                    <td><?php echo count($schedules); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card p-4">
                    <h4 class="mb-3">Active Group List</h4>
                    <?php if ($groups): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($group['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No scheduler groups created yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-6 mb-4">
                <div class="card p-4">
                    <h4 class="mb-3">Teams</h4>
                    <?php if ($teams): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Team</th>
                                        <th>Lead</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teams as $team): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($team['group_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php
                                                $leadLabel = trim((string)($team['first_name'] ?? '') . ' ' . (string)($team['last_name'] ?? ''));
                                                if ($leadLabel === '') {
                                                    $leadLabel = trim((string)($team['username'] ?? ''));
                                                }
                                                echo htmlspecialchars($leadLabel ?: 'None', ENT_QUOTES, 'UTF-8');
                                            ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No scheduler teams defined yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card p-4">
                    <h4 class="mb-3">Team Members</h4>
                    <?php if ($members): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Team</th>
                                        <th>Staff</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['group_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($member['team_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php
                                                $memberName = trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''));
                                                if ($memberName === '') {
                                                    $memberName = trim((string)($member['username'] ?? ''));
                                                }
                                                echo htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8');
                                            ?></td>
                                            <td><?php echo htmlspecialchars($member['role'] ?: 'Member', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No members assigned to scheduler teams yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card p-4">
                    <h4 class="mb-3">Scheduled Team Rotations</h4>
                    <?php if ($schedules): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Team</th>
                                        <th>Day</th>
                                        <th>Shift</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['group_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['team_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($dayNames[(int)$schedule['day_of_week']] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['shift_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(trim((string)$schedule['start_time'] . ' – ' . $schedule['end_time']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No scheduler blocks created yet. Add schedule rows to begin.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
