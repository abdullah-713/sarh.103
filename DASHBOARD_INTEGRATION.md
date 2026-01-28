# Dashboard Integration Guide - دليل تكامل لوحة التحكم

## Overview

This guide explains how the AttendanceService is integrated into the Main Dashboard with SweetAlert warnings for IP mismatch failures and proper logging to `activity_log` for Integrity Score updates.

## Integration Components

### 1. Dashboard (`dashboard.php`)

The main dashboard includes:
- **Check-in/Check-out buttons** - Integrated with AttendanceService
- **Real-time status display** - Shows today's attendance status
- **IP verification status** - Displays current IP and verification status
- **Statistics** - Monthly attendance statistics
- **Recent attendance history** - Last 5 attendance records

### 2. API Endpoint (`attendance_api.php`)

The API endpoint uses `AttendanceService::checkIn()` which:
- Calls `IPVerification::verify()` to validate IP
- Automatically logs failed attempts to `activity_log`
- Returns detailed error information for SweetAlert display

### 3. AttendanceService (`AttendanceService.php`)

The service automatically:
- Validates IP using `IPVerification::verify()`
- Logs all verification attempts to `activity_log`
- Logs failed IP attempts using `IntegrityLogger`
- Feeds the Psychological Profile system

## Flow Diagram

```
User clicks Check-in Button
    ↓
JavaScript calls attendance_api.php?action=checkin
    ↓
API calls AttendanceService::checkIn($user_id)
    ↓
AttendanceService calls IPVerification::verify($user_id)
    ↓
[If IP Invalid]
    ↓
IntegrityLogger::logFailedIPAttempt() logs to:
    - activity_log (action: 'integrity.unauthorized_ip_attempt')
    - integrity_logs (if exists)
    ↓
Returns error with IP details
    ↓
Dashboard shows SweetAlert warning
```

## SweetAlert Integration

### Success Alert

When check-in succeeds:
```javascript
Swal.fire({
    icon: 'success',
    title: 'تم بنجاح!',
    text: data.message,
    html: `
        <p>تم تسجيل الحضور بنجاح</p>
        <p><strong>وقت الحضور:</strong> ${data.data.check_in_time}</p>
        <p><strong>عنوان IP:</strong> ${data.data.ip_address}</p>
    `,
    confirmButtonText: 'حسناً'
});
```

### IP Mismatch Warning

When IP verification fails:
```javascript
Swal.fire({
    icon: 'warning',
    title: 'تحذير: IP غير مسموح به',
    html: `
        <p>${data.message}</p>
        <div class="alert alert-danger mt-3">
            <p><strong>عنوان IP الحالي:</strong> ${data.ip_address}</p>
            <p><strong>عنوان IP المطلوب:</strong> ${data.expected_ip}</p>
        </div>
        <p class="text-muted small mt-3">
            <i class="bi bi-info-circle"></i> تم تسجيل هذه المحاولة في سجل النشاطات 
            وسيتم تحديث درجة النزاهة الخاصة بك.
        </p>
    `,
    confirmButtonText: 'فهمت',
    confirmButtonColor: '#dc3545'
});
```

## Activity Log Structure

### Failed IP Attempt Log

When a user attempts check-in with invalid IP, the following is logged to `activity_log`:

```json
{
    "user_id": 1,
    "action": "integrity.unauthorized_ip_attempt",
    "model_type": "attendance",
    "model_id": null,
    "old_values": {
        "user_role": "employee",
        "branch_id": 1
    },
    "new_values": {
        "action_type": "unauthorized_ip_attempt",
        "severity": "medium",
        "ip_address": "192.168.1.200",
        "expected_ip": "192.168.1.100",
        "branch_id": 1,
        "user_role": "employee",
        "message": "عنوان IP غير مسموح به. IP الحالي: 192.168.1.200، IP المطلوب: 192.168.1.100",
        "timestamp": "2026-01-28 10:30:00"
    },
    "ip_address": "192.168.1.200",
    "user_agent": "Mozilla/5.0...",
    "created_at": "2026-01-28 10:30:00"
}
```

## Integrity Score Update

The failed IP attempts are automatically processed by the Psychological Profile system:

1. **Activity Log Entry** - Created with action `integrity.unauthorized_ip_attempt`
2. **Integrity Logs** - Also logged to `integrity_logs` table (if exists)
3. **Profile Update** - The stored procedure `sp_update_psychological_profile` processes these logs
4. **Score Impact** - Integrity score decreases based on failed attempts

### Query Failed Attempts

```sql
-- Get all failed IP attempts for a user
SELECT 
    al.*,
    JSON_EXTRACT(al.new_values, '$.ip_address') as failed_ip,
    JSON_EXTRACT(al.new_values, '$.expected_ip') as expected_ip,
    JSON_EXTRACT(al.new_values, '$.severity') as severity
FROM activity_log al
WHERE al.user_id = ?
AND al.action = 'integrity.unauthorized_ip_attempt'
ORDER BY al.created_at DESC;
```

## Testing

### Test Successful Check-in

1. Ensure user's IP matches branch `authorized_ip`
2. Click "تسجيل الحضور" button
3. Should see success alert
4. Check `activity_log` for `attendance.checkin` entry

### Test Failed IP Attempt

1. Change user's IP or branch `authorized_ip` to mismatch
2. Click "تسجيل الحضور" button
3. Should see SweetAlert warning with IP details
4. Check `activity_log` for `integrity.unauthorized_ip_attempt` entry
5. Verify Integrity Score is updated

### Verify Logging

```sql
-- Check recent failed attempts
SELECT 
    al.id,
    al.user_id,
    al.action,
    al.created_at,
    JSON_EXTRACT(al.new_values, '$.ip_address') as failed_ip,
    JSON_EXTRACT(al.new_values, '$.expected_ip') as expected_ip
FROM activity_log al
WHERE al.action = 'integrity.unauthorized_ip_attempt'
ORDER BY al.created_at DESC
LIMIT 10;
```

## Configuration

### Update Database Connection

Edit `dashboard.php` and `attendance_api.php`:

```php
$pdo = new PDO(
    "mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4",
    "YOUR_USERNAME",
    "YOUR_PASSWORD",
    [...]
);
```

### Update Session Management

Modify `authenticateUser()` function in `attendance_api.php` to match your authentication system.

## Features

✅ **Automatic IP Verification** - Uses `IPVerification::verify()`
✅ **Automatic Logging** - All attempts logged to `activity_log`
✅ **SweetAlert Integration** - Beautiful warnings for IP mismatches
✅ **Integrity Score Feed** - Failed attempts update Psychological Profile
✅ **Real-time Status** - Shows current attendance status
✅ **IP Display** - Shows current IP address
✅ **Statistics** - Monthly attendance statistics

## Troubleshooting

### Issue: SweetAlert not showing

**Solution:**
- Check browser console for JavaScript errors
- Ensure SweetAlert2 library is loaded
- Verify API response format

### Issue: Failed attempts not logged

**Solution:**
- Check `AttendanceService.php` - `logFailedIPAttempt()` method
- Verify database connection
- Check `activity_log` table structure

### Issue: Integrity Score not updating

**Solution:**
- Verify `sp_update_psychological_profile` stored procedure exists
- Check if procedure processes `integrity.unauthorized_ip_attempt` action
- Review Psychological Profile update logic

## Security Notes

- All IP verification attempts are logged
- Failed attempts are tracked for integrity scoring
- High-level roles (`developer`, `super_admin`) bypass IP checks
- IP addresses are stored for audit purposes
