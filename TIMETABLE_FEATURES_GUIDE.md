# Timetable System - Enhanced Features Documentation

## Overview
The timetable management system has been enhanced with three major features:
1. **PDF Export** - Convert timetables to professional PDF documents
2. **Automatic Portal Distribution** - Auto-distribute timetables to all staff portals
3. **Enhanced Visual Department Distinction** - Better visual separation and clarity for different departments

---

## Feature 1: PDF Export

### Purpose
Generate professional PDF documents of timetables for printing, archiving, and offline distribution.

### How to Use
1. Go to **Admin Panel → Staff Timetable Management**
2. Select the **Department**, **Month**, and **Year**
3. Click **"Generate Timetable"** to create the scheduling
4. Click the **"Export PDF"** button (red button with PDF icon)
5. The PDF will automatically download to your computer

### PDF Features
- **Professional Layout**: Landscape orientation optimized for staff viewing
- **Color-Coded Shifts**: All shift types are color-coded for quick identification
- **Legend Included**: Complete shift code legend printed on every PDF
- **Department Header**: Clear identification of department and time period
- **Timestamp**: Document generation timestamp for version control
- **Optimal for Printing**: Clean layout with proper margins and readability

### PDF Content
- Header with department name and period
- Complete shift legend with all color codes
- Full month timetable with all staff members
- Color-coded shift cells for easy scanning
- Footer with disclaimer (confidential document)

### File Naming Convention
Files are automatically saved as: `{department}_timetable_{year}_{month}.pdf`

Example: `cmds_staff_timetable_2026_05.pdf`

---

## Feature 2: Automatic Portal Distribution

### Purpose
Seamlessly distribute generated timetables to all staff portals without manual copying or email forwarding.

### Supported Portals
The timetable is automatically distributed to:
- ✓ **Doctor Portal** - Doctors can view their schedules
- ✓ **Staff Portal** - General staff access
- ✓ **Admin Portal** - Administrative staff and supervisors
- ✓ **Intern Portal** - Interns view their assignments
- ✓ **Trainee Portal** - Trainee access to schedules
- ✗ **Patient Portal** - Excluded for privacy/security (patient portal does NOT receive timetables)

### How to Use
1. Go to **Admin Panel → Staff Timetable Management**
2. Select the **Department**, **Month**, and **Year**
3. Click **"Generate Timetable"** to create the scheduling
4. Click the green **"Distribute to Portals"** button
5. Wait for the confirmation message
6. Success notification will show which portals received the update

### What Happens During Distribution
1. **PDF Generation**: System automatically generates a PDF version
2. **Data Packaging**: Timetable data is packaged in JSON format
3. **Portal Updates**: All selected portals are updated simultaneously
4. **Database Logging**: Distribution event is recorded for audit purposes
5. **Confirmation**: User receives success/failure notification

### Behind the Scenes
- Timetable data is saved to: `/uploads/timetables/{department}_{month}_{year}.json`
- Distribution is logged in the `timetable_distributions` table
- Each portal checks for new timetables when staff access their profile
- Updates are atomic - either all portals receive or none do (transactional)

### Distribution Data Structure
Each distributed timetable includes:
```json
{
  "department": "cmds_staff",
  "month": 5,
  "year": 2026,
  "distributed_at": "2026-05-13T15:30:45",
  "data": [
    {
      "id": 1,
      "user_id": 0,
      "worker_group": "cmds_staff",
      "shift_name": "M",
      "shift_date": "2026-05-01",
      "start_at": "2026-05-01 08:00:00",
      "end_at": "2026-05-01 14:00:00",
      "generated_by": 1,
      "note": "Abeng"
    }
    // ... more shifts
  ]
}
```

---

## Feature 3: Enhanced Visual Department Distinction

### Visual Improvements Made

