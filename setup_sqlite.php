<?php
// SQLite Database Setup Script
require_once 'config/config.php';

try {
    // Create database directory if it doesn't exist
    $dbDir = dirname(DB_FILE);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    // Connect to SQLite database
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    echo "✓ Connected to SQLite database successfully.\n";

    // Convert MySQL schema to SQLite
    $sqliteSchema = convertMySQLToSQLite();

    // Execute the schema
    $statements = array_filter(array_map('trim', explode(';', $sqliteSchema)));

    $tableCount = 0;
    foreach ($statements as $statement) {
        // Remove SQL single-line comments so comment-prefixed CREATE statements are not skipped.
        $statement = preg_replace('/^\s*--.*$/m', '', $statement);
        $statement = trim((string)$statement);
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE\s+(\w+)/i', $statement, $matches);
                if (isset($matches[1])) {
                    echo "✓ Created table: {$matches[1]}\n";
                    $tableCount++;
                }
            }
        } catch (Exception $e) {
            echo "⚠ Warning with statement: " . substr($statement, 0, 50) . "...\n";
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    echo "\n🎉 SQLite database setup completed successfully!\n";
    echo "📊 Total tables created: $tableCount\n";

    // Verify some key tables exist
    $tables = ['users', 'patients', 'doctors', 'appointments', 'pharmacy_inventory', 'reports', 'audit_logs'];
    echo "\n🔍 Verifying key tables:\n";

    foreach ($tables as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $result->fetchColumn();
            echo "✓ $table: OK ($count records)\n";
        } catch (Exception $e) {
            echo "✗ $table: Missing or error - " . $e->getMessage() . "\n";
        }
    }

    echo "\n🚀 Your clinic management system is ready to use!\n";
    echo "🌐 Access it at: http://localhost/your-project-path/index.php\n";

} catch (Exception $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

function convertMySQLToSQLite() {
    // SQLite-compatible schema
    return "
-- Users table
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('patient', 'doctor', 'admin', 'staff', 'intern', 'trainee', 'pharmacist', 'nurse', 'manager', 'compliance_officer', 'qa_tester', 'developer', 'translator')),
    first_name TEXT,
    last_name TEXT,
    phone TEXT,
    photo TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Patients table
CREATE TABLE patients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    medical_record_number TEXT UNIQUE,
    date_of_birth DATE,
    gender TEXT CHECK (gender IN ('male', 'female', 'other')),
    address TEXT,
    emergency_contact TEXT,
    emergency_phone TEXT,
    blood_type TEXT,
    allergies TEXT,
    medical_history TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Patient Groups
CREATE TABLE patient_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Patient Group Members
CREATE TABLE patient_group_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER,
    group_id INTEGER,
    added_by INTEGER,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (group_id) REFERENCES patient_groups(id),
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Doctors table
CREATE TABLE doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    specialization TEXT,
    license_number TEXT,
    availability TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments
CREATE TABLE appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER,
    doctor_id INTEGER,
    appointment_date DATETIME,
    service_type TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled')),
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Consultations
CREATE TABLE consultations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER,
    doctor_id INTEGER,
    patient_id INTEGER,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Prescriptions
CREATE TABLE prescriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    consultation_id INTEGER,
    medication TEXT,
    dosage TEXT,
    frequency TEXT,
    duration TEXT,
    instructions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id)
);

-- Lab Reports
CREATE TABLE lab_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER,
    doctor_id INTEGER,
    test_name TEXT,
    results TEXT,
    image_path TEXT,
    report_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Attendance
CREATE TABLE attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    check_in DATETIME,
    check_out DATETIME,
    date DATE,
    status TEXT DEFAULT 'present' CHECK (status IN ('present', 'absent', 'late')),
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Certificates
CREATE TABLE certificates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER,
    certificate_type TEXT,
    certificate_data TEXT,
    issued_by INTEGER,
    issued_date DATE,
    expiry_date DATE,
    file_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- Verification Codes for 2FA
