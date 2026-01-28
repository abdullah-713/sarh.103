<?php
/**
 * فئة التحقق من IP للتحضور
 * 
 * فئة منظمة تحتوي على جميع دوال التحقق من IP
 */

class IPVerification {
    private $pdo;
    private $highLevelRoles = ['developer', 'super_admin'];
    
    /**
     * Constructor
     * 
     * @param PDO $pdo اتصال قاعدة البيانات
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * التحقق من صحة عنوان IP للموظف (Alias for verifyUserIP)
     * 
     * @param int $user_id معرف الموظف
     * @param string|null $ip_address عنوان IP (إذا لم يتم تمريره، سيتم الحصول عليه تلقائياً)
     * @return array ['valid' => bool, 'message' => string, 'branch_id' => int|null, 'ip_address' => string, 'expected_ip' => string|null]
     */
    public function verify($user_id, $ip_address = null) {
        return $this->verifyUserIP($user_id, $ip_address);
    }
    
    /**
     * التحقق من صحة عنوان IP للموظف
     * 
     * @param int $user_id معرف الموظف
     * @param string|null $ip_address عنوان IP (إذا لم يتم تمريره، سيتم الحصول عليه تلقائياً)
     * @return array ['valid' => bool, 'message' => string, 'branch_id' => int|null, 'ip_address' => string, 'expected_ip' => string|null]
     */
    public function verifyUserIP($user_id, $ip_address = null) {
        // الحصول على عنوان IP إذا لم يتم تمريره
        if ($ip_address === null) {
            $ip_address = $this->getClientIPAddress();
        }
        
        // التحقق من رتبة المستخدم (استثناء الرتب العالية)
        $user = $this->getUserWithRole($user_id);
        if (!$user) {
            return [
                'valid' => false,
                'message' => 'المستخدم غير موجود',
                'branch_id' => null
            ];
        }
        
        // استثناء الرتب العالية من قيود IP
        if (in_array($user['role_slug'], $this->highLevelRoles)) {
            return [
                'valid' => true,
                'message' => 'الرتبة العالية - السماح من أي IP',
                'branch_id' => $user['branch_id'],
                'bypass_ip' => true
            ];
        }
        
        // الحصول على الفرع الخاص بالموظف
        $branch_id = $user['branch_id'];
        if (!$branch_id) {
            return [
                'valid' => false,
                'message' => 'المستخدم غير مرتبط بفرع',
                'branch_id' => null
            ];
        }
        
        // الحصول على معلومات الفرع
        $branch = $this->getBranchInfo($branch_id);
        if (!$branch) {
            return [
                'valid' => false,
                'message' => 'الفرع غير موجود',
                'branch_id' => null
            ];
        }
        
        // التحقق من IP المسموح به
        if (empty($branch['authorized_ip'])) {
            return [
                'valid' => false,
                'message' => 'لم يتم تحديد IP مسموح به للفرع',
                'branch_id' => $branch_id
            ];
        }
        
        // مقارنة IP
        $authorized_ip = trim($branch['authorized_ip']);
        $isValid = $this->compareIPAddresses($ip_address, $authorized_ip);
        
        if ($isValid) {
            return [
                'valid' => true,
                'message' => 'تم التحقق من IP بنجاح',
                'branch_id' => $branch_id,
                'ip_address' => $ip_address
            ];
        } else {
            return [
                'valid' => false,
                'message' => 'عنوان IP غير مسموح به. IP الحالي: ' . $ip_address . '، IP المطلوب: ' . $authorized_ip,
                'branch_id' => $branch_id,
                'ip_address' => $ip_address,
                'expected_ip' => $authorized_ip,
                'user_id' => $user_id,
                'user_role' => $user['role_slug'] ?? null
            ];
        }
    }
    
