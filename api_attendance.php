<?php
/**
 * API Endpoints لتسجيل الحضور والانصراف باستخدام IP
 * 
 * الاستخدام:
 * POST /api_attendance.php?action=checkin
 * POST /api_attendance.php?action=checkout
 * GET  /api_attendance.php?action=status
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'attendance_checkin_ip_verification.php';

// تهيئة الاتصال بقاعدة البيانات (عدّل حسب إعداداتك)
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في الاتصال بقاعدة البيانات',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من المصادقة (عدّل حسب نظام المصادقة الخاص بك)
function authenticateUser() {
    // مثال: التحقق من الجلسة أو Token
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        // أو التحقق من Bearer Token
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            // التحقق من Token هنا
            // return validateToken($token);
        }
        
        return null;
    }
    
    return $_SESSION['user_id'];
}

// الحصول على الإجراء المطلوب
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// التحقق من المصادقة
$user_id = authenticateUser();
if (!$user_id && $action !== 'status') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح - يرجى تسجيل الدخول'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// معالجة الطلبات
switch ($action) {
    case 'checkin':
        handleCheckIn($pdo, $user_id);
        break;
        
    case 'checkout':
        handleCheckOut($pdo, $user_id);
        break;
        
    case 'status':
        handleStatus($pdo, $user_id);
        break;
        
    case 'history':
        handleHistory($pdo, $user_id);
        break;
        
    case 'today':
        handleTodayAttendance($pdo, $user_id);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'إجراء غير صحيح. الإجراءات المتاحة: checkin, checkout, status, history, today'
        ], JSON_UNESCAPED_UNICODE);
}

/**
 * معالجة تسجيل الحضور
 */
function handleCheckIn($pdo, $user_id) {
    global $pdo;
    
    // التحقق من IP
    $result = checkInWithIPVerification($user_id);
    
    if ($result['success']) {
        // تسجيل في سجل النشاطات
        logActivity($pdo, $user_id, 'attendance.checkin', [
            'attendance_id' => $result['attendance_id'],
            'ip_address' => $result['ip_address'],
            'bypass_ip' => $result['bypass_ip'] ?? false
        ]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'attendance_id' => $result['attendance_id'],
                'check_in_time' => date('H:i:s'),
                'date' => date('Y-m-d'),
                'ip_address' => $result['ip_address'],
                'bypass_ip' => $result['bypass_ip'] ?? false
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // تسجيل محاولة فاشلة
        logActivity($pdo, $user_id, 'attendance.checkin_failed', [
            'error_code' => $result['error_code'],
            'ip_address' => getClientIPAddress()
        ]);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'error_code' => $result['error_code']
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * معالجة تسجيل الانصراف
 */
function handleCheckOut($pdo, $user_id) {
    $today = date('Y-m-d');
    
    try {
        // البحث عن سجل الحضور لهذا اليوم
        $stmt = $pdo->prepare("
            SELECT id, check_in_time, check_out_time 
            FROM attendance 
            WHERE user_id = ? AND date = ?
        ");
        $stmt->execute([$user_id, $today]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'لم يتم العثور على سجل حضور لهذا اليوم'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        if ($attendance['check_out_time']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'تم تسجيل الانصراف مسبقاً'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // التحقق من IP (اختياري للانصراف)
        $ipVerification = verifyUserIPForAttendance($user_id);
        $ip_address = getClientIPAddress();
        
        // تحديث سجل الانصراف
        $check_out_time = date('H:i:s');
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET check_out_time = ?,
                ip_address = COALESCE(ip_address, ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$check_out_time, $ip_address, $attendance['id']]);
        
        // حساب ساعات العمل
        $workMinutes = calculateWorkMinutes($attendance['check_in_time'], $check_out_time);
        
        // تحديث ساعات العمل
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET work_minutes = ?
            WHERE id = ?
        ");
        $stmt->execute([$workMinutes, $attendance['id']]);
        
        // تسجيل في سجل النشاطات
        logActivity($pdo, $user_id, 'attendance.checkout', [
            'attendance_id' => $attendance['id'],
            'check_out_time' => $check_out_time,
            'work_minutes' => $workMinutes
        ]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'تم تسجيل الانصراف بنجاح',
            'data' => [
                'attendance_id' => $attendance['id'],
                'check_in_time' => $attendance['check_in_time'],
                'check_out_time' => $check_out_time,
                'work_minutes' => $workMinutes,
                'work_hours' => round($workMinutes / 60, 2)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ في قاعدة البيانات',
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * الحصول على حالة الحضور اليوم
 */
function handleStatus($pdo, $user_id) {
    $today = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.*,
                b.name as branch_name,
                b.code as branch_code
            FROM attendance a
            LEFT JOIN branches b ON a.branch_id = b.id
            WHERE a.user_id = ? AND a.date = ?
        ");
        $stmt->execute([$user_id, $today]);
        $attendance = $stmt->fetch();
        
        $user = getUserWithRole($user_id);
        $ipVerification = verifyUserIPForAttendance($user_id);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'attendance' => $attendance,
                'current_ip' => getClientIPAddress(),
                'ip_verified' => $ipVerification['valid'],
                'ip_message' => $ipVerification['message'],
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'role' => $user['role_name'],
                    'branch' => $user['branch_id']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ في قاعدة البيانات',
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * الحصول على تاريخ الحضور
 */
function handleHistory($pdo, $user_id) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $limit = (int)($_GET['limit'] ?? 30);
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.*,
                b.name as branch_name,
                b.code as branch_code
            FROM attendance a
            LEFT JOIN branches b ON a.branch_id = b.id
            WHERE a.user_id = ? 
            AND a.date BETWEEN ? AND ?
            ORDER BY a.date DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $start_date, $end_date, $limit]);
        $history = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'history' => $history,
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'total_records' => count($history)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ في قاعدة البيانات',
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * الحصول على سجل الحضور اليوم
 */
function handleTodayAttendance($pdo, $user_id) {
    handleStatus($pdo, $user_id);
}

/**
 * حساب دقائق العمل
 */
function calculateWorkMinutes($check_in, $check_out) {
    $check_in_time = strtotime($check_in);
    $check_out_time = strtotime($check_out);
    
    if ($check_out_time < $check_in_time) {
        // إذا كان الانصراف في اليوم التالي
        $check_out_time += 86400; // إضافة 24 ساعة
    }
    
    return round(($check_out_time - $check_in_time) / 60);
}

/**
 * تسجيل النشاطات
 */
function logActivity($pdo, $user_id, $action, $details = []) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            getClientIPAddress(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // تجاهل الأخطاء في سجل النشاطات
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
