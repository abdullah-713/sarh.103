<?php
/**
 * ملف الإعدادات - نظام صرح الإتقان
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'u850419603_sarh_db');
define('DB_USER', 'u850419603_sarh_db');
define('DB_PASS', 'Goolbx512!!');

// إعدادات النظام
define('APP_NAME', 'صرح الإتقان');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Riyadh');

// إعدادات الجلسة
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // تغيير إلى 1 في HTTPS
ini_set('session.use_strict_mode', 1);

// تعيين المنطقة الزمنية
date_default_timezone_set(TIMEZONE);

/**
 * دالة الاتصال بقاعدة البيانات
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
    }
}

/**
 * دالة التحقق من تسجيل الدخول
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * دالة التحقق من الصلاحيات
 */
function hasPermission($permission) {
    if (!isset($_SESSION['role_slug'])) {
        return false;
    }
    
    $role = $_SESSION['role_slug'];
    
    // المطور ومدير النظام لهم صلاحيات كاملة
    if (in_array($role, ['developer', 'super_admin'])) {
        return true;
    }
    
    // يمكن إضافة منطق صلاحيات أكثر تعقيداً هنا
    return false;
}

/**
 * دالة تنظيف البيانات
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * دالة إرجاع JSON
 */
function jsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>