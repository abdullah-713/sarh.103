<?php
/**
 * صفحة إدارة عناوين IP للفروع
 * 
 * هذه الصفحة تسمح للمديرين بتحديث عناوين IP المسموح بها لكل فرع
 */

session_start();
require_once 'attendance_checkin_ip_verification.php';

// التحقق من الصلاحيات (عدّل حسب نظام الصلاحيات الخاص بك)
if (!isset($_SESSION['user_id']) || !hasPermission($_SESSION['user_id'], 'branches.manage')) {
    header('Location: login.php');
    exit;
}

// تهيئة الاتصال بقاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u850419603_101;charset=utf8mb4",
        "username",
        "password",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_ip':
                updateBranchIP($pdo);
                break;
            case 'test_ip':
                testBranchIP($pdo);
                break;
        }
    }
}

// الحصول على قائمة الفروع
$branches = getBranchesList($pdo);

/**
 * التحقق من الصلاحيات
 */
function hasPermission($user_id, $permission) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT r.permissions 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.is_active = 1
    ");
    $stmt->execute([$user_id]);
    $role = $stmt->fetch();
    
    if (!$role) return false;
    
    $permissions = json_decode($role['permissions'], true);
    
    // التحقق من الصلاحيات
    if (in_array('*', $permissions)) return true;
    if (in_array($permission, $permissions)) return true;
    if (in_array('branches.*', $permissions)) return true;
    
    return false;
}

/**
 * تحديث IP للفرع
 */
