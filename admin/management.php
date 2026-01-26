<?php
/**
 * SARH System - Management Dashboard
 * لوحة إدارة الفروع والموظفين والنزاهة
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

check_login();
require_role(5);

$user_id = $_SESSION['user_id'];
$role_level = $_SESSION['role_level'] ?? 1;
$is_super_admin = ($role_level >= 10);

$page_title = 'مركز الإدارة';
$active_tab = $_GET['tab'] ?? 'branches';

// Fetch data based on tab
try {
    $branches = Database::fetchAll("SELECT b.*, (SELECT COUNT(*) FROM users WHERE branch_id = b.id) AS employee_count FROM branches b ORDER BY b.name");
    $roles = Database::fetchAll("SELECT * FROM roles ORDER BY role_level DESC");
} catch (Exception $e) {
    $branches = [];
    $roles = [];
}

$employees = [];
if ($active_tab === 'employees') {
    try {
        $employees = Database::fetchAll("
            SELECT u.*, r.name AS role_name, r.color AS role_color, b.name AS branch_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN branches b ON u.branch_id = b.id 
            ORDER BY u.created_at DESC
        ");
    } catch (Exception $e) {
        $employees = [];
    }
}

$integrity_logs = [];
$reports = [];
if ($active_tab === 'integrity' && $role_level >= 8) {
    try {
        $integrity_logs = Database::fetchAll("
            SELECT il.*, u.full_name, u.emp_code 
            FROM integrity_logs il 
            LEFT JOIN users u ON il.user_id = u.id 
            ORDER BY il.created_at DESC 
            LIMIT 100
        ");
    } catch (Exception $e) {
        $integrity_logs = [];
    }
    
    if ($is_super_admin) {
        try {
            $reports = Database::fetchAll("
                SELECT ir.*, 
                       u_sender.full_name AS sender_name, u_sender.emp_code AS sender_code,
                       u_reported.full_name AS reported_name, u_reported.emp_code AS reported_code
                FROM integrity_reports ir
                LEFT JOIN users u_sender ON ir.sender_id = u_sender.id
                LEFT JOIN users u_reported ON ir.reported_id = u_reported.id
                ORDER BY ir.created_at DESC
            ");
        } catch (Exception $e) {
            $reports = [];
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= e(APP_NAME ?? 'صرح') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #ff6f00;
            --primary-light: #ffa040;
            --success: #00b894;
            --dark: #0a0a0a;
            --dark-light: #1a1a2e;
        }
        body { font-family: 'Tajawal', sans-serif; background: #f0f2f5; min-height: 100vh; }
        .navbar-custom { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); }
        .nav-tabs { border: none; background: white; border-radius: 12px; padding: 0.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-tabs .nav-link { color: #666; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 500; transition: all 0.2s; }
        .nav-tabs .nav-link:hover { background: #f8f9fa; }
        .nav-tabs .nav-link.active { color: white; background: var(--primary); }
        .card { border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.06); border-radius: 16px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; padding: 1rem 1.5rem; font-weight: 600; }
        .table { margin-bottom: 0; }
        .table th { font-weight: 600; color: #666; border-top: none; background: #f8f9fa; }
        .table td { vertical-align: middle; }
        .badge-severity-low { background: #a8e6cf; color: #1e5631; }
        .badge-severity-medium { background: #ffd93d; color: #6b5900; }
        .badge-severity-high { background: #ff6b6b; color: #fff; }
        .badge-severity-critical { background: #c0392b; color: #fff; }
        .btn-action { padding: 0.35rem 0.65rem; font-size: 0.85rem; border-radius: 8px; }
        .ghost-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .map-container { height: 350px; border-radius: 12px; overflow: hidden; border: 2px solid #e0e0e0; margin-bottom: 1rem; }
        #branchMapPicker { height: 100%; width: 100%; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .status-dot.online { background: #2ed573; box-shadow: 0 0 8px #2ed573; }
        .status-dot.offline { background: #ccc; }
        .integrity-feed { max-height: 600px; overflow-y: auto; }
        .log-item { padding: 1rem; border-bottom: 1px solid #eee; transition: background 0.2s; }
        .log-item:hover { background: #f8f9fa; }
        .log-item.unreviewed { border-right: 4px solid #ff6b6b; background: #fff5f5; }
        .secret-reveal { filter: blur(5px); transition: filter 0.3s; cursor: pointer; }
        .secret-reveal:hover { filter: none; }
        .snitch-badge { background: #e74c3c; color: #fff; font-size: 0.7rem; }
        .modal-content { border: none; border-radius: 16px; }
        .modal-header { background: var(--primary); color: white; border-radius: 16px 16px 0 0; }
        .modal-header .btn-close { filter: invert(1); }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e0e0e0; padding: 0.75rem 1rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,35,126,0.1); }
        .form-label { font-weight: 500; color: #444; margin-bottom: 0.5rem; }
        .radius-display { background: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; min-width: 80px; text-align: center; }
        .coordinates-box { background: #f8f9fa; border-radius: 10px; padding: 1rem; }
        .coordinates-box label { font-size: 0.85rem; color: #666; }
        .coordinates-box input { background: white; }
        .leaflet-container { font-family: 'Tajawal', sans-serif; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>">
                <i class="bi bi-building-gear me-2"></i>
                مركز الإدارة
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= url('index.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i> الرئيسية
                </a>
                <span class="badge bg-white text-primary"><?= e($_SESSION['full_name'] ?? 'مدير') ?></span>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'branches' ? 'active' : '' ?>" href="?tab=branches">
                    <i class="bi bi-building me-1"></i> الفروع
                    <span class="badge bg-secondary ms-1"><?= count($branches) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'employees' ? 'active' : '' ?>" href="?tab=employees">
                    <i class="bi bi-people me-1"></i> الموظفين
                </a>
            </li>
            <?php if ($role_level >= 8): ?>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'integrity' ? 'active' : '' ?>" href="?tab=integrity">
                    <i class="bi bi-shield-exclamation me-1"></i> النزاهة
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if ($active_tab === 'branches'): ?>
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- BRANCHES TAB -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>إدارة الفروع</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="resetBranchForm()">
                    <i class="bi bi-plus-lg me-1"></i> إضافة فرع
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>الفرع</th>
                                <th>الكود</th>
                                <th>الموظفين</th>
                                <th>الموقع</th>
                                <th>النطاق</th>
                                <th>الحالة</th>
                                <th width="150">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($branches)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-building display-4 d-block mb-2 opacity-25"></i>
                                    لا توجد فروع
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td>
                                    <strong><?= e($branch['name']) ?></strong>
                                    <?php if ($branch['is_ghost_branch']): ?>
                                    <span class="badge ghost-badge ms-1"><i class="bi bi-ghost"></i> فخ</span>
                                    <?php endif; ?>
                                    <?php if ($branch['city']): ?>
                                    <br><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($branch['city']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code class="bg-light px-2 py-1 rounded"><?= e($branch['code']) ?></code></td>
                                <td>
                                    <span class="badge bg-primary"><?= $branch['employee_count'] ?></span>
                                </td>
                                <td>
                                    <?php if ($branch['latitude'] && $branch['longitude']): ?>
                                    <a href="https://maps.google.com/?q=<?= $branch['latitude'] ?>,<?= $branch['longitude'] ?>" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-pin-map text-success me-1"></i>
                                        <small>عرض</small>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-x-circle"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info"><?= e($branch['geofence_radius'] ?? 100) ?> م</span></td>
                                <td>
                                    <?php if ($branch['is_active']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>نشط</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-outline-primary btn-action" onclick='editBranch(<?= json_encode($branch) ?>)' title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-action" onclick="deleteBranch(<?= $branch['id'] ?>, '<?= e(addslashes($branch['name'])) ?>')" title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($active_tab === 'employees'): ?>
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- EMPLOYEES TAB -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>إدارة الموظفين</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="resetEmployeeForm()">
                    <i class="bi bi-person-plus me-1"></i> إضافة موظف
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>الكود</th>
                                <th>الفرع</th>
                                <th>الدور</th>
                                <th>الحالة</th>
                                <th width="180">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people display-4 d-block mb-2 opacity-25"></i>
                                    لا يوجد موظفين
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="status-dot <?= $emp['is_online'] ? 'online' : 'offline' ?> me-2"></span>
                                        <div>
                                            <strong><?= e($emp['full_name']) ?></strong>
                                            <br><small class="text-muted"><?= e($emp['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><code class="bg-light px-2 py-1 rounded"><?= e($emp['emp_code']) ?></code></td>
                                <td><?= e($emp['branch_name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge" style="background:<?= e($emp['role_color'] ?? '#666') ?>">
                                        <?= e($emp['role_name'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($emp['is_active']): ?>
                                    <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-outline-primary btn-action" onclick='editEmployee(<?= json_encode($emp) ?>)' title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-warning btn-action" onclick="resetPassword(<?= $emp['id'] ?>, '<?= e(addslashes($emp['full_name'])) ?>')" title="إعادة كلمة المرور">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <button class="btn btn-outline-<?= $emp['is_active'] ? 'danger' : 'success' ?> btn-action" onclick="toggleEmployee(<?= $emp['id'] ?>, <?= $emp['is_active'] ?>)" title="<?= $emp['is_active'] ? 'تعطيل' : 'تفعيل' ?>">
                                            <i class="bi bi-<?= $emp['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($active_tab === 'integrity' && $role_level >= 8): ?>
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- INTEGRITY TAB -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>سجل النزاهة</h5>
                    </div>
                    <div class="card-body p-0 integrity-feed">
                        <?php if (empty($integrity_logs)): ?>
                        <p class="text-center text-muted py-5">
                            <i class="bi bi-shield-check display-4 d-block mb-2 opacity-25"></i>
                            لا توجد سجلات
                        </p>
                        <?php else: ?>
                        <?php foreach ($integrity_logs as $log): ?>
                        <div class="log-item <?= ($log['is_reviewed'] ?? 1) ? '' : 'unreviewed' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge badge-severity-<?= e($log['severity'] ?? 'low') ?>"><?= e($log['severity'] ?? 'low') ?></span>
                                    <strong class="ms-2"><?= e($log['action_type']) ?></strong>
                                </div>
                                <small class="text-muted"><?= e($log['created_at']) ?></small>
                            </div>
                            <p class="mb-1 mt-2 small">
                                <?php if ($log['full_name']): ?>
                                <i class="bi bi-person me-1"></i><?= e($log['full_name']) ?> (<?= e($log['emp_code']) ?>)
                                <?php else: ?>
                                <i class="bi bi-incognito me-1"></i>مجهول
                                <?php endif; ?>
                            </p>
                            <?php if ($log['details']): ?>
                            <details>
                                <summary class="text-muted small" style="cursor:pointer;">عرض التفاصيل</summary>
                                <pre class="small bg-light p-2 rounded mt-2 mb-0"><?= e(json_encode(json_decode($log['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </details>
                            <?php endif; ?>
                            <?php if (!($log['is_reviewed'] ?? 1)): ?>
                            <button class="btn btn-sm btn-outline-success mt-2" onclick="markReviewed(<?= $log['id'] ?>)">
                                <i class="bi bi-check me-1"></i> تمت المراجعة
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($is_super_admin && !empty($reports)): ?>
            <div class="col-lg-5">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-eye me-2"></i>البلاغات السرية 🕵️</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($reports as $report): ?>
                        <div class="log-item">
                            <div class="d-flex justify-content-between">
                                <span class="badge bg-<?= ($report['status'] ?? 'pending') === 'pending' ? 'warning' : 'secondary' ?>">
                                    <?= e($report['status'] ?? 'pending') ?>
                                </span>
                                <small class="text-muted"><?= e($report['created_at']) ?></small>
                            </div>
                            <p class="mt-2 mb-1"><strong>ضد:</strong> <?= e($report['reported_name'] ?? 'غير محدد') ?></p>
                            <p class="small text-muted mb-2"><?= e(mb_substr($report['content'] ?? '', 0, 100)) ?>...</p>
                            <div class="alert alert-danger py-2 px-3 small mb-0">
                                <i class="bi bi-incognito me-1"></i>
                                <strong>المُبلّغ:</strong>
                                <span class="secret-reveal">
                                    <?= e($report['sender_name']) ?> (<?= e($report['sender_code']) ?>)
                                </span>
                                <span class="badge snitch-badge ms-1">🐀</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- Branch Modal -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="branchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="branchModalTitle">
                        <i class="bi bi-building me-2"></i>إضافة فرع
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="branchForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="branch_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم الفرع <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="branch_name" required placeholder="مثال: فرع الرياض">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">كود الفرع <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="code" id="branch_code" required placeholder="مثال: RYD01" style="text-transform:uppercase;">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">العنوان</label>
                            <input type="text" class="form-control" name="address" id="branch_address" placeholder="العنوان التفصيلي">
                        </div>
                        
                        <div class="mt-4">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-map me-1"></i> الموقع على الخريطة</span>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getCurrentLocation()">
                                    <i class="bi bi-crosshair me-1"></i> موقعي الحالي
                                </button>
                            </label>
                            <div class="map-container">
                                <div id="branchMapPicker"></div>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>انقر على الخريطة لتحديد موقع الفرع</small>
                        </div>
                        
                        <div class="coordinates-box mt-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">خط العرض</label>
                                    <input type="number" step="any" class="form-control" name="latitude" id="branch_lat" placeholder="24.7136" onchange="updateMapFromInputs()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">خط الطول</label>
                                    <input type="number" step="any" class="form-control" name="longitude" id="branch_lng" placeholder="46.6753" onchange="updateMapFromInputs()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">نطاق السياج الجغرافي</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="range" class="form-range flex-grow-1" min="20" max="500" step="10" value="100" name="geofence_radius" id="branch_radius" oninput="updateRadius()">
                                        <span class="radius-display" id="radiusValue">100 م</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="branch_active" checked>
                                    <label class="form-check-label">فرع نشط</label>
                                </div>
                            </div>
                            <?php if ($is_super_admin): ?>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_ghost_branch" id="branch_ghost">
                                    <label class="form-check-label"><i class="bi bi-ghost text-purple"></i> فرع وهمي (فخ)</label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="branchSubmitBtn">
                            <i class="bi bi-check-lg me-1"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- Employee Modal -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalTitle">
                        <i class="bi bi-person-plus me-2"></i>إضافة موظف
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="employeeForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="emp_id">
                        
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" id="emp_name" required>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">كود الموظف <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="emp_code" id="emp_code" required style="text-transform:uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" id="emp_username" required>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="emp_email" required>
                        </div>
                        
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">الفرع</label>
                                <select class="form-select" name="branch_id" id="emp_branch">
                                    <option value="">-- بدون فرع --</option>
                                    <?php foreach ($branches as $b): ?>
                                    <?php if (!$b['is_ghost_branch']): ?>
                                    <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الدور</label>
                                <select class="form-select" name="role_id" id="emp_role">
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-3" id="passwordField">
                            <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" id="emp_password" minlength="6">
                            <small class="text-muted">6 أحرف على الأقل</small>
                        </div>
                        
                        <!-- Photo Capture Section -->
                        <div class="mt-4" id="photoSection">
                            <label class="form-label d-block mb-2">
                                <i class="bi bi-camera me-1"></i>
                                صورة الموظف <span class="text-danger photoRequiredStar">*</span>
                                <span class="text-muted photoOptionalText" style="display:none;">(اختياري)</span>
                            </label>
                            
                            <!-- Photo Preview/Display -->
                            <div class="text-center mb-3" id="photoPreviewContainer" style="display: none;">
                                <img id="photoPreview" src="" alt="Preview" 
                                     class="img-thumbnail rounded-circle" 
                                     style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--primary);">
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearPhoto()">
                                        <i class="bi bi-x-circle me-1"></i> إزالة الصورة
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Camera Capture Button -->
                            <div class="text-center" id="photoCaptureContainer">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="openCameraModal()">
                                        <i class="bi bi-camera-fill me-2"></i>
                                        التقاط صورة الموظف
                                    </button>
                                    <div class="position-relative">
                                        <input type="file" id="photoFileInput" accept="image/*" style="display: none;" onchange="handleFileUpload(event)">
                                        <button type="button" class="btn btn-outline-success w-100" onclick="document.getElementById('photoFileInput').click()">
                                            <i class="bi bi-upload me-2"></i>
                                            تحميل من الملفات
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2 photoInfoText">
                                    <i class="bi bi-info-circle me-1"></i>
                                    يمكنك التقاط صورة بالكاميرا أو تحميل صورة من الملفات
                                </small>
                                <small class="text-muted d-block mt-2 photoOptionalInfoText" style="display:none;">
                                    <i class="bi bi-info-circle me-1"></i>
                                    الصورة اختيارية. يمكنك إضافتها لاحقاً.
                                </small>
                            </div>
                            
                            <!-- Hidden input for photo data -->
                            <input type="hidden" name="photo_data" id="emp_photo_data">
                        </div>
                        
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="emp_active" checked>
                            <label class="form-check-label">موظف نشط</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="empSubmitBtn">
                            <i class="bi bi-check-lg me-1"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- Camera Modal for Employee Photo -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="cameraModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-camera me-2"></i>التقاط صورة الموظف
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCameraModal()"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="position-relative" style="background: #000; min-height: 400px;">
                        <!-- Video Stream -->
                        <video id="videoStream" autoplay playsinline 
                               style="width: 100%; display: block; max-height: 500px;"></video>
                        
                        <!-- Canvas for Capture -->
                        <canvas id="captureCanvas" style="display: none;"></canvas>
                        
                        <!-- Face Detection Overlay -->
                        <div id="faceDetectionOverlay" 
                             style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                            <div id="faceBox" style="display: none; position: absolute; border: 3px solid #00ff00; border-radius: 50%; box-shadow: 0 0 20px rgba(0,255,0,0.5);">
                                <div style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); background: #00ff00; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                    ✓ وجه مكتشف
                                </div>
                            </div>
                        </div>
                        
                        <!-- No Face Warning -->
                        <div id="noFaceWarning" class="alert alert-warning m-3" style="display: none; position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 10; width: calc(100% - 24px);">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>تحذير:</strong> لا يوجد وجه واضح في الصورة. يرجى التأكد من أن الوجه واضح ومرئي.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCameraModal()">إلغاء</button>
                    <button type="button" class="btn btn-warning" onclick="switchCamera()" id="switchCameraBtn" style="display: none;">
                        <i class="bi bi-arrow-repeat me-1"></i> تبديل الكاميرا
                    </button>
                    <button type="button" class="btn btn-primary" onclick="capturePhoto()" id="captureBtn">
                        <i class="bi bi-camera-fill me-1"></i> التقاط
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // ═══════════════════════════════════════════════════════════════════════════
    // Configuration
    // ═══════════════════════════════════════════════════════════════════════════
    const API_URL = '<?= url("api/admin/command_action.php") ?>';
    const CSRF = '<?= e($csrf) ?>';
    
    let branchMap = null;
    let branchMarker = null;
    let branchCircle = null;
    let mapInitialized = false;
    
    // Camera variables
    let videoStream = null;
    let currentStream = null;
    let faceDetector = null;
    let detectionInterval = null;
    let isFaceDetected = false;

    // ═══════════════════════════════════════════════════════════════════════════
    // Map Functions
    // ═══════════════════════════════════════════════════════════════════════════
    
    function initMap() {
        if (mapInitialized) {
            branchMap.invalidateSize();
            return;
        }
        
        const mapContainer = document.getElementById('branchMapPicker');
        if (!mapContainer) return;
        
        // Default to Riyadh
        const defaultLat = 24.7136;
        const defaultLng = 46.6753;
        
        branchMap = L.map('branchMapPicker').setView([defaultLat, defaultLng], 12);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(branchMap);
        
        // Click event to set marker
        branchMap.on('click', function(e) {
            setMarker(e.latlng.lat, e.latlng.lng);
            document.getElementById('branch_lat').value = e.latlng.lat.toFixed(7);
            document.getElementById('branch_lng').value = e.latlng.lng.toFixed(7);
        });
        
        mapInitialized = true;
        console.log('Map initialized');
    }
    
    function setMarker(lat, lng) {
        const radius = parseInt(document.getElementById('branch_radius').value) || 100;
        
        // Remove existing
        if (branchMarker) branchMap.removeLayer(branchMarker);
        if (branchCircle) branchMap.removeLayer(branchCircle);
        
        // Add new marker
        branchMarker = L.marker([lat, lng], {
            draggable: true
        }).addTo(branchMap);
        
        // Add geofence circle
        branchCircle = L.circle([lat, lng], {
            radius: radius,
            color: '#e65100',
            fillColor: '#ff9800',
            fillOpacity: 0.2,
            weight: 2
        }).addTo(branchMap);
        
        // Center map
        branchMap.setView([lat, lng], 16);
        
        // Drag event
        branchMarker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('branch_lat').value = pos.lat.toFixed(7);
            document.getElementById('branch_lng').value = pos.lng.toFixed(7);
            branchCircle.setLatLng(pos);
        });
    }
    
    function updateRadius() {
        const radius = parseInt(document.getElementById('branch_radius').value);
        document.getElementById('radiusValue').textContent = radius + ' م';
        
        if (branchCircle) {
            branchCircle.setRadius(radius);
        }
    }
    
    function updateMapFromInputs() {
        const lat = parseFloat(document.getElementById('branch_lat').value);
        const lng = parseFloat(document.getElementById('branch_lng').value);
        
        if (!isNaN(lat) && !isNaN(lng) && branchMap) {
            setMarker(lat, lng);
        }
    }
    
    function getCurrentLocation() {
        if (!navigator.geolocation) {
            Swal.fire('خطأ', 'المتصفح لا يدعم تحديد الموقع', 'error');
            return;
        }
        
        Swal.fire({
            title: 'جاري تحديد الموقع...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                Swal.close();
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                document.getElementById('branch_lat').value = lat.toFixed(7);
                document.getElementById('branch_lng').value = lng.toFixed(7);
                
                if (branchMap) {
                    setMarker(lat, lng);
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'تم تحديد الموقع',
                    toast: true,
                    position: 'top',
                    timer: 2000,
                    showConfirmButton: false
                });
            },
            function(error) {
                Swal.fire('خطأ', 'لم نتمكن من تحديد موقعك', 'error');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Branch Functions
    // ═══════════════════════════════════════════════════════════════════════════
    
    // Initialize map when modal opens
    document.getElementById('branchModal')?.addEventListener('shown.bs.modal', function() {
        setTimeout(initMap, 100);
    });
    
    function resetBranchForm() {
        document.getElementById('branchForm').reset();
        document.getElementById('branch_id').value = '';
        document.getElementById('branchModalTitle').innerHTML = '<i class="bi bi-building me-2"></i>إضافة فرع';
        document.getElementById('radiusValue').textContent = '100 م';
        
        if (branchMarker && branchMap) branchMap.removeLayer(branchMarker);
        if (branchCircle && branchMap) branchMap.removeLayer(branchCircle);
        branchMarker = null;
        branchCircle = null;
    }

    function editBranch(branch) {
        document.getElementById('branchModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>تعديل فرع';
        document.getElementById('branch_id').value = branch.id;
        document.getElementById('branch_name').value = branch.name || '';
        document.getElementById('branch_code').value = branch.code || '';
        document.getElementById('branch_address').value = branch.address || '';
        document.getElementById('branch_lat').value = branch.latitude || '';
        document.getElementById('branch_lng').value = branch.longitude || '';
        document.getElementById('branch_radius').value = branch.geofence_radius || 100;
        document.getElementById('radiusValue').textContent = (branch.geofence_radius || 100) + ' م';
        document.getElementById('branch_active').checked = branch.is_active == 1;
        
        const ghostEl = document.getElementById('branch_ghost');
        if (ghostEl) ghostEl.checked = branch.is_ghost_branch == 1;
        
        const modal = new bootstrap.Modal(document.getElementById('branchModal'));
        modal.show();
        
        // Set marker after map initializes
        setTimeout(() => {
            if (branch.latitude && branch.longitude && branchMap) {
                setMarker(parseFloat(branch.latitude), parseFloat(branch.longitude));
            }
        }, 300);
    }

    function deleteBranch(id, name) {
        Swal.fire({
            title: 'حذف الفرع؟',
            html: `هل تريد حذف فرع <strong>"${name}"</strong>؟<br><small class="text-muted">لا يمكن التراجع عن هذا الإجراء</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                apiCall('delete_branch', {id: id});
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Employee Functions
    // ═══════════════════════════════════════════════════════════════════════════
    
    function resetEmployeeForm() {
        document.getElementById('employeeForm').reset();
        document.getElementById('emp_id').value = '';
        document.getElementById('employeeModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>إضافة موظف';
        document.getElementById('passwordField').style.display = 'block';
        document.getElementById('emp_password').required = true;
        
        // Reset photo
        document.getElementById('photoPreviewContainer').style.display = 'none';
        document.getElementById('photoCaptureContainer').style.display = 'block';
        document.getElementById('emp_photo_data').value = '';
        document.getElementById('photoPreview').src = '';
        const fileInput = document.getElementById('photoFileInput');
        if (fileInput) fileInput.value = '';
        
        // Show photo as optional
        const photoRequiredStar = document.querySelector('.photoRequiredStar');
        const photoOptionalText = document.querySelector('.photoOptionalText');
        const photoInfoText = document.querySelector('.photoInfoText');
        const photoOptionalInfoText = document.querySelector('.photoOptionalInfoText');
        if (photoRequiredStar) photoRequiredStar.style.display = 'none';
        if (photoOptionalText) photoOptionalText.style.display = 'inline';
        if (photoInfoText) photoInfoText.style.display = 'none';
        if (photoOptionalInfoText) photoOptionalInfoText.style.display = 'block';
    }

    function editEmployee(emp) {
        document.getElementById('employeeModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>تعديل موظف';
        document.getElementById('emp_id').value = emp.id;
        document.getElementById('emp_name').value = emp.full_name || '';
        document.getElementById('emp_code').value = emp.emp_code || '';
        document.getElementById('emp_username').value = emp.username || '';
        document.getElementById('emp_email').value = emp.email || '';
        document.getElementById('emp_branch').value = emp.branch_id || '';
        document.getElementById('emp_role').value = emp.role_id || '';
        document.getElementById('emp_active').checked = emp.is_active == 1;
        
        // Hide password field when editing
        document.getElementById('passwordField').style.display = 'none';
        document.getElementById('emp_password').required = false;
        
        // Handle existing photo (if any)
        const photoSection = document.getElementById('photoSection');
        if (emp.avatar) {
            // Show existing photo
            const avatarUrl = '<?= url("uploads/avatars/") ?>' + emp.avatar;
            document.getElementById('photoPreview').src = avatarUrl;
            document.getElementById('photoPreviewContainer').style.display = 'block';
            document.getElementById('photoCaptureContainer').innerHTML = `
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="openCameraModal()">
                        <i class="bi bi-camera-fill me-2"></i>
                        التقاط صورة جديدة
                    </button>
                    <div class="position-relative">
                        <input type="file" id="photoFileInput" accept="image/*" style="display: none;" onchange="handleFileUpload(event)">
                        <button type="button" class="btn btn-outline-success w-100" onclick="document.getElementById('photoFileInput').click()">
                            <i class="bi bi-upload me-2"></i>
                            تحميل من الملفات
                        </button>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    الصورة الحالية معروضة أعلاه. يمكنك التقاط أو تحميل صورة جديدة.
                </small>
            `;
        } else {
            // No existing photo
            clearPhoto();
            document.getElementById('photoCaptureContainer').innerHTML = `
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="openCameraModal()">
                        <i class="bi bi-camera-fill me-2"></i>
                        التقاط صورة
                    </button>
                    <div class="position-relative">
                        <input type="file" id="photoFileInput" accept="image/*" style="display: none;" onchange="handleFileUpload(event)">
                        <button type="button" class="btn btn-outline-success w-100" onclick="document.getElementById('photoFileInput').click()">
                            <i class="bi bi-upload me-2"></i>
                            تحميل من الملفات
                        </button>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    يمكنك إضافة صورة لاحقاً
                </small>
            `;
        }
        
        // Photo is optional for both create and update
        const photoRequiredStar = document.querySelector('.photoRequiredStar');
        const photoOptionalText = document.querySelector('.photoOptionalText');
        const photoInfoText = document.querySelector('.photoInfoText');
        const photoOptionalInfoText = document.querySelector('.photoOptionalInfoText');
        
        if (photoRequiredStar) photoRequiredStar.style.display = 'none';
        if (photoOptionalText) photoOptionalText.style.display = 'inline';
        if (photoInfoText) photoInfoText.style.display = 'none';
        if (photoOptionalInfoText) photoOptionalInfoText.style.display = 'block';
        
        const textDanger = photoSection?.querySelector('.form-label .text-danger');
        if (textDanger) textDanger.style.display = 'none';
        
        const photoDataInput = document.getElementById('emp_photo_data');
        if (photoDataInput) photoDataInput.value = '';
        
        new bootstrap.Modal(document.getElementById('employeeModal')).show();
    }

    function resetPassword(id, name) {
        Swal.fire({
            title: 'إعادة تعيين كلمة المرور',
            html: `إعادة تعيين كلمة مرور <strong>"${name}"</strong>؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-key me-1"></i> نعم',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                apiCall('reset_password', {id: id}, false).then(data => {
                    if (data && data.new_password) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم إعادة التعيين',
                            html: `كلمة المرور الجديدة:<br><code style="font-size:1.5rem;background:#f8f9fa;padding:10px 20px;border-radius:8px;display:inline-block;margin-top:10px;">${data.new_password}</code>`,
                            confirmButtonText: 'حسناً'
                        });
                    }
                });
            }
        });
    }

    function toggleEmployee(id, currentStatus) {
        const action = currentStatus ? 'تعطيل' : 'تفعيل';
        Swal.fire({
            title: action + ' الموظف؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                apiCall('toggle_employee', {id: id, is_active: currentStatus ? 0 : 1});
            }
        });
    }

    function markReviewed(id) {
        apiCall('mark_reviewed', {id: id});
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // API Call Function
    // ═══════════════════════════════════════════════════════════════════════════
    
    async function apiCall(action, data = {}, reload = true) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF
                },
                body: JSON.stringify({action, ...data})
            });
            
            // Check response status first
            if (!response.ok) {
                const text = await response.text();
                let errorMessage = 'حدث خطأ في الخادم';
                try {
                    const jsonError = JSON.parse(text);
                    errorMessage = jsonError.message || errorMessage;
                } catch (e) {
                    // If not JSON, show the raw error (first 200 chars)
                    errorMessage = `خطأ ${response.status}: ${response.statusText || text.substring(0, 200)}`;
                }
                console.error('API Error Response:', {
                    status: response.status,
                    statusText: response.statusText,
                    body: text
                });
                throw new Error(`خطأ ${response.status}: ${errorMessage}`);
            }
            
            // Check if response is JSON
            let result;
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error('استجابة غير صحيحة من الخادم: ' + text.substring(0, 100));
            }
            
            try {
                result = await response.json();
            } catch (e) {
                throw new Error('خطأ في قراءة الاستجابة من السيرفر: ' + e.message);
            }
            
            if (!result.success) {
                console.error('API Error Response:', result);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: result.message || 'حدث خطأ غير متوقع',
                    footer: response.status ? `رمز الخطأ: ${response.status}` : ''
                });
                return null;
            }
            
            if (reload) {
                Swal.fire({
                    icon: 'success',
                    title: 'تم بنجاح',
                    toast: true,
                    position: 'top',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            }
            
            return result;
            
        } catch (error) {
            console.error('API Error:', error);
            // Check if it's a network error or a JSON parsing error
            if (error.message && error.message.includes('قراءة الاستجابة')) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاستجابة',
                    text: 'استجابة غير صحيحة من الخادم. يرجى المحاولة مرة أخرى.'
                });
            } else if (error.name === 'TypeError' && error.message.includes('fetch')) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال',
                    text: 'تأكد من اتصالك بالإنترنت وحاول مرة أخرى.'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'حدث خطأ',
                    text: error.message || 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
                });
            }
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Form Submissions
    // ═══════════════════════════════════════════════════════════════════════════
    
    document.getElementById('branchForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('branchSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الحفظ...';
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        data.is_active = document.getElementById('branch_active').checked ? 1 : 0;
        data.is_ghost_branch = document.getElementById('branch_ghost')?.checked ? 1 : 0;
        
        const action = data.id ? 'update_branch' : 'create_branch';
        await apiCall(action, data);
        
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> حفظ';
    });

    document.getElementById('employeeForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('empSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الحفظ...';
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        data.is_active = document.getElementById('emp_active').checked ? 1 : 0;
        
        // Handle branch_id - convert empty string to null
        if (data.branch_id === '' || data.branch_id === '0') {
            data.branch_id = null;
        } else if (data.branch_id) {
            data.branch_id = parseInt(data.branch_id);
        }
        
        // Add photo_data if provided (optional for both create and update)
        const photoData = document.getElementById('emp_photo_data')?.value || '';
        
        if (photoData) {
            data.photo_data = photoData;
        }
        
        // Check if this is an edit (id exists and not empty)
        const isEdit = data.id && data.id !== '';
        const action = isEdit ? 'update_employee' : 'create_employee';
        await apiCall(action, data);
        
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> حفظ';
    });
    
    // ═══════════════════════════════════════════════════════════════════════════
    // Camera Functions
    // ═══════════════════════════════════════════════════════════════════════════
    
    async function openCameraModal() {
        const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
        modal.show();
        
        // Initialize face detection API if available
        if ('FaceDetector' in window) {
            try {
                faceDetector = new FaceDetector({
                    fastMode: true,
                    maxDetections: 1
                });
            } catch (e) {
                console.log('FaceDetector not supported:', e);
            }
        }
        
        await startCamera();
    }
    
    async function startCamera(facingMode = 'user') {
        try {
            // Stop existing stream
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
            }
            
            videoStream = document.getElementById('videoStream');
            
            // Request camera access
            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                }
            };
            
            currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            videoStream.srcObject = currentStream;
            
            // Show switch camera button if multiple cameras available
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                if (videoDevices.length > 1) {
                    document.getElementById('switchCameraBtn').style.display = 'inline-block';
                }
            } catch (e) {
                console.log('Cannot enumerate devices:', e);
            }
            
            // Start face detection when video is playing
            videoStream.onloadedmetadata = () => {
                videoStream.play();
                startFaceDetection();
            };
            
        } catch (error) {
            console.error('Camera error:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ في الكاميرا',
                text: 'لا يمكن الوصول إلى الكاميرا. تأكد من إعطاء الإذن للوصول إلى الكاميرا.',
                confirmButtonText: 'حسناً'
            });
            closeCameraModal();
        }
    }
    
    function startFaceDetection() {
        if (!faceDetector) {
            // Fallback: Simple face detection using video dimensions
            // Just check if video is showing (not perfect but better than nothing)
            detectionInterval = setInterval(() => {
                if (videoStream && videoStream.readyState === 4) {
                    // Show a default face box in center (for UI purposes)
                    const overlay = document.getElementById('faceDetectionOverlay');
                    const faceBox = document.getElementById('faceBox');
                    const video = videoStream;
                    
                    // Position box in center of video (assuming face should be there)
                    const videoRect = video.getBoundingClientRect();
                    const overlayRect = overlay.getBoundingClientRect();
                    
                    const centerX = overlayRect.width / 2;
                    const centerY = overlayRect.height / 2;
                    const boxSize = Math.min(overlayRect.width, overlayRect.height) * 0.4;
                    
                    faceBox.style.display = 'block';
                    faceBox.style.left = (centerX - boxSize/2) + 'px';
                    faceBox.style.top = (centerY - boxSize/2) + 'px';
                    faceBox.style.width = boxSize + 'px';
                    faceBox.style.height = boxSize + 'px';
                    
                    isFaceDetected = true;
                    document.getElementById('noFaceWarning').style.display = 'none';
                }
            }, 500);
            return;
        }
        
        // Use native FaceDetector API if available
        detectionInterval = setInterval(async () => {
            try {
                if (!videoStream || videoStream.readyState !== 4) return;
                
                const faces = await faceDetector.detect(videoStream);
                
                const overlay = document.getElementById('faceDetectionOverlay');
                const faceBox = document.getElementById('faceBox');
                const warning = document.getElementById('noFaceWarning');
                
                if (faces.length > 0) {
                    // Face detected
                    const face = faces[0].boundingBox;
                    const videoRect = videoStream.getBoundingClientRect();
                    const overlayRect = overlay.getBoundingClientRect();
                    
                    // Calculate position and size
                    const scaleX = overlayRect.width / videoStream.videoWidth;
                    const scaleY = overlayRect.height / videoStream.videoHeight;
                    
                    faceBox.style.display = 'block';
                    faceBox.style.left = (face.x * scaleX) + 'px';
                    faceBox.style.top = (face.y * scaleY) + 'px';
                    faceBox.style.width = (face.width * scaleX) + 'px';
                    faceBox.style.height = (face.height * scaleY) + 'px';
                    
                    warning.style.display = 'none';
                    isFaceDetected = true;
                } else {
                    // No face detected
                    faceBox.style.display = 'none';
                    warning.style.display = 'block';
                    isFaceDetected = false;
                }
            } catch (error) {
                console.error('Face detection error:', error);
            }
        }, 300);
    }
    
    function stopFaceDetection() {
        if (detectionInterval) {
            clearInterval(detectionInterval);
            detectionInterval = null;
        }
        document.getElementById('faceBox').style.display = 'none';
        document.getElementById('noFaceWarning').style.display = 'none';
        isFaceDetected = false;
    }
    
    function switchCamera() {
        if (!currentStream) return;
        
        // Determine current facing mode
        const currentTrack = currentStream.getVideoTracks()[0];
        const currentConstraints = currentTrack.getSettings();
        const currentFacing = currentConstraints.facingMode || 'user';
        const newFacing = currentFacing === 'user' ? 'environment' : 'user';
        
        stopFaceDetection();
        startCamera(newFacing);
    }
    
    function capturePhoto() {
        if (!videoStream || !currentStream) {
            Swal.fire({
                icon: 'warning',
                title: 'خطأ',
                text: 'الكاميرا غير جاهزة',
                confirmButtonText: 'حسناً'
            });
            return;
        }
        
        const canvas = document.getElementById('captureCanvas');
        const video = videoStream;
        
        // Set canvas dimensions to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Draw video frame to canvas
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to base64
        const photoData = canvas.toDataURL('image/jpeg', 0.9);
        
        // Update preview
        document.getElementById('photoPreview').src = photoData;
        document.getElementById('photoPreviewContainer').style.display = 'block';
        document.getElementById('photoCaptureContainer').style.display = 'none';
        document.getElementById('emp_photo_data').value = photoData;
        
        // Close camera modal
        closeCameraModal();
        
        Swal.fire({
            icon: 'success',
            title: 'تم بنجاح',
            text: 'تم التقاط الصورة بنجاح',
            toast: true,
            position: 'top',
            timer: 2000,
            showConfirmButton: false
        });
    }
    
    function clearPhoto() {
        document.getElementById('photoPreviewContainer').style.display = 'none';
        document.getElementById('photoCaptureContainer').style.display = 'block';
        document.getElementById('emp_photo_data').value = '';
        document.getElementById('photoPreview').src = '';
        document.getElementById('photoFileInput').value = '';
    }
    
    function handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'يجب اختيار ملف صورة',
                confirmButtonText: 'حسناً'
            });
            return;
        }
        
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'حجم الصورة يجب أن يكون أقل من 5 ميجابايت',
                confirmButtonText: 'حسناً'
            });
            return;
        }
        
        // Read file as data URL
        const reader = new FileReader();
        reader.onload = function(e) {
            const photoData = e.target.result;
            
            // Update preview
            document.getElementById('photoPreview').src = photoData;
            document.getElementById('photoPreviewContainer').style.display = 'block';
            document.getElementById('photoCaptureContainer').style.display = 'none';
            document.getElementById('emp_photo_data').value = photoData;
            
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                text: 'تم تحميل الصورة بنجاح',
                toast: true,
                position: 'top',
                timer: 2000,
                showConfirmButton: false
            });
        };
        
        reader.onerror = function() {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'حدث خطأ أثناء قراءة الملف',
                confirmButtonText: 'حسناً'
            });
        };
        
        reader.readAsDataURL(file);
    }
    
    function closeCameraModal() {
        stopFaceDetection();
        
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
        
        if (videoStream) {
            videoStream.srcObject = null;
        }
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('cameraModal'));
        if (modal) {
            modal.hide();
        }
    }
    
    // Close camera modal when hidden
    document.getElementById('cameraModal')?.addEventListener('hidden.bs.modal', function() {
        closeCameraModal();
    });
    </script>
</body>
</html>
