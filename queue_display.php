<?php
require_once __DIR__ . '/config/database.php';

// Fetch departments for filter tab
try {
    $departments = $pdo->query("SELECT id, name FROM departments WHERE is_open_for_booking = 1 ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

$deptId = (int)($_GET['department_id'] ?? 0);
$pageTitle = 'จอแสดงผลคิวตรวจ (Live Queue Display)';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | RMUTT Hospital Queue</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/queue.css">
  <style>
    .dept-tabs {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding: 10px 36px;
      background: #091a2e;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .dept-tab-item {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      color: #94a3b8;
      background: rgba(255,255,255,0.05);
      text-decoration: none;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .dept-tab-item:hover, .dept-tab-item.active {
      background: #2563eb;
      color: #ffffff;
      font-weight: 600;
    }
    .sound-toggle-btn {
      background: rgba(56, 189, 248, 0.15);
      border: 1px solid rgba(56, 189, 248, 0.4);
      color: #38bdf8;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .sound-toggle-btn.muted {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.4);
      color: #f87171;
    }
    .calling-anim {
      animation: callingFlash 1.5s infinite alternate;
    }
    @keyframes callingFlash {
      from { border-color: rgba(56, 189, 248, 0.3); box-shadow: 0 0 20px rgba(56, 189, 248, 0.2); }
      to { border-color: rgba(56, 189, 248, 0.9); box-shadow: 0 0 45px rgba(56, 189, 248, 0.6); }
    }
  </style>
</head>
<body class="display-body">

  <!-- Top Display Header -->
  <header class="display-header">
    <div class="display-brand">
      <div class="brand-mark" style="width:48px; height:48px; font-size:28px;"><span>Q</span></div>
      <div>
        <div style="font-size: 19px; font-weight: 800; letter-spacing: 0.5px;">RMUTT <span style="color:#38bdf8;">HEALTH</span></div>
        <small style="color: #64748b; font-size: 10px; letter-spacing: 1.5px;">HOSPITAL SMART QUEUE DISPLAY</small>
      </div>
    </div>

    <!-- Center: Sound & Fullscreen Controls -->
    <div style="display: flex; gap: 12px; align-items: center;">
      <button type="button" id="btnToggleSound" class="sound-toggle-btn muted" onclick="toggleAudio()">
        🔇 คลิกเพื่อเปิดเสียงเรียกคิว (Enable Audio)
      </button>
      <button type="button" class="sound-toggle-btn" style="border-color:rgba(255,255,255,0.2); color:#cbd5e1; background:rgba(255,255,255,0.05);" onclick="toggleFullscreen()">
        ⛶ เต็มจอ
      </button>
      <a href="dashboard.php" class="sound-toggle-btn" style="border-color:rgba(255,255,255,0.2); color:#cbd5e1; background:rgba(255,255,255,0.05);">
        แดชบอร์ด ↗
      </a>
    </div>

    <!-- Clock -->
    <div>
      <div class="display-clock" id="displayClock">00:00:00</div>
      <div class="display-date" id="displayDate"><?= date('d/m/Y') ?></div>
    </div>
  </header>

  <!-- Department Selection Tabs -->
  <div class="dept-tabs">
    <a href="queue_display.php?department_id=0" class="dept-tab-item <?= $deptId === 0 ? 'active' : '' ?>">
      🏢 แผนกรวมทั้งหมด (Main Hall)
    </a>
    <?php foreach ($departments as $d): ?>
      <a href="queue_display.php?department_id=<?= $d['id'] ?>" class="dept-tab-item <?= $deptId === (int)$d['id'] ? 'active' : '' ?>">
        <?= htmlspecialchars($d['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Main Content Grid -->
  <main class="display-grid">
    
    <!-- Left Section: Now Calling Card -->
    <div class="now-calling-card calling-anim" id="nowCallingBox">
      <div class="now-calling-badge">
        <i></i> <span>กำลังเรียกรับบริการ (NOW SERVING)</span>
      </div>

      <div class="calling-dept" id="callingDeptName">
        กำลังโหลดข้อมูลคิว...
      </div>

      <div class="calling-number" id="callingNumber">
        — — —
      </div>

      <div style="margin-bottom: 24px;">
        <span class="calling-counter" id="callingLocation">
          กรุณารอสักครู่
        </span>
      </div>

      <div style="font-size: 16px; color: #94a3b8;" id="callingPatientName">
        ระบบเชื่อมต่อข้อมูลแบบเรียลไทม์
      </div>
    </div>

    <!-- Right Section: Waiting List & Completed List -->
    <div class="display-side-col">
      
      <!-- Waiting List -->
      <div class="display-list-card">
        <div class="display-list-title">
          <span>◷ คิวรอรับบริการถัดไป (NEXT PATIENTS)</span>
          <span style="font-size: 13px; color: #38bdf8;" id="waitingCount">0 คิว</span>
        </div>
        <div class="queue-tag-grid" id="waitingGrid">
          <div style="color: #64748b; font-size: 13px; grid-column: 1 / -1; padding: 20px 0; text-align: center;">
            ไม่มีคิวที่กำลังรอ
          </div>
        </div>
      </div>

      <!-- Completed List -->
      <div class="display-list-card">
        <div class="display-list-title">
          <span>✓ ตรวจเสร็จสิ้นล่าสุด (COMPLETED)</span>
          <span style="font-size: 13px; color: #4ade80;" id="completedCount">0 คิว</span>
        </div>
        <div class="queue-tag-grid" id="completedGrid">
          <div style="color: #64748b; font-size: 13px; grid-column: 1 / -1; padding: 20px 0; text-align: center;">
            ยังไม่มีรายการที่เสร็จสิ้น
          </div>
        </div>
      </div>

    </div>

  </main>

  <script>
    const currentDeptId = <?= $deptId ?>;
    let isAudioEnabled = false;
    let lastAnnouncedQueue = null;

    // Clock
    function updateClock() {
      const now = new Date();
      const h = String(now.getHours()).padStart(2, '0');
      const m = String(now.getMinutes()).padStart(2, '0');
      const s = String(now.getSeconds()).padStart(2, '0');
      document.getElementById('displayClock').textContent = `${h}:${m}:${s}`;
      
      const day = now.getDate();
      const monthNames = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
      const month = monthNames[now.getMonth()];
      const year = now.getFullYear() + 543;
      document.getElementById('displayDate').textContent = `${day} ${month} ${year}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Web Audio Synthesizer: Hospital 2-Tone Chime
    let audioCtx = null;
    function playHospitalChime() {
      if (!isAudioEnabled) return;
      try {
        if (!audioCtx) {
          audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
          audioCtx.resume();
        }

        const now = audioCtx.currentTime;
        // Tone 1: High tone (G5 - 784 Hz)
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(784, now);
        gain1.gain.setValueAtTime(0.25, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.6);

        // Tone 2: Low tone (C5 - 523 Hz)
        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(523, now + 0.35);
        gain2.gain.setValueAtTime(0.25, now + 0.35);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.1);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(now + 0.35);
        osc2.stop(now + 1.1);
      } catch (e) {
        console.error('Audio chime error:', e);
      }
    }

    // Voice announcement
    function announceQueue(queueNum, deptName, locName) {
      playHospitalChime();

      if (!isAudioEnabled) return;

      setTimeout(() => {
        if ('speechSynthesis' in window) {
          const splitNum = queueNum.split('').join(' ');
          const text = `ขอเชิญหมายเลข ${splitNum} ที่${deptName} ${locName || ''} ค่ะ`;
          const utterance = new SpeechSynthesisUtterance(text);
          utterance.lang = 'th-TH';
          utterance.rate = 0.95;
          window.speechSynthesis.speak(utterance);
        }
      }, 700);
    }

    function toggleAudio() {
      isAudioEnabled = !isAudioEnabled;
      const btn = document.getElementById('btnToggleSound');
      if (isAudioEnabled) {
        btn.className = 'sound-toggle-btn';
        btn.textContent = '🔊 เสียงเรียกคิว: เปิดใช้งาน (Audio On)';
        playHospitalChime();
      } else {
        btn.className = 'sound-toggle-btn muted';
        btn.textContent = '🔇 คลิกเพื่อเปิดเสียงเรียกคิว (Enable Audio)';
      }
    }

    function toggleFullscreen() {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => console.log(err));
      } else {
        document.exitFullscreen().catch(err => console.log(err));
      }
    }

    // Poll API Queue Data
    async function fetchQueueData() {
      try {
        const res = await fetch(`api_queue.php?department_id=${currentDeptId}`);
        const data = await res.json();

        if (data.success) {
          renderCalled(data.called_queues);
          renderWaiting(data.waiting_queues, data.stats.waiting);
          renderCompleted(data.completed_queues, data.stats.completed);
        }
      } catch (err) {
        console.error('Queue poll error:', err);
      }
    }

    function renderCalled(calledList) {
      const numberEl = document.getElementById('callingNumber');
      const deptEl = document.getElementById('callingDeptName');
      const locEl = document.getElementById('callingLocation');
      const patientEl = document.getElementById('callingPatientName');

      if (calledList && calledList.length > 0) {
        const top = calledList[0];
        numberEl.textContent = top.queue_number;
        deptEl.textContent = top.department_name;
        locEl.textContent = top.location_label || 'ห้องตรวจบริการ';
        patientEl.textContent = `ผู้รับบริการ: ${top.display_name}`;

        // Check if new queue called
        const currentKey = `${top.queue_number}-${top.queue_status}`;
        if (lastAnnouncedQueue !== currentKey) {
          lastAnnouncedQueue = currentKey;
          if (top.queue_status === 'called') {
            announceQueue(top.queue_number, top.department_name, top.location_label);
          }
        }
      } else {
        numberEl.textContent = '— — —';
        deptEl.textContent = 'ไม่มีคิวที่กำลังเรียกในขณะนี้';
        locEl.textContent = 'รอเจ้าหน้าที่เรียกตรวจ';
        patientEl.textContent = 'ยินดีต้อนรับสู่ศูนย์บริการสุขภาพ RMUTT HEALTH';
      }
    }

    function renderWaiting(waitingList, count) {
      document.getElementById('waitingCount').textContent = `${count || 0} คิว`;
      const grid = document.getElementById('waitingGrid');

      if (waitingList && waitingList.length > 0) {
        grid.innerHTML = waitingList.map(item => `
          <div class="queue-tag">
            <strong>${item.queue_number}</strong>
            <small>${item.department_name}</small>
          </div>
        `).join('');
      } else {
        grid.innerHTML = '<div style="color: #64748b; font-size: 13px; grid-column: 1 / -1; padding: 20px 0; text-align: center;">ไม่มีคิวที่กำลังรอ</div>';
      }
    }

    function renderCompleted(completedList, count) {
      document.getElementById('completedCount').textContent = `${count || 0} คิว`;
      const grid = document.getElementById('completedGrid');

      if (completedList && completedList.length > 0) {
        grid.innerHTML = completedList.map(item => `
          <div class="queue-tag completed">
            <strong>${item.queue_number}</strong>
            <small>${item.department_name}</small>
          </div>
        `).join('');
      } else {
        grid.innerHTML = '<div style="color: #64748b; font-size: 13px; grid-column: 1 / -1; padding: 20px 0; text-align: center;">ยังไม่มีรายการที่เสร็จสิ้น</div>';
      }
    }

    // Poll every 3 seconds
    setInterval(fetchQueueData, 3000);
    fetchQueueData();
  </script>

</body>
</html>
