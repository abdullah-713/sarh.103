<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - صفحة الإجراءات والمهام
 * Sarh Al-Itqan - Actions & Tasks Management Page
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 1.0.0
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'الإجراءات والمهام';
$currentPage = 'actions';

$userId = current_user_id();
$roleLevel = current_role_level();

// Load action integrations
if (file_exists(INCLUDES_PATH . '/action_integrations.php')) {
    require_once INCLUDES_PATH . '/action_integrations.php';
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';

// Build query conditions
$conditions = ["a.deleted_at IS NULL"];
$params = [];

// Filter by user role
if ($roleLevel < ROLE_MANAGER) {
    // Show only user's own actions and actions needing their approval
    $conditions[] = "(a.requester_id = :user_id OR a.current_approver_id = :user_id OR a.assigned_to = :user_id)";
    $params['user_id'] = $userId;
} elseif (!empty($filter)) {
    if ($filter === 'my_requests') {
        $conditions[] = "a.requester_id = :user_id";
        $params['user_id'] = $userId;
    } elseif ($filter === 'pending_approval') {
        $conditions[] = "a.current_approver_id = :user_id";
        $params['user_id'] = $userId;
    } elseif ($filter === 'assigned_to_me') {
        $conditions[] = "a.assigned_to = :user_id";
        $params['user_id'] = $userId;
    }
}

// Filter by type
if ($type !== 'all' && in_array($type, ['request', 'task', 'approval', 'complaint', 'suggestion', 'other'])) {
    $conditions[] = "a.type = :type";
    $params['type'] = $type;
}

// Filter by status
if ($status !== 'all' && in_array($status, ['draft', 'pending', 'in_progress', 'waiting_approval', 'approved', 'rejected', 'completed', 'cancelled'])) {
    $conditions[] = "a.status = :status";
    $params['status'] = $status;
}

$whereClause = implode(' AND ', $conditions);

// Fetch actions
try {
    $actions = Database::fetchAll("
        SELECT 
            a.*,
            u.full_name as requester_name,
            u.emp_code as requester_code,
            assigned.full_name as assigned_name,
            approver.full_name as approver_name,
            (SELECT COUNT(*) FROM action_comments WHERE action_id = a.id) as comments_count
        FROM actions a
        LEFT JOIN users u ON a.requester_id = u.id
        LEFT JOIN users assigned ON a.assigned_to = assigned.id
        LEFT JOIN users approver ON a.current_approver_id = approver.id
        WHERE {$whereClause}
        ORDER BY a.created_at DESC
        LIMIT 50
    ", $params);
    
    // Get statistics
    $stats = [
        'total' => Database::fetchValue("SELECT COUNT(*) FROM actions a WHERE {$whereClause}", $params),
        'pending' => Database::fetchValue("SELECT COUNT(*) FROM actions a WHERE {$whereClause} AND a.status IN ('pending', 'waiting_approval')", $params),
        'approved' => Database::fetchValue("SELECT COUNT(*) FROM actions a WHERE {$whereClause} AND a.status = 'approved'", $params),
        'completed' => Database::fetchValue("SELECT COUNT(*) FROM actions a WHERE {$whereClause} AND a.status = 'completed'", $params),
    ];
    
    // Get pending approvals count for current user
    $pendingApprovals = Database::fetchValue(
        "SELECT COUNT(*) FROM actions WHERE current_approver_id = :user_id AND status IN ('pending', 'waiting_approval') AND deleted_at IS NULL",
        ['user_id' => $userId]
    );
    
} catch (Exception $e) {
    $actions = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0];
    $pendingApprovals = 0;
}

// Get action types and templates
try {
    $actionTypes = Database::fetchAll("
        SELECT type, COUNT(*) as count 
        FROM actions 
        WHERE deleted_at IS NULL 
        GROUP BY type
    ");
    
    $templates = Database::fetchAll("
        SELECT * FROM action_templates 
        WHERE is_active = 1 
        ORDER BY name
    ");
} catch (Exception $e) {
    $actionTypes = [];
    $templates = [];
}

include INCLUDES_PATH . '/header.php';
?>

<style>
.actions-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin: -1rem -12px 0;
    border-radius: 0 0 30px 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: rgba(102, 126, 234, 0.2);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.action-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    border-left: 4px solid #dee2e6;
}

.action-card:hover {
    transform: translateX(-4px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.action-card.priority-urgent {
    border-left-color: #dc3545;
}

.action-card.priority-high {
    border-left-color: #fd7e14;
}

.action-card.priority-medium {
    border-left-color: #ffc107;
}

.action-card.priority-low {
    border-left-color: #28a745;
}

.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.filter-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.filter-pill {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    background: #f8f9fa;
    color: #6c757d;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.filter-pill:hover, .filter-pill.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
</style>

<!-- Hero Section -->
<div class="actions-hero">
    <div class="container">
        <h2 class="mb-3">
            <i class="bi bi-list-check me-2"></i>
            الإجراءات والمهام
        </h2>
        <p class="opacity-75 mb-0">إدارة ومتابعة الإجراءات والطلبات والمهام</p>
    </div>
</div>

<div class="container py-4">
    
    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                    <i class="bi bi-list-ul"></i>
                </div>
                <h3 class="mb-0"><?= $stats['total'] ?></h3>
                <small class="text-muted">إجمالي الإجراءات</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3 class="mb-0"><?= $stats['pending'] ?></h3>
                <small class="text-muted">قيد الانتظار</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3 class="mb-0"><?= $stats['approved'] ?></h3>
                <small class="text-muted">موافق عليها</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                    <i class="bi bi-check-all"></i>
                </div>
                <h3 class="mb-0"><?= $stats['completed'] ?></h3>
                <small class="text-muted">مكتملة</small>
            </div>
        </div>
    </div>
    
    <!-- Filters and Actions -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="bi bi-funnel me-2"></i>
                    التصفية
                </h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newActionModal">
                    <i class="bi bi-plus-lg me-1"></i>
                    إجراء جديد
                </button>
            </div>
            
            <div class="filter-pills">
                <a href="?filter=all" class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-grid-3x3-gap me-1"></i>
                    الكل
                </a>
                <a href="?filter=my_requests" class="filter-pill <?= $filter === 'my_requests' ? 'active' : '' ?>">
                    <i class="bi bi-person-fill me-1"></i>
                    طلباتي
                </a>
                <?php if ($pendingApprovals > 0): ?>
                <a href="?filter=pending_approval" class="filter-pill <?= $filter === 'pending_approval' ? 'active' : '' ?>">
                    <i class="bi bi-clipboard-check me-1"></i>
                    تحتاج موافقتي
                    <span class="badge bg-danger ms-1"><?= $pendingApprovals ?></span>
                </a>
                <?php endif; ?>
                <a href="?filter=assigned_to_me" class="filter-pill <?= $filter === 'assigned_to_me' ? 'active' : '' ?>">
                    <i class="bi bi-person-check me-1"></i>
                    المعينة لي
                </a>
            </div>
            
            <div class="row g-2">
                <div class="col-md-6">
                    <select class="form-select" onchange="location.href='?filter=<?= $filter ?>&type=' + this.value">
                        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>كل الأنواع</option>
                        <option value="request" <?= $type === 'request' ? 'selected' : '' ?>>طلب</option>
                        <option value="task" <?= $type === 'task' ? 'selected' : '' ?>>مهمة</option>
                        <option value="approval" <?= $type === 'approval' ? 'selected' : '' ?>>موافقة</option>
                        <option value="complaint" <?= $type === 'complaint' ? 'selected' : '' ?>>شكوى</option>
                        <option value="suggestion" <?= $type === 'suggestion' ? 'selected' : '' ?>>اقتراح</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <select class="form-select" onchange="location.href='?filter=<?= $filter ?>&status=' + this.value">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>كل الحالات</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>قيد التنفيذ</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>موافق عليها</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>مكتملة</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوضة</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>ملغاة</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions List -->
    <div class="actions-list">
        <?php if (empty($actions)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox display-1 text-muted mb-3"></i>
            <h5 class="text-muted">لا توجد إجراءات</h5>
            <p class="text-muted">ابدأ بإنشاء إجراء جديد</p>
        </div>
        <?php else: ?>
        <?php foreach ($actions as $action): ?>
        <?php
        $statusColors = [
            'draft' => 'secondary',
            'pending' => 'warning',
            'in_progress' => 'info',
            'waiting_approval' => 'primary',
            'approved' => 'success',
            'rejected' => 'danger',
            'completed' => 'success',
            'cancelled' => 'dark'
        ];
        $statusLabels = [
            'draft' => 'مسودة',
            'pending' => 'قيد الانتظار',
            'in_progress' => 'قيد التنفيذ',
            'waiting_approval' => 'تحتاج موافقة',
            'approved' => 'موافق عليها',
            'rejected' => 'مرفوضة',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة'
        ];
        
        $typeIcons = [
            'request' => 'bi-send',
            'task' => 'bi-list-task',
            'approval' => 'bi-clipboard-check',
            'complaint' => 'bi-exclamation-triangle',
            'suggestion' => 'bi-lightbulb',
            'other' => 'bi-question-circle'
        ];
        ?>
        <div class="action-card priority-<?= e($action['priority']) ?>" onclick="viewAction(<?= $action['id'] ?>)" style="cursor: pointer;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi <?= $typeIcons[$action['type']] ?? 'bi-question-circle' ?> text-primary"></i>
                        <h6 class="mb-0"><?= e($action['title']) ?></h6>
                    </div>
                    <div class="d-flex align-items-center gap-3 text-muted small">
                        <span>
                            <i class="bi bi-tag me-1"></i>
                            <?= e($action['action_code']) ?>
                        </span>
                        <span>
                            <i class="bi bi-person me-1"></i>
                            <?= e($action['requester_name']) ?>
                        </span>
                        <span>
                            <i class="bi bi-calendar me-1"></i>
                            <?= date('Y-m-d', strtotime($action['created_at'])) ?>
                        </span>
                        <?php if ($action['comments_count'] > 0): ?>
                        <span>
                            <i class="bi bi-chat-dots me-1"></i>
                            <?= $action['comments_count'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge bg-<?= $statusColors[$action['status']] ?>">
                        <?= $statusLabels[$action['status']] ?>
                    </span>
                </div>
            </div>
            <?php if ($action['description']): ?>
            <p class="text-muted small mb-2" style="white-space: pre-line;"><?= e(substr($action['description'], 0, 200)) ?><?= strlen($action['description']) > 200 ? '...' : '' ?></p>
            <?php endif; ?>
            <?php if ($action['assigned_name']): ?>
            <div class="small text-info">
                <i class="bi bi-person-check me-1"></i>
                معين لـ: <?= e($action['assigned_name']) ?>
            </div>
            <?php endif; ?>
            <?php if ($action['approver_name'] && in_array($action['status'], ['pending', 'waiting_approval'])): ?>
            <div class="small text-warning">
                <i class="bi bi-clock-history me-1"></i>
                في انتظار موافقة: <?= e($action['approver_name']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- New Action Modal -->
<div class="modal fade" id="newActionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>
                    إجراء جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newActionForm">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">النوع *</label>
                        <select name="type" class="form-select" required>
                            <option value="">-- اختر النوع --</option>
                            <option value="request">طلب</option>
                            <option value="task">مهمة</option>
                            <option value="complaint">شكوى</option>
                            <option value="suggestion">اقتراح</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">العنوان *</label>
                        <input type="text" name="title" class="form-control" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">الأولوية</label>
                            <select name="priority" class="form-select">
                                <option value="low">منخفضة</option>
                                <option value="medium" selected>متوسطة</option>
                                <option value="high">عالية</option>
                                <option value="urgent">عاجلة</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الفئة</label>
                            <input type="text" name="category" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">تاريخ الاستحقاق</label>
                        <input type="date" name="due_date" class="form-control" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>
                        إنشاء الإجراء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewAction(actionId) {
    // TODO: Implement action details view
    window.location.href = `actions.php?view=${actionId}`;
}

document.getElementById('newActionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        action: 'create',
        type: formData.get('type'),
        title: formData.get('title'),
        description: formData.get('description'),
        priority: formData.get('priority'),
        category: formData.get('category'),
        due_date: formData.get('due_date')
    };
    
    try {
        const response = await fetch('<?= url('api/actions/handler.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?= csrf_token() ?>'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                text: result.message || 'تم إنشاء الإجراء بنجاح',
                confirmButtonText: 'حسناً'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: result.message || 'حدث خطأ أثناء إنشاء الإجراء',
                confirmButtonText: 'حسناً'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'حدث خطأ في الاتصال بالخادم',
            confirmButtonText: 'حسناً'
        });
    }
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
