<?php
/**
 * Generate and serve timetable as PDF
 */

require_once '../config/config.php';
require_once '../includes/auth.php';

// Allow both admin and designated roles
try {
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        http_response_code(401);
        exit('Unauthorized');
    }
} catch (Exception $e) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : 'cmds_staff';

if ($month < 1 || $month > 12 || $year < 2024 || $year > 2030) {
    http_response_code(400);
    exit('Invalid month or year');
}

try {
    $pdo = getDB();
    
    // Fetch timetable data
    $currentMonth = sprintf('%04d-%02d', $year, $month);
    $stmt = $pdo->prepare("SELECT * FROM shift_timetables WHERE strftime('%Y-%m', shift_date) = ? AND worker_group = ? ORDER BY shift_date ASC");
    $stmt->execute([$currentMonth, $department]);
    $timetableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($timetableData)) {
        http_response_code(404);
        exit('No timetable data found');
    }
    
    // Get unique staff members from the data
    $staffMembers = [];
    foreach ($timetableData as $row) {
        $staffName = $row['note']; // Staff name is stored in note field
        if (!in_array($staffName, $staffMembers)) {
            $staffMembers[] = $staffName;
        }
    }
    
    // Department labels
    $departmentLabels = [
        'cmds_staff' => 'CMDS Staff',
        'it_department' => 'IT Department',
        'doctors' => 'Doctors',
        'pharmacy' => 'Pharmacy',
        'staff' => 'General Staff',
        'interns' => 'Interns',
        'trainees' => 'Trainees',
    ];
    
    $deptLabel = $departmentLabels[$department] ?? $department;
    $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    
    // Shift colors for PDF
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
    
    // Generate HTML content
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Timetable - ' . htmlspecialchars($deptLabel) . ' - ' . htmlspecialchars($monthName) . '</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header {
            background: linear-gradient(135deg, #0b2b3b 0%, #1a4a62 100%);
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .header h1 { font-size: 18px; margin-bottom: 5px; }
        .header .subtitle { font-size: 11px; opacity: 0.9; }
        .header .info { font-size: 10px; margin-top: 5px; }
        
        .legend {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .legend-title { font-weight: bold; margin-bottom: 8px; font-size: 11px; }
        .legend-items { display: flex; flex-wrap: wrap; gap: 10px; }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 9px;
        }
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            border: 1px solid #999;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        thead {
            background: #0b2b3b;
            color: white;
        }
        th {
            padding: 8px 5px;
            border: 1px solid #ddd;
            font-weight: bold;
            text-align: center;
            font-size: 9px;
        }
        td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
        }
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        .shift-cell {
            font-weight: bold;
            border-radius: 3px;
        }
        .date-cell {
            font-weight: bold;
            background: #e8e8e8;
        }
        .day-cell {
            background: #f0f0f0;
        }
        
        .footer {
            text-align: center;
            font-size: 8px;
            color: #666;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        @media print {
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 Staff Timetable</h1>
        <div class="subtitle">CENTRE MEDICAL DONS DE SOINS - Auto-Rotation System V4.0</div>
        <div class="info">
            <strong>Department:</strong> ' . htmlspecialchars($deptLabel) . ' | 
            <strong>Period:</strong> ' . htmlspecialchars($monthName) . ' | 
            <strong>Generated:</strong> ' . date('d-m-Y H:i') . '
        </div>
    </div>
    
    <div class="legend">
        <div class="legend-title">📊 Shift Codes Legend</div>
        <div class="legend-items">
            <div class="legend-item"><span class="legend-color" style="background: #ADD8E6;"></span> <strong>M</strong> = Matin (8h-14h)</div>
            <div class="legend-item"><span class="legend-color" style="background: #90EE90;"></span> <strong>A</strong> = Après-midi (14h-21h)</div>
            <div class="legend-item"><span class="legend-color" style="background: #00008B;"></span> <strong>N</strong> = Nuit (21h-8h)</div>
            <div class="legend-item"><span class="legend-color" style="background: #FFA500;"></span> <strong>J</strong> = Journée (8h-22h)</div>
            <div class="legend-item"><span class="legend-color" style="background: #191970;"></span> <strong>G</strong> = Garde (21h-8h)</div>
            <div class="legend-item"><span class="legend-color" style="background: #D3D3D3;"></span> <strong>R</strong> = Repos</div>
            <div class="legend-item"><span class="legend-color" style="background: #DC143C;"></span> <strong>REPOS</strong> = Congé</div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="8%">Date</th>
                <th width="5%">Day</th>';
    
    foreach ($staffMembers as $staff) {
        $colWidth = (100 - 13) / count($staffMembers);
        $html .= '<th width="' . $colWidth . '%">' . htmlspecialchars($staff) . '</th>';
    }
    
    $html .= '</tr>
        </thead>
        <tbody>';
    
    // Group by date
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
        $html .= '<tr>';
        $html .= '<td class="date-cell"><strong>' . $dateObj->format('d-m-Y') . '</strong></td>';
        $html .= '<td class="day-cell">' . $dateObj->format('D') . '</td>';
        
        foreach ($staffMembers as $staff) {
            $shiftCode = $shifts[$staff] ?? '—';
            $bgColor = $shiftColors[$shiftCode] ?? '#FFFFFF';
            $textColor = (in_array($shiftCode, ['N', 'G', '22h-8h', 'REPOS'])) ? '#FFFFFF' : '#000000';
            $html .= '<td class="shift-cell" style="background-color: ' . $bgColor . '; color: ' . $textColor . ';">' . htmlspecialchars($shiftCode) . '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="footer">
        <p>This document is confidential and for authorized personnel only.</p>
        <p>Generated by CMDS Timetable Management System v4.0</p>
    </div>
</body>
</html>';
    
    // Create PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $department . '_timetable_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    echo $dompdf->output();
    
} catch (Exception $e) {
    error_log('PDF Generation Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating PDF: ' . htmlspecialchars($e->getMessage());
}
