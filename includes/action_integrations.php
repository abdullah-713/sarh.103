<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - Action Integrations
 * Sarh Al-Itqan - دوال التكامل بين الأنظمة
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 1.0.0
 * @description Integration functions between different modules and actions system
 */

// منع الوصول المباشر للملف
if (!defined('SARH_SYSTEM')) {
    die('الوصول المباشر غير مسموح');
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * LEAVE REQUEST INTEGRATION
 * التكامل مع نظام الإجازات
 * ═══════════════════════════════════════════════════════════════════════════════
 */

/**
 * Create an action for a leave request
 * إنشاء إجراء لطلب إجازة
 * 
 * @param int $leaveId معرف طلب الإجازة
 * @param int $userId معرف الموظف
 * @param array $leaveData بيانات الإجازة
 * @return int|null معرف الإجراء المنشأ أو null في حالة الفشل
 */
function create_leave_action(int $leaveId, int $userId, array $leaveData): ?int {
    try {
        // Generate action code
        $actionCode = generate_action_code_for_leave($leaveId);
        
        // Get user data
        $user = Database::fetchOne(
            "SELECT branch_id, full_name FROM users WHERE id = :id",
            ['id' => $userId]
        );
        
        // Build description
        $description = "طلب إجازة {$leaveData['leave_type']}\n";
        $description .= "من تاريخ: {$leaveData['start_date']}\n";
        $description .= "إلى تاريخ: {$leaveData['end_date']}\n";
        $description .= "عدد الأيام: {$leaveData['days_count']}\n";
        
        if (!empty($leaveData['reason'])) {
            $description .= "السبب: {$leaveData['reason']}\n";
        }
        
        // Get supervisor for approval
        $supervisorId = get_action_approver($userId, 'leave');
        
        // Create the action
        $actionId = Database::insert('actions', [
            'action_code' => $actionCode,
            'title' => "طلب إجازة - {$user['full_name']}",
            'description' => $description,
            'type' => 'request',
            'category' => 'leaves',
            'priority' => 'medium',
            'status' => 'pending',
            'requester_id' => $userId,
            'requester_branch_id' => $user['branch_id'],
            'current_approver_id' => $supervisorId,
            'approval_level' => 0,
            'max_approval_level' => 1,
            'related_entity_type' => 'leave',
            'related_entity_id' => $leaveId,
            'metadata' => json_encode([
                'leave_type' => $leaveData['leave_type'],
                'days_count' => $leaveData['days_count']
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create initial approval record
        if ($supervisorId) {
            Database::insert('action_approvals', [
                'action_id' => $actionId,
                'level' => 1,
                'approver_id' => $supervisorId,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Send notification to approver
            try {
                Database::insert('notifications', [
                    'user_id' => $supervisorId,
                    'type' => 'approval',
                    'title' => 'طلب موافقة على إجازة',
                    'message' => "{$user['full_name']} قدم طلب إجازة يحتاج موافقتك",
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
            }
        }
        
        // Add initial comment
        Database::insert('action_comments', [
            'action_id' => $actionId,
            'user_id' => $userId,
            'comment_type' => 'system',
            'content' => 'تم إنشاء طلب الإجازة',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        log_activity(
            'action_created',
            'actions',
            "إنشاء إجراء لطلب إجازة #{$leaveId}",
            $actionId
        );
        
        return $actionId;
        
    } catch (Exception $e) {
        error_log("Error creating leave action: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate action code for leave request
 * توليد كود الإجراء لطلب الإجازة
 * 
 * @param int $leaveId
 * @return string
 */
function generate_action_code_for_leave(int $leaveId): string {
    $year = date('Y');
    return sprintf('LEAVE-%s-%05d', $year, $leaveId);
}

/**
 * Get approver for a specific action type
 * الحصول على الموافق على الإجراء
 * 
 * @param int $userId
 * @param string $actionType
 * @return int|null
 */
function get_action_approver(int $userId, string $actionType = 'leave'): ?int {
    try {
        // Get user's branch and role
        $user = Database::fetchOne(
            "SELECT branch_id, role_level FROM users WHERE id = :id",
            ['id' => $userId]
        );
        
        if (!$user || !$user['branch_id']) {
            return null;
        }
        
        // For leave requests, find immediate supervisor or manager
        if ($actionType === 'leave') {
            // Find supervisor or manager in the same branch
            $approver = Database::fetchOne("
                SELECT id FROM users 
                WHERE branch_id = :branch_id 
                AND role_level >= :min_level
                AND role_level > :user_level
                AND is_active = 1
                ORDER BY role_level ASC
                LIMIT 1
            ", [
                'branch_id' => $user['branch_id'],
                'min_level' => ROLE_SUPERVISOR,
                'user_level' => $user['role_level']
            ]);
            
            return $approver ? (int) $approver['id'] : null;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting action approver: " . $e->getMessage());
        return null;
    }
}

/**
 * Update action status based on leave status
 * تحديث حالة الإجراء بناءً على حالة الإجازة
 * 
 * @param int $leaveId
 * @param string $newStatus
 * @param int $approverId
 * @param string $notes
 * @return bool
 */
function update_leave_action_status(int $leaveId, string $newStatus, int $approverId = null, string $notes = ''): bool {
    try {
        // Get leave's action_id
        $leave = Database::fetchOne(
            "SELECT action_id FROM leaves WHERE id = :id",
            ['id' => $leaveId]
        );
        
        if (!$leave || !$leave['action_id']) {
            return false;
        }
        
        $actionId = $leave['action_id'];
        
        // Map leave status to action status
        $statusMap = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled'
        ];
        
        $actionStatus = $statusMap[$newStatus] ?? 'pending';
        
        // Begin transaction
        Database::beginTransaction();
        
        try {
            // Update action status
            Database::update('actions', 
                ['status' => $actionStatus],
                'id = :id',
                ['id' => $actionId]
            );
            
            // Update approval record if approver is provided
            if ($approverId) {
                Database::update('action_approvals',
                    [
                        'status' => $newStatus === 'approved' ? 'approved' : 'rejected',
                        'notes' => $notes ?: null,
                        'decided_at' => date('Y-m-d H:i:s')
                    ],
                    'action_id = :action_id AND approver_id = :approver_id',
                    [
                        'action_id' => $actionId,
                        'approver_id' => $approverId
                    ]
                );
            }
            
            // Add comment
            Database::insert('action_comments', [
                'action_id' => $actionId,
                'user_id' => $approverId ?: current_user_id(),
                'comment_type' => 'status_change',
                'content' => $notes ?: "تغيير الحالة إلى: {$actionStatus}",
                'old_value' => 'pending',
                'new_value' => $actionStatus,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Database::commit();
            
            return true;
            
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error updating leave action status: " . $e->getMessage());
        return false;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * GENERAL ACTION HELPERS
 * دوال مساعدة عامة للإجراءات
 * ═══════════════════════════════════════════════════════════════════════════════
 */

/**
 * Check if user can approve action
 * التحقق من إمكانية الموافقة على الإجراء
 * 
 * @param int $actionId
 * @param int $userId
 * @return bool
 */
function can_approve_action(int $actionId, int $userId): bool {
    try {
        $action = Database::fetchOne(
            "SELECT current_approver_id, status FROM actions WHERE id = :id",
            ['id' => $actionId]
        );
        
        if (!$action) {
            return false;
        }
        
        // Action must be pending or waiting approval
        if (!in_array($action['status'], ['pending', 'waiting_approval'])) {
            return false;
        }
        
        // User must be the current approver
        if ($action['current_approver_id'] != $userId) {
            // Or admin
            if (current_role_level() < ROLE_ADMIN) {
                return false;
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get actions pending approval for user
 * الحصول على الإجراءات المعلقة للموافقة
 * 
 * @param int $userId
 * @param int $limit
 * @return array
 */
function get_pending_approvals(int $userId, int $limit = 10): array {
    try {
        return Database::fetchAll("
            SELECT 
                a.*,
                u.full_name as requester_name,
                u.emp_code as requester_code
            FROM actions a
            JOIN users u ON a.requester_id = u.id
            WHERE a.current_approver_id = :user_id
            AND a.status IN ('pending', 'waiting_approval')
            AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ", ['user_id' => $userId]);
        
    } catch (Exception $e) {
        error_log("Error getting pending approvals: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's submitted actions
 * الحصول على الإجراءات المقدمة من المستخدم
 * 
 * @param int $userId
 * @param string $status
 * @param int $limit
 * @return array
 */
function get_user_actions(int $userId, string $status = 'all', int $limit = 20): array {
    try {
        $where = "WHERE a.requester_id = :user_id AND a.deleted_at IS NULL";
        $params = ['user_id' => $userId];
        
        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected', 'completed', 'cancelled'])) {
            $where .= " AND a.status = :status";
            $params['status'] = $status;
        }
        
        return Database::fetchAll("
            SELECT a.*, COUNT(ac.id) as comments_count
            FROM actions a
            LEFT JOIN action_comments ac ON a.id = ac.action_id
            {$where}
            GROUP BY a.id
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ", $params);
        
    } catch (Exception $e) {
        error_log("Error getting user actions: " . $e->getMessage());
        return [];
    }
}
