<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Require login
require_login();

$user = current_user();
$bookingCode = trim($_GET['code'] ?? '');

if (empty($bookingCode)) {
    set_flash('danger', 'ไม่พบรหัสการจองคิวที่ระบุ');
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.id AS appointment_id, a.booking_code, a.appointment_status, a.notes, a.created_at AS booked_at,
               d.name AS department_name, d.location_label,
               s.service_date, s.start_time, s.end_time,
               q.queue_number, q.queue_status, q.called_at,
               p.id AS patient_id, p.patient_number, p.phone,
               u.id AS user_id, u.full_name, u.email
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        JOIN schedules s ON a.schedule_id = s.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN queues q ON a.id = q.appointment_id
        WHERE a.booking_code = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => $bookingCode]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        set_flash('danger', 'ไม่พบข้อมูลบัตรคิวตามรหัสนี้');
        header('Location: dashboard.php');
        exit;
    }

    // Role check: ordinary patients can only view their own tickets
    if (!has_role(['staff', 'admin']) && (int)$ticket['user_id'] !== (int)$user['id']) {
        set_flash('danger', 'คุณไม่มีสิทธิ์เข้าถึงบัตรคิวนี้');
        header('Location: dashboard.php');
        exit;
    }

} catch (PDOException $e) {
    error_log('Ticket fetch error: ' . $e->getMessage());
    set_flash('danger', 'เกิดข้อผิดพลาดในการโหลดข้อมูลบัตรคิว');
    header('Location: dashboard.php');
    exit;
}

$statusLabels = [
    'waiting' => ['label' => 'รอเรียกตรวจ (Waiting)', 'class' => 'ticket-status-waiting'],
    'called' => ['label' => 'กำลังเรียกคิว! (Called)', 'class' => 'ticket-status-called'],
    'serving' => ['label' => 'กำลังรับบริการตรวจ (Serving)', 'class' => 'ticket-status-serving'],
    'completed' => ['label' => 'ตรวจเสร็จสิ้นแล้ว (Completed)', 'class' => 'ticket-status-completed'],
    'skipped' => ['label' => 'ข้ามคิว (Skipped)', 'class' => 'ticket-status-waiting'],
    'cancelled' => ['label' => 'ยกเลิก (Cancelled)', 'class' => 'ticket-status-cancelled']
];

$curStatus = $ticket['queue_status'] ?? 'waiting';
$statusMeta = $statusLabels[$curStatus] ?? ['label' => 'รอคิว', 'class' => 'ticket-status-waiting'];

