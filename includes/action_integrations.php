<?php
/**
 * SARH SYSTEM - ACTION INTEGRATIONS
 * دوال ربط نظام الإجراءات مع الأنظمة الأخرى
 */

defined('SARH_SYSTEM') || define('SARH_SYSTEM', true);

/**
 * توليد كود فريد للإجراء
 */
function generateActionCode(): string {
    $year = date('Y');
    $lastCode = Database::fetchValue(
        "SELECT action_code FROM actions WHERE action_code LIKE ? ORDER BY id DESC LIMIT 1",
        ["ACT-{$year}-%"]
    );
    
    if ($lastCode) {
        $lastNumber = (int) substr($lastCode, -5);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return sprintf("ACT-%s-%05d", $year, $newNumber);
}

/**
 * إنشاء إجراء من طلب إجازة
 */
function createActionFromLeaveRequest(int $leaveId, array $leaveData = []): ?int {
    try {
        if (empty($leaveData)) {
            $leaveData = Database::fetchOne("
                SELECT l.*, lt.name as leave_type_name, u.full_name, u.branch_id
                FROM leaves l
                LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = ?
            ", [$leaveId]);
        }
        
        if (!$leaveData) return null;
        
        $actionCode = generateActionCode();
        $approverId = getLeaveApprover($leaveData['user_id'], $leaveData['branch_id'] ?? null);
        
        $actionId = Database::insert('actions', [
            'action_code' => $actionCode,
            'title' => "طلب " . ($leaveData['leave_type_name'] ?? 'إجازة'),
            'description' => "طلب إجازة من {$leaveData['start_date']} إلى {$leaveData['end_date']}",
            'type' => 'request',
            'category' => 'leaves',
            'priority' => 'medium',
            'status' => 'pending',
            'requester_id' => $leaveData['user_id'],
            'requester_branch_id' => $leaveData['branch_id'] ?? null,
            'current_approver_id' => $approverId,
            'leave_request_id' => $leaveId,
            'related_entity_type' => 'leave',
            'related_entity_id' => $leaveId,
            'metadata' => json_encode($leaveData, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($approverId) {
            try {
                Database::insert('action_approvals', [
                    'action_id' => $actionId,
                    'level' => 1,
                    'approver_id' => $approverId,
                    'status' => 'pending'
                ]);
            } catch (Exception $e) {
                error_log("Failed to create action approval: " . $e->getMessage());
                // Continue anyway - the action was created successfully
            }
            
            sendActionNotification($approverId, 'طلب إجازة جديد', "طلب من {$leaveData['full_name']}", $actionId);
        }
        
        return $actionId;
    } catch (Exception $e) {
        error_log("createActionFromLeaveRequest Error: " . $e->getMessage());
        return null;
    }
}

/**
 * الحصول على المعتمد لطلب الإجازة
 */
function getLeaveApprover(int $userId, ?int $branchId): ?int {
    try {
        $userRole = Database::fetchValue(
            "SELECT r.role_level FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?",
            [$userId]
        );
        
        $approver = Database::fetchOne("
            SELECT u.id FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.branch_id = ? AND r.role_level > ? AND u.is_active = 1 AND u.id != ?
            ORDER BY r.role_level ASC LIMIT 1
        ", [$branchId, $userRole ?? 1, $userId]);
        
        return $approver ? (int)$approver['id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * مزامنة حالة الإجراء مع الإجازة
 */
function syncLeaveWithAction(int $actionId, string $newStatus, ?string $notes = null): bool {
    try {
        $action = Database::fetchOne("SELECT * FROM actions WHERE id = ?", [$actionId]);
        if (!$action || !$action['leave_request_id']) return false;
        
        $leaveStatus = match($newStatus) {
            'approved', 'completed' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            default => 'pending'
        };
        
        Database::update('leaves', 
            ['status' => $leaveStatus, 'updated_at' => date('Y-m-d H:i:s')],
            'id = :id', ['id' => $action['leave_request_id']]
        );
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * إرسال إشعار
 */
function sendActionNotification(int $userId, string $title, string $message, int $actionId): bool {
    try {
        // Check if notifications table exists
        $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notifications'");
        if (!$tableExists) {
            error_log("Notifications table does not exist, skipping notification");
            return false;
        }
        
        Database::insert('notifications', [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'action',
            'url' => "/actions.php?id={$actionId}",
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    } catch (Exception $e) {
        error_log("sendActionNotification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * إحصائيات الإجراءات للمستخدم
 */
function getUserActionStats(int $userId): array {
    $stats = Database::fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM actions WHERE requester_id = ? AND deleted_at IS NULL
    ", [$userId]);
    
    return $stats ?: ['total' => 0, 'pending' => 0, 'completed' => 0];
}
