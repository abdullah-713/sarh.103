<?php
/**
 * سكربت إعادة تعيين المستخدمين
 * Reset Users Script
 */

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='utf-8'><title>إعادة تعيين المستخدمين</title>";
echo "<style>
body{font-family:Tahoma,Arial;padding:30px;background:#1a1a2e;color:#eee;max-width:800px;margin:0 auto;}
h1{color:#ff6f00;border-bottom:2px solid #ff6f00;padding-bottom:15px;}
.success{background:linear-gradient(135deg,#28a745,#20c997);padding:15px;margin:10px 0;border-radius:8px;}
.error{background:linear-gradient(135deg,#dc3545,#c82333);padding:15px;margin:10px 0;border-radius:8px;}
.info{background:linear-gradient(135deg,#17a2b8,#138496);padding:15px;margin:10px 0;border-radius:8px;}
.user-box{background:#2d2d44;padding:20px;margin:15px 0;border-radius:10px;border-right:4px solid #ff6f00;}
.user-box h3{margin:0 0 10px 0;color:#ff6f00;}
.user-box p{margin:5px 0;}
.credentials{background:#1a1a2e;padding:10px;border-radius:5px;font-family:monospace;margin-top:10px;}
a.btn{display:inline-block;background:#ff6f00;color:#fff;padding:12px 25px;border-radius:8px;text-decoration:none;margin-top:20px;}
</style></head><body>";

echo "<h1>🔄 إعادة تعيين المستخدمين</h1>";

try {
    $pdo = Database::getInstance();
    
    // حذف جميع المستخدمين
    echo "<div class='info'>⏳ جاري حذف المستخدمين الحاليين...</div>";
    
    // حذف السجلات المرتبطة أولاً
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE FROM activity_log WHERE user_id IS NOT NULL");
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM leaves");
    $pdo->exec("DELETE FROM users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div class='success'>✅ تم حذف جميع المستخدمين</div>";
    
    // التأكد من وجود الأدوار
    $roles = $pdo->query("SELECT id, role_level FROM roles ORDER BY role_level DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($roles)) {
        // إنشاء الأدوار الافتراضية
        $pdo->exec("INSERT INTO roles (id, name, slug, role_level, is_active) VALUES 
            (1, 'موظف', 'employee', 1, 1),
            (2, 'مشرف', 'supervisor', 2, 1),
            (3, 'مدير', 'manager', 3, 1),
            (4, 'مدير أول', 'senior_manager', 4, 1),
            (5, 'مدير النظام', 'admin', 5, 1),
            (6, 'المدير العام', 'super_admin', 10, 1)
        ");
        echo "<div class='success'>✅ تم إنشاء الأدوار الافتراضية</div>";
    }
    
    // التأكد من وجود فرع
    $branch = $pdo->query("SELECT id FROM branches LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$branch) {
        $pdo->exec("INSERT INTO branches (id, name, code, is_active) VALUES (1, 'المقر الرئيسي', 'HQ', 1)");
        echo "<div class='success'>✅ تم إنشاء الفرع الرئيسي</div>";
    }
    
    // الحصول على role_id للمدير
    $adminRole = $pdo->query("SELECT id FROM roles WHERE role_level >= 5 ORDER BY role_level DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $adminRoleId = $adminRole['id'] ?? 5;
    
    $employeeRole = $pdo->query("SELECT id FROM roles WHERE role_level = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $employeeRoleId = $employeeRole['id'] ?? 1;
    
    // إنشاء المستخدمين الجدد
    $users = [
        [
            'emp_code' => 'ADMIN001',
            'username' => 'admin',
            'email' => 'admin@sarh.online',
            'password' => 'Admin@123456',
            'full_name' => 'مدير النظام',
            'role_id' => $adminRoleId,
            'branch_id' => 1
        ],
        [
            'emp_code' => 'EMP001',
            'username' => 'employee1',
            'email' => 'emp1@sarh.online',
            'password' => 'Emp@123456',
            'full_name' => 'أحمد محمد',
            'role_id' => $employeeRoleId,
            'branch_id' => 1
        ],
        [
            'emp_code' => 'EMP002',
            'username' => 'employee2',
            'email' => 'emp2@sarh.online',
            'password' => 'Emp@123456',
            'full_name' => 'سارة عبدالله',
            'role_id' => $employeeRoleId,
            'branch_id' => 1
        ]
    ];
    
    echo "<h2>👥 المستخدمون الجدد:</h2>";
    
    $stmt = $pdo->prepare("INSERT INTO users (emp_code, username, email, password_hash, full_name, role_id, branch_id, is_active, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    
    foreach ($users as $user) {
        $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $user['emp_code'],
            $user['username'],
            $user['email'],
            $passwordHash,
            $user['full_name'],
            $user['role_id'],
            $user['branch_id']
        ]);
        
        $roleType = $user['role_id'] == $adminRoleId ? '👑 مدير النظام' : '👤 موظف';
        
        echo "<div class='user-box'>";
        echo "<h3>{$roleType}: {$user['full_name']}</h3>";
        echo "<p><strong>كود الموظف:</strong> {$user['emp_code']}</p>";
        echo "<div class='credentials'>";
        echo "<p><strong>اسم المستخدم:</strong> {$user['username']}</p>";
        echo "<p><strong>كلمة المرور:</strong> {$user['password']}</p>";
        echo "</div>";
        echo "</div>";
    }
    
    echo "<div class='success' style='font-size:18px;margin-top:20px;'>🎉 تم إنشاء المستخدمين بنجاح!</div>";
    
    echo "<a href='../login.php' class='btn'>🔐 الذهاب لصفحة تسجيل الدخول</a>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