#### Color-Coded Shift Displays
- **Morning (M)**: Light Blue (#ADD8E6)
- **Afternoon (A)**: Light Green (#90EE90)
- **Night (N)**: Dark Blue (#00008B) with white text
- **Full Day (J)**: Orange (#FFA500)
- **Guard (G)**: Dark Navy (#191970) with white text
- **Rest (R)**: Light Gray (#D3D3D3)
- **Leave (REPOS)**: Crimson Red (#DC143C) with white text
- **Extended Hours (8h-22h)**: Gold (#FFD700)
- **Extended Night (22h-8h)**: Purple (#800080) with white text

#### Header Enhancements
- **Department Badge**: Visual badge showing selected department
- **Department Icon**: People icon next to department name
- **Gradient Header**: Enhanced gradient background for better visual hierarchy
- **Status Indicators**: Clear visual separation of selected department

#### Table Styling
- **Highlighted Headers**: Dark themed table headers with white text
- **Hover Effects**: Rows highlight on hover for better navigation
- **Cell Shadows**: Subtle shadows on shift cells for depth
- **Bold Text**: Shift codes displayed in bold for readability
- **Proper Spacing**: Improved padding and margins for clarity

#### Legend Display
- **Category Organization**: Grouped shift codes with descriptions
- **Color Blocks**: Large enough color samples for accurate matching
- **Descriptions**: French and English shift descriptions
- **Visual Boxes**: Each legend item in its own visual box with border

#### Print-Friendly
- Controls and headers hide when printing
- Timetable formats optimally for paper
- Colors maintained for printed PDFs
- Legend included for reference

### Department-Specific Display
When a department is selected:
1. The header shows the department name in a styled badge
2. The table displays only that department's staff
3. Color coding helps distinguish shift types quickly
4. The legend always shows available shift codes for that department

### Mobile Responsive Design
- Timetable scrolls horizontally on smaller screens
- Buttons stack properly on mobile devices
- Legend items wrap on narrow screens
- Header remains readable on all sizes

---

## Technical Implementation

### New API Endpoints

#### 1. PDF Generation Endpoint
**URL**: `/api/timetable-pdf.php`
**Method**: GET
**Parameters**:
- `month` (int): 1-12
- `year` (int): 2024-2030
- `department` (string): Department code

**Response**: PDF file download

**Example**: 
```
GET /api/timetable-pdf.php?month=5&year=2026&department=cmds_staff
```

#### 2. Portal Distribution Endpoint
**URL**: `/api/distribute-timetable.php`
**Method**: POST
**Parameters**:
- `month` (int): 1-12
- `year` (int): 2024-2030
- `department` (string): Department code

**Response**:
```json
{
  "success": true,
  "message": "Timetable successfully distributed to all portals",
  "distribution": {
    "department": "cmds_staff",
    "period": "May 2026",
    "file": "cmds_staff_2026_05.json",
    "portals_updated": {
      "doctor_portal": true,
      "staff_portal": true,
      "admin_portal": true,
      "intern_portal": true,
      "trainee_portal": true
    },
    "timestamp": "2026-05-13 15:30:45"
  }
}
```

### Database Changes

#### New Table: `timetable_distributions`
Tracks all timetable distribution events:
```sql
CREATE TABLE timetable_distributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    department TEXT NOT NULL,
    month_year TEXT NOT NULL,
    distributed_by INTEGER,
    distributed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pdf_generated INTEGER DEFAULT 0,
    sent_to_doctor INTEGER DEFAULT 0,
    sent_to_staff INTEGER DEFAULT 0,
    sent_to_admin INTEGER DEFAULT 0,
    sent_to_intern INTEGER DEFAULT 0,
    sent_to_trainee INTEGER DEFAULT 0
);
```

### Files Modified/Created

**New Files**:
- `/api/timetable-pdf.php` - PDF generation engine
- `/api/distribute-timetable.php` - Portal distribution API

**Modified Files**:
- `/admin/timetable.php` - Added buttons, enhanced styling, JavaScript functions

### Dependencies
- **DOMPDF**: For PDF generation (already in composer.json)
- **Bootstrap 5**: For UI components
- **Bootstrap Icons**: For button icons

---

## User Workflow Examples

### Example 1: Generate and Export Monthly Schedule
```
1. Login to Admin Panel
2. Navigate to "Staff Timetable Management"
3. Select Department: "CMDS Staff"
4. Select Month: "May"
5. Select Year: "2026"
6. Click "Generate Timetable"
7. Click "Export PDF"
8. Save file: cmds_staff_timetable_2026_05.pdf
9. Print or send to staff
```

### Example 2: Auto-Distribute New Schedule
```
1. Complete steps 1-6 from Example 1
2. Click "Distribute to Portals"
3. System automatically:
   - Generates PDF
   - Packages data as JSON
   - Updates all staff portals
   - Creates database record
4. View confirmation message
5. All staff can now see new schedule in their portal
```

### Example 3: Multi-Department Distribution
```
1. Generate schedule for "Doctors" department
2. Click "Distribute to Portals"
3. Generate schedule for "Pharmacy" department
4. Click "Distribute to Portals"
5. Generate schedule for "Interns" department
6. Click "Distribute to Portals"
7. Each portal displays all department schedules
```

---

## Security & Privacy Considerations

### Access Control
- Only authenticated admin users can access PDF generation and distribution
- Session verification required for all API calls
- CSRF token validation on distribution requests

### Data Privacy
- Patient portal is **explicitly excluded** from distribution
- Timetables contain sensitive staff scheduling information
- Distribution is logged for audit trails
- Patient privacy is protected from schedule access

### File Storage
- JSON files stored in `/uploads/timetables/` with restricted access
- Filenames follow predictable pattern for easy retrieval
- Old files can be archived or deleted as needed
- Consider implementing file retention policy

### Audit Trail
- Every distribution is logged with:
  - Department name
  - Time period
  - Distribution timestamp
  - Admin user who initiated
  - Status of each portal update

---

## Troubleshooting

### PDF Not Generating
**Possible Causes**:
1. No timetable generated for selected month
2. DOMPDF library not properly installed
3. Missing required PHP extensions

**Solution**:
```bash
# Verify DOMPDF installation
composer install
composer require dompdf/dompdf:^2.0
```

### Distribution Fails
**Possible Causes**:
1. Database permissions issue
2. `/uploads/timetables/` directory not writable
3. API session timeout

**Solution**:
```bash
# Check directory permissions
chmod 755 uploads/timetables/
chmod 644 uploads/timetables/*
```

### Portals Not Showing New Schedule
**Possible Causes**:
1. Portal page caching issue
2. Session not refreshed
3. Distribution API call failed

**Solution**:
- Clear browser cache
- Log out and log back in
- Check browser console for API errors
- Verify distribution API response

---

## Future Enhancement Ideas

1. **Email Notifications**: Send notification emails when timetable is distributed
2. **SMS Alerts**: Text message alerts for critical schedule changes
3. **Bulk Export**: Export multiple departments' timetables at once
4. **Schedule Comparison**: Compare timetables across departments
5. **Mobile App**: Native mobile app for schedule viewing
6. **Export Formats**: Excel, Google Sheets, iCal integration
7. **Change Tracking**: Highlight what changed from previous month
8. **Conflict Detection**: Automatic alerts for scheduling conflicts
9. **Staff Preferences**: Allow staff to indicate shift preferences
10. **Notification History**: Track when each staff member viewed their schedule

---

## Support & Documentation

For technical support or questions:
1. Check error logs in `/logs/` directory
2. Review database schema in `database/install.sql`
3. Contact system administrator
4. Submit bug reports with detailed information

Last Updated: May 13, 2026
System Version: 4.0
