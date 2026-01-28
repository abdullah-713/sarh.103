-- =====================================================
-- ملف الترحيل الشامل: إزالة GPS والاعتماد على IP فقط
-- التاريخ: 2026-01-28
-- الوصف: تبسيط قاعدة البيانات وإزالة كل ما يتعلق بالـ GPS
--         والاعتماد كلياً على عنوان الـ IP للتحقق من وجود الموظف في الفرع
-- 
-- تعليمات الاستخدام:
-- 1. قم بعمل نسخة احتياطية من قاعدة البيانات قبل التنفيذ
-- 2. راجع عناوين IP في القسم الأخير وعدلها حسب بيئتك
-- 3. قم بتنفيذ الملف بالكامل
-- 
-- ملاحظة: إذا ظهرت أخطاء "Column does not exist"، يمكن تجاهلها بأمان
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- القسم 1: تعديل جدول الفروع (branches)
-- =====================================================

-- حذف حقول GPS
ALTER TABLE `branches` DROP COLUMN `latitude`;
ALTER TABLE `branches` DROP COLUMN `longitude`;
ALTER TABLE `branches` DROP COLUMN `geofence_radius`;

-- إضافة حقل authorized_ip لتخزين عنوان IP المسموح به
ALTER TABLE `branches` 
  ADD COLUMN `authorized_ip` VARCHAR(45) DEFAULT NULL COMMENT 'عنوان IP المسموح به للفرع' AFTER `email`;

-- =====================================================
-- القسم 2: تعديل جدول الحضور (attendance)
-- =====================================================

-- حذف حقول الإحداثيات والمسافات
ALTER TABLE `attendance` DROP COLUMN `check_in_lat`;
ALTER TABLE `attendance` DROP COLUMN `check_in_lng`;
ALTER TABLE `attendance` DROP COLUMN `check_out_lat`;
ALTER TABLE `attendance` DROP COLUMN `check_out_lng`;
ALTER TABLE `attendance` DROP COLUMN `check_in_distance`;
ALTER TABLE `attendance` DROP COLUMN `check_out_distance`;

-- إضافة حقل ip_address لتسجيل عنوان IP الذي تم منه الحضور
ALTER TABLE `attendance` 
  ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'عنوان IP الذي تم منه تسجيل الحضور' AFTER `check_out_address`;

-- تعديل check_in_method لتشمل ip_verification بدلاً من auto_gps
ALTER TABLE `attendance`
  MODIFY COLUMN `check_in_method` ENUM('manual','ip_verification') DEFAULT 'manual' COMMENT 'طريقة تسجيل الحضور';

-- تحديث القيم الموجودة من auto_gps إلى ip_verification
UPDATE `attendance` SET `check_in_method` = 'ip_verification' WHERE `check_in_method` = 'auto_gps';

-- =====================================================
-- القسم 3: تعديل جدول المستخدمين (users)
-- =====================================================

-- حذف حقول الموقع الأخير
ALTER TABLE `users` DROP COLUMN `last_latitude`;
ALTER TABLE `users` DROP COLUMN `last_longitude`;

-- =====================================================
-- القسم 4: تعديل جدول employee_schedules
-- =====================================================

-- حذف حقل geofence_radius
ALTER TABLE `employee_schedules` DROP COLUMN `geofence_radius`;

-- تعديل attendance_mode لإزالة location_only و time_and_location
-- وإضافة ip_only و time_and_ip بدلاً منها
ALTER TABLE `employee_schedules`
  MODIFY COLUMN `attendance_mode` ENUM('unrestricted','time_only','ip_only','time_and_ip') NOT NULL DEFAULT 'time_and_ip';

-- تحديث القيم الموجودة
UPDATE `employee_schedules` SET `attendance_mode` = 'ip_only' WHERE `attendance_mode` = 'location_only';
UPDATE `employee_schedules` SET `attendance_mode` = 'time_and_ip' WHERE `attendance_mode` = 'time_and_location';

-- =====================================================
-- القسم 5: تعديل جدول integrity_logs
-- =====================================================

-- حذف حقول الموقع من سجلات النزاهة
ALTER TABLE `integrity_logs` DROP COLUMN `location_lat`;
ALTER TABLE `integrity_logs` DROP COLUMN `location_lng`;

-- =====================================================
-- القسم 6: حذف إعدادات GPS من system_settings
-- =====================================================

