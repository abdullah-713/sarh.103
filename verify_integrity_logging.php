<?php
/**
 * Verify Integrity Logging - التحقق من تسجيل النزاهة
 * 
 * هذا الملف يتحقق من أن IntegrityLogger يسجل البيانات بشكل صحيح
 * في activity_log لتحديث Psychological Profile
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

echo "=== Integrity Logging Verification ===\n\n";

// Test 1: Simulate failed IP attempt
echo "Test 1: Simulating Failed IP Attempt\n";
echo str_repeat("-", 50) . "\n";

$user_id = 1; // Change to your test user ID
$attendanceService = new AttendanceService($pdo);

// Get user's branch info
$stmt = $pdo->prepare("
    SELECT u.id, u.branch_id, b.authorized_ip, r.slug as role_slug
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch();

echo "User ID: $user_id\n";
echo "Branch ID: " . ($userInfo['branch_id'] ?? 'N/A') . "\n";
echo "Authorized IP: " . ($userInfo['authorized_ip'] ?? 'N/A') . "\n";
echo "Role: " . ($userInfo['role_slug'] ?? 'N/A') . "\n\n";

// Attempt check-in (will fail if IP doesn't match)
echo "Attempting check-in...\n";
$result = $attendanceService->checkIn($user_id);

if (!$result['success'] && $result['error_code'] === 'IP_NOT_AUTHORIZED') {
    echo "✓ Failed IP attempt detected\n";
    echo "  Current IP: " . ($result['ip_address'] ?? 'N/A') . "\n";
    echo "  Expected IP: " . ($result['expected_ip'] ?? 'N/A') . "\n\n";
} else {
    echo "⚠ Check-in succeeded or different error\n";
    echo "  Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "  Error Code: " . ($result['error_code'] ?? 'N/A') . "\n\n";
}

// Test 2: Verify activity_log entry
echo "Test 2: Verifying activity_log Entry\n";
echo str_repeat("-", 50) . "\n";

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            action,
            model_type,
            JSON_EXTRACT(old_values, '$.user_role') as user_role,
            JSON_EXTRACT(old_values, '$.branch_id') as branch_id,
            JSON_EXTRACT(new_values, '$.action_type') as action_type,
            JSON_EXTRACT(new_values, '$.severity') as severity,
            JSON_EXTRACT(new_values, '$.ip_address') as failed_ip,
            JSON_EXTRACT(new_values, '$.expected_ip') as expected_ip,
            JSON_EXTRACT(new_values, '$.message') as failure_message,
            JSON_EXTRACT(new_values, '$.timestamp') as timestamp,
            ip_address,
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
        echo "✓ Activity log entry found!\n\n";
        echo "Log Details:\n";
        echo "  ID: " . $logEntry['id'] . "\n";
        echo "  User ID: " . $logEntry['user_id'] . "\n";
        echo "  Action: " . $logEntry['action'] . "\n";
        echo "  Model Type: " . ($logEntry['model_type'] ?? 'N/A') . "\n";
        echo "  Action Type: " . ($logEntry['action_type'] ?? 'N/A') . "\n";
        echo "  Severity: " . ($logEntry['severity'] ?? 'N/A') . "\n";
        echo "  Failed IP: " . ($logEntry['failed_ip'] ?? 'N/A') . "\n";
        echo "  Expected IP: " . ($logEntry['expected_ip'] ?? 'N/A') . "\n";
        echo "  Failure Message: " . ($logEntry['failure_message'] ?? 'N/A') . "\n";
        echo "  IP Address (column): " . ($logEntry['ip_address'] ?? 'N/A') . "\n";
        echo "  Created At: " . $logEntry['created_at'] . "\n\n";
        
        // Verify all required fields
        $requiredFields = [
            'action' => 'integrity.unauthorized_ip_attempt',
            'action_type' => 'unauthorized_ip_attempt',
            'severity' => 'medium',
            'failed_ip' => 'not_null',
            'expected_ip' => 'not_null'
        ];
        
        $allValid = true;
        foreach ($requiredFields as $field => $expected) {
            $value = $logEntry[$field] ?? null;
            if ($expected === 'not_null') {
                if (empty($value)) {
                    echo "✗ Field '$field' is missing or empty\n";
                    $allValid = false;
                } else {
                    echo "✓ Field '$field' is present: $value\n";
                }
            } elseif ($value !== $expected) {
                echo "✗ Field '$field' mismatch. Expected: $expected, Got: $value\n";
                $allValid = false;
            } else {
                echo "✓ Field '$field' matches: $value\n";
            }
        }
        
        if ($allValid) {
            echo "\n✓ All required fields are present and correct!\n";
        } else {
            echo "\n✗ Some required fields are missing or incorrect!\n";
        }
        
    } else {
        echo "✗ No activity log entry found for failed IP attempt\n";
        echo "  This means the IntegrityLogger may not be working correctly\n";
    }
} catch (PDOException $e) {
    echo "✗ Error querying activity_log: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Verify integrity_logs entry (if table exists)
echo "Test 3: Verifying integrity_logs Entry (if exists)\n";
echo str_repeat("-", 50) . "\n";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'integrity_logs'");
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                user_id,
                action_type,
                severity,
                ip_address,
                JSON_EXTRACT(details, '$.ip_address') as failed_ip,
                JSON_EXTRACT(details, '$.expected_ip') as expected_ip,
                created_at
            FROM integrity_logs
            WHERE user_id = ?
            AND action_type = 'unauthorized_ip_attempt'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $integrityLog = $stmt->fetch();
        
        if ($integrityLog) {
            echo "✓ Integrity log entry found!\n";
            echo "  ID: " . $integrityLog['id'] . "\n";
            echo "  Action Type: " . $integrityLog['action_type'] . "\n";
            echo "  Severity: " . $integrityLog['severity'] . "\n";
            echo "  IP Address: " . ($integrityLog['ip_address'] ?? 'N/A') . "\n";
            echo "  Created At: " . $integrityLog['created_at'] . "\n";
        } else {
            echo "⚠ No integrity_logs entry found (this is optional)\n";
        }
    } else {
        echo "ℹ integrity_logs table does not exist (this is optional)\n";
    }
} catch (PDOException $e) {
    echo "⚠ Error checking integrity_logs: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Verify data structure for Psychological Profile
echo "Test 4: Verifying Data Structure for Psychological Profile\n";
echo str_repeat("-", 50) . "\n";

try {
    $stmt = $pdo->prepare("
        SELECT 
            new_values
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
        
        echo "JSON Structure:\n";
        echo json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        $requiredKeys = ['action_type', 'severity', 'ip_address', 'expected_ip', 'message', 'timestamp'];
        $missingKeys = [];
        
        foreach ($requiredKeys as $key) {
            if (!isset($details[$key])) {
                $missingKeys[] = $key;
            }
        }
        
        if (empty($missingKeys)) {
            echo "✓ All required keys present in JSON structure\n";
        } else {
            echo "✗ Missing keys: " . implode(', ', $missingKeys) . "\n";
        }
    } else {
        echo "✗ No log entry found or invalid JSON\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "1. Failed IP attempt logged: " . (isset($logEntry) && $logEntry ? '✓ Yes' : '✗ No') . "\n";
echo "2. Activity log structure: " . (isset($logEntry) && $logEntry ? '✓ Valid' : '✗ Invalid') . "\n";
echo "3. All required fields present: " . (isset($allValid) && $allValid ? '✓ Yes' : '✗ No') . "\n";
echo "4. Ready for Psychological Profile: " . (isset($allValid) && $allValid ? '✓ Yes' : '✗ No') . "\n";

echo "\n=== Test Complete ===\n";