function updateBranchIP($pdo) {
    $branch_id = (int)$_POST['branch_id'];
    $authorized_ip = trim($_POST['authorized_ip']);
    
    // التحقق من صحة IP
    if (!empty($authorized_ip) && !isValidIPOrCIDR($authorized_ip)) {
        echo json_encode([
            'success' => false,
            'message' => 'عنوان IP غير صحيح'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE branches 
            SET authorized_ip = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$authorized_ip ?: null, $branch_id]);
        
        // تسجيل النشاط
        logActivity($pdo, $_SESSION['user_id'], 'branches.update_ip', [
            'branch_id' => $branch_id,
            'authorized_ip' => $authorized_ip
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث عنوان IP بنجاح'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * اختبار IP للفرع
 */
function testBranchIP($pdo) {
    $branch_id = (int)$_POST['branch_id'];
    $test_ip = trim($_POST['test_ip']);
    
    $branch = getBranchInfo($branch_id);
    if (!$branch) {
        echo json_encode([
            'success' => false,
            'message' => 'الفرع غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if (empty($branch['authorized_ip'])) {
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم تحديد IP مسموح به للفرع'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $isValid = compareIPAddresses($test_ip, $branch['authorized_ip']);
    
    echo json_encode([
        'success' => true,
        'valid' => $isValid,
        'message' => $isValid 
            ? 'عنوان IP صحيح ومطابق للفرع' 
            : 'عنوان IP غير مطابق للفرع',
        'test_ip' => $test_ip,
        'authorized_ip' => $branch['authorized_ip']
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * الحصول على قائمة الفروع
 */
function getBranchesList($pdo) {
    $stmt = $pdo->query("
        SELECT 
            b.*,
            COUNT(DISTINCT u.id) as employees_count,
            COUNT(DISTINCT a.id) as today_attendance_count
        FROM branches b
        LEFT JOIN users u ON u.branch_id = b.id AND u.is_active = 1
        LEFT JOIN attendance a ON a.branch_id = b.id AND a.date = CURDATE()
        WHERE b.is_active = 1
        GROUP BY b.id
        ORDER BY b.id
    ");
    
    return $stmt->fetchAll();
}

/**
 * التحقق من صحة IP أو CIDR
 */
function isValidIPOrCIDR($ip) {
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

/**
 * تسجيل النشاطات
 */
function logActivity($pdo, $user_id, $action, $details = []) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            getClientIPAddress(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة عناوين IP للفروع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card { margin-bottom: 20px; }
        .ip-input { font-family: monospace; }
        .test-result { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="bi bi-router"></i> إدارة عناوين IP للفروع</h1>
        
        <div class="row">
            <?php foreach ($branches as $branch): ?>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-building"></i> <?= htmlspecialchars($branch['name']) ?>
                            <small class="float-end"><?= htmlspecialchars($branch['code']) ?></small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form class="branch-ip-form" data-branch-id="<?= $branch['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">عنوان IP المسموح به</label>
                                <input type="text" 
                                       class="form-control ip-input" 
                                       name="authorized_ip" 
                                       value="<?= htmlspecialchars($branch['authorized_ip'] ?? '') ?>"
                                       placeholder="مثال: 192.168.1.100 أو 192.168.1.0/24">
                                <small class="form-text text-muted">
                                    يمكنك استخدام IP فردي أو نطاق CIDR
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">اختبار IP</label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control ip-input test-ip-input" 
                                           placeholder="أدخل IP للاختبار"
                                           data-branch-id="<?= $branch['id'] ?>">
                                    <button type="button" 
                                            class="btn btn-outline-secondary test-ip-btn"
                                            data-branch-id="<?= $branch['id'] ?>">
                                        <i class="bi bi-check-circle"></i> اختبار
                                    </button>
                                </div>
                                <div class="test-result" id="test-result-<?= $branch['id'] ?>"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> حفظ
                                </button>
                                <small class="text-muted align-self-center">
                                    الموظفين: <?= $branch['employees_count'] ?> | 
                                    الحضور اليوم: <?= $branch['today_attendance_count'] ?>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="alert alert-info mt-4">
            <h5><i class="bi bi-info-circle"></i> ملاحظات:</h5>
            <ul class="mb-0">
                <li>عنوان IP الفردي: يسمح فقط بهذا العنوان المحدد (مثال: 192.168.1.100)</li>
                <li>نطاق CIDR: يسمح بجميع الأجهزة في النطاق (مثال: 192.168.1.0/24)</li>
                <li>اترك الحقل فارغاً لإلغاء قيود IP للفرع</li>
                <li>الرتب العالية (developer و super_admin) معفاة من قيود IP</li>
            </ul>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // معالجة تحديث IP
        document.querySelectorAll('.branch-ip-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const branchId = form.dataset.branchId;
                const formData = new FormData(form);
                formData.append('action', 'update_ip');
                formData.append('branch_id', branchId);
                
                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جاري الحفظ...';
                
                try {
                    const response = await fetch('admin_branches_ip.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('تم تحديث عنوان IP بنجاح');
                        location.reload();
                    } else {
                        alert('خطأ: ' + result.message);
                    }
                } catch (error) {
                    alert('حدث خطأ: ' + error.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        });
        
        // معالجة اختبار IP
        document.querySelectorAll('.test-ip-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const branchId = btn.dataset.branchId;
                const testIp = btn.previousElementSibling.value;
                const resultDiv = document.getElementById('test-result-' + branchId);
                
                if (!testIp) {
                    resultDiv.innerHTML = '<div class="alert alert-warning">يرجى إدخال IP للاختبار</div>';
                    return;
                }
                
                btn.disabled = true;
                resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> جاري الاختبار...';
                
                const formData = new FormData();
                formData.append('action', 'test_ip');
                formData.append('branch_id', branchId);
                formData.append('test_ip', testIp);
                
                try {
                    const response = await fetch('admin_branches_ip.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        if (result.valid) {
                            resultDiv.innerHTML = `
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> ${result.message}<br>
                                    <small>IP المختبر: ${result.test_ip}<br>IP المسموح: ${result.authorized_ip}</small>
                                </div>
                            `;
                        } else {
                            resultDiv.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="bi bi-x-circle"></i> ${result.message}<br>
                                    <small>IP المختبر: ${result.test_ip}<br>IP المسموح: ${result.authorized_ip}</small>
                                </div>
                            `;
                        }
                    } else {
                        resultDiv.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
                    }
                } catch (error) {
                    resultDiv.innerHTML = `<div class="alert alert-danger">حدث خطأ: ${error.message}</div>`;
                } finally {
                    btn.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
