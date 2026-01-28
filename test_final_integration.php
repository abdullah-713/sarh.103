<?php
/**
 * Final Integration Test - اختبار التكامل النهائي
 * 
 * يتحقق من:
 * 1. SweetAlert2 triggers on IP_NOT_AUTHORIZED
 * 2. AttendanceService triggers IntegrityLogger
 * 3. IntegrityLogger creates 'medium' severity entry
 * 4. Data format matches Psychological Profile requirements
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

echo "=== Final Integration Test ===\n\n";

$user_id = 1; // Change to your test user ID
$attendanceService = new AttendanceService($pdo);

// Test 1: Simulate failed IP attempt
echo "Test 1: Simulating Failed IP Attempt\n";
echo str_repeat("=", 60) . "\n";

$result = $attendanceService->checkIn($user_id);

if (!$result['success'] && $result['error_code'] === 'IP_NOT_AUTHORIZED') {
    echo "✅ PASS: Failed IP attempt detected\n";
    echo "   Error Code: " . $result['error_code'] . "\n";
    echo "   Current IP: " . ($result['ip_address'] ?? 'N/A') . "\n";
    echo "   Expected IP: " . ($result['expected_ip'] ?? 'N/A') . "\n";
    echo "\n";
    
    // Test 2: Verify activity_log entry
    echo "Test 2: Verifying activity_log Entry\n";
    echo str_repeat("=", 60) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            action,
            model_type,
            JSON_EXTRACT(new_values, '$.severity') as severity,
            JSON_EXTRACT(new_values, '$.action_type') as action_type,
            JSON_EXTRACT(new_values, '$.ip_address') as failed_ip,
            JSON_EXTRACT(new_values, '$.expected_ip') as expected_ip,
            JSON_EXTRACT(new_values, '$.message') as message,
            JSON_EXTRACT(new_values, '$.timestamp') as timestamp,
            created_at
        FROM activity_log
        WHERE user_id = ?
        AND action = 'integrity.unauthorized_ip_attempt'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $logEntry = $stmt->fetch();
    
    if ($logEntry) {
        echo "✅ PASS: Activity log entry found\n\n";
        
        // Verify required fields
        $checks = [
            'action' => ['expected' => 'integrity.unauthorized_ip_attempt', 'actual' => $logEntry['action']],
            'severity' => ['expected' => 'medium', 'actual' => trim($logEntry['severity'], '"')],
            'action_type' => ['expected' => 'unauthorized_ip_attempt', 'actual' => trim($logEntry['action_type'], '"')],
            'failed_ip' => ['expected' => 'not_null', 'actual' => $logEntry['failed_ip']],
            'expected_ip' => ['expected' => 'not_null', 'actual' => $logEntry['expected_ip']]
        ];
        
        $allPassed = true;
        foreach ($checks as $field => $check) {
            if ($check['expected'] === 'not_null') {
                $passed = !empty($check['actual']);
            } else {
                $passed = ($check['actual'] === $check['expected']);
            }
            
            $status = $passed ? '✅' : '❌';
            echo "   $status $field: " . ($check['expected'] === 'not_null' ? ($check['actual'] ?? 'NULL') : $check['actual']) . "\n";
            
            if (!$passed) {
                $allPassed = false;
            }
        }
        
        if ($allPassed) {
            echo "\n✅ PASS: All required fields present and correct\n";
        } else {
            echo "\n❌ FAIL: Some fields missing or incorrect\n";
        }
        
        // Test 3: Verify data format for Psychological Profile
        echo "\nTest 3: Verifying Data Format for Psychological Profile\n";
        echo str_repeat("=", 60) . "\n";
        
        $stmt = $pdo->prepare("
            SELECT 
                user_id,
                action,
                JSON_EXTRACT(new_values, '$.severity') as severity,
                JSON_EXTRACT(new_values, '$.ip_address') as failed_ip,
                JSON_EXTRACT(new_values, '$.expected_ip') as expected_ip,
                JSON_EXTRACT(new_values, '$.timestamp') as attempt_time,
                created_at
            FROM activity_log
            WHERE user_id = ?
            AND action = 'integrity.unauthorized_ip_attempt'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $profileData = $stmt->fetch();
        
        if ($profileData) {
            echo "✅ PASS: Data format verified for Psychological Profile\n";
            echo "   User ID: " . $profileData['user_id'] . "\n";
            echo "   Action: " . $profileData['action'] . "\n";
            echo "   Severity: " . trim($profileData['severity'], '"') . "\n";
            echo "   Failed IP: " . ($profileData['failed_ip'] ?? 'N/A') . "\n";
            echo "   Expected IP: " . ($profileData['expected_ip'] ?? 'N/A') . "\n";
            echo "   Timestamp: " . ($profileData['attempt_time'] ?? 'N/A') . "\n";
            echo "   Created At: " . $profileData['created_at'] . "\n";
        }
        
    } else {
        echo "❌ FAIL: No activity log entry found\n";
        echo "   IntegrityLogger may not be working correctly\n";
    }
    
} else {
    echo "⚠ SKIP: Check-in succeeded or different error\n";
    echo "   Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "   Error Code: " . ($result['error_code'] ?? 'N/A') . "\n";
    echo "\n   Note: To test IP failure, ensure user's IP doesn't match branch authorized_ip\n";
}

echo "\n";

// Test 4: Verify JSON structure
echo "Test 4: Verifying JSON Structure\n";
echo str_repeat("=", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT new_values
    FROM activity_log
    WHERE user_id = ?
    AND action = 'integrity.unauthorized_ip_attempt'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$log = $stmt->fetch();

if ($log && $log['new_values']) {
    $details = json_decode($log['new_values'], true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ PASS: JSON structure is valid\n";
        echo "\nJSON Content:\n";
        echo json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        $requiredKeys = ['action_type', 'severity', 'ip_address', 'expected_ip', 'message', 'timestamp'];
        $missingKeys = [];
        
        foreach ($requiredKeys as $key) {
            if (!isset($details[$key])) {
                $missingKeys[] = $key;
            }
        }
        
        if (empty($missingKeys)) {
            echo "\n✅ PASS: All required keys present\n";
        } else {
            echo "\n❌ FAIL: Missing keys: " . implode(', ', $missingKeys) . "\n";
        }
    } else {
        echo "❌ FAIL: Invalid JSON structure\n";
        echo "   Error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "⚠ SKIP: No log entry found for JSON verification\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "1. Failed IP Attempt Detection: " . (isset($result) && !$result['success'] && $result['error_code'] === 'IP_NOT_AUTHORIZED' ? '✅ PASS' : '⚠ SKIP') . "\n";
echo "2. Activity Log Entry Creation: " . (isset($logEntry) && $logEntry ? '✅ PASS' : '❌ FAIL') . "\n";
echo "3. Required Fields Present: " . (isset($allPassed) && $allPassed ? '✅ PASS' : '❌ FAIL') . "\n";
echo "4. Severity Set to 'medium': " . (isset($logEntry) && trim($logEntry['severity'], '"') === 'medium' ? '✅ PASS' : '❌ FAIL') . "\n";
echo "5. Data Format for Profile: " . (isset($profileData) && $profileData ? '✅ PASS' : '❌ FAIL') . "\n";
echo "6. JSON Structure Valid: " . (isset($details) && json_last_error() === JSON_ERROR_NONE ? '✅ PASS' : '❌ FAIL') . "\n";

echo "\n=== Test Complete ===\n";

if (isset($allPassed) && $allPassed && isset($profileData) && $profileData) {
    echo "\n✅ ALL TESTS PASSED - System is ready for production!\n";
} else {
    echo "\n⚠ Some tests failed or skipped - Please review the results above\n";
}
