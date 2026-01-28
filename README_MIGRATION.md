# دليل الترحيل: إزالة GPS والاعتماد على IP فقط

## نظرة عامة

تم تبسيط قاعدة البيانات لإزالة كل ما يتعلق بنظام GPS والمواقع الجغرافية، والاعتماد كلياً على عنوان IP للتحقق من وجود الموظف في الفرع.

## التغييرات المنفذة

### 1. جدول الفروع (`branches`)

**الحقول المحذوفة:**
- `latitude` - خط العرض
- `longitude` - خط الطول  
- `geofence_radius` - نصف قطر السماح بالمتر

**الحقول المضافة:**
- `authorized_ip` VARCHAR(45) - عنوان IP المسموح به للفرع

### 2. جدول الحضور (`attendance`)

**الحقول المحذوفة:**
- `check_in_lat` - خط عرض تسجيل الحضور
- `check_in_lng` - خط طول تسجيل الحضور
- `check_out_lat` - خط عرض تسجيل الانصراف
- `check_out_lng` - خط طول تسجيل الانصراف
- `check_in_distance` - المسافة عند الحضور
- `check_out_distance` - المسافة عند الانصراف

**الحقول المضافة:**
- `ip_address` VARCHAR(45) - عنوان IP الذي تم منه تسجيل الحضور

**التعديلات:**
- تم تعديل `check_in_method` من `ENUM('manual','auto_gps')` إلى `ENUM('manual','ip_verification')`
- تم تحديث جميع السجلات الموجودة من `auto_gps` إلى `ip_verification`

### 3. جدول المستخدمين (`users`)

**الحقول المحذوفة:**
- `last_latitude` - خط العرض الأخير
- `last_longitude` - خط الطول الأخير

### 4. جدول جدول المواعيد (`employee_schedules`)

**الحقول المحذوفة:**
- `geofence_radius` - نصف قطر السماح

**التعديلات:**
- تم تعديل `attendance_mode` من `ENUM('unrestricted','time_only','location_only','time_and_location')` 
  إلى `ENUM('unrestricted','time_only','ip_only','time_and_ip')`
- تم تحديث القيم:
  - `location_only` → `ip_only`
  - `time_and_location` → `time_and_ip`

### 5. جدول سجلات النزاهة (`integrity_logs`)

**الحقول المحذوفة:**
- `location_lat` - خط العرض
- `location_lng` - خط الطول

### 6. جدول الإعدادات العامة (`system_settings`)

**المفاتيح المحذوفة:**
- `map_visibility_mode` - وضع رؤية الخريطة
- `main_branch_lat` - خط عرض المقر الرئيسي
- `main_branch_lng` - خط طول المقر الرئيسي

## خطوات التنفيذ

### 1. نسخ احتياطي

**قبل تنفيذ أي تعديلات، قم بعمل نسخة احتياطية كاملة من قاعدة البيانات:**

```bash
mysqldump -u username -p database_name > backup_before_ip_migration.sql
```

### 2. مراجعة عناوين IP

افتح ملف `migration_complete.sql` وانتقل إلى **القسم 8** لتحديث عناوين IP للفروع حسب بيئتك الفعلية:

```sql
-- مثال: IP فردي
UPDATE `branches` SET `authorized_ip` = '192.168.1.100' WHERE `id` = 1;

-- مثال: نطاق CIDR (يسمح بجميع الأجهزة في النطاق)
UPDATE `branches` SET `authorized_ip` = '192.168.1.0/24' WHERE `id` = 2;
```

**ملاحظة:** يمكن استخدام:
- **IP فردي:** `'192.168.1.100'` - يسمح فقط بهذا العنوان
- **نطاق CIDR:** `'192.168.1.0/24'` - يسمح بجميع الأجهزة من 192.168.1.0 إلى 192.168.1.255
- **IPv6:** `'2001:0db8::/32'` - يدعم النظام أيضاً عناوين IPv6

### 3. تنفيذ ملف SQL الشامل

