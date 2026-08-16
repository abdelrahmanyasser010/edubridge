# 📋 EduBridge — Ultimate System Blueprint (API & Backend Logic)

> **التطبيقات المدعومة:** تطبيق المعلم (Teacher) + تطبيق ولي الأمر والطالب (Parent/Student)  
> **Headers الأساسية في كل الطلبات:**
> - `Authorization: Bearer {jwt_token}`
> - `Accept-Language: ar` (لإرجاع رسائل الخطأ والنصوص بالعربية)
> - `X-Platform: android | ios`
> - `X-App-Type: teacher | parent | student`

---

## 🔐 Feature 1: Authentication & User Profile (التوثيق والملف الشخصي)

### 🔄 كواليس الباك إند (Backend Logic)
- التحقق من التشفير (Bcrypt). إنشاء JWT يحتوي على (`user_id`, `role`). 
- حفظ الـ `Refresh Token` في جدول `user_sessions`. 
- استرجاع الـ `fcm_token` وربطه بحساب المستخدم لتصل له الإشعارات (Push Notifications).

### 🌐 APIs المرتبطة

#### 1. تسجيل الدخول (Login)
- **Endpoint:** `POST /api/auth/login`
- **Request Body:**
  ```json
  {
    "identifier": "ahmed@edubridge.com", 
    "password": "secure_password_123",
    "role": "teacher",
    "fcm_token": "device_token_xyz"
  }
  ```
- **Response (200 OK):**
  ```json
  {
    "status": "success",
    "data": {
      "access_token": "eyJhbGciOi...",
      "refresh_token": "def50200...",
      "user": {
        "id": 105,
        "name": "الأستاذ أحمد",
        "email": "ahmed@edubridge.com",
        "role": "teacher",
        "avatar_url": "https://s3.bucket.com/avatar.jpg"
      }
    }
  }
  ```

#### 2. جلب الملف الشخصي (Get Profile)
- **Endpoint:** `GET /api/profile`
- **Response (200 OK):**
  ```json
  {
    "data": {
      "id": 105,
      "name": "الأستاذ أحمد",
      "phone": "+966500000000",
      "language": "ar"
    }
  }
  ```

---

## ✅ Feature 2: Attendance System (نظام الحضور والغياب)

### 🔄 كواليس الباك إند (Backend Logic)
- عندما يطلب المعلم قائمة الطلاب، يقوم الباك إند بـ `Join` بين جدول `sessions` وجدول `students` بناءً على الـ `class_id`.
- عند إرسال الغياب، يتم حفظ الريكوردات في `attendance_records`.
- **Notification Trigger:** الباك إند يبحث عن الطلاب الذين تم وضعهم كـ (غائب/متأخر)، ويجلب الـ `fcm_token` لأولياء أمورهم، ويرسل إشعار Push فوراً.

### 🌐 APIs المرتبطة

#### 1. جلب قائمة الطلاب للحصة (Fetch Students for Session) - [خاص بالمعلم]
- **Endpoint:** `GET /api/attendance/session/{sessionId}/students`
- **Response (200 OK):**
  ```json
  {
    "session_id": 8042,
    "class_name": "الصف الخامس - أ",
    "data": [
      {
        "student_id": 1001,
        "name": "أحمد محمد العتيبي",
        "avatar_url": "https://i.pravatar.cc/150?u=1",
        "default_status": "present"
      },
      {
        "student_id": 1002,
        "name": "سارة علي الشمري",
        "avatar_url": "https://i.pravatar.cc/150?u=2",
        "default_status": "present"
      }
    ]
  }
  ```

#### 2. إرسال واعتماد الغياب (Submit Attendance) - [خاص بالمعلم]
- **Endpoint:** `POST /api/attendance/submit`
- **Request Body:**
  ```json
  {
    "session_id": 8042,
    "class_id": 101,
    "date": "2026-07-11",
    "notify_parents": true,
    "records": [
      { "student_id": 1001, "status": "present" },
      { "student_id": 1002, "status": "absent" }
    ]
  }
  ```
- **Response (200 OK):**
  ```json
  {
    "status": "success",
    "message": "تم اعتماد الغياب بنجاح وإشعار أولياء الأمور.",
    "data": { "notified_parents_count": 1 }
  }
  ```

