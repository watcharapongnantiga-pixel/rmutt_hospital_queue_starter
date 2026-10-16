/**
 * RMUTT Hospital Queue - Client-Side Database & State Management (HTML / localStorage)
 * Emulates full server-side database without needing PHP or MySQL!
 */

const AppDB = (function() {
  'use strict';

  const STORAGE_KEYS = {
    USERS: 'rmutt_users',
    CURRENT_USER: 'rmutt_current_user',
    DEPARTMENTS: 'rmutt_departments',
    SCHEDULES: 'rmutt_schedules',
    APPOINTMENTS: 'rmutt_appointments',
    QUEUES: 'rmutt_queues',
    FLASH: 'rmutt_flash'
  };

  // Helper to read / write
  function get(key, defaultVal = []) {
    try {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : defaultVal;
    } catch (e) {
      console.error('Storage read error:', e);
      return defaultVal;
    }
  }

  function set(key, val) {
    try {
      localStorage.setItem(key, JSON.stringify(val));
      // Dispatch custom event for same-window updates
      window.dispatchEvent(new CustomEvent('app_db_update', { detail: { key, val } }));
    } catch (e) {
      console.error('Storage write error:', e);
    }
  }

  // Generate HN Number: HN-26-XXXXX
  function generateHN() {
    const year = '26';
    const rand = String(Math.floor(Math.random() * 90000) + 10000);
    return `HN-${year}-${rand}`;
  }

  // Generate Booking Code: BK26-XXXXXX
  function generateBookingCode() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 6; i++) {
      code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return `BK26-${code}`;
  }

  // Format Date YYYY-MM-DD
  function formatDate(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  // Initialize Default Data
  function init() {
    // 1. Departments
    if (!localStorage.getItem(STORAGE_KEYS.DEPARTMENTS)) {
      const defaultDepts = [
        {
          id: 1,
          code: 'A',
          name: 'เวชปฏิบัติทั่วไป (General Medicine)',
          description: 'บริการตรวจรักษาโรคทั่วไป ให้คำปรึกษาและคัดกรองเบื้องต้น',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 1 ห้อง 101'
        },
        {
          id: 2,
          code: 'B',
          name: 'ทันตกรรม (Dental Clinic)',
          description: 'บริการตรวจสุขภาพฟัน อุดฟัน ขูดหินปูน และทันตกรรมทั่วไป',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 2 ห้อง 204'
        },
        {
          id: 3,
          code: 'C',
          name: 'กายภาพบำบัด (Physical Therapy)',
          description: 'ฟื้นฟูสมรรถภาพทางกายภาพ กล้ามเนื้อ ข้อต่อ และกระดูก',
          location: 'อาคารบริการสุขภาพ ชั้น 1 ห้อง 110'
        },
        {
          id: 4,
          code: 'D',
          name: 'จักษุวิทยา (Eye Clinic)',
          description: 'บริการตรวจวัดสายตาและโรคตาเบื้องต้น',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 2 ห้อง 208'
        }
      ];
      set(STORAGE_KEYS.DEPARTMENTS, defaultDepts);
    }

    // 2. Demo Users
    if (!localStorage.getItem(STORAGE_KEYS.USERS)) {
      const defaultUsers = [
        {
          id: 1,
          full_name: 'นายสมชาย สุขสมบูรณ์',
          email: 'patient@hospital.com',
          password: 'patient1234',
          role: 'patient',
          hn: 'HN-26-00101',
          phone: '0891234567',
          dob: '1995-05-15',
          created_at: new Date().toISOString()
        },
        {
          id: 2,
          full_name: 'นพ. วิทยา รักษาดี (แพทย์)',
          email: 'staff@hospital.com',
          password: 'staff1234',
          role: 'staff',
          hn: null,
          phone: '0819876543',
          created_at: new Date().toISOString()
        },
        {
          id: 3,
          full_name: 'ผู้ดูแลระบบ ไอที',
          email: 'admin@hospital.com',
          password: 'admin1234',
          role: 'admin',
          hn: null,
          phone: '0801112222',
          created_at: new Date().toISOString()
        }
      ];
      set(STORAGE_KEYS.USERS, defaultUsers);
    }

    // 3. Schedules (Next 14 Days)
    if (!localStorage.getItem(STORAGE_KEYS.SCHEDULES)) {
      const depts = get(STORAGE_KEYS.DEPARTMENTS);
      const schedules = [];
      let schedId = 1;

      for (let dayOffset = 0; dayOffset < 14; dayOffset++) {
        const d = new Date();
        d.setDate(d.getDate() + dayOffset);
        // Skip Sunday
        if (d.getDay() === 0) continue;

        const dateStr = formatDate(d);

        depts.forEach(dept => {
          // Morning slot
          schedules.push({
            id: schedId++,
            department_id: dept.id,
            service_date: dateStr,
            start_time: '09:00',
            end_time: '12:00',
            capacity: 15,
            booked_count: (dayOffset === 0 && dept.id === 1) ? 2 : 0,
            is_open: true
          });
          // Afternoon slot
          schedules.push({
            id: schedId++,
            department_id: dept.id,
            service_date: dateStr,
            start_time: '13:00',
            end_time: '16:00',
            capacity: 15,
            booked_count: 0,
            is_open: true
          });
        });
      }
      set(STORAGE_KEYS.SCHEDULES, schedules);
    }

    // 4. Starter Appointments & Queues for Today
    if (!localStorage.getItem(STORAGE_KEYS.APPOINTMENTS) || !localStorage.getItem(STORAGE_KEYS.QUEUES)) {
      const today = formatDate(new Date());
      const starterAppointments = [
        {
          id: 1,
          booking_code: 'BK26-DEMO01',
          user_id: 1,
          full_name: 'นายสมชาย สุขสมบูรณ์',
          hn: 'HN-26-00101',
          phone: '0891234567',
          department_id: 1,
          department_name: 'เวชปฏิบัติทั่วไป (General Medicine)',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 1 ห้อง 101',
          schedule_id: 1,
          service_date: today,
          start_time: '09:00',
          end_time: '12:00',
          status: 'waiting',
          notes: 'ปวดศีรษะ เป็นหวัด มีไข้เล็กน้อย',
          created_at: new Date().toISOString()
        },
        {
          id: 2,
          booking_code: 'BK26-DEMO02',
          user_id: 99,
          full_name: 'นางสาวพิมลดา สดใส',
          hn: 'HN-26-00102',
          phone: '0854443322',
          department_id: 1,
          department_name: 'เวชปฏิบัติทั่วไป (General Medicine)',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 1 ห้อง 101',
          schedule_id: 1,
          service_date: today,
          start_time: '09:00',
          end_time: '12:00',
          status: 'waiting',
          notes: 'ตรวจสุขภาพประจำปี',
          created_at: new Date().toISOString()
        }
      ];

      const starterQueues = [
        {
          id: 1,
          appointment_id: 1,
          booking_code: 'BK26-DEMO01',
          queue_number: 'A001',
          department_id: 1,
          department_name: 'เวชปฏิบัติทั่วไป (General Medicine)',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 1 ห้อง 101',
          user_id: 1,
          full_name: 'นายสมชาย สุขสมบูรณ์',
          hn: 'HN-26-00101',
          phone: '0891234567',
          service_date: today,
          queue_status: 'waiting', // waiting, called, serving, completed, skipped, cancelled
          called_at: null,
          completed_at: null,
          created_at: new Date().toISOString()
        },
        {
          id: 2,
          appointment_id: 2,
          booking_code: 'BK26-DEMO02',
          queue_number: 'A002',
          department_id: 1,
          department_name: 'เวชปฏิบัติทั่วไป (General Medicine)',
          location: 'อาคารเฉลิมพระเกียรติ ชั้น 1 ห้อง 101',
          user_id: 99,
          full_name: 'นางสาวพิมลดา สดใส',
          hn: 'HN-26-00102',
          phone: '0854443322',
          service_date: today,
          queue_status: 'waiting',
          called_at: null,
          completed_at: null,
          created_at: new Date().toISOString()
        }
      ];

      set(STORAGE_KEYS.APPOINTMENTS, starterAppointments);
      set(STORAGE_KEYS.QUEUES, starterQueues);
    }
  }

  // Call init
  init();

  return {
    // Current User Session
    getCurrentUser: function() {
      return get(STORAGE_KEYS.CURRENT_USER, null);
    },
    setCurrentUser: function(user) {
      set(STORAGE_KEYS.CURRENT_USER, user);
    },
    logout: function() {
      localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
      this.setFlash('info', 'คุณได้ออกจากระบบเรียบร้อยแล้ว');
      window.location.href = 'login.html';
    },

    // Flash Alerts
    setFlash: function(type, message) {
      localStorage.setItem(STORAGE_KEYS.FLASH, JSON.stringify({ type, message }));
    },
    getFlash: function() {
      const flash = localStorage.getItem(STORAGE_KEYS.FLASH);
      if (flash) {
        localStorage.removeItem(STORAGE_KEYS.FLASH);
        try { return JSON.parse(flash); } catch(e) { return null; }
      }
      return null;
    },
    renderFlash: function() {
      const flash = this.getFlash();
      if (!flash) return '';
      const icons = { success: '✓', danger: '✕', warning: '⚠', info: 'ℹ' };
      const icon = icons[flash.type] || 'ℹ';
      return `
        <div class="alert alert-${flash.type}" role="alert">
          <span class="alert-icon">${icon}</span>
          <div class="alert-content">${flash.message}</div>
          <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="ปิด">×</button>
        </div>
      `;
    },

    // Access Guards
    requireLogin: function(redirectTo = 'login.html') {
      const user = this.getCurrentUser();
      if (!user) {
        this.setFlash('warning', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งานหน้านี้');
        window.location.href = redirectTo;
        return null;
      }
      return user;
    },
    requireGuest: function(redirectTo = 'dashboard.html') {
      const user = this.getCurrentUser();
      if (user) {
        window.location.href = redirectTo;
        return true;
      }
      return false;
    },

    // Users
    getUsers: function() {
      return get(STORAGE_KEYS.USERS, []);
    },
    register: function(data) {
      const users = this.getUsers();
      // Check existing email
      const exists = users.find(u => u.email.toLowerCase() === data.email.toLowerCase());
      if (exists) {
        throw new Error('อีเมลนี้ถูกใช้งานแล้วในระบบ');
      }

      const hn = generateHN();
      const newUser = {
        id: Date.now(),
        full_name: data.full_name,
        email: data.email,
        password: data.password,
        role: 'patient',
        hn: hn,
        phone: data.phone,
        dob: data.dob || null,
        created_at: new Date().toISOString()
      };

      users.push(newUser);
      set(STORAGE_KEYS.USERS, users);
      this.setCurrentUser(newUser);
      return newUser;
    },
    login: function(email, password) {
      const users = this.getUsers();
      const user = users.find(u => u.email.toLowerCase() === email.toLowerCase() && u.password === password);
      if (!user) {
        throw new Error('อีเมลหรือรหัสผ่านไม่ถูกต้อง');
      }
      this.setCurrentUser(user);
      return user;
    },

    // Departments & Schedules
    getDepartments: function() {
      return get(STORAGE_KEYS.DEPARTMENTS, []);
    },
    getSchedules: function(deptId, dateStr) {
      const all = get(STORAGE_KEYS.SCHEDULES, []);
      return all.filter(s => s.department_id === Number(deptId) && s.service_date === dateStr && s.is_open);
    },

    // Booking & Queue Creation
    bookAppointment: function({ departmentId, dateStr, scheduleId, notes }) {
      const user = this.getCurrentUser();
      if (!user) throw new Error('กรุณาเข้าสู่ระบบก่อนจองคิว');

      const depts = this.getDepartments();
      const dept = depts.find(d => d.id === Number(departmentId));
      if (!dept) throw new Error('ไม่พบข้อมูลแผนก');

      const schedules = get(STORAGE_KEYS.SCHEDULES);
      const sched = schedules.find(s => s.id === Number(scheduleId));
      if (!sched) throw new Error('ไม่พบข้อมูลรอบเวลา');
      if (sched.booked_count >= sched.capacity) {
        throw new Error('ขออภัย รอบเวลานี้มีผู้จองเต็มแล้ว');
      }

      // 1. Increment booked count
      sched.booked_count += 1;
      set(STORAGE_KEYS.SCHEDULES, schedules);

      // 2. Booking Code & Next Queue Number
      const bookingCode = generateBookingCode();
      const queues = get(STORAGE_KEYS.QUEUES, []);

      // Calculate next queue number for this department and date
      const deptPrefix = dept.code || 'A';
      const todayDeptQueues = queues.filter(q => q.service_date === dateStr && q.queue_number.startsWith(deptPrefix));
      const nextNum = todayDeptQueues.length + 1;
      const queueNumber = `${deptPrefix}${String(nextNum).padStart(3, '0')}`;

      const appointmentId = Date.now();
      const newAppt = {
        id: appointmentId,
        booking_code: bookingCode,
        user_id: user.id,
        full_name: user.full_name,
        hn: user.hn || generateHN(),
        phone: user.phone || '',
        department_id: dept.id,
        department_name: dept.name,
        location: dept.location,
        schedule_id: sched.id,
        service_date: dateStr,
        start_time: sched.start_time,
        end_time: sched.end_time,
        status: 'waiting',
        notes: notes || '',
        created_at: new Date().toISOString()
      };

      const newQueue = {
        id: Date.now() + 1,
        appointment_id: appointmentId,
        booking_code: bookingCode,
        queue_number: queueNumber,
        department_id: dept.id,
        department_name: dept.name,
        location: dept.location,
        user_id: user.id,
        full_name: user.full_name,
        hn: newAppt.hn,
        phone: user.phone || '',
        service_date: dateStr,
        queue_status: 'waiting',
        called_at: null,
        completed_at: null,
        created_at: new Date().toISOString()
      };

      const appointments = get(STORAGE_KEYS.APPOINTMENTS, []);
      appointments.unshift(newAppt);
      queues.push(newQueue);

      set(STORAGE_KEYS.APPOINTMENTS, appointments);
      set(STORAGE_KEYS.QUEUES, queues);

      return { bookingCode, queueNumber, newAppt, newQueue };
    },

    // Read Appointments & Queues
    getUserAppointments: function(userId) {
      const allAppts = get(STORAGE_KEYS.APPOINTMENTS, []);
      const allQueues = get(STORAGE_KEYS.QUEUES, []);
      const userAppts = allAppts.filter(a => a.user_id === userId);

      return userAppts.map(appt => {
        const q = allQueues.find(queue => queue.appointment_id === appt.id);
        return {
          ...appt,
          queue_number: q ? q.queue_number : '—',
          queue_status: q ? q.queue_status : appt.status
        };
      });
    },
    getAppointmentByCode: function(code) {
      const allAppts = get(STORAGE_KEYS.APPOINTMENTS, []);
      const allQueues = get(STORAGE_KEYS.QUEUES, []);
      const appt = allAppts.find(a => a.booking_code === code);
      if (!appt) return null;

      const q = allQueues.find(queue => queue.appointment_id === appt.id);
      return {
        ...appt,
        queue_number: q ? q.queue_number : '—',
        queue_status: q ? q.queue_status : appt.status,
        called_at: q ? q.called_at : null
      };
    },

    // Staff Actions: Update Queue Status
    getQueues: function(deptId = 0, dateStr = null) {
      const queues = get(STORAGE_KEYS.QUEUES, []);
      const targetDate = dateStr || formatDate(new Date());

      return queues.filter(q => {
        const matchDate = (q.service_date === targetDate);
        const matchDept = (Number(deptId) === 0 || q.department_id === Number(deptId));
        return matchDate && matchDept;
      });
    },
    updateQueueStatus: function(queueId, newStatus) {
      const queues = get(STORAGE_KEYS.QUEUES, []);
      const q = queues.find(item => item.id === Number(queueId));
      if (!q) throw new Error('ไม่พบข้อมูลคิว');

      q.queue_status = newStatus;
      if (newStatus === 'called') {
        q.called_at = new Date().toISOString();
      } else if (newStatus === 'completed') {
        q.completed_at = new Date().toISOString();
      }

      // Also sync appointment status
      const appts = get(STORAGE_KEYS.APPOINTMENTS, []);
      const appt = appts.find(a => a.id === q.appointment_id);
      if (appt) {
        appt.status = newStatus;
        set(STORAGE_KEYS.APPOINTMENTS, appts);
      }

      set(STORAGE_KEYS.QUEUES, queues);
      return q;
    },

    getRoleLabel: function(role) {
      const map = {
        patient: 'ผู้รับบริการ (คนไข้)',
        staff: 'เจ้าหน้าที่โรงพยาบาล',
        admin: 'ผู้ดูแลระบบ (Admin)'
      };
      return map[role] || 'ผู้ใช้งานทั่วไป';
    },

    todayString: function() {
      return formatDate(new Date());
    }
  };
})();
