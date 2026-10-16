<?php
/**
 * Authentication and Session Management Helper
 * RMUTT Hospital Queue System
 */

// Initialize secure session
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters before starting
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    
    // Cookie lifetime: 0 means until browser is closed
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

/**
 * Generate or get current CSRF token
 * @return string
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input field
 * @return string
 */
function csrf_field(): string {
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify submitted CSRF token
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash alert message
 * @param string $type 'success', 'danger', 'warning', 'info'
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash alert message
 * @return array|null
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash alert message as HTML
 * @return string
 */
function render_flash(): string {
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');

    $icons = [
        'success' => '✓',
        'danger' => '✕',
        'warning' => '⚠',
        'info' => 'ℹ'
    ];
    $icon = $icons[$type] ?? 'ℹ';

    return <<<HTML
    <div class="alert alert-{$type}" role="alert">
        <span class="alert-icon">{$icon}</span>
        <div class="alert-content">{$message}</div>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="ปิด">×</button>
    </div>
HTML;
}

/**
 * Check if a user is currently logged in
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}

/**
 * Get the current logged in user array
 * @return array|null
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Check if the user has a specific role
 * @param string|array $roles
 * @return bool
 */
function has_role($roles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $currentRole = user_role();
    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }
    return $currentRole === $roles;
}

/**
 * Require user to be logged in; otherwise redirect to login page
 * @param string $redirect_to
 */
function require_login(string $redirect_to = 'login.php'): void {
    if (!is_logged_in()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        if (!empty($currentUri) && !str_contains($currentUri, 'login.php') && !str_contains($currentUri, 'logout.php')) {
            $redirect_to .= '?redirect=' . urlencode($currentUri);
        }
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งานหน้านี้');
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Require guest access (not logged in); redirect to dashboard if logged in
 * @param string $redirect_to
 */
function require_guest(string $redirect_to = 'dashboard.php'): void {
    if (is_logged_in()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Require specific user role
 * @param string|array $roles
 * @param string $redirect_to
 */
function require_role($roles, string $redirect_to = 'dashboard.php'): void {
    require_login();
    if (!has_role($roles)) {
        set_flash('danger', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Get client IP address
 * @return string
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Record action in audit_logs table
 * @param PDO $pdo
 * @param int|null $actor_user_id
 * @param string $action
 * @param string $entity_type
 * @param int|null $entity_id
 * @param array|null $details
 */
function log_audit(PDO $pdo, ?int $actor_user_id, string $action, string $entity_type, ?int $entity_id = null, ?array $details = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, ip_address, created_at)
            VALUES (:actor, :action, :entity_type, :entity_id, :details, :ip, NOW())
        ");
        $stmt->execute([
            ':actor' => $actor_user_id,
            ':action' => $action,
            ':entity_type' => $entity_type,
            ':entity_id' => $entity_id,
            ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            ':ip' => get_client_ip()
        ]);
    } catch (PDOException $e) {
        // Silently fail or log to error log to avoid breaking the user workflow
        error_log('Audit log failure: ' . $e->getMessage());
    }
}

/**
 * Generate a unique Hospital Number (HN) for patients
 * Format: HN-YY-XXXXX (e.g. HN-26-10492)
 * @param PDO $pdo
 * @return string
 */
function generate_patient_number(PDO $pdo): string {
    $yearPrefix = date('y'); // 26 for 2026
    
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $randomSeq = str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $patientNumber = "HN-{$yearPrefix}-{$randomSeq}";
        
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE patient_number = :num LIMIT 1");
        $stmt->execute([':num' => $patientNumber]);
        if (!$stmt->fetch()) {
            return $patientNumber;
        }
    }
    
    // Fallback if collisions occur
    return 'HN-' . $yearPrefix . '-' . time();
}

/**
 * Translates role code into friendly Thai title
 * @param string|null $role
 * @return string
 */
function get_role_label(?string $role): string {
    $map = [
        'patient' => 'ผู้รับบริการ (คนไข้)',
        'staff' => 'เจ้าหน้าที่โรงพยาบาล',
        'admin' => 'ผู้ดูแลระบบ (Admin)',
        'executive' => 'ผู้บริหาร'
    ];
    return $map[$role] ?? 'ผู้ใช้งานทั่วไป';
}
