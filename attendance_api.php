<?php
/**
 * Main Attendance API - API الرئيسي لتسجيل الحضور والانصراف
 * 
 * يستخدم AttendanceService و IPVerification::verify()
 * يسجل جميع المحاولات الفاشلة في activity_log للملف النفسي
 */

header('Content-Type: application/json; charset=utf-8');
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// تهيئة AttendanceService
$attendanceService = new AttendanceService($pdo);

// التحقق من المصادقة
function authenticateUser() {
    // عدّل حسب نظام المصادقة الخاص بك
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
        'message' => 'Unauthorized - Please login'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// معالجة الطلبات
switch ($action) {
    case 'checkin':
        handleCheckIn($attendanceService, $user_id);
        break;
        
    case 'checkout':
        handleCheckOut($attendanceService, $user_id);
        break;
        
    case 'status':
        handleStatus($attendanceService, $user_id);
        break;
        
    case 'history':
        handleHistory($attendanceService, $user_id);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Available actions: checkin, checkout, status, history'
        ], JSON_UNESCAPED_UNICODE);
}

/**
 * معالجة تسجيل الحضور
 */
function handleCheckIn($attendanceService, $user_id) {
    $check_in_time = $_POST['check_in_time'] ?? null;
    
    $result = $attendanceService->checkIn($user_id, $check_in_time);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'attendance_id' => $result['attendance_id'],
                'check_in_time' => $result['check_in_time'],
                'date' => $result['date'],
                'ip_address' => $result['ip_address'],
                'branch_id' => $result['branch_id'],
                'bypass_ip' => $result['bypass_ip'] ?? false
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // المحاولة الفاشلة تم تسجيلها تلقائياً في AttendanceService
        $status_code = $result['error_code'] === 'IP_NOT_AUTHORIZED' ? 403 : 400;
        http_response_code($status_code);
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'error_code' => $result['error_code'],
            'ip_address' => $result['ip_address'] ?? null,
            'expected_ip' => $result['expected_ip'] ?? null
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * معالجة تسجيل الانصراف
 */
function handleCheckOut($attendanceService, $user_id) {
    $check_out_time = $_POST['check_out_time'] ?? null;
    
    $result = $attendanceService->checkOut($user_id, $check_out_time);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'attendance_id' => $result['attendance_id'],
                'check_in_time' => $result['check_in_time'],
                'check_out_time' => $result['check_out_time'],
                'work_minutes' => $result['work_minutes'],
                'work_hours' => $result['work_hours'],
                'ip_address' => $result['ip_address']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'error_code' => $result['error_code']
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * الحصول على حالة الحضور اليوم
 */
function handleStatus($attendanceService, $user_id) {
    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User ID required'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $status = $attendanceService->getTodayStatus($user_id);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $status
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * الحصول على تاريخ الحضور
 */
function handleHistory($attendanceService, $user_id) {
    global $pdo;
    
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
            'message' => 'Database error',
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
