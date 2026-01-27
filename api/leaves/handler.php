<?php
/**
 * SARH SYSTEM - LEAVES API HANDLER
 * معالج API للإجازات (متكامل مع الإجراءات)
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/action_integrations.php';

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($csrfToken)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'رمز أمان غير صالح']));
    }
}

$userId = current_user_id();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $leaveTypeId = (int)($input['leave_type_id'] ?? 0);
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            $reason = trim($input['reason'] ?? '');
            
            if (!$leaveTypeId || !$startDate || !$endDate) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            $daysCount = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $user = Database::fetchOne("SELECT full_name, branch_id FROM users WHERE id = ?", [$userId]);
            $leaveType = Database::fetchOne("SELECT name FROM leave_types WHERE id = ?", [$leaveTypeId]);
            
            $leaveId = Database::insert('leaves', [
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_count' => $daysCount,
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $actionId = createActionFromLeaveRequest($leaveId, [
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'leave_type_name' => $leaveType['name'] ?? 'إجازة',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_count' => $daysCount,
                'reason' => $reason,
                'full_name' => $user['full_name'],
                'branch_id' => $user['branch_id']
            ]);
            
            if ($actionId) {
                Database::update('leaves', ['action_id' => $actionId], 'id = :id', ['id' => $leaveId]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إرسال طلب الإجازة بنجاح',
                'leave_id' => $leaveId,
                'action_id' => $actionId
            ]);
            break;
            
        case 'list':
            $leaves = Database::fetchAll("
                SELECT l.*, lt.name as leave_type_name, lt.color
                FROM leaves l
                LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
                WHERE l.user_id = ?
                ORDER BY l.created_at DESC
            ", [$userId]);
            
            echo json_encode(['success' => true, 'data' => $leaves]);
            break;
            
        default:
            throw new Exception('إجراء غير معروف');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
