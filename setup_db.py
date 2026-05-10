import sqlite3
import os

db_path = r'C:\Users\DELL\Desktop\AWCD\database\clinic.db'

# Ensure directory exists
os.makedirs(os.path.dirname(db_path), exist_ok=True)

# Connect to SQLite
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Enable foreign keys
cursor.execute('PRAGMA foreign_keys = ON')

# Schema
schema = '''
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Pharmacy Inventory
CREATE TABLE pharmacy_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    drug_name TEXT NOT NULL,
    generic_name TEXT,
    barcode TEXT UNIQUE,
    category TEXT,
    description TEXT,
    unit_price REAL,
    selling_price REAL,
    stock_quantity INTEGER DEFAULT 0,
    min_stock_level INTEGER DEFAULT 10,
    expiry_date DATE,
    batch_number TEXT,
    supplier TEXT,
    location TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pharmacy Stock Movements
CREATE TABLE pharmacy_stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inventory_id INTEGER,
    movement_type TEXT CHECK (movement_type IN ('add', 'dispense', 'adjust', 'return')),
    quantity INTEGER,
    reference_type TEXT,
    reference_id INTEGER,
    notes TEXT,
    performed_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- Reports
CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    type TEXT,
    content TEXT,
    generated_by INTEGER,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

-- Audit Logs
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT,
    table_name TEXT,
    record_id INTEGER,
    old_values TEXT,
    new_values TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Shifts and Timetables
CREATE TABLE shifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    start_time TIME,
    end_time TIME,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Shift Assignments
CREATE TABLE shift_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    shift_id INTEGER,
    date DATE,
    status TEXT DEFAULT 'assigned' CHECK (status IN ('assigned', 'completed', 'cancelled')),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (shift_id) REFERENCES shifts(id)
);

-- Attendance Records
CREATE TABLE attendance_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    attendance_date DATE,
    scheduled_start_time TIME,
    scheduled_end_time TIME,
    actual_check_in DATETIME,
    actual_check_out DATETIME,
    break_start_time TIME,
    break_end_time TIME,
    total_hours REAL,
    regular_hours REAL,
    overtime_hours REAL,
    late_minutes INTEGER DEFAULT 0,
    early_departure_minutes INTEGER DEFAULT 0,
    status TEXT DEFAULT 'present' CHECK (status IN ('present', 'absent', 'late', 'half-day')),
    notes TEXT,
    approved_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, attendance_date),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Payroll Periods
CREATE TABLE payroll_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_name TEXT NOT NULL,
    start_date DATE,
    end_date DATE,
    status TEXT DEFAULT 'open' CHECK (status IN ('open', 'processing', 'closed')),
    processed_by INTEGER,
    processed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Employee Payroll Configuration
CREATE TABLE employee_payroll (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE,
    base_salary REAL,
    hourly_rate REAL,
    work_hours_per_day REAL DEFAULT 8.0,
    work_days_per_month INTEGER DEFAULT 26,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Payroll Calculations
CREATE TABLE payroll_calculations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payroll_period_id INTEGER,
    user_id INTEGER,
    base_salary REAL,
    overtime_pay REAL DEFAULT 0,
    late_deductions REAL DEFAULT 0,
    other_deductions REAL DEFAULT 0,
    other_allowances REAL DEFAULT 0,
    gross_pay REAL,
    net_pay REAL,
    present_days INTEGER,
    absent_days INTEGER,
    late_days INTEGER,
    overtime_hours REAL,
    punctuality_rating REAL,
    notes TEXT,
    calculated_by INTEGER,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (calculated_by) REFERENCES users(id)
);

-- Indexes for performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_patients_user_id ON patients(user_id);
CREATE INDEX idx_appointments_patient_id ON appointments(patient_id);
CREATE INDEX idx_appointments_doctor_id ON appointments(doctor_id);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_pharmacy_inventory_barcode ON pharmacy_inventory(barcode);
CREATE INDEX idx_pharmacy_stock_movements_inventory_id ON pharmacy_stock_movements(inventory_id);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_shift_assignments_user_id ON shift_assignments(user_id);
CREATE INDEX idx_shift_assignments_date ON shift_assignments(date);
CREATE INDEX idx_attendance_records_user_id ON attendance_records(user_id);
CREATE INDEX idx_attendance_records_date ON attendance_records(attendance_date);
CREATE INDEX idx_payroll_calculations_period_id ON payroll_calculations(payroll_period_id);
CREATE INDEX idx_payroll_calculations_user_id ON payroll_calculations(user_id);
'''

# Execute the schema
statements = [stmt.strip() for stmt in schema.split(';') if stmt.strip() and not stmt.strip().startswith('--')]

table_count = 0
for statement in statements:
    try:
        cursor.execute(statement)
        if 'CREATE TABLE' in statement.upper():
            table_name = statement.split('CREATE TABLE')[1].split('(')[0].strip()
            print(f'✓ Created table: {table_name}')
            table_count += 1
    except Exception as e:
        print(f'⚠ Warning with statement: {statement[:50]}...')
        print(f'   Error: {e}')

conn.commit()
conn.close()

print(f'\n🎉 SQLite database setup completed successfully!')
print(f'📊 Total tables created: {table_count}')