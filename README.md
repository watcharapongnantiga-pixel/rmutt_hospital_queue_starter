# RMUTT Hospital Queue — Starter Project

ต้นแบบเว็บไซต์ระบบจองคิว พัฒนาด้วย PHP, MySQL, HTML, CSS และ JavaScript สำหรับรันบน XAMPP

> ข้อสำคัญ: นี่เป็นโปรเจกต์ต้นแบบ ไม่ใช่เว็บไซต์ทางการของโรงพยาบาล และยังไม่ควรใช้จัดเก็บข้อมูลผู้ป่วยจริง จนกว่าจะพัฒนาระบบความปลอดภัย ทดสอบ และได้รับอนุญาตจากหน่วยงานที่เกี่ยวข้อง

## สิ่งที่มีในชุดเริ่มต้น
- หน้าแรก Responsive โทน Modern Premium สีน้ำเงินเข้ม
- เมนูมือถือ
- โครงสร้างเชื่อมต่อฐานข้อมูลด้วย PDO
- SQL schema เริ่มต้นสำหรับ users, patients, departments, schedules, appointments, queues, notifications และ audit_logs

หน้า Login/Register ในหน้าแรกเป็นลิงก์เตรียมไว้สำหรับขั้นตอนถัดไป ยังไม่ได้สร้างระบบยืนยันตัวตนในชุดเริ่มต้นนี้

## วิธีติดตั้งบน XAMPP
1. แตก ZIP ไปไว้ที่ `C:\xampp\htdocs\rmutt_hospital_queue`
2. เปิด XAMPP Control Panel แล้ว Start `Apache` และ `MySQL`
3. เปิด `http://localhost/phpmyadmin`
4. เลือกแท็บ Import แล้วนำเข้าไฟล์ `database/schema.sql`
5. ตรวจสอบค่าเชื่อมต่อใน `config/database.php` ให้ตรงกับ MySQL ของเครื่อง
6. เปิด `http://localhost/rmutt_hospital_queue/`

## ขั้นตอนต่อไป
1. สร้างระบบสมัครสมาชิก/เข้าสู่ระบบอย่างปลอดภัยด้วย `password_hash()` และ `password_verify()`
2. เพิ่ม session security, CSRF protection และ role-based access control
3. สร้างหน้าจองคิว โดยตรวจสอบจำนวนที่นั่งใน transaction เพื่อป้องกันการจองเกิน
4. สร้างหน้าเจ้าหน้าที่เรียกคิวและบันทึก audit logs
5. เพิ่มการแจ้งเตือนหลังตั้งค่า LINE/SMS provider จริง
6. ทดสอบด้วยข้อมูลจำลองก่อนเสมอ

## ข้อควรทำก่อนใช้งานจริง
- ห้ามใช้ข้อมูลผู้ป่วยจริงในระหว่างพัฒนา
- เปลี่ยนรหัสผ่านฐานข้อมูลและปิดการแสดงข้อผิดพลาดใน production
- ใช้ HTTPS, จำกัดสิทธิ์ฐานข้อมูล, สำรองข้อมูล และบันทึกการเข้าถึง
- ตรวจสอบข้อกำหนด PDPA และแนวปฏิบัติด้านข้อมูลสุขภาพ
- ตรวจสอบชื่อแผนก ตารางให้บริการ และกระบวนการจริงกับโรงพยาบาล
