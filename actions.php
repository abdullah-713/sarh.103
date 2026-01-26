<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📋 صفحة الإجراءات - Actions Management Page
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'الإجراءات';
$currentPage = 'actions';
$bodyClass = 'actions-page';

$userId = current_user_id();
$roleLevel = current_role_level();

include INCLUDES_PATH . '/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════ */
/* 🎨 أنماط صفحة الإجراءات */
/* ═══════════════════════════════════════════════════════════════ */

.actions-page {
    background: #f0f2f5;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.stat-pending .stat-icon { background: #fff3cd; color: #856404; }
.stat-progress .stat-icon { background: #cfe2ff; color: #084298; }
.stat-waiting .stat-icon { background: #e7f1ff; color: #0a58ca; }
.stat-completed .stat-icon { background: #d1e7dd; color: #0f5132; }

.filters-bar {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 2px solid #dee2e6;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.filter-btn:hover {
    border-color: #ff6f00;
    color: #ff6f00;
}

.filter-btn.active {
    background: #ff6f00;
    border-color: #ff6f00;
    color: white;
}

.actions-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.action-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: all 0.2s;
    border-right: 4px solid #dee2e6;
}

.action-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    transform: translateX(-4px);
}

.action-header {
    margin-bottom: 0.75rem;
}

.action-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.status-pending { background: #ffc107; }
.status-progress { background: #17a2b8; }
.status-waiting { background: #0d6efd; }
.status-approved { background: #28a745; }
.status-completed { background: #28a745; }
.status-rejected { background: #dc3545; }
.status-cancelled { background: #6c757d; }

.action-title {
    font-weight: 600;
    color: #212529;
    margin: 0;
}

.action-meta {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.action-description {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0.75rem 0;
    line-height: 1.5;
}

.action-footer {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid #f0f0f0;
}

.badge-outline {
    background: transparent;
    border: 1px solid currentColor;
    color: #6c757d;
}

/* لوحة التفاصيل الجانبية */
.details-panel {
    position: fixed;
    top: 0;
    left: -100%;
    width: 90%;
    max-width: 500px;
    height: 100vh;
    background: white;
    box-shadow: 0 0 20px rgba(0,0,0,0.3);
    z-index: 1050;
    transition: left 0.3s;
    overflow-y: auto;
}

.details-panel.show {
    left: 0;
}

.details-header {
    background: linear-gradient(135deg, #ff6f00, #ff8f00);
    color: white;
    padding: 1.5rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.details-body {
    padding: 1.5rem;
}

.details-section {
    margin-bottom: 1.5rem;
}

.details-section h6 {
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #212529;
}

.timeline {
    position: relative;
    padding-right: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    right: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    right: 0;
    top: 0;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px currentColor;
}

.timeline-content {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 8px;
    margin-left: 0.5rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .details-panel {
        width: 100%;
        max-width: none;
    }
}
</style>

<div id="actionsApp" class="container py-4">
    <!-- العنوان الرئيسي -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-clipboard-check text-primary me-2"></i>
            الإجراءات
        </h4>
    </div>
    
    <!-- الإحصائيات -->
    <div class="stats-grid">
        <div class="stat-card stat-pending">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value" id="statPending">0</div>
            <div class="stat-label">قيد الانتظار</div>
        </div>
        
        <div class="stat-card stat-progress">
            <div class="stat-icon">
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <div class="stat-value" id="statInProgress">0</div>
            <div class="stat-label">قيد التنفيذ</div>
        </div>
        
        <div class="stat-card stat-waiting">
            <div class="stat-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value" id="statWaitingApproval">0</div>
            <div class="stat-label">بانتظار الموافقة</div>
        </div>
        
        <div class="stat-card stat-completed">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value" id="statCompleted">0</div>
            <div class="stat-label">مكتملة</div>
        </div>
    </div>
    
    <!-- الفلاتر -->
    <div class="filters-bar">
        <button class="filter-btn active" data-filter="my">
            <i class="bi bi-person-circle me-1"></i>
            إجراءاتي
        </button>
        <button class="filter-btn" data-filter="assigned">
            <i class="bi bi-person-badge me-1"></i>
            المكلف بها
        </button>
        <?php if ($roleLevel >= ROLE_MANAGER): ?>
        <button class="filter-btn" data-filter="pending_approval">
            <i class="bi bi-clipboard-check me-1"></i>
            تحتاج موافقة
        </button>
        <button class="filter-btn" data-filter="all">
            <i class="bi bi-list-ul me-1"></i>
            الكل
        </button>
        <?php endif; ?>
    </div>
    
    <!-- قائمة الإجراءات -->
    <div class="actions-container" id="actionsListContainer">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
        </div>
    </div>
</div>

<!-- لوحة التفاصيل الجانبية -->
<div id="actionDetailsPanel" class="details-panel">
    <div class="details-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5 id="detailTitle" class="mb-1">عنوان الإجراء</h5>
                <small id="detailCode">#ACT-2026-00001</small>
            </div>
            <button class="btn btn-sm btn-light" id="closeDetailsBtn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mt-3" id="detailStatus">
            <span class="badge bg-warning">قيد الانتظار</span>
        </div>
    </div>
    
    <div class="details-body">
        <!-- الوصف -->
        <div class="details-section">
            <h6><i class="bi bi-file-text me-2"></i>الوصف</h6>
            <p id="detailDescription" class="text-muted">-</p>
        </div>
        
        <!-- معلومات أساسية -->
        <div class="details-section">
            <h6><i class="bi bi-info-circle me-2"></i>معلومات أساسية</h6>
            <table class="table table-sm">
                <tr>
                    <th width="40%">مقدم الطلب:</th>
                    <td id="detailRequester">-</td>
                </tr>
                <tr>
                    <th>تاريخ الإنشاء:</th>
                    <td id="detailDate">-</td>
                </tr>
            </table>
        </div>
        
        <!-- السجل الزمني -->
        <div class="details-section">
            <h6><i class="bi bi-clock-history me-2"></i>السجل الزمني</h6>
            <div class="timeline" id="detailTimeline">
                <p class="text-muted">جاري التحميل...</p>
            </div>
        </div>
        
        <!-- إضافة تعليق -->
        <div class="details-section">
            <h6><i class="bi bi-chat-dots me-2"></i>إضافة تعليق</h6>
            <div class="input-group">
                <textarea id="commentInput" class="form-control" rows="2" placeholder="اكتب تعليقك هنا..."></textarea>
            </div>
            <button class="btn btn-primary btn-sm mt-2 w-100" id="addCommentBtn">
                <i class="bi bi-send me-1"></i>
                إرسال
            </button>
        </div>
        
        <!-- أزرار الإجراءات -->
        <?php if ($roleLevel >= ROLE_MANAGER): ?>
        <div class="details-section">
            <h6><i class="bi bi-gear me-2"></i>إجراءات</h6>
            <div class="d-grid gap-2">
                <button class="btn btn-success btn-sm" onclick="ActionsApp.changeStatus('approved')">
                    <i class="bi bi-check-circle me-1"></i>
                    الموافقة
                </button>
                <button class="btn btn-danger btn-sm" onclick="ActionsApp.changeStatus('rejected')">
                    <i class="bi bi-x-circle me-1"></i>
                    الرفض
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- تحميل JavaScript -->
<script src="<?= asset('js/actions.js') ?>?v=<?= time() ?>"></script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
