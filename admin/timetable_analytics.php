<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];

$staffList = [
    'Abeng', 'Mayan', 'Nloga', 'Favour', 'Wiltitz', 'Kadija', 'Nyanze',
    'Mvogo', 'Nagayena', 'Ndong', 'Zad', 'Florinda', 'Cathrine', 'Abanda', 'Saurel'
];

// Calculate shift counts
$shiftCounts = [];
$staffStats = [];

try {
    $pdo = getDB();
    
    // Get all shifts from current month
    $stmt = $pdo->prepare("
        SELECT note, shift_name, COUNT(*) as count
        FROM shift_timetables
        WHERE strftime('%Y-%m', shift_date) = ?
        GROUP BY note, shift_name
    ");
    $stmt->execute([date('Y-m')]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $staffName = $row['note'];
        $shiftType = $row['shift_name'];
        $count = (int)$row['count'];
        
        if (!isset($staffStats[$staffName])) {
            $staffStats[$staffName] = ['M' => 0, 'A' => 0, 'N' => 0, 'G' => 0, 'J' => 0, 'R' => 0, 'REPOS' => 0, 'Total' => 0];
        }
        
        if (isset($staffStats[$staffName][$shiftType])) {
            $staffStats[$staffName][$shiftType] += $count;
        }
        $staffStats[$staffName]['Total'] += $count;
    }
} catch (Throwable $e) {
    error_log('Analytics error: ' . $e->getMessage());
    $staffStats = [];
}

// Calculate totals
$totalStats = ['M' => 0, 'A' => 0, 'N' => 0, 'G' => 0, 'J' => 0, 'R' => 0, 'REPOS' => 0];
foreach ($staffStats as $shifts) {
    foreach ($totalStats as $type => &$total) {
        if (isset($shifts[$type])) {
            $total += $shifts[$type];
        }
    }
    unset($total);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Analytics - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f5f7fa; }
        .analytics-container { max-width: 1400px; margin: 2rem auto; }
        .analytics-header { background: linear-gradient(135deg, #0b2b3b 0%, #1a4a62 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .analytics-header h1 { font-size: 2rem; font-weight: 600; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #0b2b3b; }
        .stat-label { font-size: 0.9rem; color: #666; margin-top: 0.5rem; }
        .chart-container { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative; height: 400px; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .table-container table { margin: 0; }
        .table-container th { background: #0b2b3b; color: white; padding: 1rem; }
        .table-container td { padding: 0.75rem 1rem; border-bottom: 1px solid #eee; }
        .badge-M { background: #ADD8E6; }
        .badge-A { background: #90EE90; }
        .badge-N { background: #00008B; color: white; }
        .badge-G { background: #191970; color: white; }
        .badge-J { background: #FFA500; }
        .badge-R { background: #D3D3D3; }
        .badge-REPOS { background: #DC143C; color: white; }
        .nav-buttons { margin-bottom: 2rem; }
    </style>
</head>
<body>
<div class="analytics-container">
    <div class="analytics-header">
        <h1><i class="bi bi-graph-up"></i> Timetable Analytics</h1>
        <p>CENTRE MEDICAL DONS DE SOINS - Shift Distribution & Staff Balance</p>
    </div>
    
    <div class="nav-buttons">
        <a href="timetable.php" class="btn btn-primary">
            <i class="bi bi-calendar3"></i> Timetable
        </a>
        <a href="staff_management.php" class="btn btn-outline-primary">
            <i class="bi bi-people"></i> Staff Management
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['M']; ?></div>
            <div class="stat-label">Morning Shifts (M)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['A']; ?></div>
            <div class="stat-label">Afternoon Shifts (A)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['N']; ?></div>
            <div class="stat-label">Night Shifts (N)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['G']; ?></div>
            <div class="stat-label">Guard Shifts (G)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['J']; ?></div>
            <div class="stat-label">Full Day (J)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['R']; ?></div>
            <div class="stat-label">Rest Days (R)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStats['REPOS']; ?></div>
            <div class="stat-label">Leave Days</div>
        </div>
    </div>
    
    <div class="chart-container">
        <canvas id="shiftChart"></canvas>
    </div>
    
    <div class="chart-container">
        <canvas id="staffChart"></canvas>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th><span class="badge badge-M">M</span></th>
                    <th><span class="badge badge-A">A</span></th>
                    <th><span class="badge badge-N">N</span></th>
                    <th><span class="badge badge-G">G</span></th>
                    <th><span class="badge badge-J">J</span></th>
                    <th><span class="badge badge-R">R</span></th>
                    <th><span class="badge badge-REPOS">REPOS</span></th>
                    <th><strong>Total</strong></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $staff): ?>
                    <?php $stats = $staffStats[$staff] ?? ['M' => 0, 'A' => 0, 'N' => 0, 'G' => 0, 'J' => 0, 'R' => 0, 'REPOS' => 0, 'Total' => 0]; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($staff); ?></strong></td>
                        <td><?php echo $stats['M']; ?></td>
                        <td><?php echo $stats['A']; ?></td>
                        <td><?php echo $stats['N']; ?></td>
                        <td><?php echo $stats['G']; ?></td>
                        <td><?php echo $stats['J']; ?></td>
                        <td><?php echo $stats['R']; ?></td>
                        <td><?php echo $stats['REPOS']; ?></td>
                        <td><strong><?php echo $stats['Total']; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f5f7fa;">
                    <td><strong>TOTAL</strong></td>
                    <td><strong><?php echo $totalStats['M']; ?></strong></td>
                    <td><strong><?php echo $totalStats['A']; ?></strong></td>
                    <td><strong><?php echo $totalStats['N']; ?></strong></td>
                    <td><strong><?php echo $totalStats['G']; ?></strong></td>
                    <td><strong><?php echo $totalStats['J']; ?></strong></td>
                    <td><strong><?php echo $totalStats['R']; ?></strong></td>
                    <td><strong><?php echo $totalStats['REPOS']; ?></strong></td>
                    <td><strong><?php echo array_sum($totalStats); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
// Shift distribution chart
const shiftCtx = document.getElementById('shiftChart').getContext('2d');
new Chart(shiftCtx, {
    type: 'doughnut',
    data: {
        labels: ['Morning (M)', 'Afternoon (A)', 'Night (N)', 'Guard (G)', 'Full Day (J)', 'Rest (R)', 'Leave (REPOS)'],
        datasets: [{
            data: [<?php echo $totalStats['M']; ?>, <?php echo $totalStats['A']; ?>, <?php echo $totalStats['N']; ?>, <?php echo $totalStats['G']; ?>, <?php echo $totalStats['J']; ?>, <?php echo $totalStats['R']; ?>, <?php echo $totalStats['REPOS']; ?>],
            backgroundColor: ['#ADD8E6', '#90EE90', '#00008B', '#191970', '#FFA500', '#D3D3D3', '#DC143C']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: { display: true, text: 'Shift Distribution (Current Month)' },
            legend: { position: 'bottom' }
        }
    }
});

// Staff workload comparison
const staffCtx = document.getElementById('staffChart').getContext('2d');
const staffNames = <?php echo json_encode(array_keys($staffStats)); ?>;
const staffTotals = <?php echo json_encode(array_map(function($s) use ($staffStats) { return $staffStats[$s]['Total'] ?? 0; }, $staffList)); ?>;

new Chart(staffCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($staffList); ?>,
        datasets: [{
            label: 'Total Shifts per Staff',
            data: staffTotals,
            backgroundColor: '#0b2b3b',
            borderColor: '#1a4a62',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: { display: true, text: 'Staff Workload Distribution' },
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