#### 3. استعراض سجل الغياب المفصل (View Detailed Attendance) - [خاص بولي الأمر]
- **Endpoint:** `GET /api/student/{studentId}/attendance`
- **Response (200 OK):**
  ```json
  {
    "summary": {
      "present": 42,
      "absent": 2,
      "late": 1,
      "attendance_percentage": 93.3
    },
    "records": [
      { "date": "2026-07-11", "day": "الجمعة", "status": "absent", "session_name": "الرياضيات" },
      { "date": "2026-07-10", "day": "الخميس", "status": "present", "session_name": "العلوم" }
    ]
  }
  ```

---

## 🏫 Feature 3: Classes & Schedule (الفصول والجدول الدراسي)

### 🔄 كواليس الباك إند (Backend Logic)
- لجلب الفصول، يتم فلترة جدول `classes` بناءً على `teacher_id`.
- لمعرفة الفصل "الحالي" (Active Session)، يقارن الباك إند وقت الخادم الحالي `Current Time` بأوقات الحصص `start_time` و `end_time` في جدول `schedule`.

### 🌐 APIs المرتبطة

#### 1. جلب فصول المعلم (My Classes)
- **Endpoint:** `GET /api/teacher/classes`
- **Response (200 OK):**
  ```json
  {
    "data": [
      {
        "class_id": 101,
        "name": "الصف الخامس - أ",
        "student_count": 24,
        "subject": "اللغة العربية"
      }
    ]
  }
  ```

#### 2. جلب الحصة الجارية حالياً (Current Active Session)
- **Endpoint:** `GET /api/teacher/schedule/current-session`
- **Response (200 OK):**
  ```json
  {
    "data": {
      "session_id": 8042,
      "class_id": 101,
      "class_name": "الصف الخامس - أ",
      "subject": "اللغة العربية",
      "start_time": "08:00",
      "end_time": "08:45"
    }
  }
  ```

---

## 🧠 Feature 4: Behavior Notes & Chat (الملاحظات السلوكية)

### 🔄 كواليس الباك إند (Backend Logic)
- عند `POST /behavior-notes`: يُضاف سجل. الباك إند يولد توصيات من جدول `recommendations_dictionary` بناءً على الـ `severity` ونوع السلوك. يُرسل إشعار لولي الأمر.
- عند ضغط ولي الأمر "إقراء"، يُحدث حقل `acknowledged_at` وتُسجل الحركة في `note_timeline`.

### 🌐 APIs المرتبطة

#### 1. جلب الملاحظات السلوكية للطالب - [خاص بولي الأمر]
- **Endpoint:** `GET /api/behavior-notes/student/{studentId}`
- **Response (200 OK):**
  ```json
  {
    "data": [
      {
        "id": 450,
        "title": "ألفاظ غير مؤدبة",
        "severity": "high",
        "status": "open",
        "date": "2026-07-11"
      }
    ]
  }
  ```

#### 2. جلب تفاصيل الملاحظة (Note Details & Recommendations)
- **Endpoint:** `GET /api/behavior-notes/{noteId}`
- **Response (200 OK):**
  ```json
  {
    "id": 450,
    "title": "ألفاظ غير مؤدبة",
    "description": "قام الطالب بالتلفظ...",
    "added_by": { "name": "الأستاذة ليلى", "role": "مشرفة الساحة" },
    "timeline": [
      { "event": "تمت إضافة الملاحظة", "date": "2026-07-11" }
    ],
    "recommendations": [
      { "title": "كيف أساعد طفلي؟", "has_video": true, "url": "https://..." }
    ]
  }
  ```

#### 3. إضافة ملاحظة (Create Behavior Note) - [خاص بالمعلم]
- **Endpoint:** `POST /api/behavior-notes`
- **Request Body:**
  ```json
  {
    "student_id": 1005,
    "session_id": 8042,
    "title": "شغف زائد بكرة القدم",
    "description": "يشتت انتباهه وانتباه زملائه",
    "severity": "medium"
  }
  ```
- **Response (201 Created):** `{"status": "success", "note_id": 451}`

#### 4. تأكيد الاطلاع (Acknowledge) - [خاص بولي الأمر]
- **Endpoint:** `POST /api/behavior-notes/{noteId}/acknowledge`
- **Request Body:** `{"acknowledged": true}`
- **Response (200 OK):** `{"status": "success"}`

#### 5. إرسال رسالة في المحادثة (Send Message)
- **Endpoint:** `POST /api/chats/{chatId}/messages`
- **Request Body:** `{"message": "سأتحدث معه."}`
- **Response (201 Created):** `{"message_id": 9921}`

---

## 📚 Feature 5: Assignments (المهام والواجبات)

### 🔄 كواليس الباك إند (Backend Logic)
- يرفع المرفقات إلى (S3).
- يضع `is_urgent = true` إذا كان متبقي على التسليم أقل من 48 ساعة.

