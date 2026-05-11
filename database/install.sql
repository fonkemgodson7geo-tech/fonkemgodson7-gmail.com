-- Clinic Management System Database Schema
-- Database: dondesionc_clinic

CREATE DATABASE IF NOT EXISTS dondesionc_clinic;
USE dondesionc_clinic;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('patient', 'doctor', 'admin', 'staff', 'intern', 'trainee') NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Patients table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    medical_record_number VARCHAR(20) UNIQUE,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    blood_type VARCHAR(5),
    allergies TEXT,
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Patient Groups
CREATE TABLE patient_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Patient Group Members
CREATE TABLE patient_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    group_id INT,
    added_by INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (group_id) REFERENCES patient_groups(id),
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Doctors table
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    availability JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    doctor_id INT,
    appointment_date DATETIME,
    service_type VARCHAR(100),
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Consultations
CREATE TABLE consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    doctor_id INT,
    patient_id INT,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Prescriptions
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT,
    medication VARCHAR(200),
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(50),
    instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id)
);

-- Lab Reports
CREATE TABLE lab_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    doctor_id INT,
    test_name VARCHAR(200),
    results TEXT,
    image_path VARCHAR(255),
    report_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Enhanced Attendance and Payroll System
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
);

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
);

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
);

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
);

-- Certificates
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    certificate_type VARCHAR(100),
    certificate_data TEXT,
    issued_by INT,
    issued_date DATE,
    expiry_date DATE,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- Verification Codes for 2FA
CREATE TABLE verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    code VARCHAR(10),
    type ENUM('sms', 'email'),
    expires_at DATETIME,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Payments
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    amount DECIMAL(10,2),
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Insert default admin user
INSERT INTO users (username, password, email, role, first_name, last_name) VALUES 
('fonkemgodson', '$2y$12$xd/DoVTXkuSTI0UzD7lfQuCjJXTOmxmsPyAM3UEEworY8R1TjUNMa', 'admin@clinic.com', 'admin', 'Admin', 'User');

-- Insert sample doctor
INSERT INTO users (username, password, email, role, first_name, last_name, phone) VALUES 
('dr.smith', '$2y$12$xd/DoVTXkuSTI0UzD7lfQuCjJXTOmxmsPyAM3UEEworY8R1TjUNMa', 'dr.smith@clinic.com', 'doctor', 'John', 'Smith', '+237600000001');

INSERT INTO doctors (user_id, specialization) VALUES (LAST_INSERT_ID(), 'General Medicine');

-- Pharmacy and Inventory Management
CREATE TABLE pharmacy_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medication_name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    dosage_form VARCHAR(100),
    strength VARCHAR(50),
    batch_number VARCHAR(100),
    expiry_date DATE,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2),
    supplier_id INT,
    location VARCHAR(100),
    min_stock_level INT DEFAULT 10,
    max_stock_level INT DEFAULT 1000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    license_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE prescriptions_fulfilled (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT,
    inventory_id INT,
    quantity_dispensed INT,
    dispensed_by INT,
    dispensed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
    FOREIGN KEY (dispensed_by) REFERENCES users(id)
);

CREATE TABLE pharmacy_stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    movement_type ENUM('add', 'adjust', 'dispense', 'return', 'wastage') NOT NULL,
    quantity_change INT NOT NULL,
    quantity_before INT NOT NULL,
    quantity_after INT NOT NULL,
    reason VARCHAR(255),
    reference_type VARCHAR(50),
    reference_id INT,
    performed_by INT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movement_inventory_created (inventory_id, created_at),
    FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

CREATE TABLE pharmacy_doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    added_by INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pharmacy_doctor (doctor_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Business Intelligence and Reporting
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(200) NOT NULL,
    report_type ENUM('financial', 'clinical', 'operational', 'regulatory') NOT NULL,
    parameters JSON,
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

CREATE TABLE analytics_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2),
    metric_date DATE,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Compliance and Audit
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(200) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE compliance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    regulation VARCHAR(100) NOT NULL,
    requirement VARCHAR(200),
    status ENUM('compliant', 'non_compliant', 'pending_review') DEFAULT 'pending_review',
    last_audit DATE,
    next_audit DATE,
    responsible_person INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_person) REFERENCES users(id)
);

-- Interoperability
CREATE TABLE external_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    system_name VARCHAR(100) NOT NULL,
    system_type ENUM('hl7', 'fhir', 'api', 'webhook') NOT NULL,
    endpoint_url VARCHAR(500),
    api_key VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_sync TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    integration_id INT,
    request_data JSON,
    response_data JSON,
    status_code INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (integration_id) REFERENCES external_integrations(id)
);

-- AI and Automation
CREATE TABLE ai_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    suggestion_type ENUM('diagnosis', 'treatment', 'medication') NOT NULL,
    suggestion_text TEXT,
    confidence_score DECIMAL(3,2),
    suggested_by VARCHAR(100),
    accepted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

CREATE TABLE automated_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(200) NOT NULL,
    task_type ENUM('reminder', 'alert', 'report', 'backup') NOT NULL,
    schedule_cron VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_run TIMESTAMP,
    next_run TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quality Assurance
CREATE TABLE quality_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,2),
    target_value DECIMAL(10,2),
    department VARCHAR(50),
    measured_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_name VARCHAR(200) NOT NULL,
    test_type ENUM('unit', 'integration', 'system', 'performance') NOT NULL,
    status ENUM('passed', 'failed', 'skipped') NOT NULL,
    execution_time DECIMAL(10,2),
    error_message TEXT,
    run_by INT,
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (run_by) REFERENCES users(id)
);

-- Internationalization
CREATE TABLE languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_code VARCHAR(10) NOT NULL,
    key_name VARCHAR(200) NOT NULL,
    translation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (language_code) REFERENCES languages(code),
    UNIQUE KEY unique_translation (language_code, key_name)
);

-- Working Timetable Management
CREATE TABLE shift_timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    worker_group VARCHAR(50) NOT NULL,
    shift_name VARCHAR(100) NOT NULL,
    shift_date DATE NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    generated_by INT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id),
    INDEX idx_shift_date_group (shift_date, worker_group),
    INDEX idx_user_group (user_id, worker_group)
);

-- Employee Evaluations
CREATE TABLE employee_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    evaluation_date DATE NOT NULL,
    evaluated_by INT NOT NULL,
    assiduity_rating DECIMAL(2,1) CHECK (assiduity_rating >= 1 AND assiduity_rating <= 5),
    punctuality_rating DECIMAL(2,1) CHECK (punctuality_rating >= 1 AND punctuality_rating <= 5),
    productivity_rating DECIMAL(2,1) CHECK (productivity_rating >= 1 AND productivity_rating <= 5),
    illness_days INT DEFAULT 0,
    permission_days INT DEFAULT 0,
    absence_days INT DEFAULT 0,
    sanctions TEXT,
    suspension BOOLEAN DEFAULT FALSE,
    query_letter TEXT,
    overall_rating DECIMAL(2,1),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (evaluated_by) REFERENCES users(id),
    UNIQUE KEY uniq_employee_date (employee_id, evaluation_date)
);