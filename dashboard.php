<?php
/**
 * لوحة التحكم الرئيسية - نظام صرح الإتقان
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// معلومات المستخدم
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
$role_name = $_SESSION['role_name'] ?? '';
$branch_name = $_SESSION['branch_name'] ?? '';

// تسجيل الخروج
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - صرح الإتقان</title>
    
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
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="text-white">
    
    <!-- Header -->
    <header class="glass-effect border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-xl font-bold ml-4">
                        ص
                    </div>
                    <div>
                        <h1 class="text-xl font-bold bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                            صرح الإتقان
                        </h1>
                        <p class="text-sm text-gray-400">نظام إدارة الحضور والانصراف</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <div class="text-right">
                        <p class="font-medium"><?php echo htmlspecialchars($full_name); ?></p>
                        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($role_name); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-600 rounded-full flex items-center justify-center">
                        <i class="bi bi-person text-white"></i>
                    </div>
                    <a href="?logout=1" 
                       class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm transition-all"
                       onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                        <i class="bi bi-box-arrow-right ml-1"></i>
                        خروج
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Welcome Section -->
        <div class="glass-effect rounded-2xl p-8 mb-8 glow-effect">
            <div class="text-center">
                <h2 class="text-3xl font-bold mb-4">مرحباً بك، <?php echo htmlspecialchars($full_name); ?>!</h2>
                <p class="text-gray-400 text-lg">
                    أهلاً وسهلاً بك في نظام صرح الإتقان لإدارة الحضور والانصراف
                </p>
                <div class="mt-4 inline-flex items-center bg-green-900/30 border border-green-700 rounded-lg px-4 py-2">
                    <i class="bi bi-check-circle text-green-400 ml-2"></i>
                    <span class="text-green-300">تم تسجيل الدخول بنجاح</span>
                </div>
            </div>
        </div>
        
        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- User Info Card -->
            <div class="glass-effect rounded-xl p-6 card-hover transition-all">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-person-badge text-white text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm text-gray-400">المستخدم</p>
                        <p class="font-bold"><?php echo htmlspecialchars($username); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Role Card -->
            <div class="glass-effect rounded-xl p-6 card-hover transition-all">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-shield-check text-white text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm text-gray-400">الدور</p>
                        <p class="font-bold"><?php echo htmlspecialchars($role_name); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Branch Card -->
            <div class="glass-effect rounded-xl p-6 card-hover transition-all">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-building text-white text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm text-gray-400">الفرع</p>
                        <p class="font-bold"><?php echo htmlspecialchars($branch_name ?: 'غير محدد'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- System Status Card -->
            <div class="glass-effect rounded-xl p-6 card-hover transition-all">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-check-circle text-white text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <p class="text-sm text-gray-400">حالة النظام</p>
                        <p class="font-bold text-green-400">نشط</p>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Quick Actions -->
        <div class="glass-effect rounded-2xl p-8">
            <h3 class="text-2xl font-bold mb-6 flex items-center">
                <i class="bi bi-lightning text-yellow-400 ml-3"></i>
                الإجراءات السريعة
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Attendance Card -->
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-6 card-hover transition-all cursor-pointer">
                    <div class="text-center">
                        <i class="bi bi-clock text-4xl mb-4"></i>
                        <h4 class="text-xl font-bold mb-2">الحضور والانصراف</h4>
                        <p class="text-blue-100 mb-4">تسجيل الحضور والانصراف اليومي</p>
                        <button class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-all">
                            قريباً
                        </button>
                    </div>
                </div>
                
                <!-- Reports Card -->
                <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl p-6 card-hover transition-all cursor-pointer">
                    <div class="text-center">
                        <i class="bi bi-graph-up text-4xl mb-4"></i>
                        <h4 class="text-xl font-bold mb-2">التقارير</h4>
                        <p class="text-purple-100 mb-4">عرض تقارير الحضور والإحصائيات</p>
                        <button class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-all">
                            قريباً
                        </button>
                    </div>
                </div>
                
                <!-- Settings Card -->
                <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-xl p-6 card-hover transition-all cursor-pointer">
                    <div class="text-center">
                        <i class="bi bi-gear text-4xl mb-4"></i>
                        <h4 class="text-xl font-bold mb-2">الإعدادات</h4>
                        <p class="text-green-100 mb-4">إدارة إعدادات النظام والحساب</p>
                        <button class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-all">
                            قريباً
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- System Info -->
        <div class="mt-8 glass-effect rounded-2xl p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center">
                <i class="bi bi-info-circle text-blue-400 ml-3"></i>
                معلومات النظام
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-400">الإصدار:</span>
                    <span class="font-medium mr-2">1.0.0</span>
                </div>
                <div>
                    <span class="text-gray-400">آخر تحديث:</span>
                    <span class="font-medium mr-2"><?php echo date('Y-m-d'); ?></span>
                </div>
                <div>
                    <span class="text-gray-400">الحالة:</span>
                    <span class="text-green-400 font-medium mr-2">مُفعل</span>
                </div>
            </div>
        </div>
        
    </main>
    
    <!-- Footer -->
    <footer class="mt-16 glass-effect border-t border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> صرح الإتقان. جميع الحقوق محفوظة.</p>
                <p class="text-sm mt-2">تم تطوير النظام بواسطة فريق صرح الإتقان</p>
            </div>
        </div>
    </footer>
    
</body>
</html>