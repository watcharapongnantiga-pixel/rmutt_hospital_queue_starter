<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
require_guest();

$errors = [];
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'date_of_birth' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        // 2. Sanitize and gather inputs
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['date_of_birth'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $agreeTerms = isset($_POST['agree_terms']);

        $old['full_name'] = $fullName;
        $old['email'] = $email;
        $old['phone'] = $phone;
        $old['date_of_birth'] = $dob;

        // 3. Validate
        if (empty($fullName) || mb_strlen($fullName) < 2) {
            $errors['full_name'] = 'กรุณาระบุชื่อ-นามสกุลให้ถูกต้อง (อย่างน้อย 2 ตัวอักษร)';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'กรุณากรอกอีเมลที่ถูกต้อง';
        }

        if (empty($phone)) {
            $errors['phone'] = 'กรุณากรอกเบอร์โทรศัพท์สำหรับติดต่อ';
        } elseif (!preg_match('/^[0-9\-\+\s]{9,15}$/', $phone)) {
            $errors['phone'] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
        }

        if (!empty($dob)) {
            $dobTimestamp = strtotime($dob);
            if (!$dobTimestamp || $dobTimestamp > time()) {
                $errors['date_of_birth'] = 'วันเดือนปีเกิดต้องไม่เกินวันปัจจุบัน';
            }
        }

        if (empty($password) || strlen($password) < 8) {
            $errors['password'] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        } elseif ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        }

        if (!$agreeTerms) {
            $errors['agree_terms'] = 'กรุณายอมรับข้อกำหนดและนโยบายความเป็นส่วนตัว';
        }

        // 4. Check if email already exists
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'อีเมลนี้ถูกใช้งานแล้วในระบบ กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ';
                }
            } catch (PDOException $e) {
                error_log('Error checking email: ' . $e->getMessage());
                $errors['general'] = 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูล กรุณาลองใหม่อีกครั้ง';
            }
        }

        // 5. Proceed to register in Transaction
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (full_name, email, password_hash, role, is_active, created_at, updated_at)
                    VALUES (:full_name, :email, :password_hash, 'patient', 1, NOW(), NOW())
                ");
                $stmtUser->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':password_hash' => $passwordHash
                ]);

                $userId = (int)$pdo->lastInsertId();

                // Generate Hospital Patient Number (HN)
                $patientNumber = generate_patient_number($pdo);

                // Insert into patients
                $stmtPatient = $pdo->prepare("
                    INSERT INTO patients (user_id, patient_number, phone, date_of_birth, created_at)
                    VALUES (:user_id, :patient_number, :phone, :dob, NOW())
                ");
                $stmtPatient->execute([
                    ':user_id' => $userId,
                    ':patient_number' => $patientNumber,
                    ':phone' => $phone,
                    ':dob' => !empty($dob) ? $dob : null
                ]);

                $patientId = (int)$pdo->lastInsertId();

                // Commit Transaction
                $pdo->commit();

                // Audit log
                log_audit($pdo, $userId, 'REGISTER', 'users', $userId, [
                    'email' => $email,
                    'patient_number' => $patientNumber
                ]);

                // Auto login user
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $userId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => 'patient',
                    'patient_id' => $patientId,
                    'patient_number' => $patientNumber,
                    'phone' => $phone,
                    'date_of_birth' => $dob
                ];

                set_flash('success', "ยินดีต้อนรับคุณ {$fullName}! สมัครสมาชิกและออกหมายเลขประจำตัวผู้ป่วย ({$patientNumber}) เรียบร้อยแล้ว");
                header('Location: dashboard.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Registration failure: ' . $e->getMessage());
                $errors['general'] = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}

