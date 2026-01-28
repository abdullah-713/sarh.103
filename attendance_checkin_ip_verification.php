<?php
/**
 * ملف التحقق من الحضور باستخدام IP
 * 
 * هذا الملف يحتوي على الدوال المحدثة للتحقق من الحضور
 * باستخدام عنوان IP بدلاً من GPS
 */

/**
 * التحقق من صحة عنوان IP للموظف
 * 
 * @param int $user_id معرف الموظف
 * @param string|null $ip_address عنوان IP (إذا لم يتم تمريره، سيتم الحصول عليه تلقائياً)
 * @return array ['valid' => bool, 'message' => string, 'branch_id' => int|null]
 */
function verifyUserIPForAttendance($user_id, $ip_address = null) {
    global $pdo; // افترض أن لديك اتصال PDO
    
    // الحصول على عنوان IP إذا لم يتم تمريره
    if ($ip_address === null) {
        $ip_address = getClientIPAddress();
    }
    
    // التحقق من رتبة المستخدم (استثناء الرتب العالية)
    $user = getUserWithRole($user_id);
    if (!$user) {
        return [
            'valid' => false,
            'message' => 'المستخدم غير موجود',
            'branch_id' => null
        ];
    }
    
    // استثناء الرتب العالية من قيود IP
    $highLevelRoles = ['developer', 'super_admin'];
    if (in_array($user['role_slug'], $highLevelRoles)) {
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
    $branch = getBranchInfo($branch_id);
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
    $isValid = compareIPAddresses($ip_address, $authorized_ip);
    
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
            'expected_ip' => $authorized_ip
        ];
    }
}

/**
 * الحصول على عنوان IP الحقيقي للعميل
 * 
 * @return string
 */
function getClientIPAddress() {
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
function compareIPAddresses($user_ip, $authorized_ip) {
    // إذا كان IP المسموح به يحتوي على CIDR notation
    if (strpos($authorized_ip, '/') !== false) {
        return ipInRange($user_ip, $authorized_ip);
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
function ipInRange($ip, $range) {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    list($subnet, $mask) = explode('/', $range);
    
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return ipv4InRange($ip, $subnet, $mask);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return ipv6InRange($ip, $subnet, $mask);
    }
    
    return false;
}

/**
 * التحقق من IPv4 في نطاق CIDR
 */
function ipv4InRange($ip, $subnet, $mask) {
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);
    
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

/**
 * التحقق من IPv6 في نطاق CIDR
 */
function ipv6InRange($ip, $subnet, $mask) {
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
function checkInWithIPVerification($user_id, $check_in_time = null) {
    global $pdo;
    
    try {
        // التحقق من IP
        $ipVerification = verifyUserIPForAttendance($user_id);
        
        if (!$ipVerification['valid']) {
            return [
                'success' => false,
                'message' => $ipVerification['message'],
                'error_code' => 'IP_NOT_AUTHORIZED'
            ];
        }
        
        // التحقق من عدم وجود سجل حضور لهذا اليوم
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
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
        $ip_address = getClientIPAddress();
        
        // إدراج سجل الحضور
        $stmt = $pdo->prepare("
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
            'attendance_id' => $pdo->lastInsertId(),
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
function getUserWithRole($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
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
function getBranchInfo($branch_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM branches 
        WHERE id = ? AND is_active = 1
    ");
    
    $stmt->execute([$branch_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * مثال على الاستخدام:
 * 
 * // في ملف API أو Controller
 * $result = checkInWithIPVerification($user_id);
 * 
 * if ($result['success']) {
 *     echo json_encode([
 *         'status' => 'success',
 *         'message' => $result['message']
 *     ]);
 * } else {
 *     echo json_encode([
 *         'status' => 'error',
 *         'message' => $result['message'],
 *         'error_code' => $result['error_code']
 *     ]);
 * }
 */
