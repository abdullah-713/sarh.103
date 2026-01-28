<?php
/**
 * Test Dashboard Integration
 * 
 * اختبار تكامل لوحة التحكم مع AttendanceService
 * والتحقق من تسجيل المحاولات الفاشلة في activity_log
 */

require_once 'AttendanceService.php';

// تهيئة الاتصال بقاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u850419603_101;charset=utf8mb4",
        "username",
        "password",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$attendanceService = new AttendanceService($pdo);

echo "=== Dashboard Integration Test ===\n\n";

// Test 1: Check IP Verification
echo "Test 1: IP Verification\n";
echo "----------------------\n";
$user_id = 1; // Change to your test user ID

require_once 'IPVerification.php';
$ipVerifier = new IPVerification($pdo);
$verification = $ipVerifier->verify($user_id);

echo "User ID: $user_id\n";
echo "IP Valid: " . ($verification['valid'] ? 'Yes' : 'No') . "\n";
echo "Current IP: " . ($verification['ip_address'] ?? 'N/A') . "\n";
echo "Expected IP: " . ($verification['expected_ip'] ?? 'N/A') . "\n";
echo "Message: " . $verification['message'] . "\n\n";

// Test 2: Check-in with valid IP
echo "Test 2: Check-in Attempt\n";
echo "------------------------\n";
$result = $attendanceService->checkIn($user_id);

if ($result['success']) {
    echo "✓ Check-in successful!\n";
    echo "  Attendance ID: " . $result['attendance_id'] . "\n";
    echo "  IP Address: " . $result['ip_address'] . "\n";
} else {
    echo "✗ Check-in failed!\n";
    echo "  Error: " . $result['message'] . "\n";
    echo "  Error Code: " . $result['error_code'] . "\n";
    
    if ($result['error_code'] === 'IP_NOT_AUTHORIZED') {
        echo "  Current IP: " . ($result['ip_address'] ?? 'N/A') . "\n";
        echo "  Expected IP: " . ($result['expected_ip'] ?? 'N/A') . "\n";
        echo "\n  ⚠ This failed attempt should be logged in activity_log\n";
    }
}

echo "\n";

// Test 3: Verify Activity Log Entry
echo "Test 3: Verify Activity Log Entry\n";
echo "----------------------------------\n";

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            action,
            model_type,
            JSON_EXTRACT(new_values, '$.ip_address') as ip_address,
            JSON_EXTRACT(new_values, '$.expected_ip') as expected_ip,
            JSON_EXTRACT(new_values, '$.severity') as severity,
            created_at
        FROM activity_log
        WHERE user_id = ?
        AND (
            action = 'integrity.unauthorized_ip_attempt'
            OR action = 'attendance.checkin'
            OR action = 'ip_verification.attempt'
        )
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "⚠ No activity logs found for user $user_id\n";
    } else {
        echo "Found " . count($logs) . " recent activity log entries:\n\n";
        foreach ($logs as $log) {
            echo "  Log ID: " . $log['id'] . "\n";
            echo "  Action: " . $log['action'] . "\n";
            echo "  IP Address: " . ($log['ip_address'] ?? 'N/A') . "\n";
            if ($log['action'] === 'integrity.unauthorized_ip_attempt') {
                echo "  Expected IP: " . ($log['expected_ip'] ?? 'N/A') . "\n";
                echo "  Severity: " . ($log['severity'] ?? 'N/A') . "\n";
            }
            echo "  Created At: " . $log['created_at'] . "\n";
            echo "  ---\n";
        }
    }
} catch (PDOException $e) {
    echo "✗ Error querying activity_log: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Verify Integrity Logs Entry
echo "Test 4: Verify Integrity Logs Entry\n";
echo "------------------------------------\n";

try {
    // Check if integrity_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'integrity_logs'");
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                user_id,
                action_type,
                severity,
                ip_address,
                created_at
            FROM integrity_logs
            WHERE user_id = ?
            AND action_type = 'unauthorized_ip_attempt'
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $integrityLogs = $stmt->fetchAll();
        
        if (empty($integrityLogs)) {
            echo "⚠ No integrity logs found for user $user_id\n";
        } else {
            echo "Found " . count($integrityLogs) . " integrity log entries:\n\n";
            foreach ($integrityLogs as $log) {
                echo "  Log ID: " . $log['id'] . "\n";
                echo "  Action Type: " . $log['action_type'] . "\n";
                echo "  Severity: " . $log['severity'] . "\n";
                echo "  IP Address: " . ($log['ip_address'] ?? 'N/A') . "\n";
                echo "  Created At: " . $log['created_at'] . "\n";
                echo "  ---\n";
            }
        }
    } else {
        echo "ℹ integrity_logs table does not exist (this is optional)\n";
    }
} catch (PDOException $e) {
    echo "✗ Error querying integrity_logs: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Check Today's Status
echo "Test 5: Today's Attendance Status\n";
echo "----------------------------------\n";
$status = $attendanceService->getTodayStatus($user_id);

echo "Can Check In: " . ($status['can_check_in'] ? 'Yes' : 'No') . "\n";
echo "Can Check Out: " . ($status['can_check_out'] ? 'Yes' : 'No') . "\n";
echo "IP Verified: " . ($status['ip_verified'] ? 'Yes' : 'No') . "\n";
echo "IP Message: " . $status['ip_message'] . "\n";
echo "Current IP: " . $status['current_ip'] . "\n";

if ($status['attendance']) {
    echo "\nAttendance Record:\n";
    echo "  Check-in: " . ($status['attendance']['check_in_time'] ?? 'N/A') . "\n";
    echo "  Check-out: " . ($status['attendance']['check_out_time'] ?? 'N/A') . "\n";
    echo "  Work Minutes: " . ($status['attendance']['work_minutes'] ?? 'N/A') . "\n";
}

echo "\n=== Test Complete ===\n";

// Summary
echo "\n=== Summary ===\n";
echo "1. IP Verification: " . ($verification['valid'] ? '✓ Working' : '✗ Failed') . "\n";
echo "2. Check-in Service: " . ($result['success'] ? '✓ Working' : '✗ Failed - ' . $result['error_code']) . "\n";
echo "3. Activity Log: " . (count($logs ?? []) > 0 ? '✓ Entries found' : '⚠ No entries') . "\n";
echo "4. Status Service: " . (isset($status) ? '✓ Working' : '✗ Failed') . "\n";

if (!$verification['valid'] && $result['error_code'] === 'IP_NOT_AUTHORIZED') {
    echo "\n⚠ IP Verification Failed - This is expected if IP doesn't match.\n";
    echo "  Check activity_log for 'integrity.unauthorized_ip_attempt' entry.\n";
    echo "  This should feed the Psychological Profile system.\n";
}
