<?php
/**
 * Setup Wizard - معالج الإعداد الأولي
 * نظام صرح الإتقان - Sarh Al-Itqan
 * 
 * معالج احترافي لإعداد النظام لأول مرة
 */

// التحقق من وجود ملف القفل
$lockFile = __DIR__ . '/setup.lock';
if (file_exists($lockFile)) {
    die('<!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تم الإعداد مسبقاً - صرح الإتقان</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-3xl font-bold mb-4">تم إعداد النظام مسبقاً</h1>
            <p class="text-gray-400 mb-6">النظام تم إعداده بالفعل. لا يمكن تشغيل معالج الإعداد مرة أخرى.</p>
            <a href="dashboard.php" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg inline-block">الانتقال إلى لوحة التحكم</a>
        </div>
    </body>
    </html>');
}

// معالجة الطلبات
$step = $_GET['step'] ?? 1;
$action = $_POST['action'] ?? '';

// إعدادات قاعدة البيانات (سيتم تحديثها من النموذج)
$db_config = [
    'host' => $_POST['db_host'] ?? 'localhost',
    'name' => $_POST['db_name'] ?? 'u850419603_sarh_db',
    'user' => $_POST['db_user'] ?? 'u850419603_sarh_db',
    'pass' => $_POST['db_pass'] ?? 'Goolbx512!!'
];

// معالجة الخطوات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'check_environment':
            checkEnvironment();
            break;
        case 'run_migration':
            runMigration($db_config);
            break;
        case 'configure_branch':
            configureBranch($db_config);
            break;
        case 'create_developer':
            createDeveloper($db_config);
            break;
        case 'complete_setup':
            completeSetup();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

/**
 * فحص البيئة
 */
function checkEnvironment() {
        $checks = [
            'php_version' => [
                'name' => 'إصدار PHP',
                'required' => '7.4',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '7.4', '>=')
            ],
            'pdo' => [
                'name' => 'امتداد PDO',
                'required' => 'مطلوب',
                'current' => extension_loaded('pdo') ? 'مثبت' : 'غير مثبت',
                'passed' => extension_loaded('pdo')
            ],
            'pdo_mysql' => [
                'name' => 'امتداد PDO MySQL',
                'required' => 'مطلوب',
                'current' => extension_loaded('pdo_mysql') ? 'مثبت' : 'غير مثبت',
                'passed' => extension_loaded('pdo_mysql')
            ],
            'json' => [
                'name' => 'امتداد JSON',
                'required' => 'مطلوب',
                'current' => extension_loaded('json') ? 'مثبت' : 'غير مثبت',
                'passed' => extension_loaded('json')
            ]
        ];
        
        // اختبار الاتصال بقاعدة البيانات
        $db_config = [
            'host' => $_POST['db_host'] ?? 'localhost',
            'name' => $_POST['db_name'] ?? 'u850419603_101',
            'user' => $_POST['db_user'] ?? 'username',
            'pass' => $_POST['db_pass'] ?? 'password'
        ];
        
        $db_connected = false;
        $db_error = null;
        
        try {
            $pdo = new PDO(
                "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                $db_config['user'],
                $db_config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $db_connected = true;
        } catch (PDOException $e) {
            $db_error = $e->getMessage();
        }
        
        $checks['database'] = [
            'name' => 'اتصال قاعدة البيانات',
            'required' => 'مطلوب',
            'current' => $db_connected ? 'متصل' : 'غير متصل',
            'passed' => $db_connected,
            'error' => $db_error
        ];
        
        $allPassed = true;
        foreach ($checks as $check) {
            if (!$check['passed']) {
                $allPassed = false;
                break;
            }
        }
        
        echo json_encode([
            'success' => $allPassed,
            'checks' => $checks,
            'all_passed' => $allPassed
        ], JSON_UNESCAPED_UNICODE);
    }

/**
 * تشغيل ملف الترحيل
 */
function runMigration($db_config) {
        try {
            $pdo = new PDO(
                "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                $db_config['user'],
                $db_config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // قراءة ملف migration_complete.sql
            $migrationFile = __DIR__ . '/migration_complete.sql';
            if (!file_exists($migrationFile)) {
                throw new Exception('ملف الترحيل غير موجود: migration_complete.sql');
            }
            
            $sql = file_get_contents($migrationFile);
            
            // إزالة التعليقات من SQL
            $sql = preg_replace('/--.*$/m', '', $sql); // إزالة التعليقات أحادية السطر
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // إزالة التعليقات متعددة الأسطر
            
            // تقسيم SQL إلى عبارات منفصلة بناءً على الفاصلة المنقوطة
            $statements = [];
            $currentStatement = '';
            $inString = false;
            $stringChar = '';
            
            $length = strlen($sql);
            for ($i = 0; $i < $length; $i++) {
                $char = $sql[$i];
                $currentStatement .= $char;
                
                // تتبع حالة النصوص المرفقة بعلامات اقتباس
                if (($char === '"' || $char === "'" || $char === '`') && ($i === 0 || $sql[$i-1] !== '\\')) {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === $stringChar) {
                        $inString = false;
                        $stringChar = '';
                    }
                }
                
                // إذا كانت الفاصلة المنقوطة خارج النص، فهي نهاية العبارة
                if ($char === ';' && !$inString) {
                    $stmt = trim($currentStatement);
                    if (!empty($stmt)) {
                        // تجاهل عبارات SELECT (للتحقق فقط) و COMMIT و SET
                        if (!preg_match('/^\s*(SELECT|COMMIT|SET\s+SQL_MODE|SET\s+time_zone|START\s+TRANSACTION)/i', $stmt)) {
                            $statements[] = $stmt;
                        }
                    }
                    $currentStatement = '';
                }
            }
            
            // إضافة أي عبارة متبقية (بدون فاصلة منقوطة)
            if (!empty(trim($currentStatement))) {
                $stmt = trim($currentStatement);
                if (!preg_match('/^\s*(SELECT|COMMIT|SET\s+SQL_MODE|SET\s+time_zone|START\s+TRANSACTION)/i', $stmt)) {
                    $statements[] = $stmt;
                }
            }
            
            $executed = 0;
            $total = count($statements);
            $errors = [];
            
            foreach ($statements as $statement) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // تجاهل الأخطاء المتوقعة (الأعمدة غير موجودة، مكررة، إلخ)
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    
                    // قائمة الأخطاء التي يمكن تجاهلها
                    $ignorableErrors = [
                        'does not exist',
                        'Unknown column',
                        'Duplicate column',
                        "Can't DROP COLUMN",
                        "Can't DROP",
                        'check that it exists',
                        'already exists',
                        'Duplicate key'
                    ];
                    
                    $isIgnorable = false;
                    
                    // التحقق من رسالة الخطأ
                    foreach ($ignorableErrors as $ignorable) {
                        if (stripos($errorMessage, $ignorable) !== false) {
                            $isIgnorable = true;
                            break;
                        }
                    }
                    
                    // التحقق من كود الخطأ (1091 = Can't DROP COLUMN, 1054 = Unknown column)
                    $errorCodeInt = is_numeric($errorCode) ? (int)$errorCode : 0;
                    if (in_array($errorCodeInt, [1091, 1054, 1060])) {
                        $isIgnorable = true;
                    }
                    
                    // إذا كان الخطأ يمكن تجاهله، نحسبه كنجاح
                    if ($isIgnorable) {
                        $executed++; // نحسبها كنجاح لأنها متوقعة
                    } else {
                        // خطأ حقيقي يجب إظهاره
                        $errors[] = $errorMessage . ' (Statement: ' . substr($statement, 0, 100) . '...)';
                    }
                }
            }
            
            // إذا تم تنفيذ جميع العبارات (حتى مع أخطاء يمكن تجاهلها)، نعتبره نجاحاً
            $allExecuted = ($executed >= $total);
            
            echo json_encode([
                'success' => $allExecuted && empty($errors),
                'executed' => $executed,
                'total' => $total,
                'errors' => $errors,
                'message' => $allExecuted && empty($errors)
                    ? "تم تنفيذ جميع العبارات SQL بنجاح ($executed/$total)"
                    : ($allExecuted 
                        ? "تم تنفيذ جميع العبارات ($executed/$total). بعض الأخطاء تم تجاهلها لأنها متوقعة (أعمدة غير موجودة)."
                        : "تم تنفيذ $executed من $total عبارة SQL. بعض الأخطاء تم تجاهلها لأنها متوقعة.")
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'خطأ في تنفيذ الترحيل: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

/**
 * تكوين الفرع الرئيسي
 */
function configureBranch($db_config) {
        try {
            $authorized_ip = trim($_POST['authorized_ip'] ?? '');
            
            if (empty($authorized_ip)) {
                throw new Exception('يرجى إدخال عنوان IP المسموح به');
            }
            
            // التحقق من صحة IP (يدعم IP فردي و CIDR)
            $isValid = false;
            $errorMessage = 'عنوان IP غير صحيح';
            
            if (strpos($authorized_ip, '/') !== false) {
                // CIDR notation (مثال: 192.168.1.0/24)
                $parts = explode('/', $authorized_ip);
                if (count($parts) === 2) {
                    $subnet = trim($parts[0]);
                    $mask = trim($parts[1]);
                    
                    // التحقق من صحة IP
                    if (filter_var($subnet, FILTER_VALIDATE_IP)) {
                        // التحقق من صحة القناع (0-32 لـ IPv4، 0-128 لـ IPv6)
                        $isIPv6 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                        $maxMask = $isIPv6 ? 128 : 32;
                        
                        if (is_numeric($mask) && $mask >= 0 && $mask <= $maxMask) {
                            $isValid = true;
                        } else {
                            $errorMessage = "قناع CIDR غير صحيح. يجب أن يكون بين 0 و $maxMask";
                        }
                    } else {
                        $errorMessage = 'عنوان IP في نطاق CIDR غير صحيح';
                    }
                } else {
                    $errorMessage = 'صيغة CIDR غير صحيحة. استخدم: IP/MASK (مثال: 192.168.1.0/24)';
                }
            } else {
                // IP فردي (IPv4 أو IPv6)
                if (filter_var($authorized_ip, FILTER_VALIDATE_IP)) {
                    $isValid = true;
                } else {
                    $errorMessage = 'عنوان IP غير صحيح. استخدم IPv4 (مثال: 192.168.1.100) أو IPv6';
                }
            }
            
            if (!$isValid) {
                throw new Exception($errorMessage);
            }
            
            $pdo = new PDO(
                "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                $db_config['user'],
                $db_config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // التحقق من وجود الفرع الرئيسي
            $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = 1");
            $stmt->execute();
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$branch) {
                throw new Exception('الفرع الرئيسي غير موجود في قاعدة البيانات');
            }
            
            // تحديث IP للفرع الرئيسي (ID = 1)
            $stmt = $pdo->prepare("UPDATE branches SET authorized_ip = ? WHERE id = 1");
            $stmt->execute([$authorized_ip]);
            
            // التحقق من أن التحديث تم بنجاح
            if ($stmt->rowCount() === 0) {
                throw new Exception('فشل تحديث عنوان IP. قد يكون العنوان نفسه موجود مسبقاً.');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'تم تحديث عنوان IP للفرع الرئيسي (' . $branch['name'] . ') بنجاح',
                'ip' => $authorized_ip,
                'branch_name' => $branch['name']
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

/**
 * إنشاء حساب المطور
 */
function createDeveloper($db_config) {
        try {
            $full_name = trim($_POST['full_name'] ?? 'Abdullah Al-Kurdi');
            $username = trim($_POST['username'] ?? 'developer');
            $email = trim($_POST['email'] ?? 'developer@sarh.online');
            $password = $_POST['password'] ?? 'Sarh@2026!';
            
            if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                throw new Exception('يرجى ملء جميع الحقول المطلوبة');
            }
            
            $pdo = new PDO(
                "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                $db_config['user'],
                $db_config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $emp_code = 'DEV001';
            $role_id = 6; // developer role
            $branch_id = 1; // main branch
            
            // التحقق من وجود المستخدم (بـ emp_code أو username أو email)
            $stmt = $pdo->prepare("SELECT id, username, email, emp_code FROM users WHERE emp_code = ? OR username = ? OR email = ?");
            $stmt->execute([$emp_code, $username, $email]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // تشفير كلمة المرور
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            if ($existingUser) {
                // المستخدم موجود - تحديثه بدلاً من إنشاء جديد
                $user_id = $existingUser['id'];
                
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        username = ?,
                        email = ?,
                        password_hash = ?,
                        full_name = ?,
                        role_id = ?,
                        branch_id = ?,
                        is_active = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$username, $email, $password_hash, $full_name, $role_id, $branch_id, $user_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث حساب المطور الموجود بنجاح',
                    'user_id' => $user_id,
                    'username' => $username,
                    'updated' => true
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // إنشاء حساب جديد
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        emp_code, username, email, password_hash, full_name,
                        role_id, branch_id, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                
                $stmt->execute([$emp_code, $username, $email, $password_hash, $full_name, $role_id, $branch_id]);
                
                $user_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم إنشاء حساب المطور بنجاح',
                    'user_id' => $user_id,
                    'username' => $username,
                    'updated' => false
                ], JSON_UNESCAPED_UNICODE);
            }
            
        } catch (PDOException $e) {
            // معالجة أخطاء قاعدة البيانات بشكل خاص
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            if ($errorCode == 23000 || strpos($errorMessage, 'Duplicate entry') !== false) {
                // محاولة تحديث المستخدم الموجود
                try {
                    $pdo = new PDO(
                        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                        $db_config['user'],
                        $db_config['pass'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE emp_code = ?");
                    $stmt->execute(['DEV001']);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET
                                username = ?, email = ?, password_hash = ?, full_name = ?,
                                role_id = 6, branch_id = 1, is_active = 1, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $password_hash, $full_name, $user['id']]);
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'تم تحديث حساب المطور الموجود بنجاح',
                            'user_id' => $user['id'],
                            'username' => $username,
                            'updated' => true
                        ], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                } catch (Exception $updateError) {
                    // إذا فشل التحديث، نعرض الخطأ الأصلي
                }
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'خطأ في قاعدة البيانات: ' . $errorMessage
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

/**
 * إكمال الإعداد
 */
function completeSetup() {
        $lockFile = __DIR__ . '/setup.lock';
        file_put_contents($lockFile, date('Y-m-d H:i:s') . "\nSetup completed successfully");
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إكمال الإعداد بنجاح'
        ], JSON_UNESCAPED_UNICODE);
    }
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>معالج الإعداد - صرح الإتقان</title>
        
        <!-- Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
        
        <!-- SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        <!-- Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
            
            * {
                font-family: 'Cairo', sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                min-height: 100vh;
            }
            
            .glass-effect {
                background: rgba(15, 23, 42, 0.8);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .step-indicator {
                transition: all 0.3s ease;
            }
            
            .step-indicator.active {
                background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
                transform: scale(1.1);
            }
            
            .step-indicator.completed {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            }
            
            .check-icon {
                animation: checkmark 0.5s ease-in-out;
            }
            
            @keyframes checkmark {
                0% { transform: scale(0); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }
            
            .progress-bar {
                transition: width 0.5s ease;
            }
            
            .glow-effect {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            }
        </style>
    </head>
    <body class="text-white">
        <div class="min-h-screen py-8 px-4">
            <div class="max-w-4xl mx-auto">
                
                <!-- Header -->
                <div class="glass-effect rounded-2xl p-8 mb-8 text-center glow-effect">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-3xl font-bold">
                            ص
                        </div>
                    </div>
                    <h1 class="text-4xl font-bold mb-2 bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                        صرح الإتقان
                    </h1>
                    <p class="text-gray-400 text-lg">Sarh Al-Itqan</p>
                    <p class="text-gray-500 mt-2">معالج الإعداد الأولي</p>
                </div>
                
                <!-- Progress Steps -->
                <div class="glass-effect rounded-2xl p-6 mb-8">
                    <div class="flex justify-between items-center">
                        <div class="step-indicator active w-12 h-12 rounded-full flex items-center justify-center font-bold" data-step="1">
                            <span class="step-number">1</span>
                        </div>
                        <div class="flex-1 h-1 bg-gray-700 mx-2"></div>
                        <div class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-bold" data-step="2">
                            <span class="step-number">2</span>
                        </div>
                        <div class="flex-1 h-1 bg-gray-700 mx-2"></div>
                        <div class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-bold" data-step="3">
                            <span class="step-number">3</span>
                        </div>
                        <div class="flex-1 h-1 bg-gray-700 mx-2"></div>
                        <div class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-bold" data-step="4">
                            <span class="step-number">4</span>
                        </div>
                    </div>
                    <div class="flex justify-between mt-4 text-sm text-gray-400">
                        <span>فحص البيئة</span>
                        <span>ترحيل قاعدة البيانات</span>
                        <span>تكوين الفرع</span>
                        <span>حساب المطور</span>
                    </div>
                </div>
                
                <!-- Step Content -->
                <div class="glass-effect rounded-2xl p-8">
                    
                    <!-- Step 1: Environment Check -->
                    <div id="step-1" class="step-content">
                        <h2 class="text-2xl font-bold mb-6 flex items-center">
                            <i class="bi bi-shield-check text-blue-400 ml-3"></i>
                            فحص البيئة والاتصال
                        </h2>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">مضيف قاعدة البيانات</label>
                            <input type="text" id="db_host" value="localhost" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">اسم قاعدة البيانات</label>
                            <input type="text" id="db_name" value="u850419603_sarh_db" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">اسم المستخدم</label>
                            <input type="text" id="db_user" value="u850419603_sarh_db" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">كلمة المرور</label>
                            <input type="password" id="db_pass" value="Goolbx512!!" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div id="environment-results" class="mb-6 hidden">
                            <div class="space-y-3">
                                <!-- Results will be inserted here -->
                            </div>
                        </div>
                        
                        <button onclick="checkEnvironment()" 
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 px-6 py-3 rounded-lg font-semibold transition-all glow-effect">
                            <i class="bi bi-search ml-2"></i>
                            فحص البيئة
                        </button>
                    </div>
                    
                    <!-- Step 2: Database Migration -->
                    <div id="step-2" class="step-content hidden">
                        <h2 class="text-2xl font-bold mb-6 flex items-center">
                            <i class="bi bi-database text-green-400 ml-3"></i>
                            ترحيل قاعدة البيانات
                        </h2>
                        
                        <p class="text-gray-400 mb-6">
                            سيتم تنفيذ ملف الترحيل لإزالة بيانات GPS وإعداد النظام للاعتماد على IP فقط.
                        </p>
                        
                        <div id="migration-progress" class="mb-6 hidden">
                            <div class="flex justify-between text-sm mb-2">
                                <span>جاري التنفيذ...</span>
                                <span id="migration-status">0%</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-3">
                                <div id="migration-progress-bar" class="progress-bar bg-gradient-to-r from-blue-600 to-purple-600 h-3 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div id="migration-results" class="mb-6 hidden">
                            <!-- Results will be inserted here -->
                        </div>
                        
                        <button onclick="runMigration()" 
                                class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 px-6 py-3 rounded-lg font-semibold transition-all">
                            <i class="bi bi-play-circle ml-2"></i>
                            تشغيل الترحيل
                        </button>
                    </div>
                    
                    <!-- Step 3: Branch Configuration -->
                    <div id="step-3" class="step-content hidden">
                        <h2 class="text-2xl font-bold mb-6 flex items-center">
                            <i class="bi bi-building text-yellow-400 ml-3"></i>
                            تكوين الفرع الرئيسي
                        </h2>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">
                                عنوان IP المسموح به للفرع الرئيسي
                                <span class="text-red-400">*</span>
                            </label>
                            <input type="text" id="authorized_ip" placeholder="مثال: 192.168.1.100 أو 192.168.1.0/24" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none font-mono">
                            <p class="text-gray-500 text-sm mt-2">
                                <i class="bi bi-info-circle ml-1"></i>
                                يمكنك استخدام IP فردي (مثل 192.168.1.100) أو نطاق CIDR (مثل 192.168.1.0/24)
                            </p>
                        </div>
                        
                        <div id="branch-results" class="mb-6 hidden">
                            <!-- Results will be inserted here -->
                        </div>
                        
                        <button onclick="configureBranch()" 
                                class="w-full bg-gradient-to-r from-yellow-600 to-orange-600 hover:from-yellow-700 hover:to-orange-700 px-6 py-3 rounded-lg font-semibold transition-all">
                            <i class="bi bi-save ml-2"></i>
                            حفظ الإعدادات
                        </button>
                    </div>
                    
                    <!-- Step 4: Developer Account -->
                    <div id="step-4" class="step-content hidden">
                        <h2 class="text-2xl font-bold mb-6 flex items-center">
                            <i class="bi bi-person-badge text-purple-400 ml-3"></i>
                            إنشاء حساب المطور
                        </h2>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">الاسم الكامل</label>
                            <input type="text" id="full_name" value="Abdullah Al-Kurdi" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">اسم المستخدم</label>
                            <input type="text" id="username" value="developer" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" id="email" value="developer@sarh.online" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">كلمة المرور</label>
                            <input type="password" id="password" value="Sarh@2026!" 
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
                            <p class="text-gray-500 text-sm mt-2">
                                <i class="bi bi-shield-lock ml-1"></i>
                                كلمة المرور الافتراضية: Sarh@2026!
                            </p>
                        </div>
                        
                        <div id="developer-results" class="mb-6 hidden">
                            <!-- Results will be inserted here -->
                        </div>
                        
                        <button onclick="createDeveloper()" 
                                class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 px-6 py-3 rounded-lg font-semibold transition-all">
                            <i class="bi bi-person-plus ml-2"></i>
                            إنشاء الحساب
                        </button>
                    </div>
                    
                    <!-- Success Screen -->
                    <div id="success-screen" class="step-content hidden text-center">
                        <div class="mb-6">
                            <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 glow-effect">
                                <i class="bi bi-check-circle text-5xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold mb-4">تم الإعداد بنجاح!</h2>
                            <p class="text-gray-400 text-lg mb-8">
                                تم إعداد نظام صرح الإتقان بنجاح. يمكنك الآن البدء باستخدام النظام.
                            </p>
                        </div>
                        
                        <div class="bg-gray-800 rounded-lg p-6 mb-6 text-right">
                            <h3 class="font-bold mb-4">معلومات الحساب:</h3>
                            <div class="space-y-2 text-sm">
                                <p><span class="text-gray-400">اسم المستخدم:</span> <span id="success-username" class="font-mono">-</span></p>
                                <p><span class="text-gray-400">كلمة المرور:</span> <span id="success-password" class="font-mono">-</span></p>
                            </div>
                        </div>
                        
                    <a href="dashboard.php" 
                       class="inline-block w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 px-8 py-4 rounded-lg font-semibold text-lg transition-all glow-effect">
                        <i class="bi bi-speedometer2 ml-2"></i>
                        الانتقال إلى لوحة التحكم
                    </a>
                    </div>
                    
                </div>
                
            </div>
        </div>
        
        <script>
            let currentStep = 1;
            let dbConfig = {};
            
        // Function to show step
        function showStep(step) {
                document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
                
                if (step === 5) {
                    document.getElementById('success-screen').classList.remove('hidden');
                } else {
                    document.getElementById(`step-${step}`).classList.remove('hidden');
                }
                
                // Update step indicators
                document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                    const stepNum = index + 1;
                    indicator.classList.remove('active', 'completed');
                    
                    if (step === 5) {
                        // All steps completed
                        indicator.classList.add('completed');
                        indicator.innerHTML = '<i class="bi bi-check text-white"></i>';
                    } else if (stepNum < step) {
                        indicator.classList.add('completed');
                        indicator.innerHTML = '<i class="bi bi-check text-white"></i>';
                    } else if (stepNum === step) {
                        indicator.classList.add('active');
                        indicator.innerHTML = `<span class="step-number">${stepNum}</span>`;
                    } else {
                        indicator.innerHTML = `<span class="step-number">${stepNum}</span>`;
                    }
                });
                
                currentStep = step;
            }
            
        // Function to get DB config
        function getDBConfig() {
                return {
                    db_host: document.getElementById('db_host').value,
                    db_name: document.getElementById('db_name').value,
                    db_user: document.getElementById('db_user').value,
                    db_pass: document.getElementById('db_pass').value
                };
            }
            
        // Step 1: Check Environment
        function checkEnvironment() {
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split ml-2"></i> جاري الفحص...';
                
                const formData = new FormData();
                formData.append('action', 'check_environment');
                const config = getDBConfig();
                formData.append('db_host', config.db_host);
                formData.append('db_name', config.db_name);
                formData.append('db_user', config.db_user);
                formData.append('db_pass', config.db_pass);
                
                fetch('setup_wizard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('environment-results');
                    resultsDiv.classList.remove('hidden');
                    resultsDiv.innerHTML = '';
                    
                    let allPassed = true;
                    
                    Object.values(data.checks).forEach(check => {
                        const div = document.createElement('div');
                        div.className = `flex items-center justify-between p-4 rounded-lg ${check.passed ? 'bg-green-900/30 border border-green-700' : 'bg-red-900/30 border border-red-700'}`;
                        
                        div.innerHTML = `
                            <div class="flex items-center">
                                ${check.passed 
                                    ? '<i class="bi bi-check-circle text-green-400 text-xl ml-3 check-icon"></i>' 
                                    : '<i class="bi bi-x-circle text-red-400 text-xl ml-3"></i>'}
                                <div>
                                    <div class="font-semibold">${check.name}</div>
                                    <div class="text-sm text-gray-400">${check.current} ${check.error ? '- ' + check.error : ''}</div>
                                </div>
                            </div>
                            <div class="text-sm text-gray-400">${check.required}</div>
                        `;
                        
                        resultsDiv.appendChild(div);
                        if (!check.passed) allPassed = false;
                    });
                    
                    if (allPassed) {
                        dbConfig = getDBConfig();
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم بنجاح!',
                                text: 'جميع الفحوصات نجحت',
                                confirmButtonText: 'التالي',
                                confirmButtonColor: '#3b82f6'
                            }).then(() => {
                                showStep(2);
                            });
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'فشل الفحص',
                            text: 'يرجى إصلاح المشاكل المذكورة أعلاه',
                            confirmButtonText: 'حسناً',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'حدث خطأ أثناء فحص البيئة: ' + error.message,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#ef4444'
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                });
            }
            
        // Step 2: Run Migration
        function runMigration() {
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split ml-2"></i> جاري التنفيذ...';
                
                const progressDiv = document.getElementById('migration-progress');
                const progressBar = document.getElementById('migration-progress-bar');
                const statusSpan = document.getElementById('migration-status');
                
                progressDiv.classList.remove('hidden');
                
                const formData = new FormData();
                formData.append('action', 'run_migration');
                formData.append('db_host', dbConfig.db_host);
                formData.append('db_name', dbConfig.db_name);
                formData.append('db_user', dbConfig.db_user);
                formData.append('db_pass', dbConfig.db_pass);
                
                // Simulate progress
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 10;
                    if (progress <= 90) {
                        progressBar.style.width = progress + '%';
                        statusSpan.textContent = progress + '%';
                    }
                }, 200);
                
                fetch('setup_wizard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';
                    statusSpan.textContent = '100%';
                    
                    // إذا تم تنفيذ جميع العبارات (حتى مع أخطاء يمكن تجاهلها)، نعتبره نجاحاً
                    const allExecuted = data.executed >= data.total;
                    
                    if (data.success || allExecuted) {
                        const resultsDiv = document.getElementById('migration-results');
                        resultsDiv.classList.remove('hidden');
                        resultsDiv.innerHTML = `
                            <div class="bg-green-900/30 border border-green-700 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="bi bi-check-circle text-green-400 text-xl ml-3"></i>
                                    <div>
                                        <div class="font-semibold">تم تنفيذ الترحيل بنجاح</div>
                                        <div class="text-sm text-gray-400">${data.message}</div>
                                        ${data.errors && data.errors.length > 0 ? '<div class="text-xs text-yellow-400 mt-2">ملاحظة: تم تجاهل بعض الأخطاء المتوقعة (أعمدة غير موجودة)</div>' : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم بنجاح!',
                                text: data.message,
                                confirmButtonText: 'التالي',
                                confirmButtonColor: '#10b981'
                            }).then(() => {
                                showStep(3);
                            });
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الترحيل',
                            html: `<p>${data.message}</p>${data.errors && data.errors.length > 0 ? '<ul class="text-right mt-2 text-sm">' + data.errors.map(e => '<li class="mb-1">' + e + '</li>').join('') + '</ul>' : ''}`,
                            confirmButtonText: 'حسناً',
                            confirmButtonColor: '#ef4444',
                            width: '700px'
                        });
                    }
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'حدث خطأ أثناء تنفيذ الترحيل: ' + error.message,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#ef4444'
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                });
            }
            
        // Step 3: Configure Branch
        function configureBranch() {
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                const authorized_ip = document.getElementById('authorized_ip').value.trim();
                
                if (!authorized_ip) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'حقل مطلوب',
                        text: 'يرجى إدخال عنوان IP المسموح به',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }
                
                // التحقق الأساسي من صيغة IP قبل الإرسال
                const ipPattern = /^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$|^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}(\/\d{1,3})?$/;
                if (!ipPattern.test(authorized_ip)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'صيغة غير صحيحة',
                        html: `
                            <p>عنوان IP المدخل غير صحيح.</p>
                            <p class="text-sm mt-2">أمثلة صحيحة:</p>
                            <ul class="text-right text-sm mt-2">
                                <li>IP فردي: <code>192.168.1.100</code></li>
                                <li>نطاق CIDR: <code>192.168.1.0/24</code></li>
                            </ul>
                        `,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }
                
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split ml-2"></i> جاري الحفظ...';
                
                const formData = new FormData();
                formData.append('action', 'configure_branch');
                formData.append('authorized_ip', authorized_ip);
                formData.append('db_host', dbConfig.db_host);
                formData.append('db_name', dbConfig.db_name);
                formData.append('db_user', dbConfig.db_user);
                formData.append('db_pass', dbConfig.db_pass);
                
                fetch('setup_wizard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const resultsDiv = document.getElementById('branch-results');
                        resultsDiv.classList.remove('hidden');
                        resultsDiv.innerHTML = `
                            <div class="bg-green-900/30 border border-green-700 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="bi bi-check-circle text-green-400 text-xl ml-3"></i>
                                    <div class="flex-1">
                                        <div class="font-semibold">تم التكوين بنجاح</div>
                                        <div class="text-sm text-gray-400 mt-1">
                                            <div>الفرع: <strong>${data.branch_name || 'الفرع الرئيسي'}</strong></div>
                                            <div class="mt-1">IP المسموح به: <code class="bg-gray-800 px-2 py-1 rounded">${data.ip}</code></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح!',
                            html: `
                                <p>${data.message}</p>
                                <div class="mt-3 p-3 bg-gray-800 rounded text-right">
                                    <div class="text-sm"><strong>عنوان IP:</strong> <code>${data.ip}</code></div>
                                </div>
                            `,
                            confirmButtonText: 'التالي',
                            confirmButtonColor: '#f59e0b'
                        }).then(() => {
                            showStep(4);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الحفظ',
                            text: data.message,
                            confirmButtonText: 'حسناً',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الاتصال',
                        text: 'حدث خطأ أثناء الاتصال بالخادم: ' + error.message,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#ef4444'
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                });
            }
            
        // Step 4: Create Developer
        function createDeveloper() {
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                
                const full_name = document.getElementById('full_name').value.trim();
                const username = document.getElementById('username').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                
                if (!full_name || !username || !email || !password) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'حقول مطلوبة',
                        text: 'يرجى ملء جميع الحقول',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }
                
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split ml-2"></i> جاري الإنشاء...';
                
                const formData = new FormData();
                formData.append('action', 'create_developer');
                formData.append('full_name', full_name);
                formData.append('username', username);
                formData.append('email', email);
                formData.append('password', password);
                formData.append('db_host', dbConfig.db_host);
                formData.append('db_name', dbConfig.db_name);
                formData.append('db_user', dbConfig.db_user);
                formData.append('db_pass', dbConfig.db_pass);
                
                fetch('setup_wizard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // إظهار رسالة نجاح مناسبة
                        const actionText = data.updated ? 'تم تحديث' : 'تم إنشاء';
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: data.message,
                            confirmButtonText: 'متابعة',
                            confirmButtonColor: '#8b5cf6'
                        }).then(() => {
                            // Complete setup
                            const completeFormData = new FormData();
                            completeFormData.append('action', 'complete_setup');
                            
                            return fetch('setup_wizard.php', {
                                method: 'POST',
                                body: completeFormData
                            })
                            .then(response => response.json())
                            .then(completeData => {
                                if (completeData.success) {
                                    // Show success screen
                                    document.getElementById('success-username').textContent = username;
                                    document.getElementById('success-password').textContent = password;
                                    showStep(5);
                                    
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'تم الإعداد بنجاح!',
                                        html: `
                                            <p>${data.message}</p>
                                            <p class="mt-3">تم إكمال جميع خطوات الإعداد بنجاح.</p>
                                        `,
                                        confirmButtonText: 'ممتاز',
                                        confirmButtonColor: '#8b5cf6'
                                    });
                                }
                            });
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في إنشاء الحساب',
                            text: data.message,
                            confirmButtonText: 'حسناً',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'حدث خطأ: ' + error.message,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#ef4444'
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                });
            }
        </script>
    </body>
    </html>