### 🌐 APIs المرتبطة

#### 1. إنشاء واجب (Create Assignment) - [المعلم]
- **Endpoint:** `POST /api/assignments`
- **Request Body:**
  ```json
  {
    "class_id": 101,
    "title": "حل تمارين صفحة 45",
    "due_date": "2026-07-12T23:59:59Z",
    "attachments": ["https://s3.edubridge.com/files/math_hw.pdf"],
    "notify_parents": true
  }
  ```

#### 2. جلب واجبات الطالب (Get Assignments) - [ولي الأمر]
- **Endpoint:** `GET /api/student/{studentId}/assignments`
- **Response (200 OK):**
  ```json
  {
    "data": [
      {
        "id": 772,
        "subject": "الرياضيات",
        "title": "حل تمارين صفحة 45",
        "due_date": "2026-07-12T23:59:59Z",
        "is_urgent": true,
        "status": "pending"
      }
    ]
  }
  ```

---

## 🎓 Feature 6: Grades & Exams (الدرجات)

### 🔄 كواليس الباك إند (Backend Logic)
- الـ Bulk Insert يضمن سرعة إدخال بيانات فصل كامل.
- عند إدخال درجة، الباك إند يعيد حساب الـ `Overall Average` للطالب.

### 🌐 APIs المرتبطة

#### 1. رصد الدرجات (Bulk Grade Entry) - [المعلم]
- **Endpoint:** `POST /api/exams/{examId}/grades`
- **Request Body:**
  ```json
  {
    "grades": [
      { "student_id": 1001, "score": 95, "feedback": "ممتاز" }
    ]
  }
  ```

#### 2. جلب درجات الطالب (Get Grades) - [ولي الأمر]
- **Endpoint:** `GET /api/student/{studentId}/grades`
- **Response (200 OK):**
  ```json
  {
    "overall_avg": 88,
    "grades": [
      { "subject": "الرياضيات", "mid_term": 85, "final": 90, "total": 88 }
    ]
  }
  ```

---

## 💰 Feature 7: Wallet & Payments (المقصف والرسوم)

### 🔄 كواليس الباك إند (Backend Logic)
- **QR Token:** يُولد مشفراً `EDU_QR_...` بصلاحية 5 دقائق.
- **Deduct:** يتم عمل Transaction في الـ Database للتحقق من الرصيد والخصم في خطوة واحدة لمنع الأخطاء.

### 🌐 APIs المرتبطة

#### 1. جلب رصيد المحفظة والـ QR (Wallet Info) - [الطالب/ولي الأمر]
- **Endpoint:** `GET /api/wallet/{studentId}/info`
- **Response (200 OK):**
  ```json
  {
    "balance": 150.00,
    "currency": "SAR",
    "qr_token": "EDU_QR_eyJhbGci_ValidFor5Mins"
  }
  ```

#### 2. الدفع في المقصف (POS Endpoint)
- **Endpoint:** `POST /api/wallet/pos/deduct`
- **Request Body:** `{"qr_token": "EDU_QR...", "amount": 15.00}`
- **Response (200 OK):** `{"status": "success", "remaining_balance": 135.00}`

#### 3. الرسوم الدراسية (School Fees) - [ولي الأمر]
- **Endpoint:** `GET /api/payments/student/{studentId}/fees`
- **Response (200 OK):**
  ```json
  {
    "data": [
      { "fee_id": 880, "title": "القسط الدراسي الأول", "amount": 2500.00, "status": "unpaid" }
    ]
  }
  ```

---

## 🚌 Feature 8: Bus Tracking (تتبع الحافلة)

### 🌐 APIs المرتبطة
#### 1. تتبع الحافلة (Track Bus) - [ولي الأمر]
- **Endpoint:** `GET /api/bus/student/{studentId}/location`
- **Response (200 OK):**
  ```json
  {
    "lat": 24.7136,
    "lng": 46.6753,
    "speed_kmh": 40,
    "eta_minutes": 15
  }
  ```

---

## 🔔 Feature 9: Notifications (الإشعارات)

### 🌐 APIs المرتبطة
#### 1. جلب الإشعارات (Fetch Notifications)
- **Endpoint:** `GET /api/notifications`
- **Response (200 OK):**
  ```json
  {
    "unread_count": 1,
    "data": [
      {
        "id": 901,
        "type": "attendance_absent",
        "title": "إشعار غياب",
        "body": "تم تسجيل غياب لابنك في الحصة الأولى.",
        "is_read": false
      }
    ]
  }
  ```
