<?php
/**
 * Database Seed & Auto-Setup Script
 * RMUTT Hospital Queue System
 * Run via browser: http://localhost/rmutt_hospital_queue_starter/database/seed_data.php
 * or CLI: php database/seed_data.php
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = '127.0.0.1';
$dbname = 'rmutt_hospital_queue';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$isCli = (php_sapi_name() === 'cli');

function out(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        echo "[{$type}] {$msg}\n";
    } else {
        $color = $type === 'success' ? '#16a34a' : ($type === 'error' ? '#dc2626' : '#2563eb');
        echo "<div style='margin: 6px 0; font-family: monospace; color: {$color};'>[{$type}] {$msg}</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='th'><head><meta charset='utf-8'><title>Database Setup & Seed</title></head><body style='background:#0d213a; color:#f1f5f9; padding:30px; font-family: sans-serif;'><div style='max-width:700px; margin:auto; background:#1e293b; padding:24px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.5);'><h2>RMUTT Hospital Queue - Database Setup & Seed</h2><hr style='border:1px solid #334155; margin-bottom:20px;'>";
}

try {
    // 1. Connect without db name first to create DB if needed
    $pdoRoot = new PDO("mysql:host={$host};charset={$charset}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("Database `{$dbname}` checked/created successfully.", 'success');

    // 2. Connect to the database
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset={$charset}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 3. Execute schema.sql
    $schemaPath = __DIR__ . '/schema.sql';
    if (file_exists($schemaPath)) {
        $sql = file_get_contents($schemaPath);
        $pdo->exec($sql);
        out("Schema tables loaded successfully from schema.sql.", 'success');
    }

    // 4. Ensure Sample Departments
    $departments = [
        [
            'name' => 'เวชปฏิบัติทั่วไป (General Medicine)',
            'description' => 'บริการตรวจรักษาโรคทั่วไป ให้คำปรึกษาและคัดกรองเบื้องต้น',
            'location_label' => 'อาคารเฉลิมพระเกียรติ ชั้น 1 ห้อง 101'
        ],
        [
            'name' => 'ทันตกรรม (Dental Clinic)',
            'description' => 'บริการตรวจสุขภาพฟัน อุดฟัน ขูดหินปูน และทันตกรรมทั่วไป',
            'location_label' => 'อาคารเฉลิมพระเกียรติ ชั้น 2 ห้อง 204'
        ],
        [
            'name' => 'กายภาพบำบัด (Physical Therapy)',
            'description' => 'ฟื้นฟูสมรรถภาพทางกายภาพ กล้ามเนื้อ ข้อต่อ และกระดูก',
            'location_label' => 'อาคารบริการสุขภาพ ชั้น 1 ห้อง 110'
        ],
        [
            'name' => 'จักษุวิทยา (Ophthalmology)',
            'description' => 'บริการตรวจสายตาและโรคตาเบื้องต้น',
            'location_label' => 'อาคารเฉลิมพระเกียรติ ชั้น 2 ห้อง 208'
        ]
    ];

    foreach ($departments as $dept) {
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $dept['name']]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare("INSERT INTO departments (name, description, location_label, is_open_for_booking) VALUES (:name, :desc, :loc, 1)");
            $ins->execute([
                ':name' => $dept['name'],
                ':desc' => $dept['description'],
                ':loc' => $dept['location_label']
            ]);
            out("Created department: {$dept['name']}", 'success');
        }
    }

    // 5. Generate Schedules for next 14 days
    $allDepts = $pdo->query("SELECT id, name FROM departments WHERE is_open_for_booking = 1")->fetchAll();

    $timeSlots = [
        ['start' => '09:00:00', 'end' => '12:00:00', 'capacity' => 15],
        ['start' => '13:00:00', 'end' => '16:00:00', 'capacity' => 15]
    ];

    $scheduleCount = 0;
    $startDate = new DateTime(); // Today

    for ($day = 0; $day < 14; $day++) {
        $currentDate = clone $startDate;
        $currentDate->modify("+{$day} days");
        
        // Skip Sundays (optional, but keep Mondays-Saturdays open)
        if ($currentDate->format('w') == '0') {
            continue;
        }

        $dateStr = $currentDate->format('Y-m-d');

        foreach ($allDepts as $dept) {
            foreach ($timeSlots as $slot) {
                $chk = $pdo->prepare("SELECT id FROM schedules WHERE department_id = :dept_id AND service_date = :s_date AND start_time = :s_time LIMIT 1");
                $chk->execute([
                    ':dept_id' => $dept['id'],
                    ':s_date' => $dateStr,
                    ':s_time' => $slot['start']
                ]);

                if (!$chk->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open) VALUES (:dept_id, :s_date, :s_time, :e_time, :cap, 0, 1)");
                    $ins->execute([
                        ':dept_id' => $dept['id'],
                        ':s_date' => $dateStr,
                        ':s_time' => $slot['start'],
                        ':e_time' => $slot['end'],
                        ':cap' => $slot['capacity']
                    ]);
                    $scheduleCount++;
                }
            }
        }
    }
    out("Generated {$scheduleCount} new schedule slots across 14 days.", 'success');

    // 6. Ensure Demo Users exist
    $demoUsers = [
        [
            'full_name' => 'เจ้าหน้าที่ ประจำห้องตรวจ',
            'email' => 'staff@hospital.com',
            'password' => 'staff1234',
            'role' => 'staff'
        ],
        [
            'full_name' => 'ผู้ดูแลระบบ ไอที',
            'email' => 'admin@hospital.com',
            'password' => 'admin1234',
            'role' => 'admin'
        ],
        [
            'full_name' => 'นายสมชาย สุขสมบูรณ์',
            'email' => 'patient@hospital.com',
            'password' => 'patient1234',
            'role' => 'patient',
            'phone' => '0891234567',
            'dob' => '1995-05-15'
        ]
    ];

    foreach ($demoUsers as $u) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $chk->execute([':email' => $u['email']]);
        $existing = $chk->fetch();

        if (!$existing) {
            $hash = password_hash($u['password'], PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, is_active, created_at, updated_at) VALUES (:name, :email, :hash, :role, 1, NOW(), NOW())");
            $ins->execute([
                ':name' => $u['full_name'],
                ':email' => $u['email'],
                ':hash' => $hash,
                ':role' => $u['role']
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            if ($u['role'] === 'patient') {
                $hn = 'HN-' . date('y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
                $insPat = $pdo->prepare("INSERT INTO patients (user_id, patient_number, phone, date_of_birth, created_at) VALUES (:uid, :hn, :phone, :dob, NOW())");
                $insPat->execute([
                    ':uid' => $newUserId,
                    ':hn' => $hn,
                    ':phone' => $u['phone'],
                    ':dob' => $u['dob']
                ]);
            }

            out("Created demo user: {$u['email']} (Password: {$u['password']} | Role: {$u['role']})", 'success');
        } else {
            out("User already exists: {$u['email']}", 'info');
        }
    }

    out("Database setup and seeding completed successfully!", 'success');

} catch (Exception $e) {
    out("Error during setup: " . $e->getMessage(), 'error');
}

if (!$isCli) {
    echo "<div style='margin-top:25px;'><a href='../index.php' style='display:inline-block; background:#2563eb; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold;'>กลับสู่หน้าแรก (Go to Homepage) →</a></div></div></body></html>";
}
