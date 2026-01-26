<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - LEAVES API HANDLER                                   ║
 * ║           معالج API لنظام الإجازات                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Endpoint: POST /api/leaves/handler.php                                      ║
 * ║  Features:                                                                   ║
 * ║  - Create leave requests                                                     ║
 * ║  - List user leave requests                                                  ║
 * ║  - Cancel leave requests                                                     ║
 * ║  - Integration with Actions system                                           ║
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

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? '';

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

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = trim($input['action'] ?? '');

if (empty($action)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'missing_action',
        'message' => 'يجب تحديد الإجراء المطلوب'
    ], JSON_UNESCAPED_UNICODE));
}

$userId = current_user_id();
$roleLevel = current_role_level();

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Calculate working days between two dates
 * حساب أيام العمل بين تاريخين
 */
function calculate_working_days(string $startDate, string $endDate): int {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // Include end date
    
    $days = 0;
    $current = clone $start;
    
    while ($current < $end) {
        // Skip weekends (Friday = 5, Saturday = 6 in Saudi Arabia)
        $dayOfWeek = $current->format('N');
        if ($dayOfWeek != 5 && $dayOfWeek != 6) {
            $days++;
        }
        $current->modify('+1 day');
    }
    
    return $days;
}

/**
 * Check if user has sufficient leave balance
 * التحقق من رصيد الإجازات
 */
function check_leave_balance(int $userId, int $leaveTypeId, int $daysRequested): array {
    try {
        $balance = Database::fetchOne(
            "SELECT * FROM leave_balances 
             WHERE user_id = :user_id 
             AND leave_type_id = :leave_type_id 
             AND year = :year",
            [
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'year' => date('Y')
            ]
        );
        
        if (!$balance) {
            return [
                'success' => false,
                'message' => 'لا يوجد رصيد إجازات لهذا النوع'
            ];
        }
        
        if ($balance['remaining_days'] < $daysRequested) {
            return [
                'success' => false,
                'message' => "الرصيد المتاح ({$balance['remaining_days']} يوم) غير كافي"
            ];
        }
        
        return [
            'success' => true,
            'balance' => $balance
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'خطأ في التحقق من الرصيد'
        ];
    }
}

/**
 * Get User Supervisor
 * الحصول على المشرف المباشر
 */
