<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Protect page: require login
require_login();

$user = current_user();
$userId = (int)$user['id'];

// Fetch fresh details from database
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.role, u.created_at,
               p.id AS patient_id, p.patient_number, p.phone, p.date_of_birth
        FROM users u
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    $freshUser = $stmt->fetch();

    if ($freshUser) {
        $user = array_merge($user, $freshUser);
        $_SESSION['user'] = $user;
    }

    // Check for any upcoming appointments
    $stmtAppt = $pdo->prepare("
        SELECT a.id, a.booking_code, a.appointment_status, a.created_at,
               d.name AS department_name, s.service_date, s.start_time, s.end_time,
               q.queue_number, q.queue_status
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        JOIN schedules s ON a.schedule_id = s.id
        LEFT JOIN queues q ON a.id = q.appointment_id
        WHERE a.patient_id = :patient_id
        ORDER BY s.service_date DESC, s.start_time DESC
        LIMIT 5
    ");
    $stmtAppt->execute([':patient_id' => $user['patient_id'] ?? 0]);
    $appointments = $stmtAppt->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard data fetch error: ' . $e->getMessage());
    $appointments = [];
}

$pageTitle = 'แดชบอร์ดผู้รับบริการ';
$userInitials = mb_substr($user['full_name'], 0, 1, 'UTF-8');
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
</head>
<body class="dashboard-wrapper">

  <!-- Header -->
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="index.php" aria-label="หน้าแรก">
        <div class="brand-mark"><span>Q</span></div>
        <div class="brand-copy">
          <strong>RMUTT <span>HEALTH</span></strong>
          <small>SMART QUEUE SYSTEM</small>
        </div>
      </a>
      <nav class="main-nav" id="mainNav">
        <a href="index.php">หน้าแรก</a>
        <a class="active" href="dashboard.php">แดชบอร์ด</a>
        <a href="book.php">จองคิวออนไลน์</a>
        <a href="queue_display.php" target="_blank">จอแสดงผลคิว (TV) ↗</a>
        <?php if (has_role(['staff', 'admin']) || isset($_SESSION['staff_demo_mode'])): ?>
          <a href="staff_queue.php" style="color:#2563eb; font-weight:600;">ระบบเจ้าหน้าที่ 🩺</a>
        <?php endif; ?>
      </nav>
      <div class="nav-actions">
        <span class="user-menu-pill">
          <span class="user-menu-avatar"><?= htmlspecialchars($userInitials) ?></span>
          <span><?= htmlspecialchars($user['full_name']) ?></span>
        </span>
        <a href="logout.php" class="btn btn-ghost btn-small" title="ออกจากระบบ">
          ออกจากระบบ ⎋
        </a>
      </div>
    </div>
  </header>

  <!-- Hero Banner -->
  <section class="dashboard-hero">
    <div class="container">
      <div class="dash-user-row">
        <div class="dash-user-profile">
          <div class="dash-avatar"><?= htmlspecialchars($userInitials) ?></div>
          <div class="dash-user-meta">
            <h1>สวัสดีคุณ <?= htmlspecialchars($user['full_name']) ?></h1>
            <div class="dash-user-badges">
              <span class="badge badge-role-patient">
                ● <?= htmlspecialchars(get_role_label($user['role'] ?? 'patient')) ?>
              </span>
              <?php if (!empty($user['patient_number'])): ?>
                <span class="badge badge-hn">HN: <?= htmlspecialchars($user['patient_number']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="dash-actions-top">
          <a href="book.php" class="btn btn-white btn-small">
            ＋ จองคิวตรวจใหม่
          </a>
          <?php if (has_role(['staff', 'admin']) || isset($_SESSION['staff_demo_mode'])): ?>
            <a href="staff_queue.php" class="btn btn-primary btn-small" style="background:#2563eb;">
              🩺 คอนโซลเจ้าหน้าที่
            </a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline-light btn-small">
            ออกจากระบบ
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content -->
  <main class="dashboard-main">
    <div class="container">
      
      <?= render_flash() ?>

      <div class="dash-grid">
        <!-- Left: Quick Actions and Recent Appointments -->
        <div class="dash-col-main">
          
          <div class="dash-card">
            <h2 class="dash-card-title">เมนูบริการด่วน (Quick Services)</h2>
            <div class="quick-action-cards">
              <a href="book.php" class="quick-btn">
                <div class="quick-icon blue">▦</div>
                <div class="quick-text">
                  <h4>จองคิวออนไลน์</h4>
                  <p>เลือกแผนกบริการ วัน และเวลานัดหมายล่วงหน้า</p>
                </div>
              </a>
              <a href="queue_display.php" target="_blank" class="quick-btn">
                <div class="quick-icon purple">📺</div>
                <div class="quick-text">
                  <h4>จอแสดงผลคิวสด (TV)</h4>
                  <p>เปิดดูหน้าจอเรียกคิวขนาดใหญ่พร้อมเสียงอ่าน</p>
                </div>
              </a>
              <a href="staff_queue.php" class="quick-btn">
                <div class="quick-icon green">🩺</div>
                <div class="quick-text">
                  <h4>ระบบเรียกคิวเจ้าหน้าที่</h4>
                  <p>สำหรับแพทย์/พยาบาลในการกดเรียกและบันทึกคิว</p>
                </div>
              </a>
              <a href="#history" class="quick-btn">
                <div class="quick-icon amber">✓</div>
                <div class="quick-text">
                  <h4>ประวัติการนัดหมาย</h4>
                  <p>ดูรายการนัดหมายย้อนหลังและพิมพ์บัตรคิว</p>
                </div>
              </a>
            </div>
          </div>

          <div class="dash-card" id="history">
            <h2 class="dash-card-title">
              <span>รายการนัดหมายและคิวล่าสุด</span>
              <small style="font-size: 12px; color: #8794a7; font-weight: normal;">
                <?= count($appointments) ?> รายการ
              </small>
            </h2>

            <?php if (empty($appointments)): ?>
              <div style="text-align: center; padding: 35px 20px; color: #64748b;">
                <div style="font-size: 40px; margin-bottom: 12px; opacity: 0.5;">📋</div>
                <h3 style="font-size: 16px; margin: 0 0 6px; color: #1e293b;">ยังไม่มีรายการนัดหมายในขณะนี้</h3>
                <p style="font-size: 13px; margin: 0 0 20px;">คุณสามารถกดปุ่มด้านล่างเพื่อเริ่มเลือกแผนกและทำการนัดหมายคิวออนไลน์ได้ทันที</p>
                <a href="book.php" class="btn btn-primary btn-small">เริ่มทำการจองคิว <span>→</span></a>
              </div>
            <?php else: ?>
              <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                  <thead>
                    <tr style="border-bottom: 2px solid #eef2f7; color: #64748b;">
                      <th style="padding: 10px 8px;">รหัสจอง</th>
                      <th style="padding: 10px 8px;">แผนก</th>
                      <th style="padding: 10px 8px;">วัน-เวลา</th>
                      <th style="padding: 10px 8px;">เลขคิว</th>
                      <th style="padding: 10px 8px;">สถานะ</th>
                      <th style="padding: 10px 8px; text-align: right;">บัตรคิว</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($appointments as $appt): ?>
                      <tr style="border-bottom: 1px solid #f1f4f9;">
                        <td style="padding: 12px 8px; font-weight: 600; color: #2869d8;">
                          <a href="ticket.php?code=<?= urlencode($appt['booking_code']) ?>" style="text-decoration:underline;">
                            <?= htmlspecialchars($appt['booking_code']) ?>
                          </a>
                        </td>
                        <td style="padding: 12px 8px;"><?= htmlspecialchars($appt['department_name']) ?></td>
                        <td style="padding: 12px 8px;">
                          <?= htmlspecialchars($appt['service_date']) ?><br>
                          <small style="color: #64748b;"><?= htmlspecialchars(substr($appt['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($appt['end_time'], 0, 5)) ?> น.</small>
                        </td>
                        <td style="padding: 12px 8px; font-weight: 700; color: #0d213a;">
                          <span style="font-size: 16px; color: #2563eb;"><?= htmlspecialchars($appt['queue_number'] ?? '—') ?></span>
                        </td>
                        <td style="padding: 12px 8px;">
                          <span class="badge" style="background:#eaf2ff; color:#2869d8;">
                            <?= htmlspecialchars($appt['appointment_status']) ?>
                          </span>
                        </td>
                        <td style="padding: 12px 8px; text-align: right;">
                          <a href="ticket.php?code=<?= urlencode($appt['booking_code']) ?>" class="btn btn-ghost btn-small" style="padding: 6px 10px; font-size: 11px;">
                            ดูบัตรคิว ↗
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

        </div>

        <!-- Right: Profile Details & Account Security -->
        <div class="dash-col-side" id="profile">
          <div class="dash-card">
            <h2 class="dash-card-title">ข้อมูลผู้รับบริการ</h2>
            <div class="profile-info-list">
              <div class="info-item">
                <span class="info-label">ชื่อ - นามสกุล</span>
                <span class="info-val"><?= htmlspecialchars($user['full_name']) ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">หมายเลขผู้ป่วย (HN)</span>
                <span class="info-val" style="color: #2869d8;"><?= htmlspecialchars($user['patient_number'] ?? 'ยังไม่ออกเลข') ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">อีเมล</span>
                <span class="info-val"><?= htmlspecialchars($user['email']) ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">เบอร์โทรศัพท์</span>
                <span class="info-val"><?= htmlspecialchars($user['phone'] ?? '—') ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">วันเดือนปีเกิด</span>
                <span class="info-val"><?= htmlspecialchars($user['date_of_birth'] ?? '—') ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">วันที่ลงทะเบียน</span>
                <span class="info-val"><?= date('d/m/Y', strtotime($user['created_at'] ?? 'now')) ?></span>
              </div>
            </div>
          </div>

          <div class="dash-card">
            <h2 class="dash-card-title">ความปลอดภัย & การเข้าถึง</h2>
            <div class="profile-info-list">
              <div class="info-item">
                <span class="info-label">ระดับสิทธิ์</span>
                <span class="info-val"><?= htmlspecialchars(get_role_label($user['role'] ?? 'patient')) ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">สถานะบัญชี</span>
                <span class="info-val" style="color: #10b981;">✓ ใช้งานได้ปกติ (Active)</span>
              </div>
              <div class="info-item">
                <span class="info-label">ระบบความปลอดภัย</span>
                <span class="info-val">CSRF & Session Shield</span>
              </div>
            </div>
            <div style="margin-top: 18px;">
              <a href="logout.php" class="btn btn-ghost" style="width: 100%; border-color: #fecaca; color: #dc2626;">
                ออกจากระบบอย่างปลอดภัย
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container footer-bottom">
      <span>© <?= date('Y') ?> RMUTT Hospital Queue — แดชบอร์ดผู้รับบริการ</span>
      <a href="index.php" style="color: inherit;">กลับหน้าแรก</a>
    </div>
  </footer>

</body>
</html>