$pageTitle = 'บัตรคิวนัดหมาย ' . $ticket['queue_number'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | RMUTT Hospital Queue</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Libre+Barcode+39&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/auth.css">
  <link rel="stylesheet" href="assets/css/queue.css">
  <style>
    .barcode-display {
      font-family: 'Libre Barcode 39', monospace;
      font-size: 38px;
      letter-spacing: 4px;
      color: #0f172a;
      text-align: center;
      margin-top: 15px;
    }
  </style>
</head>
<body style="background: #f8fafc;">

  <!-- Header -->
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="index.php">
        <div class="brand-mark"><span>Q</span></div>
        <div class="brand-copy">
          <strong>RMUTT <span>HEALTH</span></strong>
          <small>SMART QUEUE SYSTEM</small>
        </div>
      </a>
      <nav class="main-nav" id="mainNav">
        <a href="index.php">หน้าแรก</a>
        <a href="dashboard.php">แดชบอร์ด</a>
        <a href="book.php">จองคิวใหม่</a>
        <a href="queue_display.php" target="_blank">จอแสดงผลคิว (TV) ↗</a>
      </nav>
      <div class="nav-actions">
        <a href="dashboard.php" class="btn btn-ghost btn-small">← กลับสู่แดชบอร์ด</a>
      </div>
    </div>
  </header>

  <main>
    <div class="ticket-wrapper">
      
      <?= render_flash() ?>

      <div class="ticket-card" id="printArea">
        
        <!-- Ticket Header -->
        <div class="ticket-header">
          <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
              <span class="ticket-badge-pill">RMUTT HEALTHCARE PASS</span>
              <h1 class="ticket-dept-title"><?= htmlspecialchars($ticket['department_name']) ?></h1>
              <span class="ticket-location">📍 <?= htmlspecialchars($ticket['location_label'] ?? 'จุดตรวจบริการผู้ป่วย') ?></span>
            </div>
            <div style="text-align:right;">
              <small style="color: #94a3b8; font-size: 11px;">รหัสการจอง (Booking Code)</small>
              <div style="font-weight:700; font-size:14px; letter-spacing:0.5px;"><?= htmlspecialchars($ticket['booking_code']) ?></div>
            </div>
          </div>
        </div>

        <!-- Ticket Number Area -->
        <div class="ticket-number-area">
          <div class="ticket-queue-label">หมายเลขคิวของคุณ</div>
          <div class="ticket-queue-num"><?= htmlspecialchars($ticket['queue_number'] ?? '—') ?></div>
          <div>
            <span class="ticket-status-pill <?= $statusMeta['class'] ?>">
              ● <?= htmlspecialchars($statusMeta['label']) ?>
            </span>
          </div>
        </div>

        <!-- Ticket Details -->
        <div class="ticket-details">
          <div class="ticket-grid">
            <div class="ticket-grid-item">
              <small>ชื่อ - นามสกุล ผู้รับบริการ</small>
              <strong><?= htmlspecialchars($ticket['full_name']) ?></strong>
            </div>
            <div class="ticket-grid-item">
              <small>หมายเลขประจำตัวผู้ป่วย (HN)</small>
              <strong style="color: #2563eb;"><?= htmlspecialchars($ticket['patient_number'] ?? '—') ?></strong>
            </div>
            <div class="ticket-grid-item">
              <small>วันที่นัดหมายตรวจ</small>
              <strong><?= date('d/m/Y', strtotime($ticket['service_date'])) ?></strong>
            </div>
            <div class="ticket-grid-item">
              <small>รอบเวลาที่นัดหมาย</small>
              <strong><?= substr($ticket['start_time'], 0, 5) ?> - <?= substr($ticket['end_time'], 0, 5) ?> น.</strong>
            </div>
            <?php if (!empty($ticket['notes'])): ?>
              <div class="ticket-grid-item" style="grid-column: 1 / -1;">
                <small>อาการเบื้องต้น / บันทึก</small>
                <span><?= htmlspecialchars($ticket['notes']) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #f1f5f9;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div style="font-size: 11px; color: #64748b; line-height: 1.6;">
                * กรุณาเดินทางมาถึงก่อนเวลานัดหมาย 15 นาที พร้อมนำบัตรประชาชนมาแสดงที่เคาน์เตอร์<br>
                * คุณสามารถเปิดหน้านี้บนมือถือหรือบันทึกภาพหน้าจอไว้เพื่อแสดงต่อเจ้าหน้าที่
              </div>
              <div style="text-align:center; padding-left: 15px;">
                <div style="width:55px; height:55px; background:#f1f5f9; border-radius:10px; display:grid; place-items:center; font-size:24px; color:#2563eb;">
                  📱
                </div>
                <small style="font-size:9px; color:#94a3b8;">บัตรคิวดิจิทัล</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="ticket-actions">
          <button type="button" class="btn btn-primary" onclick="window.print()" style="flex:1;">
            🖨️ พิมพ์บัตรคิว / บันทึก PDF
          </button>
          <a href="queue_display.php" target="_blank" class="btn btn-ghost" style="flex:1;">
            📺 ดูจอเรียกคิวสด (TV) ↗
          </a>
        </div>

      </div>

      <div style="text-align: center; margin-top: 24px;">
        <a href="dashboard.php" style="color: #64748b; font-size: 13px; text-decoration: underline;">
          ← กลับไปยังหน้าแดชบอร์ดหลัก
        </a>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-bottom">
      <span>© <?= date('Y') ?> RMUTT Hospital Queue — บัตรคิวดิจิทัล</span>
      <a href="index.php" style="color: inherit;">หน้าแรก</a>
    </div>
  </footer>

</body>
</html>
