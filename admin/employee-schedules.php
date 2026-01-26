<?php
/**
 * ========================================================================
 * إدارة جداول دوام الموظفين
 * Employee Schedule Management
 * ========================================================================
 * نظام مرن لإدارة أوقات الدوام والأذونات الخاصة بكل موظف
 */

require_once '../config/app.php';
require_once '../includes/functions.php';

// التحقق من الصلاحيات (مدير النظام فقط)
check_login();
$role_level = intval($_SESSION['role_level'] ?? 1);
if ($role_level < 4) {
    redirect(url('errors/403.php'));
}

$message = '';
$messageType = 'success';

// جلب قائمة الموظفين
$employees = Database::fetchAll("
    SELECT u.id, u.full_name, u.emp_code, u.job_title, u.avatar,
           b.name as branch_name,
           es.id as schedule_id, es.attendance_mode, es.work_start_time, es.work_end_time,
           es.is_active as schedule_active
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    LEFT JOIN employee_schedules es ON u.id = es.user_id
    WHERE u.is_active = 1
    ORDER BY u.full_name
");

// جلب الفروع
$branches = Database::fetchAll("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId > 0) {
        $scheduleData = [
            'user_id' => $userId,
            'work_start_time' => $_POST['work_start_time'] ?? '08:00',
            'work_end_time' => $_POST['work_end_time'] ?? '17:00',
            'grace_period_minutes' => intval($_POST['grace_period_minutes'] ?? 15),
            'attendance_mode' => $_POST['attendance_mode'] ?? 'time_and_location',
            'working_days' => json_encode(array_map('intval', $_POST['working_days'] ?? [0,1,2,3,4])),
            'allowed_branches' => !empty($_POST['allowed_branches']) ? json_encode(array_map('intval', $_POST['allowed_branches'])) : null,
            'geofence_radius' => intval($_POST['geofence_radius'] ?? 100),
            'is_flexible_hours' => isset($_POST['is_flexible_hours']) ? 1 : 0,
            'min_working_hours' => floatval($_POST['min_working_hours'] ?? 8),
            'max_working_hours' => floatval($_POST['max_working_hours'] ?? 12),
            'early_checkin_minutes' => intval($_POST['early_checkin_minutes'] ?? 30),
            'late_checkout_allowed' => isset($_POST['late_checkout_allowed']) ? 1 : 0,
            'overtime_allowed' => isset($_POST['overtime_allowed']) ? 1 : 0,
            'remote_checkin_allowed' => isset($_POST['remote_checkin_allowed']) ? 1 : 0,
            'late_penalty_per_minute' => floatval($_POST['late_penalty_per_minute'] ?? 0.5),
            'early_bonus_points' => floatval($_POST['early_bonus_points'] ?? 5),
            'overtime_bonus_per_hour' => floatval($_POST['overtime_bonus_per_hour'] ?? 10),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'effective_from' => !empty($_POST['effective_from']) ? $_POST['effective_from'] : null,
            'effective_until' => !empty($_POST['effective_until']) ? $_POST['effective_until'] : null,
            'notes' => $_POST['notes'] ?? null,
            'created_by' => current_user_id(),
        ];
        
        // تحقق من وجود جدول سابق
        $existing = Database::fetchOne("SELECT id FROM employee_schedules WHERE user_id = ?", [$userId]);
        
        try {
            if ($existing) {
                // تحديث
                unset($scheduleData['user_id']);
                unset($scheduleData['created_by']);
                $sets = [];
                $params = [];
                foreach ($scheduleData as $key => $value) {
                    $sets[] = "{$key} = ?";
                    $params[] = $value;
                }
                $params[] = $userId;
                Database::query("UPDATE employee_schedules SET " . implode(', ', $sets) . " WHERE user_id = ?", $params);
                $message = 'تم تحديث جدول الدوام بنجاح';
            } else {
                // إدراج جديد
                $columns = implode(', ', array_keys($scheduleData));
                $placeholders = implode(', ', array_fill(0, count($scheduleData), '?'));
                Database::query("INSERT INTO employee_schedules ({$columns}) VALUES ({$placeholders})", array_values($scheduleData));
                $message = 'تم إنشاء جدول الدوام بنجاح';
            }
            
            log_activity('schedule_updated', "تم تحديث جدول دوام الموظف #{$userId}");
            
            // إعادة تحميل الصفحة
            header('Location: ' . $_SERVER['REQUEST_URI'] . '?success=1');
            exit;
        } catch (Exception $e) {
            $message = 'خطأ: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// رسالة النجاح
if (isset($_GET['success'])) {
    $message = 'تم حفظ الإعدادات بنجاح';
}

// جلب بيانات موظف محدد (AJAX)
if (isset($_GET['get_schedule']) && isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    $userId = intval($_GET['user_id']);
    $schedule = Database::fetchOne("SELECT * FROM employee_schedules WHERE user_id = ?", [$userId]);
    echo json_encode($schedule ?: ['new' => true]);
    exit;
}

// أيام الأسبوع
$weekDays = [
    0 => 'الأحد',
    1 => 'الاثنين',
    2 => 'الثلاثاء',
    3 => 'الأربعاء',
    4 => 'الخميس',
    5 => 'الجمعة',
    6 => 'السبت'
];

// أنواع الحضور
$attendanceModes = [
    'unrestricted' => ['label' => 'غير مشروط', 'icon' => 'unlock', 'color' => 'success', 'desc' => 'يمكن تسجيل الحضور في أي وقت ومن أي مكان'],
    'time_only' => ['label' => 'مشروط بالوقت فقط', 'icon' => 'clock', 'color' => 'info', 'desc' => 'مرتبط بوقت الدوام المحدد فقط'],
    'location_only' => ['label' => 'مشروط بالموقع فقط', 'icon' => 'geo-alt', 'color' => 'warning', 'desc' => 'يجب التواجد في نطاق الفرع'],
    'time_and_location' => ['label' => 'مشروط بالوقت والموقع', 'icon' => 'shield-check', 'color' => 'danger', 'desc' => 'الأكثر صرامة: وقت + موقع']
];

include '../includes/header.php';
?>

<style>
    :root {
        --page-bg: #f8f9fa;
        --card-bg: #ffffff;
        --text-primary: #1a1a2e;
        --text-secondary: #495057;
        --text-muted: #6c757d;
        --border-color: #dee2e6;
        --sarh-primary: #e65100;
        --sarh-primary-light: rgba(230, 81, 0, 0.1);
    }
    
    body {
        background: var(--page-bg) !important;
    }
    
    .schedule-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .schedule-card:hover {
        border-color: var(--sarh-primary);
        box-shadow: 0 4px 16px rgba(230, 81, 0, 0.15);
    }
    
    .schedule-card h5 {
        color: var(--text-primary);
    }
    
    .employee-avatar {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid var(--sarh-primary);
    }
    
    .mode-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .mode-card {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fff;
    }
    
    .mode-card:hover {
        background: var(--sarh-primary-light);
        border-color: var(--sarh-primary);
    }
    
    .mode-card.selected {
        border-color: var(--sarh-primary);
        background: var(--sarh-primary-light);
    }
    
    .mode-card input[type="radio"] {
        display: none;
    }
    
    .mode-card .fw-bold {
        color: var(--text-primary);
    }
    
    .mode-card small {
        color: var(--text-muted);
    }
    
    .day-checkbox {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        border-radius: 10px;
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-primary);
        background: #fff;
    }
    
    .day-checkbox:hover {
        border-color: var(--sarh-primary);
        background: var(--sarh-primary-light);
    }
    
    .day-checkbox.checked {
        background: var(--sarh-primary);
        border-color: var(--sarh-primary);
        color: #fff;
    }
    
    .day-checkbox input {
        display: none;
    }
    
    .toggle-card {
        background: #f8f9fa;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    
    .toggle-card .fw-bold {
        color: var(--text-primary);
    }
    
    .toggle-card small {
        color: var(--text-muted);
    }
    
    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--sarh-primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-control, .form-select {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 10px;
    }
    
    .form-control:focus, .form-select:focus {
        background: #fff;
        border-color: var(--sarh-primary);
        color: var(--text-primary);
        box-shadow: 0 0 0 3px rgba(230, 81, 0, 0.15);
    }
    
    .form-label {
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .employee-select-item {
        padding: 12px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
        background: #fff;
        margin-bottom: 8px;
    }
    
    .employee-select-item:hover {
        background: var(--sarh-primary-light);
        border-color: var(--sarh-primary);
    }
    
    .employee-select-item.active {
        background: var(--sarh-primary-light);
        border-color: var(--sarh-primary);
    }
    
    .employee-select-item .fw-bold {
        color: var(--text-primary);
    }
    
    .employee-select-item .text-muted {
        color: var(--text-muted) !important;
    }
    
    .search-box {
        position: sticky;
        top: 0;
        background: var(--card-bg);
        padding: 10px 0;
        z-index: 10;
    }
    
    #employeesList {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .quick-stats {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .quick-stat {
        flex: 1;
        background: var(--sarh-primary-light);
        border-radius: 10px;
        padding: 12px;
        text-align: center;
        border: 1px solid rgba(230, 81, 0, 0.2);
    }
    
    .quick-stat-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--sarh-primary);
    }
    
    .quick-stat-label {
        font-size: 11px;
        color: var(--text-secondary);
    }
    
    #scheduleFormContent {
        color: var(--text-primary);
    }
    
    #selectPrompt {
        color: var(--text-muted);
    }
    
    #selectPrompt i {
        color: var(--sarh-primary) !important;
        opacity: 0.7;
    }
    
    .btn-warning {
        background: var(--sarh-primary);
        border-color: var(--sarh-primary);
        color: #fff;
    }
    
    .btn-warning:hover {
        background: #bf4400;
        border-color: #bf4400;
        color: #fff;
    }
    
    .form-check-input:checked {
        background-color: var(--sarh-primary);
        border-color: var(--sarh-primary);
    }