$pageTitle = 'สมัครสมาชิกผู้รับบริการ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | RMUTT Hospital Queue</title>
  <meta name="description" content="สมัครสมาชิกเพื่อเข้าใช้งานระบบจองคิวออนไลน์ RMUTT Health">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">

  <!-- Header -->
  <header class="auth-header">
    <div class="container nav-wrap">
      <a class="brand" href="index.php" aria-label="กลับสู่หน้าแรก">
        <div class="brand-mark"><span>Q</span></div>
        <div class="brand-copy">
          <strong>RMUTT <span>HEALTH</span></strong>
          <small>SMART QUEUE SYSTEM</small>
        </div>
      </a>
      <a href="login.php" class="btn btn-ghost btn-small">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    </div>
  </header>

  <!-- Main Container -->
  <main class="auth-container">
    <div class="auth-card-wrapper wide">
      <div class="auth-card">
        <div class="auth-card-head">
          <span class="auth-card-badge">✦ สร้างบัญชีผู้รับบริการ</span>
          <h1>สมัครสมาชิกใหม่</h1>
          <p>กรอกข้อมูลเพื่อลงทะเบียนเข้าใช้งานระบบจองคิวและรับหมายเลขผู้ป่วย (HN)</p>
        </div>

        <?= render_flash() ?>

        <?php if (!empty($errors['general'])): ?>
          <div class="alert alert-danger" role="alert">
            <span class="alert-icon">✕</span>
            <div class="alert-content"><?= htmlspecialchars($errors['general']) ?></div>
          </div>
        <?php endif; ?>

        <form action="register.php" method="POST" novalidate id="registerForm">
          <?= csrf_field() ?>

          <div class="form-group">
            <label class="form-label" for="full_name">ชื่อ - นามสกุล <span class="required">*</span></label>
            <div class="input-wrapper">
              <span class="input-icon">👤</span>
              <input type="text" id="full_name" name="full_name" 
                     class="form-control <?= isset($errors['full_name']) ? 'has-error' : '' ?>"
                     placeholder="เช่น นายสมชาย ใจดี" 
                     value="<?= htmlspecialchars($old['full_name']) ?>" required autofocus>
            </div>
            <?php if (isset($errors['full_name'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['full_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="email">อีเมล <span class="required">*</span></label>
              <div class="input-wrapper">
                <span class="input-icon">✉</span>
                <input type="email" id="email" name="email" 
                       class="form-control <?= isset($errors['email']) ? 'has-error' : '' ?>"
                       placeholder="example@mail.com" 
                       value="<?= htmlspecialchars($old['email']) ?>" required>
              </div>
              <?php if (isset($errors['email'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['email']) ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="phone">เบอร์โทรศัพท์มือถือ <span class="required">*</span></label>
              <div class="input-wrapper">
                <span class="input-icon">📞</span>
                <input type="tel" id="phone" name="phone" 
                       class="form-control <?= isset($errors['phone']) ? 'has-error' : '' ?>"
                       placeholder="0812345678" 
                       value="<?= htmlspecialchars($old['phone']) ?>" required>
              </div>
              <?php if (isset($errors['phone'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['phone']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="date_of_birth">วัน/เดือน/ปีเกิด (สำหรับประวัติผู้ป่วย)</label>
            <div class="input-wrapper">
              <span class="input-icon">📅</span>
              <input type="date" id="date_of_birth" name="date_of_birth" 
                     class="form-control <?= isset($errors['date_of_birth']) ? 'has-error' : '' ?>"
                     value="<?= htmlspecialchars($old['date_of_birth']) ?>"
                     max="<?= date('Y-m-d') ?>">
            </div>
            <?php if (isset($errors['date_of_birth'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['date_of_birth']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="password">รหัสผ่าน <span class="required">*</span></label>
              <div class="input-wrapper">
                <span class="input-icon">🔒</span>
                <input type="password" id="password" name="password" 
                       class="form-control <?= isset($errors['password']) ? 'has-error' : '' ?>"
                       placeholder="อย่างน้อย 8 ตัวอักษร" required>
                <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('password', this)">แสดง</button>
              </div>
              <div class="pwd-strength-bar"><div class="pwd-strength-fill" id="pwdStrengthFill"></div></div>
              <?php if (isset($errors['password'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['password']) ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="confirm_password">ยืนยันรหัสผ่าน <span class="required">*</span></label>
              <div class="input-wrapper">
                <span class="input-icon">🔒</span>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="form-control <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>"
                       placeholder="กรอกรหัสผ่านเดิมอีกครั้ง" required>
                <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('confirm_password', this)">แสดง</button>
              </div>
              <div id="pwdMatchMsg" class="field-error" style="display:none;"></div>
              <?php if (isset($errors['confirm_password'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['confirm_password']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group" style="margin-top: 10px;">
            <label class="checkbox-label">
              <input type="checkbox" name="agree_terms" id="agree_terms" value="1" <?= isset($_POST['agree_terms']) ? 'checked' : '' ?> required>
              <span>ข้าพเจ้ายินยอมให้จัดเก็บและประมวลผลข้อมูลส่วนบุคคลตามนโยบาย PDPA สำหรับการนัดหมายและการให้บริการสุขภาพ</span>
            </label>
            <?php if (isset($errors['agree_terms'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['agree_terms']) ?></div>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary auth-btn-submit">
            สร้างบัญชีและรับหมายเลขคิว <span>→</span>
          </button>
        </form>

        <div class="auth-footer-links">
          มีบัญชีผู้ใช้งานอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a>
        </div>
      </div>
    </div>
  </main>

  <script>
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'ซ่อน';
      } else {
        input.type = 'password';
        btn.textContent = 'แสดง';
      }
    }

    const pwdInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const strengthFill = document.getElementById('pwdStrengthFill');
    const matchMsg = document.getElementById('pwdMatchMsg');

    if (pwdInput && strengthFill) {
      pwdInput.addEventListener('input', () => {
        const val = pwdInput.value;
        strengthFill.className = 'pwd-strength-fill';
        if (val.length === 0) {
          strengthFill.style.width = '0%';
        } else if (val.length < 6) {
          strengthFill.classList.add('weak');
        } else if (val.length < 10) {
          strengthFill.classList.add('medium');
        } else {
          strengthFill.classList.add('strong');
        }
        checkMatch();
      });
    }

    if (confirmInput) {
      confirmInput.addEventListener('input', checkMatch);
    }

    function checkMatch() {
      if (!confirmInput.value) {
        matchMsg.style.display = 'none';
        return;
      }
      if (pwdInput.value !== confirmInput.value) {
        matchMsg.style.display = 'flex';
        matchMsg.textContent = '⚠ รหัสผ่านทั้งสองช่องยังไม่ตรงกัน';
        matchMsg.style.color = '#dc2626';
      } else {
        matchMsg.style.display = 'flex';
        matchMsg.textContent = '✓ รหัสผ่านตรงกันเรียบร้อย';
        matchMsg.style.color = '#16a34a';
      }
    }
  </script>
</body>
</html>
