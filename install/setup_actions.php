<?php
/**
 * =====================================================
 * نظام صرح الإتقان - Sarh Al-Itqan System
 * =====================================================
 * Actions System Setup Script
 * سكربت إعداد نظام الإجراءات
 * =====================================================
 */

// Load dependencies
require_once dirname(__DIR__) . '/config/app.php';

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');

// Check if user is admin (for security)
if (!is_logged_in() || current_role_level() < ROLE_ADMIN) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالوصول - يتطلب صلاحيات المدير'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Read the SQL migration file
    $sqlFile = __DIR__ . '/migrations/add_actions_table.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception('ملف التهيئة غير موجود: ' . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    
    if (!$sql) {
        throw new Exception('فشل قراءة ملف التهيئة');
    }
    
    // Split SQL statements by semicolon
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    $errors = [];
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            Database::query($statement);
            $executed++;
        } catch (PDOException $e) {
            // Log error but continue
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Add action_id column to leaves table if it doesn't exist
    try {
        $checkColumn = Database::fetchOne("SHOW COLUMNS FROM leaves LIKE 'action_id'");
        if (!$checkColumn) {
            Database::query("ALTER TABLE leaves ADD COLUMN action_id INT UNSIGNED NULL AFTER status");
            Database::query("ALTER TABLE leaves ADD INDEX idx_action_id (action_id)");
            $executed++;
        }
    } catch (PDOException $e) {
        $errors[] = [
            'statement' => 'ALTER TABLE leaves ADD action_id',
            'error' => $e->getMessage()
        ];
    }
    
    // Log activity
    log_activity(
        'system_setup',
        'actions',
        'تم تنفيذ إعداد نظام الإجراءات - ' . $executed . ' عملية نجحت'
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء جداول الإجراءات بنجاح',
        'details' => [
            'executed' => $executed,
            'errors_count' => count($errors),
            'errors' => DEBUG_MODE ? $errors : []
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في إعداد النظام: ' . $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE);
}
