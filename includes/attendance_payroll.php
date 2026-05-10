<?php
/**
 * Enhanced Attendance and Payroll System
 * Functions for time tracking, payroll calculations, and reporting
 */

function attendanceGetScheduledTimes(PDO $pdo, int $userId, string $date): array
{
    // Default work schedule (can be customized per user/role)
    $user = getUserById($pdo, $userId);
    $defaultSchedule = [
        'doctor' => ['start' => '08:00', 'end' => '17:00'],
        'staff' => ['start' => '08:00', 'end' => '17:00'],
        'intern' => ['start' => '09:00', 'end' => '18:00'],
        'trainee' => ['start' => '09:00', 'end' => '18:00']
    ];

    $role = $user['role'] ?? 'staff';
    return $defaultSchedule[$role] ?? $defaultSchedule['staff'];
}

function attendanceRecordCheckIn(PDO $pdo, int $userId, ?string $notes = null): bool
{
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    // Check if already checked in today
    $stmt = $pdo->prepare("SELECT id FROM attendance_records WHERE user_id = ? AND attendance_date = ?");
    $stmt->execute([$userId, $today]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing record
        $update = $pdo->prepare("
            UPDATE attendance_records
            SET actual_check_in = ?, status = 'present', notes = COALESCE(?, notes)
            WHERE id = ?
        ");
        return $update->execute([$now, $notes, $existing['id']]);
    } else {
        // Create new record
        $schedule = attendanceGetScheduledTimes($pdo, $userId, $today);
        $insert = $pdo->prepare("
            INSERT INTO attendance_records
            (user_id, attendance_date, scheduled_start_time, scheduled_end_time, actual_check_in, status, notes)
            VALUES (?, ?, ?, ?, ?, 'present', ?)
        ");
        return $insert->execute([$userId, $today, $schedule['start'], $schedule['end'], $now, $notes]);
    }
}

function attendanceRecordCheckOut(PDO $pdo, int $userId, ?string $notes = null): bool
{
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT id, actual_check_in, scheduled_start_time, scheduled_end_time
        FROM attendance_records
        WHERE user_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$userId, $today]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        return false; // No check-in record found
    }

    // Calculate hours worked
    $checkIn = strtotime($record['actual_check_in']);
    $checkOut = strtotime($now);
    $totalHours = round(($checkOut - $checkIn) / 3600, 2);

    // Calculate regular and overtime hours
    $scheduledStart = strtotime($today . ' ' . $record['scheduled_start_time']);
    $scheduledEnd = strtotime($today . ' ' . $record['scheduled_end_time']);
    $scheduledHours = round(($scheduledEnd - $scheduledStart) / 3600, 2);

    $regularHours = min($totalHours, $scheduledHours);
    $overtimeHours = max(0, $totalHours - $scheduledHours);

    // Calculate late minutes
    $lateMinutes = 0;
    if ($checkIn > $scheduledStart) {
        $lateMinutes = round(($checkIn - $scheduledStart) / 60);
    }

    // Calculate early departure
    $earlyDepartureMinutes = 0;
    if ($checkOut < $scheduledEnd) {
        $earlyDepartureMinutes = round(($scheduledEnd - $checkOut) / 60);
    }

    // Determine status
    $status = 'present';
    if ($lateMinutes > 30) {
        $status = 'late';
    } elseif ($totalHours < ($scheduledHours / 2)) {
        $status = 'half_day';
    }

    $update = $pdo->prepare("
        UPDATE attendance_records
        SET actual_check_out = ?, total_hours = ?, regular_hours = ?, overtime_hours = ?,
            late_minutes = ?, early_departure_minutes = ?, status = ?,
            notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    return $update->execute([
        $now, $totalHours, $regularHours, $overtimeHours,
        $lateMinutes, $earlyDepartureMinutes, $status, $notes, $record['id']
    ]);
}

function attendanceGetTodayRecord(PDO $pdo, int $userId): ?array
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_records
        WHERE user_id = ? AND attendance_date = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId, $today]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function payrollCalculateForPeriod(PDO $pdo, int $periodId, int $userId, int $calculatedBy): bool
{
    // Get payroll period
    $periodStmt = $pdo->prepare("SELECT * FROM payroll_periods WHERE id = ?");
    $periodStmt->execute([$periodId]);
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$period) {
        return false;
    }

    // Get employee payroll settings
    $payrollStmt = $pdo->prepare("SELECT * FROM employee_payroll WHERE user_id = ?");
    $payrollStmt->execute([$userId]);
    $payrollSettings = $payrollStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payrollSettings) {
        return false;
    }

    // Get attendance records for the period
    $attendanceStmt = $pdo->prepare("
        SELECT * FROM attendance_records
        WHERE user_id = ? AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date
    ");
    $attendanceStmt->execute([$userId, $period['start_date'], $period['end_date']]);
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalWorkDays = 0;
    $totalPresentDays = 0;
    $totalAbsentDays = 0;
    $totalLateDays = 0;
    $totalOvertimeHours = 0;
    $totalLateMinutes = 0;

    foreach ($attendanceRecords as $record) {
        $totalWorkDays++;
        if ($record['status'] === 'present' || $record['status'] === 'late') {
            $totalPresentDays++;
        } elseif ($record['status'] === 'absent') {
            $totalAbsentDays++;
        }

        if ($record['status'] === 'late') {
            $totalLateDays++;
        }

        $totalOvertimeHours += (float)($record['overtime_hours'] ?? 0);
        $totalLateMinutes += (int)($record['late_minutes'] ?? 0);
    }

    // Calculate pay components
    $baseSalary = (float)$payrollSettings['base_salary'];
    $hourlyRate = (float)$payrollSettings['hourly_rate'];

    // Overtime pay (assuming 1.5x rate)
    $overtimePay = $totalOvertimeHours * $hourlyRate * 1.5;

    // Late deductions (200 CFA per 30 minutes late)
    $lateDeductions = floor($totalLateMinutes / 30) * 200;

    // Gross pay (prorated based on attendance)
    $attendanceRatio = $totalPresentDays / max(1, $totalWorkDays);
    $grossPay = ($baseSalary * $attendanceRatio) + $overtimePay;

    // Net pay after deductions
    $netPay = $grossPay - $lateDeductions;

    // Punctuality rating (0-100, higher is better)
    $punctualityRating = 100;
    if ($totalLateDays > 0) {
        $punctualityRating = max(0, 100 - ($totalLateDays * 10) - ($totalAbsentDays * 20));
    }

    // Insert or update payroll calculation
    $insert = $pdo->prepare("
        INSERT INTO payroll_calculations
        (payroll_period_id, user_id, base_salary, overtime_pay, late_deductions,
         gross_pay, net_pay, total_work_days, total_present_days, total_absent_days,
         total_late_days, total_overtime_hours, total_late_minutes, punctuality_rating,
         calculated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        base_salary = VALUES(base_salary),
        overtime_pay = VALUES(overtime_pay),
        late_deductions = VALUES(late_deductions),
        gross_pay = VALUES(gross_pay),
        net_pay = VALUES(net_pay),
        total_work_days = VALUES(total_work_days),
        total_present_days = VALUES(total_present_days),
        total_absent_days = VALUES(total_absent_days),
        total_late_days = VALUES(total_late_days),
        total_overtime_hours = VALUES(total_overtime_hours),
        total_late_minutes = VALUES(total_late_minutes),
        punctuality_rating = VALUES(punctuality_rating),
        calculated_by = VALUES(calculated_by),
        calculated_at = CURRENT_TIMESTAMP
    ");

    return $insert->execute([
        $periodId, $userId, $baseSalary, $overtimePay, $lateDeductions,
        $grossPay, $netPay, $totalWorkDays, $totalPresentDays, $totalAbsentDays,
        $totalLateDays, $totalOvertimeHours, $totalLateMinutes, $punctualityRating,
        $calculatedBy
    ]);
}

function payrollCreatePeriod(PDO $pdo, string $periodName, string $startDate, string $endDate, int $createdBy): ?int
{
    $insert = $pdo->prepare("
        INSERT INTO payroll_periods (period_name, start_date, end_date)
        VALUES (?, ?, ?)
    ");

    if ($insert->execute([$periodName, $startDate, $endDate])) {
        return (int)$pdo->lastInsertId();
    }

    return null;
}

function payrollGetMonthlyReport(PDO $pdo, int $userId, int $year, int $month): array
{
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    // Get attendance records
    $attendanceStmt = $pdo->prepare("
        SELECT * FROM attendance_records
        WHERE user_id = ? AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date
    ");
    $attendanceStmt->execute([$userId, $startDate, $endDate]);
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payroll calculation if exists
    $payrollStmt = $pdo->prepare("
        SELECT pc.* FROM payroll_calculations pc
        JOIN payroll_periods pp ON pc.payroll_period_id = pp.id
        WHERE pc.user_id = ? AND pp.start_date <= ? AND pp.end_date >= ?
        ORDER BY pc.calculated_at DESC LIMIT 1
    ");
    $payrollStmt->execute([$userId, $startDate, $endDate]);
    $payrollData = $payrollStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $summary = [
        'total_days' => count($attendanceRecords),
        'present_days' => 0,
        'absent_days' => 0,
        'late_days' => 0,
        'half_days' => 0,
        'total_hours' => 0,
        'overtime_hours' => 0,
        'late_minutes' => 0,
        'punctuality_rating' => 0
    ];

    foreach ($attendanceRecords as $record) {
        $summary['total_hours'] += (float)($record['total_hours'] ?? 0);
        $summary['overtime_hours'] += (float)($record['overtime_hours'] ?? 0);
        $summary['late_minutes'] += (int)($record['late_minutes'] ?? 0);

        switch ($record['status']) {
            case 'present':
                $summary['present_days']++;
                break;
            case 'absent':
                $summary['absent_days']++;
                break;
            case 'late':
                $summary['late_days']++;
                $summary['present_days']++;
                break;
            case 'half_day':
                $summary['half_days']++;
                break;
        }
    }

    // Calculate punctuality rating
    if ($summary['total_days'] > 0) {
        $onTimeDays = $summary['present_days'] - $summary['late_days'];
        $summary['punctuality_rating'] = round(($onTimeDays / $summary['total_days']) * 100, 1);
    }

    return [
        'attendance_records' => $attendanceRecords,
        'summary' => $summary,
        'payroll' => $payrollData,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}

function attendanceMarkAbsent(PDO $pdo, int $userId, string $date, ?string $reason = null): bool
{
    $schedule = attendanceGetScheduledTimes($pdo, $userId, $date);

    $insert = $pdo->prepare("
        INSERT INTO attendance_records
        (user_id, attendance_date, scheduled_start_time, scheduled_end_time, status, notes)
        VALUES (?, ?, ?, ?, 'absent', ?)
        ON DUPLICATE KEY UPDATE
        status = 'absent', notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP
    ");

    return $insert->execute([$userId, $date, $schedule['start'], $schedule['end'], $reason, $reason]);
}
?>