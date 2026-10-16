<?php
/**
 * Live Queue API Endpoint
 * RMUTT Hospital Queue System
 * Returns JSON for Queue TV Display and Staff Console
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/config/database.php';

$deptId = (int)($_GET['department_id'] ?? 0);
$date = trim($_GET['date'] ?? date('Y-m-d'));

try {
    // 1. Fetch currently called or serving queues
    $stmtCalled = $pdo->prepare("
        SELECT q.id AS queue_id, q.queue_number, q.queue_status, q.called_at,
               d.id AS department_id, d.name AS department_name, d.location_label,
               p.patient_number, u.full_name
        FROM queues q
        JOIN appointments a ON q.appointment_id = a.id
        JOIN departments d ON a.department_id = d.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE q.queue_date = :qdate
          AND q.queue_status IN ('called', 'serving')
          AND (:dept_id = 0 OR a.department_id = :dept_id)
        ORDER BY q.called_at DESC, q.id DESC
        LIMIT 6
    ");
    $stmtCalled->execute([':qdate' => $date, ':dept_id' => $deptId]);
    $calledQueues = $stmtCalled->fetchAll();

    // Mask patient names slightly for privacy on TV screen (e.g. สมชาย ส. or สมชาย สุข...)
    foreach ($calledQueues as &$cq) {
        $nameParts = explode(' ', $cq['full_name']);
        if (count($nameParts) > 1) {
            $cq['display_name'] = $nameParts[0] . ' ' . mb_substr($nameParts[1], 0, 1, 'UTF-8') . '...';
        } else {
            $cq['display_name'] = $cq['full_name'];
        }
    }

    // 2. Fetch waiting queues
    $stmtWaiting = $pdo->prepare("
        SELECT q.id AS queue_id, q.queue_number, q.queue_status,
               d.id AS department_id, d.name AS department_name,
               u.full_name
        FROM queues q
        JOIN appointments a ON q.appointment_id = a.id
        JOIN departments d ON a.department_id = d.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE q.queue_date = :qdate
          AND q.queue_status = 'waiting'
          AND (:dept_id = 0 OR a.department_id = :dept_id)
        ORDER BY q.id ASC
        LIMIT 10
    ");
    $stmtWaiting->execute([':qdate' => $date, ':dept_id' => $deptId]);
    $waitingQueues = $stmtWaiting->fetchAll();

    // 3. Fetch completed queues
    $stmtCompleted = $pdo->prepare("
        SELECT q.id AS queue_id, q.queue_number, q.queue_status, q.completed_at,
               d.name AS department_name
        FROM queues q
        JOIN appointments a ON q.appointment_id = a.id
        JOIN departments d ON a.department_id = d.id
        WHERE q.queue_date = :qdate
          AND q.queue_status = 'completed'
          AND (:dept_id = 0 OR a.department_id = :dept_id)
        ORDER BY q.completed_at DESC, q.id DESC
        LIMIT 8
    ");
    $stmtCompleted->execute([':qdate' => $date, ':dept_id' => $deptId]);
    $completedQueues = $stmtCompleted->fetchAll();

    // 4. Summary counts
    $stmtCounts = $pdo->prepare("
        SELECT q.queue_status, COUNT(*) AS total
        FROM queues q
        JOIN appointments a ON q.appointment_id = a.id
        WHERE q.queue_date = :qdate
          AND (:dept_id = 0 OR a.department_id = :dept_id)
        GROUP BY q.queue_status
    ");
    $stmtCounts->execute([':qdate' => $date, ':dept_id' => $deptId]);
    $rawCounts = $stmtCounts->fetchAll();

    $stats = [
        'waiting' => 0,
        'called' => 0,
        'serving' => 0,
        'completed' => 0,
        'skipped' => 0,
        'total' => 0
    ];
    foreach ($rawCounts as $c) {
        $st = $c['queue_status'];
        if (isset($stats[$st])) {
            $stats[$st] = (int)$c['total'];
        }
        $stats['total'] += (int)$c['total'];
    }

    echo json_encode([
        'success' => true,
        'server_time' => date('H:i:s'),
        'server_date' => date('d/m/Y'),
        'department_id' => $deptId,
        'date' => $date,
        'called_queues' => $calledQueues,
        'waiting_queues' => $waitingQueues,
        'completed_queues' => $completedQueues,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
