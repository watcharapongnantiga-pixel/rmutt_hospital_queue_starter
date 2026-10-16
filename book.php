<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Require user to be logged in
require_login();

$user = current_user();
$userId = (int)$user['id'];

// AJAX: Get available slots for a department and date
if (isset($_GET['action']) && $_GET['action'] === 'get_slots') {
    header('Content-Type: application/json; charset=utf-8');
    $deptId = (int)($_GET['department_id'] ?? 0);
    $date = trim($_GET['date'] ?? '');

    if (!$deptId || empty($date)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, department_id, service_date, start_time, end_time, capacity, booked_count, is_open
            FROM schedules
            WHERE department_id = :dept_id AND service_date = :s_date AND is_open = 1
            ORDER BY start_time ASC
        ");
        $stmt->execute([':dept_id' => $deptId, ':s_date' => $date]);
        $slots = $stmt->fetchAll();

        // Calculate available seats
        foreach ($slots as &$slot) {
            $slot['available'] = max(0, (int)$slot['capacity'] - (int)$slot['booked_count']);
            $slot['start_time_fmt'] = substr($slot['start_time'], 0, 5);
            $slot['end_time_fmt'] = substr($slot['end_time'], 0, 5);
        }

        echo json_encode(['success' => true, 'slots' => $slots]);
    } catch (PDOException $e) {
        error_log('Error fetching slots: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงรอบเวลา']);
    }
    exit;
}

$error = null;
$selectedDeptId = (int)($_GET['department_id'] ?? ($_POST['department_id'] ?? 0));
$selectedDate = trim($_GET['date'] ?? ($_POST['date'] ?? date('Y-m-d')));
$notes = trim($_POST['notes'] ?? '');

