# Integration Guide - دليل التكامل

## Overview

This guide explains how to integrate the Check-in/Check-out logic using `IPVerification::verify()` method and how failed IP attempts are logged to `activity_log` for the Psychological Profile (Integrity System).

## Architecture

### Components

1. **IPVerification Class** (`IPVerification.php`)
   - `verify($user_id, $ip_address = null)` - Main verification method
   - `verifyUserIP()` - Alias for verify()
   - Handles IP validation, CIDR support, and high-level role bypass

2. **AttendanceService Class** (`AttendanceService.php`)
   - `checkIn($user_id, $check_in_time = null)` - Check-in with IP verification
   - `checkOut($user_id, $check_out_time = null)` - Check-out
   - `getTodayStatus($user_id)` - Get today's attendance status
   - Automatically logs all attempts to `activity_log`

3. **IntegrityLogger Class** (inside `AttendanceService.php`)
   - `logFailedIPAttempt($user_id, $verification_result)` - Logs failed attempts
   - Feeds the Psychological Profile system
   - Logs to both `activity_log` and `integrity_logs`

## Integration Steps

### Step 1: Database Setup

Run the migration file:
```bash
mysql -u username -p database_name < migration_complete.sql
```

### Step 2: Include Required Files

```php
require_once 'IPVerification.php';
require_once 'AttendanceService.php';
```

### Step 3: Initialize Services

```php
// Database connection
$pdo = new PDO(
    "mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4",
    "username",
    "password",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Initialize AttendanceService
$attendanceService = new AttendanceService($pdo);
```

### Step 4: Use in Your Code

#### Check-In Example

```php
$user_id = $_SESSION['user_id']; // Get from session or token

$result = $attendanceService->checkIn($user_id);

if ($result['success']) {
    // Success - attendance recorded
    echo "Check-in successful!";
    echo "IP: " . $result['ip_address'];
} else {
    // Failed - automatically logged to activity_log
    echo "Check-in failed: " . $result['message'];
    
    // The failed attempt is already logged in:
    // - activity_log (action: 'integrity.unauthorized_ip_attempt')
    // - integrity_logs (if table exists)
}
```

#### Check-Out Example

```php
$result = $attendanceService->checkOut($user_id);

if ($result['success']) {
    echo "Check-out successful!";
    echo "Work hours: " . $result['work_hours'];
}
```

#### IP Verification Only

```php
require_once 'IPVerification.php';
$ipVerifier = new IPVerification($pdo);

$verification = $ipVerifier->verify($user_id);

if ($verification['valid']) {
    // IP is valid
} else {
    // IP is invalid - log manually if needed
    $integrityLogger = new IntegrityLogger($pdo);
    $integrityLogger->logFailedIPAttempt($user_id, $verification);
}
```

## Activity Log Structure

### Successful Check-In

```json
{
    "user_id": 1,
    "action": "attendance.checkin",
    "model_type": "attendance",
    "model_id": 123,
    "new_values": {
        "attendance_id": 123,
        "ip_address": "192.168.1.100",
        "branch_id": 1,
        "bypass_ip": false,
        "check_in_time": "08:00:00"
    },
    "ip_address": "192.168.1.100"
}
```

### Failed IP Attempt

```json
{
    "user_id": 1,
    "action": "integrity.unauthorized_ip_attempt",
    "model_type": "attendance",
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
        "message": "عنوان IP غير مسموح به...",
        "timestamp": "2026-01-28 10:30:00"
    },
    "ip_address": "192.168.1.200"
}
```

## Psychological Profile Integration

The failed IP attempts are automatically logged with:
- **Action**: `integrity.unauthorized_ip_attempt`
- **Severity**: `medium`
- **Details**: Full information about the failed attempt

These logs feed into the Psychological Profile system through:
1. `activity_log` table - Main logging table
2. `integrity_logs` table - If exists, for integrity tracking
3. The stored procedure `sp_update_psychological_profile` can process these logs

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

## API Integration

Use the provided `attendance_api.php`:

```javascript
// Check-in
fetch('/attendance_api.php?action=checkin', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer YOUR_TOKEN'
    }
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Check-in successful');
    } else {
        console.error('Check-in failed:', data.message);
        // Failed attempt is automatically logged
    }
});
```

## Error Codes

- `IP_NOT_AUTHORIZED` - IP verification failed (logged to activity_log)
- `ALREADY_CHECKED_IN` - User already checked in today
- `ALREADY_CHECKED_OUT` - User already checked out today
- `NO_CHECK_IN_RECORD` - No check-in record found for checkout
- `DATABASE_ERROR` - Database error occurred

## Best Practices

1. **Always use AttendanceService** - Don't bypass IP verification
2. **Handle errors gracefully** - Failed attempts are logged automatically
3. **Monitor activity_log** - Review failed attempts regularly
4. **Update branch IPs** - Keep authorized IPs up to date
5. **Test with different IPs** - Ensure verification works correctly

## Testing

See `example_usage.php` for complete examples of:
- Check-in with IP verification
- Check-out
- IP verification only
- Status checking
- Viewing failed attempts

## Security Notes

- High-level roles (`developer`, `super_admin`) bypass IP checks
- All attempts (successful and failed) are logged
- IP addresses are stored for audit purposes
- Failed attempts feed the integrity system automatically
