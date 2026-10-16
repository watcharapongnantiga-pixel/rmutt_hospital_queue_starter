<?php
/**
 * Database connection settings for local development.
 * Change credentials before deploying to any public server.
 */
$host = '127.0.0.1';
$dbname = 'rmutt_hospital_queue';
$username = 'root';
$password = ''; // Default XAMPP local setup often uses an empty password.
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);

    // If running in CLI, output plain text
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
        exit(1);
    }

    // Friendly, helpful web UI error card
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>การเชื่อมต่อฐานข้อมูลขัดข้อง | RMUTT Hospital Queue</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
      <style>
        body {
          margin: 0;
          font-family: 'IBM Plex Sans Thai', sans-serif;
          background: #0d213a;
          color: #e2ecf9;
          min-height: 100vh;
          display: grid;
          place-items: center;
          padding: 24px;
          box-sizing: border-box;
        }
        .db-error-card {
          max-width: 620px;
          width: 100%;
          background: #ffffff;
          color: #172b49;
          border-radius: 20px;
          padding: 36px 32px;
          box-shadow: 0 30px 80px rgba(0,0,0,0.35);
        }
        .db-header {
          display: flex;
          align-items: center;
          gap: 16px;
          margin-bottom: 20px;
        }
        .db-icon {
          width: 50px;
          height: 50px;
          background: #fee2e2;
          color: #dc2626;
          border-radius: 14px;
          display: grid;
          place-items: center;
          font-size: 26px;
          font-weight: bold;
        }
        h1 {
          font-size: 22px;
          margin: 0;
          color: #0f1f38;
        }
        p {
          font-size: 14px;
          color: #556987;
          line-height: 1.7;
          margin: 0 0 18px;
        }
        .steps-box {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 12px;
          padding: 18px 20px;
          margin-bottom: 22px;
        }
        .steps-box h3 {
          margin: 0 0 10px;
          font-size: 15px;
          color: #1e293b;
        }
        .steps-box ol {
          margin: 0;
          padding-left: 20px;
          color: #475569;
          font-size: 13px;
          line-height: 1.9;
        }
        .steps-box code {
          background: #e2e8f0;
          color: #0f172a;
          padding: 2px 6px;
          border-radius: 4px;
          font-size: 12px;
        }
        .btn-retry {
          display: inline-block;
          background: #2563eb;
          color: #ffffff;
          padding: 12px 24px;
          border-radius: 10px;
          text-decoration: none;
          font-weight: 600;
          font-size: 14px;
          transition: background 0.2s;
        }
        .btn-retry:hover {
          background: #1d4ed8;
        }
      </style>
    </head>
    <body>
      <div class="db-error-card">
        <div class="db-header">
          <div class="db-icon">!</div>
          <div>
            <h1>ไม่สามารถเชื่อมต่อฐานข้อมูลได้</h1>
            <p style="margin: 4px 0 0; font-size: 12px; color: #8292a8;">RMUTT Hospital Queue System</p>
          </div>
        </div>
        <p>ระบบไม่สามารถเชื่อมต่อไปยัง MySQL Server ได้ กรุณาตรวจสอบว่า Service ของ MySQL บนเครื่องของคุณทำงานอยู่ และได้นำเข้าไฟล์ฐานข้อมูลแล้ว</p>
        
        <div class="steps-box">
          <h3>ขั้นตอนการแก้ไข:</h3>
          <ol>
            <li>เปิด <strong>XAMPP Control Panel</strong> แล้วกดปุ่ม <strong>Start</strong> ที่ <code>MySQL</code> (และ <code>Apache</code>)</li>
            <li>เปิดเบราว์เซอร์ไปที่ <code>http://localhost/phpmyadmin</code></li>
            <li>สร้างฐานข้อมูลชื่อ <code>rmutt_hospital_queue</code> หรือกดแท็บ <strong>Import</strong></li>
            <li>เลือกไฟล์ <code>database/schema.sql</code> แล้วกดปุ่ม <strong>Import / Go</strong></li>
            <li>ตรวจสอบค่าการเชื่อมต่อใน <code>config/database.php</code></li>
          </ol>
        </div>

        <a href="" class="btn-retry" onclick="location.reload(); return false;">ลองเชื่อมต่อใหม่อีกครั้ง ↻</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}