قم بتنفيذ ملف `migration_complete.sql` على قاعدة البيانات:

**من سطر الأوامر:**
```bash
mysql -u username -p database_name < migration_complete.sql
```

**من phpMyAdmin:**
1. افتح phpMyAdmin
2. اختر قاعدة البيانات
3. اذهب إلى تبويب SQL
4. انسخ محتوى ملف `migration_complete.sql` والصقه
5. اضغط تنفيذ

**ملاحظة:** إذا ظهرت أخطاء "Column does not exist"، يمكن تجاهلها بأمان (يعني أن الحقل غير موجود أصلاً)

### 4. تحديث الكود البرمجي

استخدم ملف `attendance_checkin_ip_verification.php` كمرجع لتحديث منطق التحقق في التطبيق.

**الدوال الرئيسية:**
- `verifyUserIPForAttendance()` - التحقق من صحة IP للموظف
- `checkInWithIPVerification()` - تسجيل الحضور مع التحقق من IP
- `getClientIPAddress()` - الحصول على IP الحقيقي للعميل
- `compareIPAddresses()` - مقارنة عناوين IP (يدعم CIDR)

## منطق التحقق

### القواعد الأساسية:

1. **الرتب العالية:** المستخدمون برتبة `developer` أو `super_admin` معفون من قيود IP ويمكنهم تسجيل الحضور من أي مكان.

2. **التحقق من IP:** للمستخدمين الآخرين، يتم مقارنة IP الحالي (`$_SERVER['REMOTE_ADDR']`) مع `authorized_ip` الخاص بفرعهم.

3. **دعم CIDR:** يمكن تحديد نطاق IP باستخدام CIDR notation (مثل `192.168.1.0/24`).

### مثال على الاستخدام:

```php
// في ملف API أو Controller
require_once 'attendance_checkin_ip_verification.php';

$result = checkInWithIPVerification($user_id);

if ($result['success']) {
    echo json_encode([
        'status' => 'success',
        'message' => $result['message'],
        'ip_address' => $result['ip_address']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => $result['message'],
        'error_code' => $result['error_code']
    ]);
}
```

## ملاحظات مهمة

### 1. عنوان IP الحقيقي

الملف `attendance_checkin_ip_verification.php` يحتوي على دالة `getClientIPAddress()` التي تحاول الحصول على IP الحقيقي للعميل حتى في حالة استخدام Cloudflare أو Proxy.

### 2. جدول user_location_history

تم تعليق أمر حذف جدول `user_location_history` في ملف SQL. إذا لم تعد هناك حاجة لهذا الجدول، يمكن إلغاء التعليق عن السطر:

```sql
DROP TABLE IF EXISTS `user_location_history`;
```

### 3. تحديث البيانات الموجودة

- تم تحديث جميع سجلات الحضور من `auto_gps` إلى `ip_verification` تلقائياً
- تم تحديث `attendance_mode` في `employee_schedules` تلقائياً

### 4. الاختبار

بعد التنفيذ، تأكد من:
- ✅ تسجيل الحضور يعمل بشكل صحيح
- ✅ التحقق من IP يعمل للمستخدمين العاديين
- ✅ الرتب العالية يمكنها تسجيل الحضور من أي IP
- ✅ رسائل الخطأ واضحة عند فشل التحقق

## الملفات المطلوبة

### ملف SQL الشامل
- **`migration_complete.sql`** - ملف SQL واحد شامل يحتوي على جميع التعديلات وتحديث عناوين IP

### ملف PHP للتحقق
- **`attendance_checkin_ip_verification.php`** - يحتوي على دوال التحقق من IP وتسجيل الحضور

### ملف التوثيق
- **`README_MIGRATION.md`** - هذا الملف - دليل الترحيل الكامل

## الدعم

في حالة وجود أي مشاكل أو استفسارات، يرجى مراجعة:
- ملف SQL الشامل: `migration_complete.sql`
- ملف PHP: `attendance_checkin_ip_verification.php`
- هذا الملف: `README_MIGRATION.md`
