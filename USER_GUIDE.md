# AWCD User Training Guide

## Centre Médical Dons de Soins - User Manual

### System Overview
The AWCD (Automated Workflow & Compliance Dashboard) is a comprehensive healthcare management system designed for medical centers. It supports role-based access for administrators, doctors, patients, staff, interns, trainees, and pharmacists.

### Getting Started

#### 1. Access the System
- **URL**: https://www.cmdonsdesoins.com
- **Alternative**: https://awcd.onrender.com
- **Health Check**: Visit `/api/health.php` to verify system status

#### 2. Login Credentials
- **Admin**: Username: `admie`, Password: `dds_awc2018`
- **Doctor**: Login via doctor portal
- **Patient**: Login via patient portal
- **Staff**: Login via staff portal

### User Roles & Permissions

#### Administrator
- **Dashboard**: System overview, user management, compliance monitoring
- **Timetable Management**: Create and distribute staff schedules
- **User Management**: Add/edit users, manage roles
- **Reports**: Access all system reports and analytics
- **Compliance**: Audit logs, security monitoring

#### Doctor
- **Dashboard**: Patient appointments, medical records
- **Patient Management**: View/edit patient records
- **Appointments**: Schedule and manage consultations
- **Reports**: Generate medical reports

#### Patient
- **Dashboard**: Personal health information
- **Appointments**: Book and view appointments
- **Payments**: Make payments for services
- **Medical Records**: View personal health history

#### Staff/Intern/Trainee
- **Dashboard**: Assigned tasks and schedule
- **Timetable**: View work schedule
- **Reports**: Submit daily reports

### Key Features

#### 1. Timetable Management
- **Auto-Generate**: AI-powered schedule creation
- **Manual Assignment**: Drag-and-drop shift assignment
- **Distribution**: Automatic notification to all portals
- **Export**: PDF and CSV export options

#### 2. Patient Management
- **Registration**: New patient onboarding
- **Records**: Comprehensive medical history
- **Appointments**: Scheduling system
- **Payments**: Integrated payment processing

#### 3. Compliance & Security
- **Audit Logs**: All system activities tracked
- **Role-Based Access**: Strict permission controls
- **CSRF Protection**: Secure form submissions
- **Data Encryption**: Sensitive data protection

#### 4. Communication
- **Public Announcements**: Hospital-wide communications
- **Notifications**: Automated alerts
- **Multi-language**: English/French support

### Common Tasks

#### For Administrators
1. **Add New User**:
   - Go to Admin → Manage Users
   - Click "Add User"
   - Fill required fields and assign role

2. **Create Timetable**:
   - Go to Admin → Timetable
   - Select month and department
   - Click "Auto Generate" or assign manually
   - Click "Distribute to Portals"

3. **View Reports**:
   - Go to Admin → Reports
   - Select report type and date range
   - Export as needed

#### For Doctors
1. **View Appointments**:
   - Go to Doctor → Appointments
   - Review today's schedule

2. **Update Patient Record**:
   - Search for patient
   - Update medical information
   - Save changes

#### For Patients
1. **Book Appointment**:
   - Go to Patient → Book Appointment
   - Select doctor and time
   - Confirm booking

2. **View Medical Records**:
   - Go to Patient → Medical Records
   - Review history and reports

### Troubleshooting

#### Login Issues
- Verify username and password
- Check if account is active
- Contact administrator if locked out

#### System Slow/Unresponsive
- Check health status at `/api/health.php`
- Clear browser cache
- Try different browser

#### Permission Errors
- Verify your role has required permissions
- Contact administrator for role changes

### Support
- **Technical Support**: Contact system administrator
- **Emergency**: Use hospital emergency contacts
- **Documentation**: This guide and inline help

### Security Best Practices
- Never share login credentials
- Log out after use
- Report suspicious activity
- Use strong passwords
- Enable two-factor authentication when available

---

**System Version**: AWCD v2.0
**Last Updated**: May 13, 2026
**Contact**: admin@cmdonsdesoins.com</content>
<parameter name="filePath">c:\Users\TECHWAVE\Desktop\AWCD\USER_GUIDE.md