</style>

<div class="container-fluid py-4">
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
        <i class="bi bi-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- قائمة الموظفين -->
        <div class="col-lg-4">
            <div class="schedule-card p-3 mb-3">
                <h5 class="mb-3">
                    <i class="bi bi-people-fill text-warning me-2"></i>
                    الموظفون (<?= count($employees) ?>)
                </h5>
                
                <!-- إحصائيات سريعة -->
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="quick-stat-value"><?= count(array_filter($employees, fn($e) => $e['schedule_id'])) ?></div>
                        <div class="quick-stat-label">لديهم جدول</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value"><?= count(array_filter($employees, fn($e) => !$e['schedule_id'])) ?></div>
                        <div class="quick-stat-label">بدون جدول</div>
                    </div>
                </div>
                
                <div class="search-box">
                    <input type="text" class="form-control" id="searchEmployee" placeholder="🔍 ابحث عن موظف...">
                </div>
                
                <div id="employeesList">
                    <?php foreach ($employees as $emp): ?>
                    <div class="employee-select-item d-flex align-items-center gap-3" 
                         data-user-id="<?= $emp['id'] ?>"
                         data-name="<?= htmlspecialchars($emp['full_name']) ?>">
                        <img src="<?= $emp['avatar'] ? url('uploads/avatars/' . $emp['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($emp['full_name']) . '&background=ff6f00&color=fff' ?>" 
                             class="employee-avatar" alt="">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars($emp['full_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($emp['job_title'] ?? $emp['emp_code']) ?></small>
                        </div>
                        <?php if ($emp['schedule_id']): ?>
                            <span class="badge bg-<?= $attendanceModes[$emp['attendance_mode']]['color'] ?? 'secondary' ?>">
                                <i class="bi bi-<?= $attendanceModes[$emp['attendance_mode']]['icon'] ?? 'question' ?>"></i>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary">
                                <i class="bi bi-dash"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- نموذج الإعدادات -->
        <div class="col-lg-8">
            <form method="POST" id="scheduleForm">
                <input type="hidden" name="user_id" id="selectedUserId" value="">
                
                <div class="schedule-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-week text-warning me-2"></i>
                            <span id="selectedEmployeeName">اختر موظفاً من القائمة</span>
                        </h5>
                        <button type="submit" class="btn btn-warning" id="saveBtn" disabled>
                            <i class="bi bi-save me-1"></i>
                            حفظ الإعدادات
                        </button>
                    </div>
                    
                    <div id="scheduleFormContent" style="display: none;">
                        <!-- نوع الحضور -->
                        <div class="mb-4">
                            <div class="section-title">
                                <i class="bi bi-shield-lock"></i>
                                نوع تسجيل الحضور
                            </div>
                            <div class="row g-2">
                                <?php foreach ($attendanceModes as $mode => $info): ?>
                                <div class="col-md-6 col-lg-3">
                                    <label class="mode-card d-block" data-mode="<?= $mode ?>">
                                        <input type="radio" name="attendance_mode" value="<?= $mode ?>" <?= $mode === 'time_and_location' ? 'checked' : '' ?>>
                                        <div class="text-center">
                                            <div class="text-<?= $info['color'] ?> mb-2">
                                                <i class="bi bi-<?= $info['icon'] ?>" style="font-size: 24px;"></i>
                                            </div>
                                            <div class="fw-bold mb-1"><?= $info['label'] ?></div>
                                            <small class="text-muted"><?= $info['desc'] ?></small>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- أوقات الدوام -->
                        <div class="row mb-4" id="timeSettings">
                            <div class="col-12">
                                <div class="section-title">
                                    <i class="bi bi-clock"></i>
                                    أوقات الدوام
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">بداية الدوام</label>
                                <input type="time" class="form-control" name="work_start_time" value="08:00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">نهاية الدوام</label>
                                <input type="time" class="form-control" name="work_end_time" value="17:00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">فترة السماح (دقيقة)</label>
                                <input type="number" class="form-control" name="grace_period_minutes" value="15" min="0" max="120">
                            </div>
                        </div>
                        
                        <!-- أيام العمل -->
                        <div class="mb-4" id="daysSettings">
                            <div class="section-title">
                                <i class="bi bi-calendar3"></i>
                                أيام العمل
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php foreach ($weekDays as $num => $name): ?>
                                <label class="day-checkbox <?= in_array($num, [0,1,2,3,4]) ? 'checked' : '' ?>" data-day="<?= $num ?>">
                                    <input type="checkbox" name="working_days[]" value="<?= $num ?>" <?= in_array($num, [0,1,2,3,4]) ? 'checked' : '' ?>>
                                    <?= mb_substr($name, 0, 1) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- إعدادات الموقع -->
                        <div class="mb-4" id="locationSettings">
                            <div class="section-title">
                                <i class="bi bi-geo-alt"></i>
                                إعدادات الموقع
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">الفروع المسموحة</label>
                                    <select class="form-select" name="allowed_branches[]" multiple>
                                        <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">اترك فارغاً للفرع الافتراضي</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">نصف قطر السماح (متر)</label>
                                    <input type="number" class="form-control" name="geofence_radius" value="100" min="50" max="1000">
                                </div>
                            </div>
                        </div>
                        
                        <!-- الأذونات الخاصة -->
                        <div class="mb-4">
                            <div class="section-title">
                                <i class="bi bi-key"></i>
                                الأذونات الخاصة
                            </div>
                            
                            <div class="toggle-card">
                                <div>
                                    <div class="fw-bold">الساعات المرنة</div>
                                    <small class="text-muted">السماح ببداية ونهاية مرنة</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_flexible_hours" id="flexibleHours">
                                </div>
                            </div>
                            
                            <div class="toggle-card">
                                <div>
                                    <div class="fw-bold">الحضور عن بُعد</div>
                                    <small class="text-muted">السماح بتسجيل الحضور من خارج الفرع</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="remote_checkin_allowed" id="remoteCheckin">
                                </div>
                            </div>
                            
                            <div class="toggle-card">
                                <div>
                                    <div class="fw-bold">العمل الإضافي</div>
                                    <small class="text-muted">احتساب نقاط العمل الإضافي</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="overtime_allowed" id="overtimeAllowed">
                                </div>
                            </div>
                            
                            <div class="toggle-card">
                                <div>
                                    <div class="fw-bold">الانصراف المتأخر</div>
                                    <small class="text-muted">السماح بتسجيل الانصراف بعد نهاية الدوام</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="late_checkout_allowed" id="lateCheckout" checked>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-2">
                                <div class="col-md-4">
                                    <label class="form-label">الحضور المبكر (دقيقة)</label>
                                    <input type="number" class="form-control" name="early_checkin_minutes" value="30" min="0" max="120">
                                    <small class="text-muted">كم دقيقة قبل الدوام</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الحد الأدنى للساعات</label>
                                    <input type="number" class="form-control" name="min_working_hours" value="8" min="1" max="24" step="0.5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الحد الأقصى للساعات</label>
                                    <input type="number" class="form-control" name="max_working_hours" value="12" min="1" max="24" step="0.5">
                                </div>
                            </div>
                        </div>
                        
                        <!-- النقاط والخصومات -->
                        <div class="mb-4">
                            <div class="section-title">
                                <i class="bi bi-star"></i>
                                النقاط والخصومات
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">خصم التأخير / دقيقة</label>
                                    <input type="number" class="form-control" name="late_penalty_per_minute" value="0.5" min="0" max="10" step="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">مكافأة الحضور المبكر</label>
                                    <input type="number" class="form-control" name="early_bonus_points" value="5" min="0" max="50" step="0.5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">مكافأة العمل الإضافي / ساعة</label>
                                    <input type="number" class="form-control" name="overtime_bonus_per_hour" value="10" min="0" max="100" step="0.5">
                                </div>
                            </div>
                        </div>
                        
                        <!-- فترة السريان -->
                        <div class="mb-4">
                            <div class="section-title">
                                <i class="bi bi-calendar-range"></i>
                                فترة السريان
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">من تاريخ</label>
                                    <input type="date" class="form-control" name="effective_from">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">إلى تاريخ</label>
                                    <input type="date" class="form-control" name="effective_until">
                                    <small class="text-muted">اترك فارغاً للدوام</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الحالة</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="scheduleActive" checked>
                                        <label class="form-check-label" for="scheduleActive">الجدول فعّال</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ملاحظات -->
                        <div class="mb-4">
                            <label class="form-label">ملاحظات</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                        </div>
                    </div>
                    
                    <!-- رسالة اختيار موظف -->
                    <div id="selectPrompt" class="text-center py-5">
                        <i class="bi bi-person-badge" style="font-size: 64px; color: var(--sarh-primary); opacity: 0.5;"></i>
                        <p class="text-muted mt-3">اختر موظفاً من القائمة لتعديل جدول دوامه</p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const employeeItems = document.querySelectorAll('.employee-select-item');
    const scheduleForm = document.getElementById('scheduleFormContent');
    const selectPrompt = document.getElementById('selectPrompt');
    const selectedUserIdInput = document.getElementById('selectedUserId');
    const selectedEmployeeName = document.getElementById('selectedEmployeeName');
    const saveBtn = document.getElementById('saveBtn');
    const searchInput = document.getElementById('searchEmployee');
    
    // البحث
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        employeeItems.forEach(item => {
            const name = item.dataset.name.toLowerCase();
            item.style.display = name.includes(query) ? 'flex' : 'none';
        });
    });
    
    // اختيار موظف
    employeeItems.forEach(item => {
        item.addEventListener('click', function() {
            // إزالة التحديد السابق
            employeeItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            const userId = this.dataset.userId;
            const userName = this.dataset.name;
            
            selectedUserIdInput.value = userId;
            selectedEmployeeName.textContent = 'إعدادات: ' + userName;
            saveBtn.disabled = false;
            
            // إظهار النموذج
            scheduleForm.style.display = 'block';
            selectPrompt.style.display = 'none';
            
            // جلب البيانات
            fetch(`?get_schedule=1&user_id=${userId}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.new) {
                        // تعبئة البيانات
                        document.querySelector(`input[name="attendance_mode"][value="${data.attendance_mode}"]`).checked = true;
                        updateModeCards();
                        
                        document.querySelector('[name="work_start_time"]').value = data.work_start_time?.substring(0, 5) || '08:00';
                        document.querySelector('[name="work_end_time"]').value = data.work_end_time?.substring(0, 5) || '17:00';
                        document.querySelector('[name="grace_period_minutes"]').value = data.grace_period_minutes || 15;
                        document.querySelector('[name="geofence_radius"]').value = data.geofence_radius || 100;
                        document.querySelector('[name="early_checkin_minutes"]').value = data.early_checkin_minutes || 30;
                        document.querySelector('[name="min_working_hours"]').value = data.min_working_hours || 8;
                        document.querySelector('[name="max_working_hours"]').value = data.max_working_hours || 12;
                        document.querySelector('[name="late_penalty_per_minute"]').value = data.late_penalty_per_minute || 0.5;
                        document.querySelector('[name="early_bonus_points"]').value = data.early_bonus_points || 5;
                        document.querySelector('[name="overtime_bonus_per_hour"]').value = data.overtime_bonus_per_hour || 10;
                        
                        document.getElementById('flexibleHours').checked = data.is_flexible_hours == 1;
                        document.getElementById('remoteCheckin').checked = data.remote_checkin_allowed == 1;
                        document.getElementById('overtimeAllowed').checked = data.overtime_allowed == 1;
                        document.getElementById('lateCheckout').checked = data.late_checkout_allowed == 1;
                        document.getElementById('scheduleActive').checked = data.is_active == 1;
                        
                        document.querySelector('[name="effective_from"]').value = data.effective_from || '';
                        document.querySelector('[name="effective_until"]').value = data.effective_until || '';
                        document.querySelector('[name="notes"]').value = data.notes || '';
                        
                        // أيام العمل
                        const workingDays = JSON.parse(data.working_days || '[0,1,2,3,4]');
                        document.querySelectorAll('.day-checkbox').forEach(dc => {
                            const day = parseInt(dc.dataset.day);
                            const checkbox = dc.querySelector('input');
                            checkbox.checked = workingDays.includes(day);
                            dc.classList.toggle('checked', workingDays.includes(day));
                        });
                        
                        // الفروع
                        if (data.allowed_branches) {
                            const branches = JSON.parse(data.allowed_branches);
                            document.querySelectorAll('[name="allowed_branches[]"] option').forEach(opt => {
                                opt.selected = branches.includes(parseInt(opt.value));
                            });
                        }
                    } else {
                        // إعادة تعيين للقيم الافتراضية
                        document.querySelector('input[name="attendance_mode"][value="time_and_location"]').checked = true;
                        updateModeCards();
                    }
                });
        });
    });
    
    // تحديث بطاقات نوع الحضور
    function updateModeCards() {
        document.querySelectorAll('.mode-card').forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('selected', radio.checked);
        });
        
        // إظهار/إخفاء الإعدادات حسب النوع
        const mode = document.querySelector('input[name="attendance_mode"]:checked').value;
        const timeSettings = document.getElementById('timeSettings');
        const locationSettings = document.getElementById('locationSettings');
        
        timeSettings.style.display = (mode === 'unrestricted' || mode === 'location_only') ? 'none' : 'flex';
        locationSettings.style.display = (mode === 'unrestricted' || mode === 'time_only') ? 'none' : 'block';
    }
    
    // أحداث بطاقات النوع
    document.querySelectorAll('.mode-card').forEach(card => {
        card.addEventListener('click', function() {
            this.querySelector('input[type="radio"]').checked = true;
            updateModeCards();
        });
    });
    
    // أيام العمل
    document.querySelectorAll('.day-checkbox').forEach(dc => {
        dc.addEventListener('click', function() {
            const checkbox = this.querySelector('input');
            checkbox.checked = !checkbox.checked;
            this.classList.toggle('checked', checkbox.checked);
        });
    });
    
    updateModeCards();
});
</script>

<?php include '../includes/footer.php'; ?>
