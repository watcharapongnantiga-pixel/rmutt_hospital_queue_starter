<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
require_guest();

$error = null;
$emailInput = $_COOKIE['remember_email'] ?? '';
$redirectTo = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'dashboard.php');

// Ensure redirect stays local for security
if (!empty($redirectTo)) {
    $parsed = parse_url($redirectTo);
    if (!empty($parsed['host']) || str_starts_with($redirectTo, '//')) {
        $redirectTo = 'dashboard.php';
    }
} else {
    $redirectTo = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        $emailInput = $email;

        if (empty($email) || empty($password)) {
            $error = 'กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน';
        } else {
            try {
                // Fetch user and joined patient details if any
                $stmt = $pdo->prepare("
                    SELECT u.id, u.full_name, u.email, u.password_hash, u.role, u.is_active,
                           p.id AS patient_id, p.patient_number, p.phone, p.date_of_birth
                    FROM users u
                    LEFT JOIN patients p ON u.id = p.user_id
                    WHERE u.email = :email
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
                    if ($user) {
                        log_audit($pdo, $user['id'], 'LOGIN_FAILED', 'users', $user['id'], ['reason' => 'invalid_password']);
                    }
                } elseif ((int)$user['is_active'] !== 1) {
                    $error = 'บัญชีผู้ใช้นี้ถูกระงับการใช้งานชั่วคราว กรุณาติดต่อแผนกบริการผู้ป่วย';
                    log_audit($pdo, $user['id'], 'LOGIN_BLOCKED', 'users', $user['id'], ['reason' => 'inactive']);
                } else {
                    // Login Success
                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'id' => (int)$user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'patient_id' => $user['patient_id'] ? (int)$user['patient_id'] : null,
                        'patient_number' => $user['patient_number'] ?? null,
                        'phone' => $user['phone'] ?? null,
                        'date_of_birth' => $user['date_of_birth'] ?? null
                    ];

                    // Remember email cookie
                    if ($rememberMe) {
                        setcookie('remember_email', $email, time() + (86400 * 30), '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
                    } else {
                        setcookie('remember_email', '', time() - 3600, '/');
                    }

                    // Audit log
                    log_audit($pdo, (int)$user['id'], 'LOGIN_SUCCESS', 'users', (int)$user['id']);

                    set_flash('success', "เข้าสู่ระบบสำเร็จ ยินดีต้อนรับคุณ {$user['full_name']}");
                    header('Location: ' . $redirectTo);
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Login query error: ' . $e->getMessage());
                $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | RMUTT Hospital Queue</title>
  <meta name="description" content="เข้าสู่ระบบจองคิวออนไลน์ RMUTT Health">
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
      <a href="register.php" class="btn btn-primary btn-small">สมัครสมาชิกใหม่ <span aria-hidden="true">↗</span></a>
    </div>
  </header>

  <!-- Main Container -->
  <main class="auth-container">
    <div class="auth-card-wrapper">
      <div class="auth-card">
        <div class="auth-card-head">
          <span class="auth-card-badge">✦ ระบบยืนยันตัวตน</span>
          <h1>เข้าสู่ระบบ</h1>
          <p>กรอกข้อมูลบัญชีเพื่อเข้าจัดการคิวและการนัดหมายของคุณ</p>
        </div>

        <?= render_flash() ?>

        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert">
            <span class="alert-icon">✕</span>
            <div class="alert-content"><?= htmlspecialchars($error) ?></div>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="ปิด">×</button>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTo) ?>">

          <div class="form-group">
            <label class="form-label" for="email">อีเมลบัญชีผู้ใช้ <span class="required">*</span></label>
            <div class="input-wrapper">
              <span class="input-icon">✉</span>
              <input type="email" id="email" name="email" class="form-control" 
                     placeholder="example@mail.com" 
                     value="<?= htmlspecialchars($emailInput) ?>" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="password">รหัสผ่าน <span class="required">*</span></label>
            <div class="input-wrapper">
              <span class="input-icon">🔒</span>
              <input type="password" id="password" name="password" class="form-control" 
                     placeholder="กรอกรหัสผ่านของคุณ" required>
              <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('password', this)">แสดง</button>
            </div>
          </div>

          <div class="form-options">
            <label class="checkbox-label">
              <input type="checkbox" name="remember_me" value="1" <?= !empty($_COOKIE['remember_email']) ? 'checked' : '' ?>>
              <span>จำอีเมลไว้ในเครื่องนี้</span>
            </label>
          </div>

          <button type="submit" class="btn btn-primary auth-btn-submit">
            เข้าสู่ระบบ <span>→</span>
          </button>
        </form>

        <div class="auth-footer-links">
          ยังไม่มีบัญชีผู้ใช้งาน? <a href="register.php">สมัครสมาชิกที่นี่</a>
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
  </script>
</body>
</html>
