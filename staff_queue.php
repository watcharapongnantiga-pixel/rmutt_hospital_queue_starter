<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Require login
require_login();

$user = current_user();

// Allow staff, admin, or demo access for testing
$isStaffOrAdmin = has_role(['staff', 'admin']);
$demoMode = isset($_GET['demo']) || isset($_SESSION['staff_demo_mode']);
if (isset($_GET['demo'])) {
    $_SESSION['staff_demo_mode'] = true;
    $demoMode = true;
}

if (!$isStaffOrAdmin && !$demoMode) {
    // Show friendly demo switch instead of hard 403
    $pageTitle = 'เจ้าหน้าที่ระบบคิว';
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8">
      <title>ระบบเรียกคิวสำหรับเจ้าหน้าที่ | RMUTT Hospital Queue</title>
      <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="assets/css/style.css">
      <link rel="stylesheet" href="assets/css/auth.css">
    </head>
    <body class="auth-page">
      <div class="auth-card-wrapper" style="max-width: 520px;">
        <div class="auth-card" style="text-align: center;">
          <div style="font-size: 48px; margin-bottom: 12px;">🩺</div>
          <h1>ระบบจัดการคิวห้องตรวจ</h1>
          <p>หน้านี้เป็นระบบสำหรับเจ้าหน้าที่โรงพยาบาลและแพทย์ในการเรียกคิวผู้ป่วย</p>
          <div class="alert alert-info" style="text-align: left; margin: 20px 0;">
            <strong>ℹ ข้อมูลสิทธิ์การใช้งาน:</strong><br>
            บัญชีปัจจุบันของคุณคือ <code><?= htmlspecialchars($user['email']) ?></code> (สิทธิ์: <?= htmlspecialchars(get_role_label($user['role'] ?? 'patient')) ?>)<br>
            คุณสามารถเปิด "โหมดทดสอบระบบ (Demo Mode)" เพื่อทดลองเรียกคิวได้ทันที
          </div>
          <div style="display: flex; gap: 10px;">
            <a href="staff_queue.php?demo=1" class="btn btn-primary" style="flex: 1;">
              เข้าใช้งานในโหมดทดสอบเจ้าหน้าที่ 🚀
            </a>
            <a href="dashboard.php" class="btn btn-ghost" style="flex: 1;">
              กลับหน้าแดชบอร์ด
            </a>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle Queue Action (Call, Serving, Complete, Skip, Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'เซสชันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
    } else {
        $action = trim($_POST['action']);
        $queueId = (int)($_POST['queue_id'] ?? 0);

        try {
            // Find queue
            $stmtQ = $pdo->prepare("
                SELECT q.id, q.queue_number, q.appointment_id, q.queue_status,
                       a.department_id, a.schedule_id, u.full_name
                FROM queues q
                JOIN appointments a ON q.appointment_id = a.id
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE q.id = :qid
                LIMIT 1
            ");
            $stmtQ->execute([':qid' => $queueId]);
            $targetQ = $stmtQ->fetch();

            if ($targetQ) {
                if ($action === 'call') {
                    $upd = $pdo->prepare("UPDATE queues SET queue_status = 'called', called_at = NOW() WHERE id = :id");
                    $upd->execute([':id' => $queueId]);

                    $updAppt = $pdo->prepare("UPDATE appointments SET appointment_status = 'waiting', updated_at = NOW() WHERE id = :id");
                    $updAppt->execute([':id' => $targetQ['appointment_id']]);

                    log_audit($pdo, (int)$user['id'], 'QUEUE_CALLED', 'queues', $queueId, ['queue_number' => $targetQ['queue_number']]);
                    set_flash('success', "เรียกคิว {$targetQ['queue_number']} ({$targetQ['full_name']}) เรียบร้อยแล้ว");

                } elseif ($action === 'serving') {
                    $upd = $pdo->prepare("UPDATE queues SET queue_status = 'serving' WHERE id = :id");
                    $upd->execute([':id' => $queueId]);

                    $updAppt = $pdo->prepare("UPDATE appointments SET appointment_status = 'in_service', updated_at = NOW() WHERE id = :id");
                    $updAppt->execute([':id' => $targetQ['appointment_id']]);

                    log_audit($pdo, (int)$user['id'], 'QUEUE_SERVING', 'queues', $queueId);
                    set_flash('info', "คิว {$targetQ['queue_number']} กำลังรับบริการตรวจ");

                } elseif ($action === 'complete') {
                    $upd = $pdo->prepare("UPDATE queues SET queue_status = 'completed', completed_at = NOW() WHERE id = :id");
                    $upd->execute([':id' => $queueId]);

                    $updAppt = $pdo->prepare("UPDATE appointments SET appointment_status = 'completed', updated_at = NOW() WHERE id = :id");
                    $updAppt->execute([':id' => $targetQ['appointment_id']]);

                    log_audit($pdo, (int)$user['id'], 'QUEUE_COMPLETED', 'queues', $queueId);
                    set_flash('success', "บันทึกคิว {$targetQ['queue_number']} เสร็จสิ้นการตรวจเรียบร้อย");

                } elseif ($action === 'skip') {
                    $upd = $pdo->prepare("UPDATE queues SET queue_status = 'skipped' WHERE id = :id");
                    $upd->execute([':id' => $queueId]);

                    log_audit($pdo, (int)$user['id'], 'QUEUE_SKIPPED', 'queues', $queueId);
                    set_flash('warning', "ข้ามคิว {$targetQ['queue_number']}");

                } elseif ($action === 'cancel') {
                    $upd = $pdo->prepare("UPDATE queues SET queue_status = 'cancelled' WHERE id = :id");
                    $upd->execute([':id' => $queueId]);

                    $updAppt = $pdo->prepare("UPDATE appointments SET appointment_status = 'cancelled', updated_at = NOW() WHERE id = :id");
                    $updAppt->execute([':id' => $targetQ['appointment_id']]);

                    // Decrement booked count in schedule
                    $updSched = $pdo->prepare("UPDATE schedules SET booked_count = GREATEST(0, booked_count - 1) WHERE id = :id");
                    $updSched->execute([':id' => $targetQ['schedule_id']]);

                    log_audit($pdo, (int)$user['id'], 'QUEUE_CANCELLED', 'queues', $queueId);
                    set_flash('danger', "ยกเลิกคิว {$targetQ['queue_number']} เรียบร้อย");
                }
            }
        } catch (PDOException $e) {
            error_log('Queue action error: ' . $e->getMessage());
            set_flash('danger', 'เกิดข้อผิดพลาดในการดำเนินการ');
        }

        // Redirect back preserving filters
        $returnUrl = 'staff_queue.php?' . http_build_query([
            'dept_id' => $_POST['filter_dept_id'] ?? '',
            'date' => $_POST['filter_date'] ?? ''
        ]);
        header('Location: ' . $returnUrl);
        exit;
    }
}

// Filters
$filterDeptId = (int)($_GET['dept_id'] ?? 0);
$filterDate = trim($_GET['date'] ?? date('Y-m-d'));

// Departments list
try {
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Fetch queues for the day & department
try {
    $stmtQList = $pdo->prepare("
        SELECT q.id AS queue_id, q.queue_number, q.queue_status, q.called_at, q.completed_at,
               a.id AS appointment_id, a.booking_code, a.notes,
               d.id AS department_id, d.name AS department_name, d.location_label,
               s.start_time, s.end_time,
               p.patient_number, p.phone,
               u.full_name
        FROM queues q
        JOIN appointments a ON q.appointment_id = a.id
        JOIN departments d ON a.department_id = d.id
        JOIN schedules s ON a.schedule_id = s.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE q.queue_date = :qdate
          AND (:dept_id = 0 OR a.department_id = :dept_id)
        ORDER BY 
          CASE q.queue_status 
            WHEN 'called' THEN 1 
            WHEN 'serving' THEN 2 
            WHEN 'waiting' THEN 3 
            WHEN 'skipped' THEN 4 
            WHEN 'completed' THEN 5 
            ELSE 6 
          END,
          q.id ASC
    ");
    $stmtQList->execute([':qdate' => $filterDate, ':dept_id' => $filterDeptId]);
    $queues = $stmtQList->fetchAll();

    // Calculate stats
    $countWaiting = 0;
    $countCalled = 0;
    $countServing = 0;
    $countCompleted = 0;
    $firstWaitingId = null;

    foreach ($queues as $q) {
        if ($q['queue_status'] === 'waiting') {
            $countWaiting++;
            if ($firstWaitingId === null) {
                $firstWaitingId = $q['queue_id'];
            }
        } elseif ($q['queue_status'] === 'called') {
            $countCalled++;
        } elseif ($q['queue_status'] === 'serving') {
            $countServing++;
        } elseif ($q['queue_status'] === 'completed') {
            $countCompleted++;
        }
    }

} catch (PDOException $e) {
    error_log('Error loading queues: ' . $e->getMessage());
    $queues = [];
    $countWaiting = 0;
    $countCalled = 0;
    $countServing = 0;
    $countCompleted = 0;
    $firstWaitingId = null;
}

$pageTitle = 'คอนโซลเรียกคิวเจ้าหน้าที่';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | RMUTT Hospital Queue</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/auth.css">
  <link rel="stylesheet" href="assets/css/queue.css">
</head>
<body style="background: #f4f6fa;">

  <!-- Header -->
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="index.php">
        <div class="brand-mark"><span>Q</span></div>
        <div class="brand-copy">
          <strong>RMUTT <span>HEALTH</span></strong>
          <small>STAFF CALLING CONSOLE</small>
        </div>
      </a>
      <nav class="main-nav" id="mainNav">
        <a href="index.php">หน้าแรก</a>
        <a href="dashboard.php">แดชบอร์ด</a>
        <a class="active" href="staff_queue.php">ระบบเจ้าหน้าที่</a>
        <a href="queue_display.php" target="_blank">เปิดจอคิว TV ↗</a>
      </nav>
      <div class="nav-actions">
        <span class="user-menu-pill">
          <span class="user-menu-avatar"><?= htmlspecialchars(mb_substr($user['full_name'], 0, 1, 'UTF-8')) ?></span>
          <span><?= htmlspecialchars($user['full_name']) ?> (เจ้าหน้าที่)</span>
        </span>
        <a href="logout.php" class="btn btn-ghost btn-small">ออกจากระบบ</a>
      </div>
    </div>
  </header>

  <main class="staff-console">
    <div class="container">
      
      <!-- Top Filters -->
      <div class="staff-topbar">
        <div>
          <span class="auth-card-badge">✦ ระบบห้องตรวจและเรียกคิว</span>
          <h1 style="font-size: 24px; color: #0d213a; margin: 6px 0 0;">คอนโซลบริหารจัดการคิวตรวจ</h1>
        </div>

        <form method="GET" action="staff_queue.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <select name="dept_id" class="form-control" style="width: auto; padding: 10px 14px;" onchange="this.form.submit()">
            <option value="0">-- ทุกแผนกบริการ --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $filterDeptId === (int)$d['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <input type="date" name="date" class="form-control" style="width: auto; padding: 9px 12px;" 
                 value="<?= htmlspecialchars($filterDate) ?>" onchange="this.form.submit()">

          <button type="submit" class="btn btn-primary btn-small">ค้นหา</button>
          <a href="queue_display.php" target="_blank" class="btn btn-ghost btn-small" title="เปิดหน้าจอสำหรับผู้รับบริการ">
            📺 จอแสดงผล TV
          </a>
        </form>
      </div>

      <?= render_flash() ?>

      <!-- Stat Cards -->
      <div class="staff-stats-row">
        <div class="stat-box waiting">
          <div class="stat-icon">◷</div>
          <div>
            <div class="stat-number"><?= $countWaiting ?></div>
            <div class="stat-label">คิวที่รอรับบริการ</div>
          </div>
        </div>
        <div class="stat-box called">
          <div class="stat-icon">🔔</div>
          <div>
            <div class="stat-number"><?= $countCalled + $countServing ?></div>
            <div class="stat-label">กำลังเรียก / รับบริการ</div>
          </div>
        </div>
        <div class="stat-box completed">
          <div class="stat-icon">✓</div>
          <div>
            <div class="stat-number"><?= $countCompleted ?></div>
            <div class="stat-label">ตรวจเสร็จสิ้นแล้ว</div>
          </div>
        </div>

        <!-- Quick Call Next Button -->
        <div style="display: flex; align-items: center;">
          <?php if ($firstWaitingId): ?>
            <form method="POST" action="staff_queue.php" style="width: 100%;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="call">
              <input type="hidden" name="queue_id" value="<?= $firstWaitingId ?>">
              <input type="hidden" name="filter_dept_id" value="<?= $filterDeptId ?>">
              <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
              <button type="submit" class="btn btn-primary" style="width: 100%; padding: 18px; font-size: 15px; box-shadow: 0 8px 25px rgba(37, 99, 235, 0.35);">
                🔔 เรียกคิวถัดไปอัตโนมัติ
              </button>
            </form>
          <?php else: ?>
            <button class="btn btn-ghost" style="width: 100%; padding: 18px; opacity: 0.6;" disabled>
              ✓ ไม่มีคิวที่รอรับบริการ
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Queue List Table -->
      <div class="queue-table-card">
        <div style="padding: 18px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
          <h2 style="font-size: 16px; margin: 0; color: #0f172a;">
            รายการคิวตรวจประจำวัน (<?= date('d/m/Y', strtotime($filterDate)) ?>)
          </h2>
          <span style="font-size: 12px; color: #64748b;">ทั้งหมด <?= count($queues) ?> คิว</span>
        </div>

        <?php if (empty($queues)): ?>
          <div style="text-align: center; padding: 50px 20px; color: #64748b;">
            <div style="font-size: 40px; margin-bottom: 10px;">📋</div>
            <h3 style="font-size: 16px; color: #1e293b; margin: 0 0 4px;">ยังไม่มีรายการคิวในวันนี้</h3>
            <p style="font-size: 13px; margin: 0;">คุณสามารถไปที่หน้า <a href="book.php" style="color:#2563eb; text-decoration:underline;">จองคิวออนไลน์</a> เพื่อทดลองสร้างคิวนัดหมายใหม่ได้</p>
          </div>
        <?php else: ?>
          <div style="overflow-x: auto;">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>เลขคิว</th>
                  <th>ผู้รับบริการ (HN)</th>
                  <th>แผนก / ห้องตรวจ</th>
                  <th>รอบเวลา</th>
                  <th>สถานะคิว</th>
                  <th>หมายเหตุ/อาการ</th>
                  <th style="text-align: right;">การจัดการคิว</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($queues as $q): ?>
                  <tr>
                    <td>
                      <strong style="font-size: 18px; color: #2563eb; letter-spacing: 0.5px;">
                        <?= htmlspecialchars($q['queue_number']) ?>
                      </strong>
                      <div style="font-size: 11px; color: #94a3b8;"><?= htmlspecialchars($q['booking_code']) ?></div>
                    </td>
                    <td>
                      <strong><?= htmlspecialchars($q['full_name']) ?></strong>
                      <div style="font-size: 11px; color: #64748b;">HN: <?= htmlspecialchars($q['patient_number'] ?? '—') ?></div>
                      <?php if (!empty($q['phone'])): ?>
                        <div style="font-size: 11px; color: #8292a8;">📞 <?= htmlspecialchars($q['phone']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?= htmlspecialchars($q['department_name']) ?></div>
                      <small style="color: #64748b;"><?= htmlspecialchars($q['location_label'] ?? '') ?></small>
                    </td>
                    <td>
                      <?= substr($q['start_time'], 0, 5) ?> - <?= substr($q['end_time'], 0, 5) ?> น.
                    </td>
                    <td>
                      <?php
                        $badges = [
                          'waiting' => ['bg' => '#fef3c7', 'col' => '#92400e', 'text' => 'รอตรวจ'],
                          'called' => ['bg' => '#dbeafe', 'col' => '#1d4ed8', 'text' => '🔔 กำลังเรียก'],
                          'serving' => ['bg' => '#f3e8ff', 'col' => '#7e22ce', 'text' => '🩺 เข้าตรวจ'],
                          'completed' => ['bg' => '#dcfce7', 'col' => '#15803d', 'text' => '✓ เสร็จสิ้น'],
                          'skipped' => ['bg' => '#f1f5f9', 'col' => '#64748b', 'text' => 'ข้ามคิว'],
                          'cancelled' => ['bg' => '#fee2e2', 'col' => '#b91c1c', 'text' => '✕ ยกเลิก']
                        ];
                        $badge = $badges[$q['queue_status']] ?? ['bg' => '#f1f5f9', 'col' => '#475569', 'text' => $q['queue_status']];
                      ?>
                      <span style="display:inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?= $badge['bg'] ?>; color: <?= $badge['col'] ?>;">
                        <?= $badge['text'] ?>
                      </span>
                    </td>
                    <td style="max-width: 180px; font-size: 12px; color: #64748b;">
                      <?= !empty($q['notes']) ? htmlspecialchars($q['notes']) : '—' ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <form method="POST" action="staff_queue.php" style="display: inline-flex; gap: 6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="queue_id" value="<?= $q['queue_id'] ?>">
                        <input type="hidden" name="filter_dept_id" value="<?= $filterDeptId ?>">
                        <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>">

                        <?php if ($q['queue_status'] === 'waiting' || $q['queue_status'] === 'skipped'): ?>
                          <button type="submit" name="action" value="call" class="queue-pill-btn btn-call" 
                                  onclick="announceVoice('<?= $q['queue_number'] ?>', '<?= addslashes($q['department_name']) ?>')">
                            🔔 เรียกคิว
                          </button>
                        <?php endif; ?>

                        <?php if ($q['queue_status'] === 'called'): ?>
                          <button type="submit" name="action" value="serving" class="queue-pill-btn btn-serving">
                            🩺 เริ่มตรวจ
                          </button>
                          <button type="submit" name="action" value="call" class="queue-pill-btn btn-call" 
                                  title="เรียกซ้ำ" onclick="announceVoice('<?= $q['queue_number'] ?>', '<?= addslashes($q['department_name']) ?>')">
                            🔔 เรียกซ้ำ
                          </button>
                        <?php endif; ?>

                        <?php if ($q['queue_status'] === 'serving'): ?>
                          <button type="submit" name="action" value="complete" class="queue-pill-btn btn-complete">
                            ✓ ตรวจเสร็จ
                          </button>
                        <?php endif; ?>

                        <?php if ($q['queue_status'] === 'waiting' || $q['queue_status'] === 'called'): ?>
                          <button type="submit" name="action" value="skip" class="queue-pill-btn btn-skip" title="ข้ามคิวนี้ชั่วคราว">
                            ข้าม
                          </button>
                        <?php endif; ?>

                        <?php if ($q['queue_status'] !== 'completed' && $q['queue_status'] !== 'cancelled'): ?>
                          <button type="submit" name="action" value="cancel" class="queue-pill-btn" style="background:none; color:#ef4444;" 
                                  title="ยกเลิกคิว" onclick="return confirm('ยืนยันยกเลิกคิว <?= $q['queue_number'] ?> หรือไม่?')">
                            ✕
                          </button>
                        <?php endif; ?>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>

    </div>
  </main>

  <script>
    function announceVoice(queueNum, dept) {
      if ('speechSynthesis' in window) {
        // Pronounce letters with space e.g. "A 0 0 1"
        const cleanNum = queueNum.split('').join(' ');
        const text = `ขอเชิญหมายเลข ${cleanNum} ที่ห้องตรวจ ${dept} ค่ะ`;
        const ut = new SpeechSynthesisUtterance(text);
        ut.lang = 'th-TH';
        ut.rate = 0.95;
        window.speechSynthesis.speak(ut);
      }
    }
  </script>

</body>
</html>
