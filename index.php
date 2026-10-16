<?php
require_once __DIR__ . '/includes/auth.php';
$currentUser = current_user();
$isLoggedIn = is_logged_in();
$pageTitle = 'ระบบจองคิวออนไลน์';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | RMUTT Hospital Queue</title>
  <meta name="description" content="ต้นแบบระบบจองคิวออนไลน์และติดตามสถานะคิว">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
  <div class="announcement">
    <div class="container announcement-inner">
      <span><span class="status-dot"></span> ระบบต้นแบบจองคิวออนไลน์ RMUTT HEALTH</span>
      <span class="announcement-note">หากเป็นกรณีฉุกเฉิน โปรดติดต่อหน่วยบริการฉุกเฉินโดยตรง</span>
    </div>
  </div>

  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="index.php" aria-label="หน้าแรก">
        <div class="brand-mark"><span>Q</span></div>
        <div class="brand-copy">
          <strong>RMUTT <span>HEALTH</span></strong>
          <small>SMART QUEUE SYSTEM</small>
        </div>
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู" aria-expanded="false">☰</button>
      <nav class="main-nav" id="mainNav">
        <a class="active" href="index.php">หน้าแรก</a>
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php">แดชบอร์ดของฉัน</a>
        <?php endif; ?>
        <a href="#services">บริการของเรา</a>
        <a href="#how-it-works">ขั้นตอนการจอง</a>
        <a href="#queue-info">สถานะคิว</a>
      </nav>
      <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
          <a class="user-menu-pill" href="dashboard.php" title="ไปที่แดชบอร์ด">
            <span class="user-menu-avatar"><?= htmlspecialchars(mb_substr($currentUser['full_name'], 0, 1, 'UTF-8')) ?></span>
            <span><?= htmlspecialchars($currentUser['full_name']) ?></span>
          </a>
          <a class="btn btn-ghost btn-small" href="logout.php">ออกจากระบบ</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="login.php">เข้าสู่ระบบ</a>
          <a class="btn btn-primary btn-small" href="register.php">สมัครสมาชิก <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <div class="container" style="padding-top: 15px;">
      <?= render_flash() ?>
    </div>

    <section class="hero">
      <div class="hero-glow glow-one"></div><div class="hero-glow glow-two"></div>
      <div class="container hero-grid">
        <div class="hero-copy">
          <div class="eyebrow"><span class="eyebrow-icon">✦</span> HEALTHCARE, MADE SIMPLER</div>
          <h1>ดูแลสุขภาพ<br>เริ่มต้นได้ง่าย ๆ<br><span>ไม่ต้องรอคิวนาน</span></h1>
          <p class="hero-description">จองคิวเข้ารับบริการ ตรวจสอบสถานะ และวางแผนเวลาของคุณได้ในที่เดียว สะดวกขึ้น ชัดเจนขึ้น ทุกขั้นตอน</p>
          <div class="hero-actions">
            <?php if ($isLoggedIn): ?>
              <a class="btn btn-primary btn-large" href="dashboard.php">ไปที่แดชบอร์ด / จองคิว <span aria-hidden="true">→</span></a>
            <?php else: ?>
              <a class="btn btn-primary btn-large" href="login.php">จองคิวเข้ารับบริการ <span aria-hidden="true">→</span></a>
            <?php endif; ?>
            <a class="btn btn-outline-light btn-large" href="#how-it-works">ดูขั้นตอนการจอง</a>
          </div>
          <div class="hero-assurance">
            <span class="assurance-icon">✓</span>
            <span>ออกแบบเพื่อให้เข้าถึงบริการได้สะดวกยิ่งขึ้น</span>
          </div>
        </div>

        <div class="hero-visual" aria-label="ภาพสรุปการจองคิว">
          <div class="visual-orbit orbit-a"></div><div class="visual-orbit orbit-b"></div>
          <div class="floating-label label-top"><span class="mini-icon blue">◷</span><span><b>จัดการเวลาของคุณ</b><small>วางแผนก่อนเข้ารับบริการ</small></span></div>
          <div class="appointment-card">
            <div class="appointment-head">
              <div><span class="card-kicker">YOUR HEALTH, YOUR TIME</span><h3>การนัดหมายของคุณ</h3></div>
              <div class="calendar-icon">▦</div>
            </div>
            <div class="appointment-line"><span class="line-icon">＋</span><span><small>บริการ</small><b>เลือกแผนกที่ต้องการ</b></span><span class="line-arrow">›</span></div>
            <div class="appointment-line"><span class="line-icon">◷</span><span><small>วันและเวลา</small><b>เลือกช่วงเวลาที่สะดวก</b></span><span class="line-arrow">›</span></div>
            <div class="appointment-footer"><span class="footer-check">✓</span><span>ยืนยันข้อมูลก่อนส่งคำขอจอง</span></div>
            <a class="btn btn-primary card-cta" href="<?= $isLoggedIn ? 'dashboard.php' : 'login.php' ?>">เริ่มจองคิว <span>→</span></a>
          </div>
          <div class="floating-label label-bottom"><span class="mini-icon green">✓</span><span><b>ติดตามได้ทุกขั้นตอน</b><small>ตรวจสอบสถานะคิวของคุณ</small></span></div>
          <div class="decorative-cross cross-one">+</div><div class="decorative-cross cross-two">+</div>
        </div>
      </div>
      <div class="hero-bottom-line"></div>
    </section>

    <section class="trust-strip">
      <div class="container trust-inner">
        <div class="trust-heading"><span class="trust-symbol">✚</span><span><b>บริการสุขภาพที่เข้าถึงง่าย</b><small>เริ่มต้นจัดการนัดหมายออนไลน์</small></span></div>
        <div class="trust-item"><b>01</b><span>จองคิวสะดวก</span></div>
        <div class="trust-item"><b>02</b><span>ติดตามสถานะได้</span></div>
        <div class="trust-item"><b>03</b><span>ข้อมูลเป็นระเบียบ</span></div>
      </div>
    </section>

    <section class="section services-section" id="services">
      <div class="container">
        <div class="section-heading">
          <div><div class="eyebrow eyebrow-dark">OUR SERVICES</div><h2>บริการที่ออกแบบ<br class="mobile-break">เพื่อคุณ</h2></div>
          <p>เข้าถึงข้อมูลที่จำเป็นก่อนเข้ารับบริการ<br>ผ่านหน้าจอที่ใช้งานง่ายและเป็นขั้นตอน</p>
        </div>
        <div class="service-grid">
          <article class="service-card featured">
            <div class="service-icon icon-blue">▦</div><span class="service-number">01 / BOOKING</span>
            <h3>จองคิวออนไลน์</h3><p>เลือกแผนก วัน และช่วงเวลาที่สะดวก พร้อมตรวจสอบรายละเอียดก่อนยืนยัน</p><a href="<?= $isLoggedIn ? 'dashboard.php' : 'login.php' ?>" class="text-link">เริ่มจองคิว <span>→</span></a>
          </article>
          <article class="service-card">
            <div class="service-icon icon-purple">◷</div><span class="service-number">02 / QUEUE STATUS</span>
            <h3>ติดตามคิวของฉัน</h3><p>ตรวจสอบหมายเลขคิว สถานะการให้บริการ และข้อมูลนัดหมายของคุณ</p><a href="<?= $isLoggedIn ? 'dashboard.php' : 'login.php' ?>" class="text-link">ตรวจสอบคิว <span>→</span></a>
          </article>
          <article class="service-card">
            <div class="service-icon icon-green">⌖</div><span class="service-number">03 / INFORMATION</span>
            <h3>ข้อมูลก่อนเข้ารับบริการ</h3><p>เตรียมเอกสารและตรวจสอบขั้นตอนเบื้องต้นก่อนเดินทางมาโรงพยาบาล</p><a href="#how-it-works" class="text-link">ดูรายละเอียด <span>→</span></a>
          </article>
        </div>
      </div>
    </section>

    <section class="queue-section" id="queue-info">
      <div class="container queue-banner">
        <div class="queue-banner-copy">
          <div class="eyebrow eyebrow-light">QUEUE INFORMATION</div>
          <h2>วางแผนก่อนมา<br>มั่นใจทุกขั้นตอน</h2>
          <p>เมื่อระบบเชื่อมต่อฐานข้อมูลและระบบเรียกคิวแล้ว คุณจะสามารถตรวจสอบสถานะคิวและข้อมูลการนัดหมายได้จากหน้านี้</p>
          <a href="<?= $isLoggedIn ? 'dashboard.php' : 'login.php' ?>" class="btn btn-white"><?= $isLoggedIn ? 'ไปที่แดชบอร์ดของคุณ' : 'เข้าสู่ระบบเพื่อติดตามคิว' ?> <span>→</span></a>
        </div>
        <div class="queue-preview">
          <div class="preview-top"><span>QUEUE OVERVIEW</span><span class="preview-live"><i></i> UI PREVIEW</span></div>
          <div class="preview-main"><div><small>หมายเลขคิวของคุณ</small><strong>Q— — —</strong><span class="preview-status">ตัวอย่างหน้าจอ</span></div><div class="preview-ticket">Q</div></div>
          <div class="preview-divider"></div>
          <div class="preview-stats"><div><small>สถานะคิว</small><b>รอข้อมูลจริง</b></div><div><small>เวลารอโดยประมาณ</small><b>— นาที</b></div></div>
          <p class="preview-note">หน้าจอตัวอย่าง · ยังไม่มีการเชื่อมต่อข้อมูลคิวจริง</p>
        </div>
      </div>
    </section>

    <section class="section steps-section" id="how-it-works">
      <div class="container">
        <div class="section-heading centered">
          <div class="eyebrow eyebrow-dark">HOW IT WORKS</div><h2>จองคิวได้ใน 4 ขั้นตอน</h2><p>เตรียมข้อมูลให้พร้อม แล้วดำเนินการตามขั้นตอนที่แสดงบนหน้าจอ</p>
        </div>
        <div class="steps-grid">
          <div class="step-card"><div class="step-count">01</div><div class="step-icon">♙</div><h3>เข้าสู่ระบบ</h3><p>สมัครสมาชิกหรือเข้าสู่ระบบด้วยบัญชีของคุณ</p></div>
          <div class="step-card"><div class="step-count">02</div><div class="step-icon">✚</div><h3>เลือกบริการ</h3><p>เลือกแผนกและช่วงเวลาที่เปิดให้จอง</p></div>
          <div class="step-card"><div class="step-count">03</div><div class="step-icon">▦</div><h3>ยืนยันการจอง</h3><p>ตรวจสอบข้อมูลและยืนยันคำขอจองคิว</p></div>
          <div class="step-card"><div class="step-count">04</div><div class="step-icon">✓</div><h3>ติดตามสถานะ</h3><p>ตรวจสอบรายละเอียดนัดหมายและสถานะคิว</p></div>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-main">
      <a class="brand footer-brand" href="index.php"><div class="brand-mark"><span>Q</span></div><div class="brand-copy"><strong>RMUTT <span>HEALTH</span></strong><small>SMART QUEUE SYSTEM</small></div></a>
      <p>ระบบต้นแบบสำหรับพัฒนาการจองคิวและบริหารจัดการคิวผู้รับบริการ</p>
      <a href="#top" class="back-top">กลับด้านบน ↑</a>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> RMUTT Hospital Queue — Prototype</span><span>ไม่ใช่ระบบทางการ และยังไม่รองรับข้อมูลผู้ป่วยจริง</span></div>
  </footer>
  <script src="assets/js/main.js"></script>
</body>
</html>