CREATE TABLE verification_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    code TEXT,
    type TEXT CHECK (type IN ('sms', 'email')),
    expires_at DATETIME,
    used INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Payments
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER,
    amount REAL,
    payment_method TEXT,
    transaction_id TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed')),
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Pharmacy and Inventory Management
CREATE TABLE pharmacy_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    medication_name TEXT NOT NULL,
    generic_name TEXT,
    dosage_form TEXT,
    strength TEXT,
    batch_number TEXT,
    expiry_date DATE,
    quantity INTEGER NOT NULL,
    unit_price REAL,
    supplier_id INTEGER,
    location TEXT,
    min_stock_level INTEGER DEFAULT 10,
    max_stock_level INTEGER DEFAULT 1000,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    license_number TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE prescriptions_fulfilled (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prescription_id INTEGER,
    inventory_id INTEGER,
    quantity_dispensed INTEGER,
    dispensed_by INTEGER,
    dispensed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
    FOREIGN KEY (dispensed_by) REFERENCES users(id)
);

CREATE TABLE pharmacy_sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prescription_id INTEGER,
    patient_id INTEGER,
    inventory_id INTEGER,
    quantity_sold INTEGER NOT NULL,
    unit_price REAL NOT NULL DEFAULT 0,
    total_amount REAL NOT NULL DEFAULT 0,
    payment_status TEXT NOT NULL DEFAULT 'unpaid' CHECK (payment_status IN ('paid', 'unpaid', 'partial')),
    has_debt INTEGER NOT NULL DEFAULT 1,
    sold_by INTEGER,
    sold_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
    FOREIGN KEY (sold_by) REFERENCES users(id)
);

CREATE TABLE shift_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type TEXT NOT NULL CHECK (event_type IN ('sign_in', 'sign_out', 'shift_change', 'shift_swap')),
    event_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    shift_date DATE,
    partner_user_id INTEGER,
    note TEXT,
    status TEXT DEFAULT 'recorded' CHECK (status IN ('recorded', 'requested', 'approved', 'rejected')),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (partner_user_id) REFERENCES users(id)
);

-- Business Intelligence and Reporting
CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_name TEXT NOT NULL,
    report_type TEXT NOT NULL CHECK (report_type IN ('financial', 'clinical', 'operational', 'regulatory', 'patient_summary', 'appointment_report', 'revenue_report', 'inventory_report')),
    parameters TEXT,
    generated_by INTEGER,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    file_path TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed')),
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

CREATE TABLE analytics_data (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    metric_name TEXT NOT NULL,
    metric_value REAL,
    metric_date DATE,
    category TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Compliance and Audit
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    table_name TEXT,
    record_id INTEGER,
    old_values TEXT,
    new_values TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE compliance_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    regulation TEXT NOT NULL,
    requirement TEXT,
    status TEXT DEFAULT 'pending_review' CHECK (status IN ('compliant', 'non_compliant', 'pending_review')),
    last_audit DATE,
    next_audit DATE,
    responsible_person INTEGER,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_person) REFERENCES users(id)
);

-- Interoperability
CREATE TABLE external_integrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_name TEXT NOT NULL,
    system_type TEXT NOT NULL CHECK (system_type IN ('hl7', 'fhir', 'api', 'webhook')),
    endpoint_url TEXT,
    api_key TEXT,
    is_active INTEGER DEFAULT 1,
    last_sync DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE api_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    integration_id INTEGER,
    request_data TEXT,
    response_data TEXT,
    status_code INTEGER,
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (integration_id) REFERENCES external_integrations(id)
);

-- AI and Automation
CREATE TABLE ai_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER,
    doctor_id INTEGER,
    suggestion_type TEXT NOT NULL CHECK (suggestion_type IN ('diagnosis', 'treatment', 'followup', 'prevention')),
    context_data TEXT,
    suggestion_text TEXT,
    confidence_score REAL,
    applied_at DATETIME,
    dismissed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

