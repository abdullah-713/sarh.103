<?php
/**
 * مثال على استخدام AttendanceService و IPVerification
 * 
 * يوضح كيفية استخدام النظام المتكامل
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

// تهيئة AttendanceService
$attendanceService = new AttendanceService($pdo);

// مثال 1: تسجيل الحضور
echo "=== Example 1: Check In ===\n";
$user_id = 1; // معرف المستخدم

$result = $attendanceService->checkIn($user_id);

if ($result['success']) {
    echo "✓ Check-in successful!\n";
    echo "  Attendance ID: " . $result['attendance_id'] . "\n";
    echo "  IP Address: " . $result['ip_address'] . "\n";
    echo "  Branch ID: " . $result['branch_id'] . "\n";
    echo "  Check-in Time: " . $result['check_in_time'] . "\n";
} else {
    echo "✗ Check-in failed!\n";
    echo "  Error: " . $result['message'] . "\n";
    echo "  Error Code: " . $result['error_code'] . "\n";
    
    if (isset($result['ip_address'])) {
        echo "  Current IP: " . $result['ip_address'] . "\n";
    }
    if (isset($result['expected_ip'])) {
        echo "  Expected IP: " . $result['expected_ip'] . "\n";
    }
    
    // هذه المحاولة الفاشلة تم تسجيلها تلقائياً في activity_log
    // و integrity_logs لإطعام نظام الملف النفسي
}

echo "\n";

// مثال 2: التحقق من IP فقط (بدون تسجيل حضور)
echo "=== Example 2: IP Verification Only ===\n";
require_once 'IPVerification.php';
$ipVerifier = new IPVerification($pdo);

$verification = $ipVerifier->verify($user_id);

if ($verification['valid']) {
    echo "✓ IP verification successful!\n";
    echo "  IP Address: " . $verification['ip_address'] . "\n";
    echo "  Branch ID: " . $verification['branch_id'] . "\n";
    if (isset($verification['bypass_ip']) && $verification['bypass_ip']) {
        echo "  Note: High-level role - IP bypass enabled\n";
    }
} else {
    echo "✗ IP verification failed!\n";
    echo "  Error: " . $verification['message'] . "\n";
    echo "  Current IP: " . ($verification['ip_address'] ?? 'N/A') . "\n";
    echo "  Expected IP: " . ($verification['expected_ip'] ?? 'N/A') . "\n";
    
    // يمكنك تسجيل هذه المحاولة الفاشلة يدوياً إذا أردت
    // $integrityLogger = new IntegrityLogger($pdo);
    // $integrityLogger->logFailedIPAttempt($user_id, $verification);
}

echo "\n";

// مثال 3: الحصول على حالة الحضور اليوم
echo "=== Example 3: Today's Status ===\n";
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

echo "\n";

// مثال 4: تسجيل الانصراف
echo "=== Example 4: Check Out ===\n";
$result = $attendanceService->checkOut($user_id);

if ($result['success']) {
    echo "✓ Check-out successful!\n";
    echo "  Check-in Time: " . $result['check_in_time'] . "\n";
    echo "  Check-out Time: " . $result['check_out_time'] . "\n";
    echo "  Work Hours: " . $result['work_hours'] . "\n";
    echo "  Work Minutes: " . $result['work_minutes'] . "\n";
} else {
    echo "✗ Check-out failed!\n";
    echo "  Error: " . $result['message'] . "\n";
}

echo "\n";

// مثال 5: عرض سجلات activity_log للمحاولات الفاشلة
echo "=== Example 5: View Failed IP Attempts ===\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            action,
            new_values,
            ip_address,
            created_at
        FROM activity_log
        WHERE user_id = ?
        AND action LIKE 'integrity.unauthorized_ip_attempt%'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $failedAttempts = $stmt->fetchAll();
    
    if (empty($failedAttempts)) {
        echo "No failed IP attempts found.\n";
    } else {
        echo "Found " . count($failedAttempts) . " failed IP attempts:\n";
        foreach ($failedAttempts as $attempt) {
            $details = json_decode($attempt['new_values'], true);
            echo "\n  Attempt #" . $attempt['id'] . ":\n";
            echo "    Date: " . $attempt['created_at'] . "\n";
            echo "    IP: " . ($details['ip_address'] ?? 'N/A') . "\n";
            echo "    Expected IP: " . ($details['expected_ip'] ?? 'N/A') . "\n";
            echo "    Message: " . ($details['message'] ?? 'N/A') . "\n";
        }
    }
} catch (PDOException $e) {
    echo "Error fetching failed attempts: " . $e->getMessage() . "\n";
}

echo "\n=== Examples Complete ===\n";