-- حذف المفاتيح المتعلقة بالخريطة والإحداثيات
DELETE FROM `system_settings` WHERE `setting_key` IN (
  'map_visibility_mode',
  'main_branch_lat',
  'main_branch_lng'
);

-- =====================================================
-- القسم 7: حذف جدول user_location_history (اختياري)
-- =====================================================

-- يمكن إلغاء التعليق عن السطر التالي إذا أردت حذف الجدول بالكامل
-- DROP TABLE IF EXISTS `user_location_history`;

-- =====================================================
-- القسم 8: تحديث عناوين IP للفروع الموجودة
-- =====================================================
-- 
-- مهم: قم بتعديل عناوين IP التالية حسب بيئتك الفعلية
-- يمكنك استخدام:
--   - IP فردي: '192.168.1.100'
--   - نطاق CIDR: '192.168.1.0/24' (يسمح بجميع الأجهزة من 192.168.1.0 إلى 192.168.1.255)
--   - IPv6: '2001:0db8::/32'
-- 
-- للتحقق من IP الخادم: SELECT @@hostname; أو في PHP: $_SERVER['SERVER_ADDR']
-- للتحقق من IP العميل: $_SERVER['REMOTE_ADDR']
-- =====================================================

-- تحديث IP للفرع الرئيسي (صرح الاتقان الرئيسي)
UPDATE `branches` 
SET `authorized_ip` = '192.168.1.100' 
WHERE `id` = 1 AND `code` = 'SARH01';

-- تحديث IP لفرع كورنر (صرح الاتقان كورنر)
-- يمكنك استخدام IP فردي أو نطاق CIDR
UPDATE `branches` 
SET `authorized_ip` = '192.168.1.101' 
WHERE `id` = 2 AND `code` = 'SARH02';

-- مثال على استخدام نطاق CIDR (يسمح بجميع الأجهزة في النطاق):
-- UPDATE `branches` 
-- SET `authorized_ip` = '192.168.1.0/24' 
-- WHERE `id` = 2 AND `code` = 'SARH02';

-- تحديث IP لصرح الاتقان 2
UPDATE `branches` 
SET `authorized_ip` = '192.168.1.102' 
WHERE `id` = 3 AND `code` = 'SARH03';

-- تحديث IP لفضاء المحركات 1
UPDATE `branches` 
SET `authorized_ip` = '192.168.1.103' 
WHERE `id` = 4 AND `code` = 'FADA01';

-- تحديث IP لفضاء المحركات 2
UPDATE `branches` 
SET `authorized_ip` = '192.168.1.104' 
WHERE `id` = 5 AND `code` = 'FADA02';

-- =====================================================
-- القسم 9: التحقق من التعديلات
-- =====================================================

-- التحقق من وجود حقل authorized_ip في جدول branches
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'branches' 
  AND COLUMN_NAME = 'authorized_ip';

-- التحقق من وجود حقل ip_address في جدول attendance
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'attendance' 
  AND COLUMN_NAME = 'ip_address';

-- عرض عناوين IP المحدثة للفروع
SELECT id, code, name, authorized_ip 
FROM `branches` 
ORDER BY id;

-- =====================================================
-- إنهاء المعاملة
-- =====================================================

COMMIT;

-- =====================================================
-- ملاحظات نهائية:
-- =====================================================
-- 
-- 1. بعد تنفيذ هذا الملف، تأكد من:
--    ✓ تحديث عناوين IP للفروع حسب بيئتك الفعلية
--    ✓ تحديث الكود البرمجي لاستخدام دوال التحقق من IP
--    ✓ اختبار تسجيل الحضور للتأكد من عمل النظام بشكل صحيح
--
-- 2. الرتب المعفاة من قيود IP:
--    - developer (المطور)
--    - super_admin (مدير النظام الكامل)
--
-- 3. ملف PHP المطلوب:
--    استخدم ملف attendance_checkin_ip_verification.php
--    الذي يحتوي على دوال التحقق من IP وتسجيل الحضور
--
-- 4. دعم CIDR:
--    النظام يدعم نطاقات IP باستخدام CIDR notation
--    مثال: '192.168.1.0/24' يسمح ب 256 عنوان IP
--    مثال: '192.168.1.0/28' يسمح ب 16 عنوان IP فقط
--
-- =====================================================
