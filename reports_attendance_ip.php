<?php
/**
 * تقارير الحضور مع عناوين IP
 * 
 * يعرض تقارير مفصلة عن الحضور مع عناوين IP المستخدمة
 */

session_start();
require_once 'attendance_checkin_ip_verification.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['user_id']) || !hasPermission($_SESSION['user_id'], 'reports.view')) {
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
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$format = $_GET['format'] ?? 'html'; // html, json, csv

// الحصول على البيانات
$report_data = getAttendanceReport($pdo, $start_date, $end_date, $branch_id, $user_id);

// التحقق من الصلاحيات
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
    
    if (in_array('*', $permissions)) return true;
    if (in_array($permission, $permissions)) return true;
    if (in_array('reports.*', $permissions)) return true;
    
    return false;
}

/**
 * الحصول على تقرير الحضور
 */
function getAttendanceReport($pdo, $start_date, $end_date, $branch_id = null, $user_id = null) {
    $sql = "
        SELECT 
            a.id,
            a.date,
            a.check_in_time,
            a.check_out_time,
            a.ip_address,
            a.check_in_method,
            a.work_minutes,
            a.status,
            u.id as user_id,
            u.full_name,
            u.emp_code,
            b.id as branch_id,
            b.name as branch_name,
            b.code as branch_code,
            b.authorized_ip,
            r.name as role_name
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN branches b ON a.branch_id = b.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE a.date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    if ($branch_id) {
        $sql .= " AND a.branch_id = ?";
        $params[] = $branch_id;
    }
    
    if ($user_id) {
        $sql .= " AND a.user_id = ?";
        $params[] = $user_id;
    }
    
    $sql .= " ORDER BY a.date DESC, a.check_in_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * إحصائيات التقرير
 */
function getReportStatistics($pdo, $start_date, $end_date, $branch_id = null) {
    $sql = "
        SELECT 
            COUNT(DISTINCT a.user_id) as total_employees,
            COUNT(DISTINCT a.date) as total_days,
            COUNT(a.id) as total_records,
            COUNT(DISTINCT a.ip_address) as unique_ips,
            SUM(a.work_minutes) as total_work_minutes,
            AVG(a.work_minutes) as avg_work_minutes
        FROM attendance a
        WHERE a.date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    if ($branch_id) {
        $sql .= " AND a.branch_id = ?";
        $params[] = $branch_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch();
}

/**
 * الحصول على قائمة الفروع
 */
function getBranchesList($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, code 
        FROM branches 
        WHERE is_active = 1 
        ORDER BY id
    ");
    return $stmt->fetchAll();
}

// الحصول على الإحصائيات
$statistics = getReportStatistics($pdo, $start_date, $end_date, $branch_id);
$branches = getBranchesList($pdo);

// تصدير CSV
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM للـ UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // رأس الملف
    fputcsv($output, [
        'التاريخ', 'كود الموظف', 'اسم الموظف', 'الفرع', 
        'وقت الحضور', 'وقت الانصراف', 'ساعات العمل', 
        'عنوان IP', 'طريقة التسجيل', 'الحالة'
    ]);
    
    // البيانات
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['date'],
            $row['emp_code'],
            $row['full_name'],
            $row['branch_name'],
            $row['check_in_time'],
            $row['check_out_time'],
            round($row['work_minutes'] / 60, 2),
            $row['ip_address'],
            $row['check_in_method'],
            $row['status']
        ]);
    }
    
    fclose($output);
    exit;
}

// تصدير JSON
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'period' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ],
        'statistics' => $statistics,
        'data' => $report_data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير الحضور - عناوين IP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .stat-card { margin-bottom: 20px; }
        .ip-badge { font-family: monospace; }
        .table-responsive { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4"><i class="bi bi-graph-up"></i> تقارير الحضور - عناوين IP</h1>
        
        <!-- الفلتر -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الفرع</label>
                        <select name="branch_id" class="form-select">
                            <option value="">جميع الفروع</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>" <?= $branch_id == $branch['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> بحث
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="mt-3">
                    <a href="?<?= http_build_query(array_merge($_GET, ['format' => 'csv'])) ?>" class="btn btn-success">
                        <i class="bi bi-download"></i> تصدير CSV
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['format' => 'json'])) ?>" class="btn btn-info">
                        <i class="bi bi-code-square"></i> تصدير JSON
                    </a>
                </div>
            </div>
        </div>
        
        <!-- الإحصائيات -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5><i class="bi bi-people"></i> الموظفين</h5>
                        <h2><?= $statistics['total_employees'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5><i class="bi bi-calendar-check"></i> سجلات الحضور</h5>
                        <h2><?= $statistics['total_records'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5><i class="bi bi-router"></i> عناوين IP فريدة</h5>
                        <h2><?= $statistics['unique_ips'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5><i class="bi bi-clock"></i> متوسط ساعات العمل</h5>
                        <h2><?= round($statistics['avg_work_minutes'] / 60, 1) ?> ساعة</h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الجدول -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">سجلات الحضور</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الموظف</th>
                                <th>الفرع</th>
                                <th>وقت الحضور</th>
                                <th>وقت الانصراف</th>
                                <th>ساعات العمل</th>
                                <th>عنوان IP</th>
                                <th>طريقة التسجيل</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">لا توجد بيانات</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['emp_code']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['branch_name'] ?? 'غير محدد') ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['branch_code'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['check_in_time'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['check_out_time'] ?? '-') ?></td>
                                <td>
                                    <?php if ($row['work_minutes']): ?>
                                        <?= round($row['work_minutes'] / 60, 2) ?> ساعة
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary ip-badge">
                                        <?= htmlspecialchars($row['ip_address'] ?? 'غير محدد') ?>
                                    </span>
                                    <?php if ($row['authorized_ip']): ?>
                                        <br><small class="text-muted">
                                            مسموح: <?= htmlspecialchars($row['authorized_ip']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['check_in_method'] === 'ip_verification'): ?>
                                        <span class="badge bg-success">تحقق IP</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">يدوي</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'present' => 'bg-success',
                                        'absent' => 'bg-danger',
                                        'late' => 'bg-warning',
                                        'half_day' => 'bg-info',
                                        'leave' => 'bg-primary'
                                    ];
                                    $badge_class = $status_badges[$row['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
