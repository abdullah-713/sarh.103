<?php
/**
 * الصفحة الرئيسية - نظام صرح الإتقان
 * توجيه المستخدم إلى الصفحة المناسبة
 */

session_start();

// التحقق من وجود ملف القفل (إذا تم الإعداد)
$setupLockFile = __DIR__ . '/setup.lock';

if (!file_exists($setupLockFile)) {
    // النظام لم يتم إعداده بعد - توجيه إلى معالج الإعداد
    header('Location: setup_wizard.php');
    exit;
}

// النظام تم إعداده - التحقق من تسجيل الدخول
if (isset($_SESSION['user_id'])) {
    // المستخدم مسجل دخول - توجيه إلى لوحة التحكم
    header('Location: dashboard.php');
} else {
    // المستخدم غير مسجل دخول - توجيه إلى صفحة تسجيل الدخول
    header('Location: login.php');
}
exit;
?>