CREATE TABLE automated_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_name TEXT NOT NULL,
    task_type TEXT NOT NULL CHECK (task_type IN ('reminder', 'alert', 'report', 'backup')),
    schedule_cron TEXT,
    is_active INTEGER DEFAULT 1,
    last_run DATETIME,
    next_run DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Quality Assurance
CREATE TABLE quality_metrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    metric_name TEXT NOT NULL,
    metric_value REAL,
    target_value REAL,
    department TEXT,
    measured_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE test_cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    test_type TEXT NOT NULL CHECK (test_type IN ('unit', 'integration', 'functional', 'security', 'performance')),
    priority TEXT NOT NULL CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'passed', 'failed')),
    created_by INTEGER,
    assigned_to INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

CREATE TABLE test_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    test_name TEXT NOT NULL,
    test_type TEXT NOT NULL CHECK (test_type IN ('unit', 'integration', 'system', 'performance', 'security', 'database', 'api')),
    status TEXT NOT NULL CHECK (status IN ('passed', 'failed', 'skipped', 'warning')),
    duration_ms REAL,
    results TEXT,
    error_message TEXT,
    run_by INTEGER,
    run_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (run_by) REFERENCES users(id)
);

-- Internationalization
CREATE TABLE languages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    language_code TEXT NOT NULL,
    translation_key TEXT NOT NULL,
    translation_text TEXT,
    created_by INTEGER,
    updated_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (language_code) REFERENCES languages(code),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- API Keys
CREATE TABLE api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    api_key TEXT UNIQUE NOT NULL,
    name TEXT,
    created_by INTEGER,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'revoked')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Regulatory Requirements
CREATE TABLE regulatory_requirements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requirement_name TEXT NOT NULL,
    regulation_type TEXT NOT NULL,
    description TEXT,
    due_date DATE,
    priority TEXT CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed', 'overdue')),
    assigned_to INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Compliance Issues
CREATE TABLE compliance_issues (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    severity TEXT CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    status TEXT DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')),
    assigned_to INTEGER,
    reported_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

-- Compliance Audits
CREATE TABLE compliance_audits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_name TEXT NOT NULL,
    audit_type TEXT,
    status TEXT DEFAULT 'planned' CHECK (status IN ('planned', 'in_progress', 'completed', 'failed')),
    start_date DATE,
    end_date DATE,
    auditor INTEGER,
    findings TEXT,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auditor) REFERENCES users(id)
);

-- Services
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    category TEXT,
    duration_minutes INTEGER,
    price REAL,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Medications (expanded)
CREATE TABLE medications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    generic_name TEXT,
    category_id INTEGER,
    dosage_form TEXT,
    strength TEXT,
    manufacturer TEXT,
    batch_number TEXT,
    expiry_date DATE,
    stock_quantity INTEGER DEFAULT 0,
    unit_price REAL,
    supplier_id INTEGER,
    min_stock_level INTEGER DEFAULT 10,
    max_stock_level INTEGER DEFAULT 1000,
    requires_prescription INTEGER DEFAULT 1,
    side_effects TEXT,
    contraindications TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (category_id) REFERENCES medication_categories(id)
);

CREATE TABLE medication_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, password, email, role, first_name, last_name) VALUES
('admie', '\$2y\$12\$xd/DoVTXkuSTI0UzD7lfQuCjJXTOmxmsPyAM3UEEworY8R1TjUNMa', 'admin@clinic.com', 'admin', 'Admin', 'User');

-- Insert sample doctor
INSERT INTO users (username, password, email, role, first_name, last_name, phone) VALUES
('dr.smith', '\$2y\$12\$xd/DoVTXkuSTI0UzD7lfQuCjJXTOmxmsPyAM3UEEworY8R1TjUNMa', 'dr.smith@clinic.com', 'doctor', 'John', 'Smith', '+237600000001');

INSERT INTO doctors (user_id, specialization) VALUES (2, 'General Medicine');

-- Insert default language
INSERT INTO languages (code, name) VALUES ('en', 'English');

-- Insert sample API key
INSERT INTO api_keys (api_key, name, created_by) VALUES ('test-api-key-123', 'Development API Key', 1);
";
}
?>