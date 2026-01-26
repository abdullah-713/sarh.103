<?php
/**
 * إرسال إشعار - Send Notification
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();
require_permission('send_notifications');

$pageTitle = 'إرسال إشعار';
$currentPage = 'notifications';

$success = '';
$error = '';

// معالجة الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'خطأ في التحقق من الأمان';
    } else {
        $title = clean_input($_POST['title'] ?? '');
        $message = clean_input($_POST['message'] ?? '');
        $type = clean_input($_POST['type'] ?? 'info');
        $scope_type = clean_input($_POST['scope_type'] ?? 'global');
        $scope_id = !empty($_POST['scope_id']) ? (int)$_POST['scope_id'] : null;
        
        if (empty($title) || empty($message)) {
            $error = 'يرجى ملء جميع الحقول المطلوبة';
        } else {
            try {
                Database::insert('notifications', [
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'scope_type' => $scope_type,
                    'scope_id' => $scope_id,
                    'created_by' => current_user_id()
                ]);
                $success = 'تم إرسال الإشعار بنجاح!';
                log_activity('send_notification', 'notifications', $title);
            } catch (Exception $e) {
                $error = 'حدث خطأ أثناء الإرسال';
            }
        }
    }
}

// جلب الفروع والمستخدمين
$branches = Database::fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
$users = Database::fetchAll("SELECT id, full_name, emp_code FROM users WHERE is_active = 1 ORDER BY full_name");

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <h4 class="mb-4">
        <i class="bi bi-megaphone-fill text-primary me-2"></i>
        إرسال إشعار
    </h4>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                
                <div class="mb-3">
                    <label class="form-label">نوع الإشعار *</label>
                    <select name="type" class="form-select" required>
                        <option value="info">ℹ️ معلومات</option>
                        <option value="success">✅ نجاح</option>
                        <option value="warning">⚠️ تحذير</option>
                        <option value="danger">🚨 تنبيه هام</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">عنوان الإشعار *</label>
                    <input type="text" name="title" class="form-control" required maxlength="255" placeholder="عنوان قصير وواضح">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">نص الإشعار *</label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="اكتب محتوى الإشعار هنا..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">نطاق الإرسال *</label>
                    <select name="scope_type" class="form-select" id="scopeType" onchange="toggleScopeId()">
                        <option value="global">🌍 الجميع (كل المستخدمين)</option>
                        <option value="branch">🏢 فرع محدد</option>
                        <option value="user">👤 مستخدم محدد</option>
                    </select>
                </div>
                
                <div class="mb-3" id="branchSelect" style="display:none;">
                    <label class="form-label">اختر الفرع</label>
                    <select name="scope_id" class="form-select" id="branchId">
                        <option value="">-- اختر الفرع --</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>"><?= e($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3" id="userSelect" style="display:none;">
                    <label class="form-label">اختر المستخدم</label>
                    <select name="scope_id" class="form-select" id="userId">
                        <option value="">-- اختر المستخدم --</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= e($user['full_name']) ?> (<?= e($user['emp_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-3">
                    <i class="bi bi-send me-2"></i>
                    إرسال الإشعار
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleScopeId() {
    const scopeType = document.getElementById('scopeType').value;
    document.getElementById('branchSelect').style.display = scopeType === 'branch' ? 'block' : 'none';
    document.getElementById('userSelect').style.display = scopeType === 'user' ? 'block' : 'none';
    
    document.getElementById('branchId').name = scopeType === 'branch' ? 'scope_id' : '';
    document.getElementById('userId').name = scopeType === 'user' ? 'scope_id' : '';
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
