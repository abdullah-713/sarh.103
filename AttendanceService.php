<?php
/**
 * Attendance Service - خدمة متكاملة لتسجيل الحضور والانصراف
 * 
 * يدمج التحقق من IP مع تسجيل الحضور والانصراف
 * ويسجل جميع المحاولات الفاشلة في activity_log لإطعام نظام الملف النفسي
 */

require_once 'IPVerification.php';

class AttendanceService {
    private $pdo;
    private $ipVerifier;
    private $integrityLogger;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo اتصال قاعدة البيانات
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ipVerifier = new IPVerification($pdo);
        $this->integrityLogger = new IntegrityLogger($pdo);
    }
    
    /**
     * تسجيل الحضور مع التحقق من IP
     * 
     * @param int $user_id معرف الموظف
     * @param string|null $check_in_time وقت الحضور (null = الآن)
     * @return array
     */
    public function checkIn($user_id, $check_in_time = null) {
        try {
            // التحقق من IP باستخدام verify() method
            $ipVerification = $this->ipVerifier->verify($user_id);
            
            // تسجيل محاولة التحقق (حتى لو فشلت)
            $this->logIPVerificationAttempt($user_id, $ipVerification);
            
            // إذا فشل التحقق من IP
            if (!$ipVerification['valid']) {
                // تسجيل في activity_log للملف النفسي
                $this->integrityLogger->logFailedIPAttempt($user_id, $ipVerification);
                
                return [
                    'success' => false,
                    'message' => $ipVerification['message'],
                    'error_code' => 'IP_NOT_AUTHORIZED',
                    'ip_address' => $ipVerification['ip_address'] ?? null,
                    'expected_ip' => $ipVerification['expected_ip'] ?? null
                ];
            }
            
            // التحقق من عدم وجود سجل حضور لهذا اليوم
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT id, check_in_time FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$user_id, $today]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'تم تسجيل الحضور مسبقاً لهذا اليوم',
                    'error_code' => 'ALREADY_CHECKED_IN',
                    'existing_check_in' => $existing['check_in_time']
                ];
            }
            
            // تحديد وقت الحضور
            if ($check_in_time === null) {
                $check_in_time = date('H:i:s');
            }
            
            // الحصول على عنوان IP
            $ip_address = $this->ipVerifier->getClientIPAddress();
            
            // إدراج سجل الحضور
            $stmt = $this->pdo->prepare("
                INSERT INTO attendance (
                    user_id, 
                    branch_id, 
                    recorded_branch_id,
                    date, 
                    check_in_time, 
                    ip_address,
                    check_in_method,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'ip_verification', 'present', NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $ipVerification['branch_id'],
                $ipVerification['branch_id'],
                $today,
                $check_in_time,
                $ip_address
            ]);
            
            $attendance_id = $this->pdo->lastInsertId();
            
            // تسجيل نجاح الحضور في activity_log
            $this->logActivity($user_id, 'attendance.checkin', [
                'attendance_id' => $attendance_id,
                'ip_address' => $ip_address,
                'branch_id' => $ipVerification['branch_id'],
                'bypass_ip' => $ipVerification['bypass_ip'] ?? false,
                'check_in_time' => $check_in_time
            ]);
            
            return [
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح',
                'attendance_id' => $attendance_id,
                'ip_address' => $ip_address,
                'branch_id' => $ipVerification['branch_id'],
                'bypass_ip' => $ipVerification['bypass_ip'] ?? false,
                'check_in_time' => $check_in_time,
                'date' => $today
            ];
            
        } catch (PDOException $e) {
            // تسجيل خطأ قاعدة البيانات
            $this->logActivity($user_id, 'attendance.checkin_error', [
                'error' => $e->getMessage(),
                'ip_address' => $this->ipVerifier->getClientIPAddress()
            ]);
            
            return [
                'success' => false,
                'message' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage(),
                'error_code' => 'DATABASE_ERROR'
            ];
        }
    }
    
    /**
     * تسجيل الانصراف
     * 
     * @param int $user_id معرف الموظف
     * @param string|null $check_out_time وقت الانصراف (null = الآن)
     * @return array
     */
    public function checkOut($user_id, $check_out_time = null) {
        try {
            $today = date('Y-m-d');
            
            // البحث عن سجل الحضور لهذا اليوم
            $stmt = $this->pdo->prepare("
                SELECT id, check_in_time, check_out_time, branch_id
                FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$user_id, $today]);
            $attendance = $stmt->fetch();
            
            if (!$attendance) {
                return [
                    'success' => false,
                    'message' => 'لم يتم العثور على سجل حضور لهذا اليوم',
                    'error_code' => 'NO_CHECK_IN_RECORD'
                ];
            }
            
            if ($attendance['check_out_time']) {
                return [
                    'success' => false,
                    'message' => 'تم تسجيل الانصراف مسبقاً',
                    'error_code' => 'ALREADY_CHECKED_OUT',
                    'existing_check_out' => $attendance['check_out_time']
                ];
            }
            
            // التحقق من IP (اختياري - يمكن إزالته إذا لم تكن هناك حاجة)
            $ipVerification = $this->ipVerifier->verify($user_id);
            $ip_address = $this->ipVerifier->getClientIPAddress();
            
            // تحديث سجل الانصراف
            if ($check_out_time === null) {
                $check_out_time = date('H:i:s');
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE attendance 
                SET check_out_time = ?,
                    ip_address = COALESCE(ip_address, ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$check_out_time, $ip_address, $attendance['id']]);
            
            // حساب ساعات العمل
            $workMinutes = $this->calculateWorkMinutes($attendance['check_in_time'], $check_out_time);
            
            // تحديث ساعات العمل
            $stmt = $this->pdo->prepare("
                UPDATE attendance 
                SET work_minutes = ?
                WHERE id = ?
            ");
            $stmt->execute([$workMinutes, $attendance['id']]);
            
            // تسجيل الانصراف في activity_log
            $this->logActivity($user_id, 'attendance.checkout', [
                'attendance_id' => $attendance['id'],
                'check_out_time' => $check_out_time,
                'work_minutes' => $workMinutes,
                'ip_address' => $ip_address
            ]);
            
            return [
                'success' => true,
                'message' => 'تم تسجيل الانصراف بنجاح',
                'attendance_id' => $attendance['id'],
                'check_in_time' => $attendance['check_in_time'],
                'check_out_time' => $check_out_time,
                'work_minutes' => $workMinutes,
                'work_hours' => round($workMinutes / 60, 2),
                'ip_address' => $ip_address
            ];
            
        } catch (PDOException $e) {
            $this->logActivity($user_id, 'attendance.checkout_error', [
                'error' => $e->getMessage(),
                'ip_address' => $this->ipVerifier->getClientIPAddress()
            ]);
            
            return [
                'success' => false,
                'message' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage(),
                'error_code' => 'DATABASE_ERROR'
            ];
        }
    }
    
    /**
     * الحصول على حالة الحضور اليوم
     * 
     * @param int $user_id
     * @return array
     */
    public function getTodayStatus($user_id) {
        $today = date('Y-m-d');
        
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                b.name as branch_name,
                b.code as branch_code,
                b.authorized_ip
            FROM attendance a
            LEFT JOIN branches b ON a.branch_id = b.id
            WHERE a.user_id = ? AND a.date = ?
        ");
        $stmt->execute([$user_id, $today]);
        $attendance = $stmt->fetch();
        
        $ipVerification = $this->ipVerifier->verify($user_id);
        
        return [
            'attendance' => $attendance,
            'current_ip' => $this->ipVerifier->getClientIPAddress(),
            'ip_verified' => $ipVerification['valid'],
            'ip_message' => $ipVerification['message'],
            'can_check_in' => !$attendance || !$attendance['check_in_time'],
            'can_check_out' => $attendance && $attendance['check_in_time'] && !$attendance['check_out_time']
        ];
    }
    
    /**
     * حساب دقائق العمل
     */
    private function calculateWorkMinutes($check_in, $check_out) {
        $check_in_time = strtotime($check_in);
        $check_out_time = strtotime($check_out);
        
        if ($check_out_time < $check_in_time) {
            // إذا كان الانصراف في اليوم التالي
            $check_out_time += 86400; // إضافة 24 ساعة
        }
        
        return round(($check_out_time - $check_in_time) / 60);
    }
    
    /**
     * تسجيل محاولة التحقق من IP
     */
    private function logIPVerificationAttempt($user_id, $verification) {
        $this->logActivity($user_id, 'ip_verification.attempt', [
            'valid' => $verification['valid'],
            'ip_address' => $verification['ip_address'] ?? null,
            'expected_ip' => $verification['expected_ip'] ?? null,
            'branch_id' => $verification['branch_id'] ?? null,
            'bypass_ip' => $verification['bypass_ip'] ?? false
        ]);
    }
    
    /**
     * تسجيل النشاطات في activity_log
     */
    private function logActivity($user_id, $action, $details = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_log (
                    user_id, 
                    action, 
                    model_type,
                    model_id,
                    new_values,
                    ip_address, 
                    user_agent, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $model_type = null;
            $model_id = null;
            
            // استخراج model_type و model_id من التفاصيل إذا كانا موجودين
            if (isset($details['attendance_id'])) {
                $model_type = 'attendance';
                $model_id = $details['attendance_id'];
            }
            
            $stmt->execute([
                $user_id,
                $action,
                $model_type,
                $model_id,
                json_encode($details, JSON_UNESCAPED_UNICODE),
                $this->ipVerifier->getClientIPAddress(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}

/**
 * Integrity Logger - مسجل النزاهة للملف النفسي
 * 
 * يسجل المحاولات الفاشلة في activity_log بشكل مناسب
 * لإطعام نظام الملف النفسي (Psychological Profile)
 */
class IntegrityLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تسجيل محاولة IP فاشلة للملف النفسي
     * 
     * @param int $user_id
     * @param array $verification_result نتيجة التحقق من IP
     */
    public function logFailedIPAttempt($user_id, $verification_result) {
        try {
            // الحصول على معلومات المستخدم
            $user = $this->getUserInfo($user_id);
            
            // إعداد تفاصيل المحاولة الفاشلة
            $details = [
                'action_type' => 'unauthorized_ip_attempt',
                'severity' => 'medium',
                'ip_address' => $verification_result['ip_address'] ?? null,
                'expected_ip' => $verification_result['expected_ip'] ?? null,
                'branch_id' => $verification_result['branch_id'] ?? null,
                'user_role' => $verification_result['user_role'] ?? null,
                'message' => $verification_result['message'] ?? 'IP verification failed',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // تسجيل في activity_log
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_log (
                    user_id,
                    action,
                    model_type,
                    model_id,
                    old_values,
                    new_values,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                'integrity.unauthorized_ip_attempt',
                'attendance',
                null,
                json_encode([
                    'user_role' => $user['role_slug'] ?? null,
                    'branch_id' => $verification_result['branch_id'] ?? null
                ], JSON_UNESCAPED_UNICODE),
                json_encode($details, JSON_UNESCAPED_UNICODE),
                $verification_result['ip_address'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // تسجيل أيضاً في integrity_logs إذا كان الجدول موجوداً
            $this->logToIntegrityLogs($user_id, $details);
            
        } catch (PDOException $e) {
            error_log("Failed to log failed IP attempt: " . $e->getMessage());
        }
    }
    
    /**
     * تسجيل في integrity_logs
     */
    private function logToIntegrityLogs($user_id, $details) {
        try {
            // التحقق من وجود الجدول
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'integrity_logs'");
            if (!$stmt->fetch()) {
                return; // الجدول غير موجود
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO integrity_logs (
                    user_id,
                    action_type,
                    target_type,
                    target_id,
                    details,
                    severity,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                'unauthorized_ip_attempt',
                'attendance',
                null,
                json_encode($details, JSON_UNESCAPED_UNICODE),
                'medium',
                $details['ip_address'],
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            // تجاهل الأخطاء - integrity_logs قد لا يكون موجوداً
            error_log("Failed to log to integrity_logs: " . $e->getMessage());
        }
    }
    
    /**
     * الحصول على معلومات المستخدم
     */
    private function getUserInfo($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.slug as role_slug, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