// Handle POST Booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $selectedDeptId = (int)($_POST['department_id'] ?? 0);

        if (!$selectedDeptId) {
            $error = 'กรุณาเลือกแผนกที่ต้องการเข้ารับบริการ';
        } elseif (!$scheduleId) {
            $error = 'กรุณาเลือกรอบเวลาที่ต้องการนัดหมาย';
        } else {
            try {
                $patientId = get_or_create_patient_id($pdo, $userId);

                // Transaction with row locking
                $pdo->beginTransaction();

                // Lock the schedule row to prevent overbooking
                $stmtSched = $pdo->prepare("
                    SELECT s.id, s.department_id, s.service_date, s.start_time, s.end_time,
                           s.capacity, s.booked_count, s.is_open, d.name AS department_name
                    FROM schedules s
                    JOIN departments d ON s.department_id = d.id
                    WHERE s.id = :id
                    FOR UPDATE
                ");
                $stmtSched->execute([':id' => $scheduleId]);
                $schedule = $stmtSched->fetch();

                if (!$schedule) {
                    $pdo->rollBack();
                    $error = 'ไม่พบรอบเวลาที่ระบุ กรุณาเลือกรอบเวลาใหม่';
                } elseif ((int)$schedule['is_open'] !== 1) {
                    $pdo->rollBack();
                    $error = 'รอบเวลานี้ปิดรับการนัดหมายแล้ว';
                } elseif ((int)$schedule['booked_count'] >= (int)$schedule['capacity']) {
                    $pdo->rollBack();
                    $error = 'ขออภัย รอบเวลานี้มีผู้จองเต็มจำนวนแล้ว กรุณาเลือกรอบเวลาอื่น';
                } else {
                    // 1. Increment booked count
                    $stmtUpdSched = $pdo->prepare("
                        UPDATE schedules SET booked_count = booked_count + 1 WHERE id = :id
                    ");
                    $stmtUpdSched->execute([':id' => $scheduleId]);

                    // 2. Generate unique booking code: BK26-XXXXX
                    $bookingCode = 'BK' . date('y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

                    // 3. Insert appointment
                    $stmtAppt = $pdo->prepare("
                        INSERT INTO appointments (booking_code, patient_id, department_id, schedule_id, appointment_status, notes, created_at, updated_at)
                        VALUES (:code, :patient_id, :dept_id, :schedule_id, 'booked', :notes, NOW(), NOW())
                    ");
                    $stmtAppt->execute([
                        ':code' => $bookingCode,
                        ':patient_id' => $patientId,
                        ':dept_id' => $schedule['department_id'],
                        ':schedule_id' => $scheduleId,
                        ':notes' => !empty($notes) ? $notes : null
                    ]);
                    $appointmentId = (int)$pdo->lastInsertId();

                    // 4. Generate queue number for that department & service_date
                    // Prefix mapping: Dept 1 -> 'A', Dept 2 -> 'B', Dept 3 -> 'C', etc.
                    $deptLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
                    $prefixIndex = ((int)$schedule['department_id'] - 1) % count($deptLetters);
                    $prefix = $deptLetters[$prefixIndex];

                    $stmtLastQ = $pdo->prepare("
                        SELECT queue_number
                        FROM queues
                        WHERE queue_date = :qdate AND queue_number LIKE :prefix
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmtLastQ->execute([
                        ':qdate' => $schedule['service_date'],
                        ':prefix' => $prefix . '%'
                    ]);
                    $lastQ = $stmtLastQ->fetch();

                    $nextNum = 1;
                    if ($lastQ && preg_match('/^' . $prefix . '(\d+)$/', $lastQ['queue_number'], $matches)) {
                        $nextNum = (int)$matches[1] + 1;
                    }
                    $queueNumber = $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

                    // 5. Insert Queue
                    $stmtQ = $pdo->prepare("
                        INSERT INTO queues (appointment_id, queue_number, queue_date, queue_status, created_at)
                        VALUES (:appt_id, :queue_num, :qdate, 'waiting', NOW())
                    ");
                    $stmtQ->execute([
                        ':appt_id' => $appointmentId,
                        ':queue_num' => $queueNumber,
                        ':qdate' => $schedule['service_date']
                    ]);

                    // 6. Insert in-app notification
                    $notifyMsg = "จองคิวสำเร็จ! แผนก {$schedule['department_name']} คิวหมายเลข {$queueNumber} รหัสจอง {$bookingCode}";
                    $stmtNotif = $pdo->prepare("
                        INSERT INTO notifications (user_id, appointment_id, channel, message, send_status, created_at)
                        VALUES (:uid, :aid, 'in_app', :msg, 'sent', NOW())
                    ");
                    $stmtNotif->execute([
                        ':uid' => $userId,
                        ':aid' => $appointmentId,
                        ':msg' => $notifyMsg
                    ]);

                    // 7. Audit log
                    log_audit($pdo, $userId, 'BOOK_APPOINTMENT', 'appointments', $appointmentId, [
                        'booking_code' => $bookingCode,
                        'queue_number' => $queueNumber,
                        'department' => $schedule['department_name'],
                        'date' => $schedule['service_date']
                    ]);

                    $pdo->commit();

                    set_flash('success', "จองคิวสำเร็จ! หมายเลขคิวของคุณคือ {$queueNumber}");
                    header("Location: ticket.php?code={$bookingCode}");
                    exit;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Booking exception: ' . $e->getMessage());
                $error = 'เกิดข้อผิดพลาดในการบันทึกการจอง กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}

// Fetch available departments
try {
    $departments = $pdo->query("
        SELECT id, name, description, location_label
        FROM departments
        WHERE is_open_for_booking = 1
        ORDER BY id ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Default select first department if none specified
if (!$selectedDeptId && !empty($departments)) {
    $selectedDeptId = (int)$departments[0]['id'];
}

$pageTitle = 'จองคิวออนไลน์';
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
<body>

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
        <a class="active" href="book.php">จองคิวออนไลน์</a>
        <a href="queue_display.php" target="_blank">จอแสดงผลคิว (TV) ↗</a>
      </nav>
      <div class="nav-actions">
        <a href="dashboard.php" class="user-menu-pill">
          <span class="user-menu-avatar"><?= htmlspecialchars(mb_substr($user['full_name'], 0, 1, 'UTF-8')) ?></span>
          <span><?= htmlspecialchars($user['full_name']) ?></span>
        </a>
        <a href="logout.php" class="btn btn-ghost btn-small">ออกจากระบบ</a>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <main class="booking-container">
    <div class="container">
      
      <div class="booking-header">
        <span class="auth-card-badge">✦ ระบบนัดหมายออนไลน์</span>
        <h1>จองคิวเข้ารับบริการทางการแพทย์</h1>
        <p>เลือกแผนก วัน และช่วงเวลาที่ต้องการเข้ารับบริการ พร้อมรับหมายเลขคิวทันที</p>
      </div>

      <?= render_flash() ?>

      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
          <span class="alert-icon">✕</span>
          <div class="alert-content"><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <form action="book.php" method="POST" id="bookingForm">
        <?= csrf_field() ?>

        <div class="booking-grid">
          
          <!-- Left Column: Steps -->
          <div class="booking-steps-col">
            
            <!-- Step 1: Select Department -->
            <div class="booking-card">
              <h2 class="booking-card-title">
                <span class="step-badge">1</span>
                <span>เลือกแผนกที่ต้องการรับบริการ</span>
              </h2>

              <div class="dept-grid">
                <?php foreach ($departments as $dept): ?>
                  <?php $isCurrent = ($selectedDeptId === (int)$dept['id']); ?>
                  <label class="dept-card-label">
                    <input type="radio" name="department_id" value="<?= $dept['id'] ?>" 
                           <?= $isCurrent ? 'checked' : '' ?>
                           data-name="<?= htmlspecialchars($dept['name']) ?>"
                           data-location="<?= htmlspecialchars($dept['location_label'] ?? '') ?>"
                           onchange="handleDeptChange(this)">
                    <div class="dept-card-inner">
                      <div class="dept-icon">
                        <?php 
                          if (str_contains($dept['name'], 'ทันต')) echo '🦷';
                          elseif (str_contains($dept['name'], 'กายภาพ')) echo '🤸';
                          elseif (str_contains($dept['name'], 'ตา') || str_contains($dept['name'], 'จักษุ')) echo '👁️';
                          else echo '🩺';
                        ?>
                      </div>
                      <strong><?= htmlspecialchars($dept['name']) ?></strong>
                      <p><?= htmlspecialchars($dept['description'] ?? 'บริการตรวจและรักษา') ?></p>
                      <span class="dept-location">📍 <?= htmlspecialchars($dept['location_label'] ?? 'จุดตรวจบริการ') ?></span>
                    </div>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Step 2: Select Date -->
            <div class="booking-card">
              <h2 class="booking-card-title">
                <span class="step-badge">2</span>
                <span>เลือกวันที่เข้ารับบริการ</span>
              </h2>

              <div class="form-group" style="max-width: 320px;">
                <label class="form-label" for="bookingDate">วันที่นัดหมาย <span class="required">*</span></label>
                <div class="input-wrapper">
                  <span class="input-icon">📅</span>
                  <input type="date" id="bookingDate" name="date" class="form-control"
                         value="<?= htmlspecialchars($selectedDate) ?>"
                         min="<?= date('Y-m-d') ?>"
                         max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                         onchange="handleDateChange(this.value)" required>
                </div>
                <small style="color: #64748b; font-size: 11px; margin-top: 4px; display: block;">
                  เปิดจองล่วงหน้าสูงสุด 30 วัน (เปิดบริการวันจันทร์ - วันเสาร์)
                </small>
              </div>
            </div>

            <!-- Step 3: Select Time Slot -->
            <div class="booking-card">
              <h2 class="booking-card-title">
                <span class="step-badge">3</span>
                <span>เลือกรอบเวลาที่สะดวก</span>
              </h2>

              <div id="slotsLoading" style="display:none; color: #2563eb; font-size: 13px; padding: 12px 0;">
                ⏳ กำลังโหลดรอบเวลาที่เปิดให้บริการ...
              </div>

              <div id="slotsContainer" class="slots-container">
                <!-- Dynamically loaded via JavaScript -->
              </div>

              <div id="noSlotsMessage" style="display:none; padding: 25px; text-align: center; color: #64748b; background: #f8fafc; border-radius: 12px;">
                <div style="font-size: 28px; margin-bottom: 8px;">🗓️</div>
                <strong style="color: #1e293b; font-size: 14px;">ไม่พบรอบเวลาให้บริการในวันที่เลือก</strong>
                <p style="font-size: 12px; margin: 4px 0 0;">กรุณาลองเปลี่ยนวันที่ หรือเลือกแผนกอื่น</p>
              </div>
            </div>

            <!-- Step 4: Symptoms / Notes -->
            <div class="booking-card">
              <h2 class="booking-card-title">
                <span class="step-badge">4</span>
                <span>ระบุอาการเบื้องต้น หรือหมายเหตุ (ถ้ามี)</span>
              </h2>

              <div class="form-group">
                <label class="form-label" for="notes">รายละเอียดอาการ หรือความประสงค์ในการตรวจ</label>
                <textarea id="notes" name="notes" class="form-control" rows="3" 
                          placeholder="เช่น มีไข้ ไอ เจ็บคอ 2 วัน หรือ ต้องการตรวจสุขภาพประจำปี..."><?= htmlspecialchars($notes) ?></textarea>
              </div>
            </div>

          </div>

          <!-- Right Column: Sticky Summary & Confirmation -->
          <div class="booking-summary-col">
            <div class="booking-summary-card">
              <h3 class="summary-title">สรุปข้อมูลการจอง</h3>

              <div class="summary-line">
                <span class="summary-label">ผู้รับบริการ:</span>
                <span class="summary-value"><?= htmlspecialchars($user['full_name']) ?></span>
              </div>
              <div class="summary-line">
                <span class="summary-label">หมายเลข HN:</span>
                <span class="summary-value" style="color:#2563eb;"><?= htmlspecialchars($user['patient_number'] ?? 'จะออกให้อัตโนมัติ') ?></span>
              </div>
              <div class="summary-divider"></div>

              <div class="summary-line">
                <span class="summary-label">แผนกตรวจ:</span>
                <span class="summary-value" id="summaryDept">—</span>
              </div>
              <div class="summary-line">
                <span class="summary-label">สถานที่:</span>
                <span class="summary-value" id="summaryLoc" style="font-size:11px; color:#475569;">—</span>
              </div>
              <div class="summary-line">
                <span class="summary-label">วันที่นัดหมาย:</span>
                <span class="summary-value" id="summaryDate">—</span>
              </div>
              <div class="summary-line">
                <span class="summary-label">รอบเวลา:</span>
                <span class="summary-value" id="summaryTime" style="color: #16a34a;">กรุณาเลือกรอบเวลา</span>
              </div>

              <div class="summary-divider"></div>

              <button type="submit" id="btnSubmitBooking" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 14px;" disabled>
                ยืนยันการจองคิว <span>→</span>
              </button>

              <p style="font-size: 11px; color: #94a3b8; text-align: center; margin: 12px 0 0;">
                ระบบจะสร้างหมายเลขคิวและบันทึกข้อมูลการนัดหมายทันที
              </p>
            </div>
          </div>

        </div>
      </form>

    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-bottom">
      <span>© <?= date('Y') ?> RMUTT Hospital Queue — ระบบจองคิวออนไลน์</span>
      <a href="dashboard.php" style="color: inherit;">กลับแดชบอร์ด</a>
    </div>
  </footer>

  <script>
    let currentDeptId = <?= $selectedDeptId ?: 0 ?>;
    let currentDate = '<?= $selectedDate ?>';
    let selectedSlotId = null;

    function handleDeptChange(radio) {
      currentDeptId = radio.value;
      updateSummaryDept();
      loadSlots();
    }

    function handleDateChange(val) {
      currentDate = val;
      updateSummaryDate();
      loadSlots();
    }

    function updateSummaryDept() {
      const checked = document.querySelector('input[name="department_id"]:checked');
      if (checked) {
        document.getElementById('summaryDept').textContent = checked.dataset.name || '—';
        document.getElementById('summaryLoc').textContent = checked.dataset.location || '—';
      }
    }

    function updateSummaryDate() {
      if (currentDate) {
        const parts = currentDate.split('-');
        if (parts.length === 3) {
          document.getElementById('summaryDate').textContent = `${parts[2]}/${parts[1]}/${parseInt(parts[0]) + 543}`;
          return;
        }
      }
      document.getElementById('summaryDate').textContent = currentDate || '—';
    }

    function handleSlotSelect(slotId, timeStr) {
      selectedSlotId = slotId;
      document.getElementById('summaryTime').textContent = timeStr;
      document.getElementById('summaryTime').style.color = '#2563eb';
      document.getElementById('btnSubmitBooking').disabled = false;
    }

    async function loadSlots() {
      const container = document.getElementById('slotsContainer');
      const loader = document.getElementById('slotsLoading');
      const noSlots = document.getElementById('noSlotsMessage');
      const submitBtn = document.getElementById('btnSubmitBooking');
      
      selectedSlotId = null;
      submitBtn.disabled = true;
      document.getElementById('summaryTime').textContent = 'กรุณาเลือกรอบเวลา';
      document.getElementById('summaryTime').style.color = '#e11d48';

      if (!currentDeptId || !currentDate) {
        container.innerHTML = '';
        return;
      }

      loader.style.display = 'block';
      noSlots.style.display = 'none';
      container.innerHTML = '';

      try {
        const res = await fetch(`book.php?action=get_slots&department_id=${currentDeptId}&date=${currentDate}`);
        const data = await res.json();
        loader.style.display = 'none';

        if (data.success && data.slots && data.slots.length > 0) {
          let html = '';
          data.slots.forEach(s => {
            const isFull = s.available <= 0;
            const timeLabel = `${s.start_time_fmt} - ${s.end_time_fmt} น.`;
            html += `
              <label class="slot-radio">
                <input type="radio" name="schedule_id" value="${s.id}" ${isFull ? 'disabled' : ''} 
                       onchange="handleSlotSelect('${s.id}', '${timeLabel}')">
                <div class="slot-card ${isFull ? 'disabled' : ''}">
                  <div class="slot-time">${timeLabel}</div>
                  <div class="slot-quota ${isFull ? 'full' : ''}">
                    ${isFull ? '✕ เต็มแล้ว' : `✓ ว่าง ${s.available} / ${s.capacity} ที่`}
                  </div>
                </div>
              </label>
            `;
          });
          container.innerHTML = html;
        } else {
          noSlots.style.display = 'block';
        }
      } catch (err) {
        loader.style.display = 'none';
        noSlots.style.display = 'block';
        console.error('Slot load error:', err);
      }
    }

    // Initialize summary and slots on page load
    document.addEventListener('DOMContentLoaded', () => {
      updateSummaryDept();
      updateSummaryDate();
      loadSlots();
    });
  </script>
</body>
</html>
