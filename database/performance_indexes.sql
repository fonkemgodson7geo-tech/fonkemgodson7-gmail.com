-- Performance index migration for AWCD
-- Safe to run multiple times in SQLite/MySQL 8+ because of IF NOT EXISTS.

CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);

CREATE INDEX IF NOT EXISTS idx_patients_user_id ON patients(user_id);

CREATE INDEX IF NOT EXISTS idx_appointments_doctor_date_status ON appointments(doctor_id, appointment_date, status);
CREATE INDEX IF NOT EXISTS idx_appointments_patient_date ON appointments(patient_id, appointment_date);
CREATE INDEX IF NOT EXISTS idx_appointments_status_date ON appointments(status, appointment_date);

CREATE INDEX IF NOT EXISTS idx_consultations_doctor_created ON consultations(doctor_id, created_at);
CREATE INDEX IF NOT EXISTS idx_consultations_patient_created ON consultations(patient_id, created_at);
CREATE INDEX IF NOT EXISTS idx_consultations_appointment_id ON consultations(appointment_id);

CREATE INDEX IF NOT EXISTS idx_prescriptions_consultation_id ON prescriptions(consultation_id);
CREATE INDEX IF NOT EXISTS idx_prescriptions_created_at ON prescriptions(created_at);

CREATE INDEX IF NOT EXISTS idx_lab_reports_patient_date ON lab_reports(patient_id, report_date);
CREATE INDEX IF NOT EXISTS idx_lab_reports_doctor_date ON lab_reports(doctor_id, report_date);

CREATE INDEX IF NOT EXISTS idx_payments_patient_date ON payments(patient_id, payment_date);
CREATE INDEX IF NOT EXISTS idx_payments_status_date ON payments(status, payment_date);

CREATE INDEX IF NOT EXISTS idx_reports_generated_by_date ON reports(generated_by, generated_at);
CREATE INDEX IF NOT EXISTS idx_reports_status_date ON reports(status, generated_at);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user_date ON audit_logs(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action_date ON audit_logs(action, created_at);

CREATE INDEX IF NOT EXISTS idx_patient_group_members_group_patient ON patient_group_members(group_id, patient_id);
CREATE INDEX IF NOT EXISTS idx_patient_group_members_patient_group ON patient_group_members(patient_id, group_id);

CREATE INDEX IF NOT EXISTS idx_pharmacy_inventory_quantity ON pharmacy_inventory(quantity);
CREATE INDEX IF NOT EXISTS idx_pharmacy_inventory_expiry_date ON pharmacy_inventory(expiry_date);

CREATE INDEX IF NOT EXISTS idx_prescriptions_fulfilled_prescription ON prescriptions_fulfilled(prescription_id);
CREATE INDEX IF NOT EXISTS idx_prescriptions_fulfilled_dispensed_at ON prescriptions_fulfilled(dispensed_at);