    /**
     * الحصول على عنوان IP الحقيقي للعميل
     * 
     * @return string
     */
    public function getClientIPAddress() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    // التحقق من صحة IP
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        // إذا لم يتم العثور على IP عام، استخدم REMOTE_ADDR
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * مقارنة عناوين IP (يدعم CIDR notation)
     * 
     * @param string $user_ip عنوان IP المستخدم
     * @param string $authorized_ip عنوان IP المسموح به (يمكن أن يكون CIDR مثل 192.168.1.0/24)
     * @return bool
     */
    public function compareIPAddresses($user_ip, $authorized_ip) {
        // إذا كان IP المسموح به يحتوي على CIDR notation
        if (strpos($authorized_ip, '/') !== false) {
            return $this->ipInRange($user_ip, $authorized_ip);
        }
        
        // مقارنة مباشرة
        return $user_ip === $authorized_ip;
    }
    
    /**
     * التحقق من وجود IP ضمن نطاق CIDR
     * 
     * @param string $ip عنوان IP للتحقق
     * @param string $range نطاق CIDR (مثل 192.168.1.0/24)
     * @return bool
     */
    public function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4InRange($ip, $subnet, $mask);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, $mask);
        }
        
        return false;
    }
    
    /**
     * التحقق من IPv4 في نطاق CIDR
     */
    private function ipv4InRange($ip, $subnet, $mask) {
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    /**
     * التحقق من IPv6 في نطاق CIDR
     */
    private function ipv6InRange($ip, $subnet, $mask) {
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);
        
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        
        $mask_bytes = (int)$mask / 8;
        $mask_bits = (int)$mask % 8;
        
        // مقارنة البايتات الكاملة
        for ($i = 0; $i < $mask_bytes; $i++) {
            if ($ip_bin[$i] !== $subnet_bin[$i]) {
                return false;
            }
        }
        
        // مقارنة البتات المتبقية
        if ($mask_bits > 0) {
            $mask_byte = 0xFF << (8 - $mask_bits);
            if ((ord($ip_bin[$mask_bytes]) & $mask_byte) !== (ord($subnet_bin[$mask_bytes]) & $mask_byte)) {
                return false;
            }
        }
        
        return true;
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
            // التحقق من IP
            $ipVerification = $this->verifyUserIP($user_id);
            
            if (!$ipVerification['valid']) {
                return [
                    'success' => false,
                    'message' => $ipVerification['message'],
                    'error_code' => 'IP_NOT_AUTHORIZED'
                ];
            }
            
            // التحقق من عدم وجود سجل حضور لهذا اليوم
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT id FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$user_id, $today]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'تم تسجيل الحضور مسبقاً لهذا اليوم',
                    'error_code' => 'ALREADY_CHECKED_IN'
                ];
            }
            
            // تحديد وقت الحضور
            if ($check_in_time === null) {
                $check_in_time = date('H:i:s');
            }
            
            // الحصول على عنوان IP
            $ip_address = $this->getClientIPAddress();
            
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
            
            return [
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح',
                'attendance_id' => $this->pdo->lastInsertId(),
                'ip_address' => $ip_address,
                'bypass_ip' => $ipVerification['bypass_ip'] ?? false
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage(),
                'error_code' => 'DATABASE_ERROR'
            ];
        }
    }
    
    /**
     * الحصول على معلومات المستخدم مع الرتبة
     * 
     * @param int $user_id
     * @return array|null
     */
    private function getUserWithRole($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.slug as role_slug, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND u.is_active = 1
        ");
        
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على معلومات الفرع
     * 
     * @param int $branch_id
     * @return array|null
     */
    private function getBranchInfo($branch_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM branches 
            WHERE id = ? AND is_active = 1
        ");
        
        $stmt->execute([$branch_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * التحقق من صحة IP أو CIDR
     * 
     * @param string $ip
     * @return bool
     */
    public function isValidIPOrCIDR($ip) {
        // التحقق من CIDR
        if (strpos($ip, '/') !== false) {
            list($subnet, $mask) = explode('/', $ip);
            
            if (!filter_var($subnet, FILTER_VALIDATE_IP)) {
                return false;
            }
            
            $mask = (int)$mask;
            if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $mask >= 0 && $mask <= 32;
            } elseif (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $mask >= 0 && $mask <= 128;
            }
            
            return false;
        }
        
        // التحقق من IP عادي
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
