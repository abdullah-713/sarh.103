<?php
/**
 * صفحة تسجيل الدخول - نظام صرح الإتقان
 */

session_start();

// إذا كان المستخدم مسجل دخول بالفعل، انتقل إلى لوحة التحكم
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// معالجة تسجيل الدخول
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        try {
            // الاتصال بقاعدة البيانات
            $pdo = new PDO(
                "mysql:host=localhost;dbname=u850419603_sarh_db;charset=utf8mb4",
                "u850419603_sarh_db",
                "Goolbx512!!",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // البحث عن المستخدم
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, u.password_hash, u.full_name, 
                       u.role_id, u.branch_id, u.is_active,
                       r.name as role_name, r.slug as role_slug,
                       b.name as branch_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // تسجيل دخول ناجح
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['role_slug'] = $user['role_slug'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['branch_name'] = $user['branch_name'];
                
                // تحديث آخر تسجيل دخول
                $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_activity_at = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            }
        } catch (PDOException $e) {
            $error = 'خطأ في الاتصال بقاعدة البيانات';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - صرح الإتقان</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
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
        
        .glow-effect {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="text-white">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-3xl font-bold mb-6 glow-effect">
                    ص
                </div>
                <h2 class="text-3xl font-bold mb-2 bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                    صرح الإتقان
                </h2>
                <p class="text-gray-400">تسجيل الدخول إلى النظام</p>
            </div>
            
            <!-- Login Form -->
            <div class="glass-effect rounded-2xl p-8 glow-effect">
                <?php if ($error): ?>
                <div class="mb-6 bg-red-900/30 border border-red-700 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="bi bi-exclamation-triangle text-red-400 text-xl ml-3"></i>
                        <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium mb-2">
                            <i class="bi bi-person ml-2"></i>
                            اسم المستخدم أو البريد الإلكتروني
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none input-glow transition-all"
                               placeholder="أدخل اسم المستخدم أو البريد الإلكتروني">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium mb-2">
                            <i class="bi bi-lock ml-2"></i>
                            كلمة المرور
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 focus:border-blue-500 focus:outline-none input-glow transition-all"
                               placeholder="أدخل كلمة المرور">
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 px-6 py-3 rounded-lg font-semibold transition-all glow-effect">
                        <i class="bi bi-box-arrow-in-right ml-2"></i>
                        تسجيل الدخول
                    </button>
                </form>
                
                <!-- Default Accounts Info -->
                <div class="mt-8 pt-6 border-t border-gray-700">
                    <h3 class="text-sm font-medium mb-4 text-gray-300">
                        <i class="bi bi-info-circle ml-2"></i>
                        الحسابات الافتراضية:
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="bg-gray-800/50 rounded-lg p-3">
                            <div class="font-medium text-purple-300">حساب المطور:</div>
                            <div class="text-gray-400 mt-1">
                                المستخدم: <code class="text-purple-300">developer</code><br>
                                كلمة المرور: <code class="text-purple-300">Sarh@2026!</code>
                            </div>
                        </div>
                        <div class="bg-gray-800/50 rounded-lg p-3">
                            <div class="font-medium text-blue-300">مدير النظام:</div>
                            <div class="text-gray-400 mt-1">
                                المستخدم: <code class="text-blue-300">admin</code><br>
                                كلمة المرور: <code class="text-blue-300">admin123</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center text-gray-500 text-sm">
                <p>&copy; <?php echo date('Y'); ?> صرح الإتقان. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </div>
</body>
</html>