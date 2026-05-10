<?php
/**
 * Database Migration: Enhanced Attendance and Payroll System
 * Adds comprehensive time tracking and payroll calculation tables
 */

require_once __DIR__ . '/config/config.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting database migration for enhanced attendance and payroll system...\n";

    // Check if tables already exist
    $tables = ['employee_payroll', 'attendance_records', 'payroll_periods', 'payroll_calculations'];

    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "Table '$table' already exists, skipping...\n";
            continue;
        }

        echo "Creating table '$table'...\n";

        switch ($table) {
            case 'employee_payroll':
                $pdo->exec("
                    CREATE TABLE employee_payroll (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        base_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
                        hourly_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
                        work_hours_per_day DECIMAL(4,2) NOT NULL DEFAULT 8,
                        work_days_per_month INT NOT NULL DEFAULT 26,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE KEY uniq_employee_payroll (user_id)
                    )
                ");
                break;

            case 'attendance_records':
                $pdo->exec("
                    CREATE TABLE attendance_records (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        attendance_date DATE NOT NULL,
                        scheduled_start_time TIME,
                        scheduled_end_time TIME,
                        actual_check_in DATETIME,
                        actual_check_out DATETIME,
                        break_start_time TIME,
                        break_end_time TIME,
                        total_hours DECIMAL(5,2) DEFAULT 0,
                        regular_hours DECIMAL(5,2) DEFAULT 0,
                        overtime_hours DECIMAL(5,2) DEFAULT 0,
                        late_minutes INT DEFAULT 0,
                        early_departure_minutes INT DEFAULT 0,
                        status ENUM('present', 'absent', 'late', 'half_day', 'holiday') DEFAULT 'present',
                        notes TEXT,
                        approved_by INT,
                        approved_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id),
                        FOREIGN KEY (approved_by) REFERENCES users(id),
                        UNIQUE KEY uniq_user_date (user_id, attendance_date),
                        INDEX idx_user_date (user_id, attendance_date),
                        INDEX idx_date_status (attendance_date, status)
                    )
                ");
                break;

            case 'payroll_periods':
                $pdo->exec("
                    CREATE TABLE payroll_periods (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        period_name VARCHAR(100) NOT NULL,
                        start_date DATE NOT NULL,
                        end_date DATE NOT NULL,
                        status ENUM('open', 'processing', 'completed', 'locked') DEFAULT 'open',
                        processed_by INT,
                        processed_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (processed_by) REFERENCES users(id),
                        UNIQUE KEY uniq_period_dates (start_date, end_date)
                    )
                ");
                break;

            case 'payroll_calculations':
                $pdo->exec("
                    CREATE TABLE payroll_calculations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        payroll_period_id INT NOT NULL,
                        user_id INT NOT NULL,
                        base_salary DECIMAL(10,2) DEFAULT 0,
                        overtime_pay DECIMAL(10,2) DEFAULT 0,
                        late_deductions DECIMAL(10,2) DEFAULT 0,
                        other_deductions DECIMAL(10,2) DEFAULT 0,
                        other_allowances DECIMAL(10,2) DEFAULT 0,
                        gross_pay DECIMAL(10,2) DEFAULT 0,
                        net_pay DECIMAL(10,2) DEFAULT 0,
                        total_work_days INT DEFAULT 0,
                        total_present_days INT DEFAULT 0,
                        total_absent_days INT DEFAULT 0,
                        total_late_days INT DEFAULT 0,
                        total_overtime_hours DECIMAL(5,2) DEFAULT 0,
                        total_late_minutes INT DEFAULT 0,
                        punctuality_rating DECIMAL(5,2) DEFAULT 0,
                        notes TEXT,
                        calculated_by INT,
                        calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
                        FOREIGN KEY (user_id) REFERENCES users(id),
                        FOREIGN KEY (calculated_by) REFERENCES users(id),
                        UNIQUE KEY uniq_period_user (payroll_period_id, user_id)
                    )
                ");
                break;
        }
    }

    // Migrate existing attendance data to new table
    echo "Checking for existing attendance data to migrate...\n";
    $result = $pdo->query("SHOW TABLES LIKE 'attendance'");
    if ($result->rowCount() > 0) {
        $count = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
        if ($count > 0) {
            echo "Migrating $count existing attendance records...\n";

            $stmt = $pdo->query("
                SELECT user_id, date, check_in, check_out, status, notes
                FROM attendance
                ORDER BY date, check_in
            ");

            $insert = $pdo->prepare("
                INSERT IGNORE INTO attendance_records
                (user_id, attendance_date, actual_check_in, actual_check_out, status, notes, total_hours)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $totalHours = 0;
                if ($row['check_in'] && $row['check_out']) {
                    $checkIn = strtotime($row['check_in']);
                    $checkOut = strtotime($row['check_out']);
                    $totalHours = round(($checkOut - $checkIn) / 3600, 2);
                }

                $insert->execute([
                    $row['user_id'],
                    $row['date'],
                    $row['check_in'],
                    $row['check_out'],
                    $row['status'],
                    $row['notes'],
                    $totalHours
                ]);
            }

            echo "Migration completed successfully.\n";
        }
    }

    // Insert default payroll settings for existing staff
    echo "Setting up default payroll configurations...\n";
    $staffRoles = ['doctor', 'staff', 'intern', 'trainee'];

    foreach ($staffRoles as $role) {
        // Get users by role
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            // Check if payroll record already exists
            $check = $pdo->prepare("SELECT id FROM employee_payroll WHERE user_id = ?");
            $check->execute([$user['id']]);
            if ($check->rowCount() == 0) {
                // Insert default payroll based on role
                $defaults = [
                    'doctor' => ['salary' => 500000, 'hourly' => 2500, 'hours' => 8, 'days' => 26],
                    'staff' => ['salary' => 150000, 'hourly' => 750, 'hours' => 8, 'days' => 26],
                    'intern' => ['salary' => 80000, 'hourly' => 400, 'hours' => 8, 'days' => 26],
                    'trainee' => ['salary' => 60000, 'hourly' => 300, 'hours' => 8, 'days' => 26]
                ];

                $config = $defaults[$role] ?? $defaults['staff'];

                $insert = $pdo->prepare("
                    INSERT INTO employee_payroll
                    (user_id, base_salary, hourly_rate, work_hours_per_day, work_days_per_month)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $user['id'],
                    $config['salary'],
                    $config['hourly'],
                    $config['hours'],
                    $config['days']
                ]);
            }
        }
    }

    echo "Migration completed successfully!\n";
    echo "Enhanced attendance and payroll system is now ready.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>