function get_user_supervisor(int $userId): ?int {
    try {
        // Get user's branch manager or direct supervisor
        $user = Database::fetchOne("SELECT branch_id FROM users WHERE id = :id", ['id' => $userId]);
        
        if (!$user || !$user['branch_id']) {
            return null;
        }
        
        // Find manager or supervisor in the same branch
        $supervisor = Database::fetchOne("
            SELECT id FROM users 
            WHERE branch_id = :branch_id 
            AND role_level >= :min_level 
            AND is_active = 1
            ORDER BY role_level ASC
            LIMIT 1
        ", [
            'branch_id' => $user['branch_id'],
            'min_level' => ROLE_SUPERVISOR
        ]);
        
        return $supervisor ? (int) $supervisor['id'] : null;
        
    } catch (Exception $e) {
        return null;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════════════════════

try {
    switch ($action) {
        
        // ───────────────────────────────────────────────────────────────────────
        // CREATE LEAVE REQUEST
        // إنشاء طلب إجازة
        // ───────────────────────────────────────────────────────────────────────
        case 'create':
            $leaveTypeId = (int) ($input['leave_type_id'] ?? 0);
            $startDate = clean_input($input['start_date'] ?? '');
            $endDate = clean_input($input['end_date'] ?? '');
            $reason = clean_input($input['reason'] ?? '');
            
            // Validation
            if (!$leaveTypeId || !$startDate || !$endDate) {
                throw new Exception('جميع الحقول الأساسية مطلوبة');
            }
            
            // Validate dates
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $now = new DateTime();
            
            if ($start < $now->modify('-1 day')) {
                throw new Exception('لا يمكن طلب إجازة في تاريخ سابق');
            }
            
            if ($end < $start) {
                throw new Exception('تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية');
            }
            
            // Calculate days
            $daysCount = calculate_working_days($startDate, $endDate);
            
            if ($daysCount <= 0) {
                throw new Exception('يجب أن تكون مدة الإجازة يوم عمل واحد على الأقل');
            }
            
            // Check balance
            $balanceCheck = check_leave_balance($userId, $leaveTypeId, $daysCount);
            if (!$balanceCheck['success']) {
                throw new Exception($balanceCheck['message']);
            }
            
            // Get leave type info
            $leaveType = Database::fetchOne(
                "SELECT * FROM leave_types WHERE id = :id AND is_active = 1",
                ['id' => $leaveTypeId]
            );
            
            if (!$leaveType) {
                throw new Exception('نوع الإجازة غير صالح');
            }
            
            // Begin transaction
            Database::beginTransaction();
            
            try {
                // Insert leave request
                $leaveId = Database::insert('leaves', [
                    'user_id' => $userId,
                    'leave_type_id' => $leaveTypeId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days_count' => $daysCount,
                    'reason' => $reason ?: null,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Create action if action_integrations exists
                $actionId = null;
                if (file_exists(__DIR__ . '/../../includes/action_integrations.php')) {
                    require_once __DIR__ . '/../../includes/action_integrations.php';
                    
                    if (function_exists('create_leave_action')) {
                        $actionId = create_leave_action($leaveId, $userId, [
                            'leave_type' => $leaveType['name'],
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'days_count' => $daysCount,
                            'reason' => $reason
                        ]);
                        
                        // Update leave with action_id
                        if ($actionId) {
                            Database::update('leaves', 
                                ['action_id' => $actionId], 
                                'id = :id', 
                                ['id' => $leaveId]
                            );
                        }
                    }
                }
                
                // Log activity
                log_activity(
                    'leave_requested',
                    'leaves',
                    "طلب إجازة: {$leaveType['name']} من {$startDate} إلى {$endDate}",
                    $leaveId
                );
                
                // Commit transaction
                Database::commit();
                
                // Send notification to supervisor/manager
                try {
                    $user = Database::fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
                    $supervisorId = get_user_supervisor($userId);
                    
                    if ($supervisorId) {
                        Database::insert('notifications', [
                            'user_id' => $supervisorId,
                            'type' => 'leave',
                            'title' => 'طلب إجازة جديد',
                            'message' => "{$user['full_name']} قدم طلب إجازة {$leaveType['name']} لمدة {$daysCount} يوم",
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                } catch (Exception $e) {
                    // Notification failure shouldn't break the flow
                    error_log("Notification error: " . $e->getMessage());
                }
                
                json_response([
                    'success' => true,
                    'message' => 'تم إرسال طلب الإجازة بنجاح',
                    'data' => [
                        'leave_id' => $leaveId,
                        'action_id' => $actionId,
                        'days_count' => $daysCount
                    ]
                ]);
                
            } catch (Exception $e) {
                Database::rollback();
                throw $e;
            }
            break;
        
        // ───────────────────────────────────────────────────────────────────────
        // LIST LEAVE REQUESTS
        // عرض طلبات الإجازة
        // ───────────────────────────────────────────────────────────────────────
        case 'list':
            $status = clean_input($input['status'] ?? 'all');
            $limit = min(100, max(1, (int) ($input['limit'] ?? 20)));
            $offset = max(0, (int) ($input['offset'] ?? 0));
            
            // Build query
            $where = "WHERE l.user_id = :user_id";
            $params = ['user_id' => $userId];
            
            if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'])) {
                $where .= " AND l.status = :status";
                $params['status'] = $status;
            }
            
            // Get leaves
            $leaves = Database::fetchAll("
                SELECT 
                    l.*,
                    lt.name as leave_type_name,
                    lt.color as leave_type_color,
                    a.action_code,
                    a.status as action_status
                FROM leaves l
                LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
                LEFT JOIN actions a ON l.action_id = a.id
                {$where}
                ORDER BY l.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ", $params);
            
            // Get total count
            $total = Database::fetchValue("
                SELECT COUNT(*) FROM leaves l {$where}
            ", $params);
            
            json_response([
                'success' => true,
                'data' => [
                    'leaves' => $leaves,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
            break;
        
        // ───────────────────────────────────────────────────────────────────────
        // CANCEL LEAVE REQUEST
        // إلغاء طلب إجازة
        // ───────────────────────────────────────────────────────────────────────
        case 'cancel':
            $leaveId = (int) ($input['leave_id'] ?? 0);
            
            if (!$leaveId) {
                throw new Exception('معرف الإجازة مطلوب');
            }
            
            // Get leave
            $leave = Database::fetchOne(
                "SELECT * FROM leaves WHERE id = :id AND user_id = :user_id",
                ['id' => $leaveId, 'user_id' => $userId]
            );
            
            if (!$leave) {
                throw new Exception('طلب الإجازة غير موجود');
            }
            
            if ($leave['status'] !== 'pending') {
                throw new Exception('لا يمكن إلغاء طلب الإجازة في هذه الحالة');
            }
            
            // Begin transaction
            Database::beginTransaction();
            
            try {
                // Update leave status
                Database::update('leaves',
                    ['status' => 'cancelled'],
                    'id = :id',
                    ['id' => $leaveId]
                );
                
                // Update related action if exists
                if ($leave['action_id']) {
                    Database::update('actions',
                        ['status' => 'cancelled'],
                        'id = :id',
                        ['id' => $leave['action_id']]
                    );
                    
                    // Add comment to action
                    Database::insert('action_comments', [
                        'action_id' => $leave['action_id'],
                        'user_id' => $userId,
                        'comment_type' => 'status_change',
                        'content' => 'تم إلغاء طلب الإجازة من قبل الموظف',
                        'old_value' => 'pending',
                        'new_value' => 'cancelled',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                // Log activity
                log_activity(
                    'leave_cancelled',
                    'leaves',
                    'تم إلغاء طلب الإجازة',
                    $leaveId
                );
                
                Database::commit();
                
                json_response([
                    'success' => true,
                    'message' => 'تم إلغاء طلب الإجازة بنجاح'
                ]);
                
            } catch (Exception $e) {
                Database::rollback();
                throw $e;
            }
            break;
        
        // ───────────────────────────────────────────────────────────────────────
        // GET LEAVE BALANCES
        // عرض أرصدة الإجازات
        // ───────────────────────────────────────────────────────────────────────
        case 'balances':
            $year = (int) ($input['year'] ?? date('Y'));
            
            $balances = Database::fetchAll("
                SELECT 
                    lb.*,
                    lt.name as leave_type_name,
                    lt.color,
                    lt.max_days_per_request
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.user_id = :user_id AND lb.year = :year
                ORDER BY lt.name
            ", [
                'user_id' => $userId,
                'year' => $year
            ]);
            
            json_response([
                'success' => true,
                'data' => [
                    'balances' => $balances,
                    'year' => $year
                ]
            ]);
            break;
        
        // ───────────────────────────────────────────────────────────────────────
        // INVALID ACTION
        // ───────────────────────────────────────────────────────────────────────
        default:
            throw new Exception('الإجراء المطلوب غير معروف');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    json_response([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
}
