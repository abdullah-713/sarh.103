<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ACTIONS API HANDLER                                  ║
 * ║           معالج API لنظام الإجراءات والمهام                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Endpoint: POST /api/actions/handler.php                                     ║
 * ║  Features:                                                                   ║
 * ║  - CRUD operations for actions/tasks                                        ║
 * ║  - Approval workflow management                                             ║
 * ║  - Comments and timeline                                                    ║
 * ║  - Role-based access control                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Load dependencies
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK
// ═══════════════════════════════════════════════════════════════════════════════

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode([
        'success' => false,
        'error' => 'unauthorized',
        'message' => 'غير مصرح بالوصول'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// CSRF VERIFICATION
// ═══════════════════════════════════════════════════════════════════════════════

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (empty($csrf_token) || !verify_csrf($csrf_token)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'error' => 'csrf_invalid',
        'message' => 'رمز الأمان غير صالح'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// PARSE INPUT
// ═══════════════════════════════════════════════════════════════════════════════

$input = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? '');

if (empty($action)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'missing_action',
        'message' => 'يجب تحديد الإجراء المطلوب'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate unique action code
 * توليد كود فريد للإجراء
 */
function generate_action_code(): string {
    $year = date('Y');
    
    // Get last action code for this year
    $sql = "SELECT action_code FROM actions 
            WHERE action_code LIKE :pattern 
            ORDER BY id DESC LIMIT 1";
    
    $lastCode = Database::fetchValue($sql, ['pattern' => "ACT-{$year}-%"]);
    
    if ($lastCode) {
        // Extract number and increment
        preg_match('/ACT-\d{4}-(\d{5})/', $lastCode, $matches);
        $number = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $number = 1;
    }
    
    return sprintf('ACT-%s-%05d', $year, $number);
}

/**
 * Check if user can modify action
 * التحقق من صلاحية تعديل الإجراء
 */
function can_modify_action(array $action): bool {
    $userId = current_user_id();
    $roleLevel = current_role_level();
    
    // Admin can modify all
    if ($roleLevel >= ROLE_ADMIN) {
        return true;
    }
    
    // Requester can modify own pending/draft actions
    if ($action['requester_id'] == $userId && 
        in_array($action['status'], ['draft', 'pending'])) {
        return true;
    }
    
    // Assigned user can modify assigned actions
    if ($action['assigned_to'] == $userId) {
        return true;
    }
    
    // Managers can modify actions in their branch
    if ($roleLevel >= ROLE_MANAGER) {
        return true;
    }
    
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════════════════════

try {
    
    switch ($action) {
        
        // ═══════════════════════════════════════════════════════════════════════
        // LIST ACTIONS - قائمة الإجراءات
        // ═══════════════════════════════════════════════════════════════════════
        case 'list':
            $page = max(1, intval($input['page'] ?? 1));
            $perPage = min(100, max(1, intval($input['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            
            $userId = current_user_id();
            $roleLevel = current_role_level();
            
            // Build filters
            $where = ['deleted_at IS NULL'];
            $params = [];
            
            // Filter by type
            if (!empty($input['type'])) {
                $where[] = 'type = :type';
                $params['type'] = $input['type'];
            }
            
            // Filter by status
            if (!empty($input['status'])) {
                $where[] = 'status = :status';
                $params['status'] = $input['status'];
            }
            
            // Filter by priority
            if (!empty($input['priority'])) {
                $where[] = 'priority = :priority';
                $params['priority'] = $input['priority'];
            }
            
            // Filter by view mode
            $view = $input['view'] ?? 'my';
            if ($view === 'my') {
                $where[] = '(requester_id = :user_id OR assigned_to = :user_id)';
                $params['user_id'] = $userId;
            } elseif ($view === 'assigned') {
                $where[] = 'assigned_to = :user_id';
                $params['user_id'] = $userId;
            } elseif ($view === 'pending_approval' && $roleLevel >= ROLE_MANAGER) {
                $where[] = 'status = :approval_status';
                $params['approval_status'] = 'waiting_approval';
            }
            // Admins see all by default
            
            $whereClause = implode(' AND ', $where);
            
            // Get total count
            $totalSql = "SELECT COUNT(*) FROM actions WHERE {$whereClause}";
            $total = Database::fetchValue($totalSql, $params);
            
            // Get actions
            $sql = "SELECT 
                        a.*,
                        u.full_name as requester_name,
                        u.avatar as requester_avatar,
                        assigned.full_name as assigned_name,
                        assigned.avatar as assigned_avatar,
                        b.name as branch_name
                    FROM actions a
                    INNER JOIN users u ON a.requester_id = u.id
                    LEFT JOIN users assigned ON a.assigned_to = assigned.id
                    LEFT JOIN branches b ON a.requester_branch_id = b.id
                    WHERE {$whereClause}
                    ORDER BY a.created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            $params['limit'] = $perPage;
            $params['offset'] = $offset;
            
            $actions = Database::fetchAll($sql, $params);
            
            json_response([
                'success' => true,
                'data' => $actions,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // GET SINGLE ACTION - عرض إجراء واحد
        // ═══════════════════════════════════════════════════════════════════════
        case 'get':
            $id = intval($input['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('معرف الإجراء مطلوب');
            }
            
            // Get action with details
            $sql = "SELECT 
                        a.*,
                        u.full_name as requester_name,
                        u.avatar as requester_avatar,
                        u.emp_code as requester_emp_code,
                        assigned.full_name as assigned_name,
                        assigned.avatar as assigned_avatar,
                        assigned_by_user.full_name as assigned_by_name,
                        b.name as branch_name
                    FROM actions a
                    INNER JOIN users u ON a.requester_id = u.id
                    LEFT JOIN users assigned ON a.assigned_to = assigned.id
                    LEFT JOIN users assigned_by_user ON a.assigned_by = assigned_by_user.id
                    LEFT JOIN branches b ON a.requester_branch_id = b.id
                    WHERE a.id = :id AND a.deleted_at IS NULL";
            
            $actionData = Database::fetchOne($sql, ['id' => $id]);
            
            if (!$actionData) {
                throw new Exception('الإجراء غير موجود');
            }
            
            // Get comments/timeline
            $commentsSql = "SELECT 
                                c.*,
                                u.full_name as user_name,
                                u.avatar as user_avatar
                            FROM action_comments c
                            INNER JOIN users u ON c.user_id = u.id
                            WHERE c.action_id = :action_id
                            ORDER BY c.created_at ASC";
            
            $comments = Database::fetchAll($commentsSql, ['action_id' => $id]);
            
            // Get approvals if needed
            $approvals = [];
            if ($actionData['max_approval_level'] > 0) {
                $approvalsSql = "SELECT 
                                    ap.*,
                                    u.full_name as approver_name,
                                    u.avatar as approver_avatar
                                FROM action_approvals ap
                                INNER JOIN users u ON ap.approver_id = u.id
                                WHERE ap.action_id = :action_id
                                ORDER BY ap.level ASC";
                
                $approvals = Database::fetchAll($approvalsSql, ['action_id' => $id]);
            }
            
            json_response([
                'success' => true,
                'data' => [
                    'action' => $actionData,
                    'comments' => $comments,
                    'approvals' => $approvals
                ]
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // CREATE ACTION - إنشاء إجراء جديد
        // ═══════════════════════════════════════════════════════════════════════
        case 'create':
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $type = $input['type'] ?? 'request';
            $priority = $input['priority'] ?? 'medium';
            
            if (empty($title)) {
                throw new Exception('عنوان الإجراء مطلوب');
            }
            
            // Generate unique code
            $actionCode = generate_action_code();
            
            // Get user info
            $userId = current_user_id();
            $userInfo = Database::fetchOne(
                "SELECT branch_id FROM users WHERE id = :id",
                ['id' => $userId]
            );
            
            // Insert action
            $actionId = Database::insert('actions', [
                'action_code' => $actionCode,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'category' => $input['category'] ?? null,
                'priority' => $priority,
                'status' => 'pending',
                'requester_id' => $userId,
                'requester_branch_id' => $userInfo['branch_id'] ?? null,
                'due_date' => $input['due_date'] ?? null,
                'max_approval_level' => intval($input['approval_levels'] ?? 1),
                'metadata' => !empty($input['metadata']) ? json_encode($input['metadata']) : null
            ]);
            
            // Add initial comment
            Database::insert('action_comments', [
                'action_id' => $actionId,
                'user_id' => $userId,
                'comment_type' => 'system',
                'content' => 'تم إنشاء الإجراء'
            ]);
            
            // Log activity
            log_activity(
                'action_created',
                'actions',
                "إنشاء إجراء جديد: {$actionCode} - {$title}",
                $actionId
            );
            
            json_response([
                'success' => true,
                'message' => 'تم إنشاء الإجراء بنجاح',
                'data' => [
                    'id' => $actionId,
                    'action_code' => $actionCode
                ]
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // UPDATE ACTION - تحديث إجراء
        // ═══════════════════════════════════════════════════════════════════════
        case 'update':
            $id = intval($input['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('معرف الإجراء مطلوب');
            }
            
            // Get action
            $actionData = Database::fetchOne(
                "SELECT * FROM actions WHERE id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );
            
            if (!$actionData) {
                throw new Exception('الإجراء غير موجود');
            }
            
            // Check permissions
            if (!can_modify_action($actionData)) {
                throw new Exception('ليس لديك صلاحية تعديل هذا الإجراء');
            }
            
            // Update fields
            $updateData = [];
            
            if (isset($input['title'])) {
                $updateData['title'] = trim($input['title']);
            }
            if (isset($input['description'])) {
                $updateData['description'] = trim($input['description']);
            }
            if (isset($input['priority'])) {
                $updateData['priority'] = $input['priority'];
            }
            if (isset($input['due_date'])) {
                $updateData['due_date'] = $input['due_date'];
            }
            
            if (!empty($updateData)) {
                Database::update('actions', $updateData, 'id = :id', ['id' => $id]);
                
                // Add comment
                Database::insert('action_comments', [
                    'action_id' => $id,
                    'user_id' => current_user_id(),
                    'comment_type' => 'system',
                    'content' => 'تم تحديث الإجراء'
                ]);
                
                log_activity('action_updated', 'actions', 'تحديث إجراء', $id);
            }
            
            json_response([
                'success' => true,
                'message' => 'تم تحديث الإجراء بنجاح'
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // CHANGE STATUS - تغيير الحالة
        // ═══════════════════════════════════════════════════════════════════════
        case 'change_status':
            $id = intval($input['id'] ?? 0);
            $newStatus = $input['status'] ?? '';
            
            if (!$id || empty($newStatus)) {
                throw new Exception('المعرف والحالة الجديدة مطلوبان');
            }
            
            // Get action
            $actionData = Database::fetchOne(
                "SELECT * FROM actions WHERE id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );
            
            if (!$actionData) {
                throw new Exception('الإجراء غير موجود');
            }
            
            $oldStatus = $actionData['status'];
            
            // Check permissions for status change
            $roleLevel = current_role_level();
            
            if (in_array($newStatus, ['approved', 'rejected']) && $roleLevel < ROLE_MANAGER) {
                throw new Exception('فقط المدراء يمكنهم الموافقة أو الرفض');
            }
            
            // Update status
            $updateData = ['status' => $newStatus];
            
            if ($newStatus === 'completed') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
            }
            
            Database::update('actions', $updateData, 'id = :id', ['id' => $id]);
            
            // Add status change comment
            Database::insert('action_comments', [
                'action_id' => $id,
                'user_id' => current_user_id(),
                'comment_type' => 'status_change',
                'content' => "تغيير الحالة من {$oldStatus} إلى {$newStatus}",
                'old_value' => $oldStatus,
                'new_value' => $newStatus
            ]);
            
            log_activity('action_status_changed', 'actions', "تغيير حالة الإجراء: {$oldStatus} -> {$newStatus}", $id);
            
            json_response([
                'success' => true,
                'message' => 'تم تغيير الحالة بنجاح'
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // ASSIGN ACTION - تكليف موظف
        // ═══════════════════════════════════════════════════════════════════════
        case 'assign':
            $id = intval($input['id'] ?? 0);
            $assignedTo = intval($input['assigned_to'] ?? 0);
            
            if (!$id || !$assignedTo) {
                throw new Exception('المعرف والموظف المكلف مطلوبان');
            }
            
            // Check permissions
            if (current_role_level() < ROLE_MANAGER) {
                throw new Exception('فقط المدراء يمكنهم تكليف الموظفين');
            }
            
            // Update assignment
            Database::update('actions', [
                'assigned_to' => $assignedTo,
                'assigned_by' => current_user_id(),
                'assigned_at' => date('Y-m-d H:i:s'),
                'status' => 'in_progress'
            ], 'id = :id', ['id' => $id]);
            
            // Get assigned user name
            $assignedUser = Database::fetchOne(
                "SELECT full_name FROM users WHERE id = :id",
                ['id' => $assignedTo]
            );
            
            // Add comment
            Database::insert('action_comments', [
                'action_id' => $id,
                'user_id' => current_user_id(),
                'comment_type' => 'assignment',
                'content' => "تم التكليف إلى: " . ($assignedUser['full_name'] ?? 'موظف'),
                'new_value' => (string)$assignedTo
            ]);
            
            log_activity('action_assigned', 'actions', 'تكليف إجراء', $id);
            
            json_response([
                'success' => true,
                'message' => 'تم التكليف بنجاح'
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // ADD COMMENT - إضافة تعليق
        // ═══════════════════════════════════════════════════════════════════════
        case 'add_comment':
            $actionId = intval($input['action_id'] ?? 0);
            $content = trim($input['content'] ?? '');
            
            if (!$actionId || empty($content)) {
                throw new Exception('معرف الإجراء والمحتوى مطلوبان');
            }
            
            // Verify action exists
            $exists = Database::fetchValue(
                "SELECT COUNT(*) FROM actions WHERE id = :id AND deleted_at IS NULL",
                ['id' => $actionId]
            );
            
            if (!$exists) {
                throw new Exception('الإجراء غير موجود');
            }
            
            // Insert comment
            $commentId = Database::insert('action_comments', [
                'action_id' => $actionId,
                'user_id' => current_user_id(),
                'comment_type' => 'comment',
                'content' => $content,
                'is_internal' => intval($input['is_internal'] ?? 0)
            ]);
            
            json_response([
                'success' => true,
                'message' => 'تمت إضافة التعليق بنجاح',
                'data' => ['id' => $commentId]
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // GET TEMPLATES - جلب القوالب
        // ═══════════════════════════════════════════════════════════════════════
        case 'templates':
            $templates = Database::fetchAll(
                "SELECT * FROM action_templates WHERE is_active = 1 ORDER BY name"
            );
            
            json_response([
                'success' => true,
                'data' => $templates
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // GET STATS - الإحصائيات
        // ═══════════════════════════════════════════════════════════════════════
        case 'stats':
            $userId = current_user_id();
            $roleLevel = current_role_level();
            
            // Build WHERE clause based on role
            $whereClause = $roleLevel >= ROLE_ADMIN 
                ? "deleted_at IS NULL" 
                : "(requester_id = :user_id OR assigned_to = :user_id) AND deleted_at IS NULL";
            
            $params = $roleLevel >= ROLE_ADMIN ? [] : ['user_id' => $userId];
            
            $stats = [
                'pending' => Database::fetchValue(
                    "SELECT COUNT(*) FROM actions WHERE status = 'pending' AND {$whereClause}",
                    $params
                ),
                'in_progress' => Database::fetchValue(
                    "SELECT COUNT(*) FROM actions WHERE status = 'in_progress' AND {$whereClause}",
                    $params
                ),
                'waiting_approval' => Database::fetchValue(
                    "SELECT COUNT(*) FROM actions WHERE status = 'waiting_approval' AND {$whereClause}",
                    $params
                ),
                'completed' => Database::fetchValue(
                    "SELECT COUNT(*) FROM actions WHERE status = 'completed' AND {$whereClause}",
                    $params
                ),
                'total' => Database::fetchValue(
                    "SELECT COUNT(*) FROM actions WHERE {$whereClause}",
                    $params
                )
            ];
            
            json_response([
                'success' => true,
                'data' => $stats
            ]);
            break;
        
        // ═══════════════════════════════════════════════════════════════════════
        // DELETE ACTION - حذف (Soft Delete)
        // ═══════════════════════════════════════════════════════════════════════
        case 'delete':
            $id = intval($input['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('معرف الإجراء مطلوب');
            }
            
            // Check permissions
            if (current_role_level() < ROLE_ADMIN) {
                throw new Exception('فقط المدراء يمكنهم حذف الإجراءات');
            }
            
            // Soft delete
            Database::update('actions', [
                'deleted_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $id]);
            
            log_activity('action_deleted', 'actions', 'حذف إجراء', $id);
            
            json_response([
                'success' => true,
                'message' => 'تم حذف الإجراء بنجاح'
            ]);
            break;
        
        default:
            throw new Exception('إجراء غير معروف: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    json_response([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
}
