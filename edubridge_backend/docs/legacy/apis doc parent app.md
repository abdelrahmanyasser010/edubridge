# دليل الهندسة البرمجية وتوصيف واجهات برمجة التطبيقات (API Specification Doc)
## تطبيق ولي الأمر والطالب الجوال (EduBridge Parent & Student Mobile App — Flutter)
**المشروع:** `edubridge` (Flutter Mobile App) ↔ `edubridge_backend` (Laravel 11 REST API)  
**الإصدار:** 2.5.0 — الوثيقة الرسمية الشاملة لبناء الباك إند  
**تاريخ الاعتماد:** 2026-07-12

---

## 🏛️ 1. النظرة العامة ومعمارية تطبيق ولي الأمر (Parent App Architecture & Auth Flow)

يخدم تطبيق **EduBridge Mobile App** ولي الأمر كبوابة عائلية متكاملة تتيح متابعة الأبناء المسجلين في المدرسة في تطبيق واحد عبر آلية **تعدد الأبناء (Multi-Child Profile Switching)**.

```
+-------------------------------------------------------------------------------+
|                       EduBridge Parent Mobile App (Flutter)                   |
|  +-------------------------------------------------------------------------+  |
|  | [ Switch Child Banner ] : فيصل سعد (الخامس ب) | راشد سعد (الثاني أ)       |  |
|  +-------------------------------------------------------------------------+  |
|        |                  |                  |                  |             |
|   [الرئيسية]       [الجدول والمواد]    [الحضور والأعذار]   [تتبع الحافلة]       |
+-------------------------------------------------------------------------------+
                                 | HTTPS / JWT Bearer Token
                                 v
+-------------------------------------------------------------------------------+
|               EduBridge Backend Gateway (Laravel 11 REST API)                 |
|  +-------------------------------------------------------------------------+  |
|  | Role & Policy Gateways | Parent-Student Relationship Verification Middleware|  |
|  +-------------------------------------------------------------------------+  |
|  | MySQL Database (Parents, Students, Attendance, Bus Routes, Wallet, DB)  |  |
+-------------------------------------------------------------------------------+
```

### 🔐 آلية المصادقة وحماية بيانات الأبناء (Authentication & Security Middleware):
1. **تسجيل الدخول (Login):** يتم عبر رقم الجوال / رقم الهوية الوطنية + كلمة المرور.
2. **صلاحية التحقق العائلي (`EnsureParentChildOwnership` Middleware):** كل طلب يرسله تطبيق ولي الأمر لجلب أو تعديل بيانات طالب (`student_id`) يمر عبر Middleware يتحقق أن هذا الطالب مسجل رسمياً في جدول الربط العائلي (`student_parent`) تحت رقم تعريف ولي الأمر (`parent_id`) الحاصل على الـ `JWT Access Token`.

---

## 🗄️ 2. مخطط قواعد البيانات العلائقي لتطبيق ولي الأمر (Eloquent Models & Schema)

لخدمة جميع شاشات وخصائص تطبيق ولي الأمر في الباك إند (Laravel 11)، يتم الاعتماد على الجداول التالية:

```sql
-- 1. جدول أولياء الأمور (Parents Profile)
CREATE TABLE parents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL, -- يربط بجدول users العام
    national_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    secondary_phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    workplace VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. جدول ربط الأبناء بأولياء الأمور (Student Parent Pivot)
CREATE TABLE student_parent (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NOT NULL,
    relationship_type ENUM('father', 'mother', 'guardian') DEFAULT 'father',
    is_primary BOOLEAN DEFAULT TRUE,
    can_pickup BOOLEAN DEFAULT TRUE,
    UNIQUE KEY (student_id, parent_id)
);

-- 3. جدول طلبات الأعذار الطبية (Medical Excuses)
CREATE TABLE medical_excuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NOT NULL,
    absence_date DATE NOT NULL,
    hospital_name VARCHAR(150) NOT NULL,
    diagnosis_summary VARCHAR(255) NULL,
    attachment_path VARCHAR(255) NOT NULL, -- صورة تقرير المستشفى أو تطبيق صحتي
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason VARCHAR(255) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. جدول أذونات الخروج وتصاريح البوابة (Leave Permits & Gate Pass)
CREATE TABLE leave_permits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NOT NULL,
    request_time TIME NOT NULL,
    request_date DATE NOT NULL,
    pickup_type ENUM('parent', 'authorized_driver', 'relative') DEFAULT 'parent',
    pickup_person_name VARCHAR(120) NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('waiting_gate', 'released', 'rejected') DEFAULT 'waiting_gate',
    gate_pass_code VARCHAR(30) UNIQUE NOT NULL, -- كود الخروج لبوابة المدرسة
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. جدول محفظة الطالب والمقصف (Student Wallets & Transactions)
CREATE TABLE student_wallets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED UNIQUE NOT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    daily_spend_limit DECIMAL(10, 2) DEFAULT 20.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id BIGINT UNSIGNED NOT NULL,
    transaction_type ENUM('top_up', 'canteen_purchase', 'tuition_fee') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    reference_code VARCHAR(60) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. جدول الرسوم المدرسية والمصروفات (Tuition Fees & Payments)
CREATE TABLE tuition_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    fee_type ENUM('tuition', 'bus', 'uniform', 'books', 'activities') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    late_fee DECIMAL(10, 2) DEFAULT 0.00,
    due_date DATE NOT NULL,
    status ENUM('unpaid', 'paid', 'partially_paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fee_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    receipt_title VARCHAR(150) NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    pdf_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. جدول إشعارات ولي الأمر والأجهزة (Parent Device Tokens & Push Notifications)
CREATE TABLE parent_device_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    fcm_token VARCHAR(255) NOT NULL,
    device_type ENUM('ios', 'android') NOT NULL,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. جدول تذاكر الدعم الفني (Support Tickets)
CREATE TABLE support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    ticket_type ENUM('technical_issue', 'financial_query', 'academic_question', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'resolved', 'closed') DEFAULT 'open',
    admin_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 📱 3. التوصيف التفصيلي لشاشات التطبيق ومسارات الـ REST API (Screen-by-Screen Endpoints)

### 3.1 شاشة المصادقة وتبديل الأبناء (Auth & Child Selector)

#### 1. تسجيل الدخول لبوابة أولياء الأمور
* **الـ Endpoint:** `POST /api/v1/parent/auth/login`
* **Payload Request Body:**
  ```json
  {
    "national_id_or_phone": "0501112233",
    "password": "secretPassword123",
    "fcm_device_token": "fcm_token_string_here",
    "device_platform": "android"
  }
  ```
* **Payload Response (200 OK):**
  ```json
  {
    "status": "success",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
    "parent": {
      "id": 14,
      "full_name": "سعد بن فيصل الدوسري",
      "national_id": "1044882910",
      "phone": "0501112233"
    },
    "children": [
      {
        "student_id": 3,
        "full_name": "فيصل سعد الدوسري",
        "student_code": "STU-10034",
        "grade_name": "الصف الخامس الابتدائي",
        "section_name": "شعبة ب",
        "avatar": "/storage/students/st3.jpg",
        "is_default": true
      }
    ]
  }
  ```

#### 2. جلب الملف الشخصي لولي الأمر والأبناء (Profile Page)
* **الـ Endpoint:** `GET /api/v1/parent/profile`
* **وصف الوظيفة:** عرض البيانات الشخصية لولي الأمر وقائمة أبنائه المسجلين لصفحة الملف الشخصي.
* **Payload Response (200 OK):**
  ```json
  {
    "parent_profile": {
      "name": "محمد علي أحمد",
      "national_id": "1029384756",
      "phone": "+966 50 123 4567",
      "email": "mohammed.ali@edubridge.com",
      "account_number": "123456"
    },
    "registered_children": [
      { "id": 3, "name": "أحمد محمد", "grade": "الصف الخامس الإبتدائي" },
      { "id": 9, "name": "سارة محمد", "grade": "الصف الثاني الإبتدائي" }
    ]
  }
  ```

---

### 3.2 الشاشة الرئيسية للطالب المحدد (`Main Screen` / `Hero Banner`)

#### 3. جلب الملخص التفاعلي اليومي للطالب المحدد (Home Dashboard Summary)
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/home-summary`
* **Payload Response (200 OK):**
  ```json
  {
    "student": {
      "id": 3,
      "name": "فيصل سعد الدوسري",
      "grade_section": "الصف الخامس / شعبة ب"
    },
    "quick_stats": {
      "attendance_percentage": 97.4,
      "behavior_score": 145,
      "academic_rank": "الأول على الفصل",
      "wallet_balance": 85.50
    },
    "daily_updates_carousel": [
      {
        "id": 101,
        "type": "homework",
        "title": "واجب الرياضيات: حل تمارين الكسور (ص 44)",
        "subject": "الرياضيات",
        "due_date": "2026-07-13",
        "teacher_name": "أ. خالد المنصور"
      }
    ]
  }
  ```

---

### 3.3 شاشة الحضور، الغياب، ورفع الأعذار (`Attendance & Excuses Screen`)

#### 4. جلب سجل الحضور والغياب الشهري
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/attendance?month=2026-07`
* **Payload Response (200 OK):**
  ```json
  {
    "summary": { "present_days": 18, "excused_absences": 1, "unexcused_absences": 0 },
    "calendar_records": [
      { "date": "2026-07-12", "status": "present", "check_in": "06:48 ص" }
    ]
  }
  ```

#### 5. إرسال ورفع عذر غياب طبي رسمي
* **الـ Endpoint:** `POST /api/v1/parent/student/{student_id}/medical-excuse`
* **Payload Request Body (Multipart / Form-Data):**
  - `absence_date`: `2026-07-05`
  - `hospital_name`: `مستشفى الحبيب الطيبي`
  - `diagnosis_summary`: `التهاب لوزتين`
  - `report_attachment`: `(File image/pdf)`
* **Payload Response (201 Created):**
  ```json
  { "status": "success", "message": "تم إرسال العذر للمراجعة والاعتماد." }
  ```

#### 6. طلب إذن خروج مبكر وتوليد تصريح البوابة (Gate Pass)
* **الـ Endpoint:** `POST /api/v1/parent/student/{student_id}/leave-permit`
* **Payload Request Body:**
  ```json
  {
    "request_date": "2026-07-12",
    "request_time": "11:30",
    "reason": "موعد سفارة طارئ",
    "pickup_type": "parent"
  }
  ```
* **Payload Response (201 Created):**
  ```json
  { "status": "success", "gate_pass_code": "PASS-8842" }
  ```

---

### 3.4 شاشة تتبع الحافلة المدرسية الذكية (`Bus Tracker Screen`)

#### 7. جلب الحالة الحية لرحلة الحافلة المدرسية
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/bus-live-status`
* **Payload Response (200 OK):**
  ```json
  {
    "bus_assigned": true,
    "route_info": { "driver_name": "أبو فهد السبيعي", "driver_phone": "0551122334" },
    "current_trip": { "status": "on_way", "live_gps": { "latitude": 24.8132, "longitude": 46.6219 }, "eta_minutes": 7 }
  }
  ```

#### 8. إعفاء الحافلة اليومي (الطالب لن يركب الحافلة اليوم)
* **الـ Endpoint:** `POST /api/v1/parent/student/{student_id}/bus-opt-out`
* **Payload Request Body:**
  ```json
  { "trip_date": "2026-07-12", "trip_period": "afternoon_dropoff", "reason": "سأستلمه بنفسي" }
  ```

---

### 3.5 شاشة السلوك والمواظبة والأنشطة (`Behavior & Activities`)

#### 9. جلب سجل الملاحظات السلوكية والتحفيز ونقاط التميز
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/behavior-records`
* **Payload Response (200 OK):**
  ```json
  {
    "total_points": 145,
    "records": [
      { "id": 501, "type": "positive", "title": "تميز دراسي ومبادرة", "points": 10, "acknowledged_by_parent": true }
    ]
  }
  ```

#### 10. توقيع ولي الأمر على إشعار الملاحظة السلوكية
* **الـ Endpoint:** `POST /api/v1/parent/behavior/{record_id}/acknowledge`

#### 11. جلب قائمة الأنشطة المدرسية والرحلات (Activities)
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/activities`
* **Payload Response (200 OK):**
  ```json
  {
    "upcoming": [
      { "id": 4, "title": "معرض العلوم والابتكار", "date": "15 مارس 2026", "location": "القاعة الرئيسية", "fees": "مجاناً", "status": "متاح للتسجيل" }
    ],
    "previous": [
      { "id": 1, "title": "ورشة برمجة الروبوتات", "date": "10 نوفمبر 2025", "status": "منتهي" }
    ]
  }
  ```

#### 12. تسجيل الموافقة والتسجيل في النشاط/الرحلة
* **الـ Endpoint:** `POST /api/v1/parent/student/{student_id}/activities/{activity_id}/register`
* **Payload Response (200 OK):**
  ```json
  { "status": "success", "message": "تم التسجيل والموافقة بنجاح!" }
  ```

---

### 3.6 شاشة المحفظة والمقصف والمدفوعات (`Wallet & Tuition Payments`)

#### 13. جلب رصيد المحفظة وسجل الحركات وتوليد كود الدفع المؤقت للمقصف
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/wallet`
* **Payload Response (200 OK):**
  ```json
  {
    "wallet_balance": 150.00,
    "transactions": [
      { "id": 1, "type": "canteen_purchase", "amount": -12.00, "desc": "وجبة إفطار", "date": "اليوم 09:30 ص" }
    ]
  }
  ```

#### 14. توليد رمز الدفع (QR Code) المؤقت والآمن للمقصف
* **الـ Endpoint:** `POST /api/v1/parent/student/{student_id}/wallet/generate-qr-token`
* **وصف الوظيفة:** يولد رمز دفع مشفر فريد وصالح لمدة 60 ثانية لكي يقوم البائع في المقصف بمسحه ليخصم من الرصيد.
* **Payload Response (200 OK):**
  ```json
  {
    "status": "success",
    "qr_token_data": "EDU_WALLET_TOKEN_5583940284710294",
    "expires_in_seconds": 60
  }
  ```

#### 15. جلب المصروفات الدراسية المستحقة والإيصالات (Tuition Fees Screen)
* **الـ Endpoint:** `GET /api/v1/parent/student/{student_id}/tuition-fees`
* **Payload Response (200 OK):**
  ```json
  {
    "fees": [
      { "type": "الرسوم الدراسية", "amount": 2500.00, "late_fee": 100.00, "total": 2600.00, "due_date": "15 أغسطس 2024" },
      { "type": "رسوم الحافلة", "amount": 500.00, "late_fee": 25.00, "total": 525.00, "due_date": "15 أغسطس 2024" }
    ],
    "receipts": [
      { "id": 88, "title": "الرسوم الدراسية - يوليو", "date": "1 يوليو 2024", "pdf_url": "/storage/receipts/rec_88.pdf" }
    ]
  }
  ```

---

### 3.7 شاشة الدعم الفني والإشعارات (`Support & Notifications`)

#### 16. إرسال تذكرة دعم فني جديدة من ولي الأمر
* **الـ Endpoint:** `POST /api/v1/parent/support/ticket`
* **Payload Request Body:**
  ```json
  {
    "ticket_type": "technical_issue", -- 'technical_issue' | 'financial_query' | 'academic_question' | 'other'
    "description": "واجهة المحفظة لا تفتح وتظهر شاشة بيضاء عند محاولة الشحن"
  }
  ```
* **Payload Response (201 Created):**
  ```json
  { "status": "success", "message": "تم إرسال تذكرتك بنجاح برقم #1034" }
  ```

#### 17. جلب الإشعارات وتحديدها كمقروءة
* **الـ Endpoint:** `GET /api/v1/parent/notifications`
* **الـ Endpoint لتبديل الحالة:** `PATCH /api/v1/parent/notifications/{id}/read`

---

## 🎛️ 4. مصفوفة الأحداث والأزرار التفصيلية 100% (Granular Event & Button Matrix)

| الشاشة في تطبيق الجوال | الزر / العنصر التفاعلي (Button/Action) | الـ HTTP Method & Endpoint في Laravel 11 | الوظيفة الفورية عند الضغط |
| :--- | :--- | :--- | :--- |
| **شريط العنوان الموحد** | شريط تبديل الابن (`Child Switcher Banner`) | `GET /api/v1/parent/children` | يعرض قائمة أبناء ولي الأمر لاختيار الطالب المراد عرض بياناته وتحديث الـ Active State. |
| **الملف الشخصي** | فتح الشاشة وعرض البيانات الشخصية | `GET /api/v1/parent/profile` | جلب تفاصيل ولي الأمر وقائمة أبنائه لمطابقتها مع المدرسة. |
| **سجل الحضور والغياب** | زر "تقديم عذر غياب رسمي" (`Submit Medical Excuse`) | `POST /api/v1/parent/student/{id}/medical-excuse` | رفع تقرير طبي أو عذر مرفق بصورة وإرساله فوراً لوكيل شؤون الطلاب في لوحة التحكم. |
| **سجل الحضور والغياب** | زر "طلب استئذان خروج مبكر" (`Request Leave Permit`) | `POST /api/v1/parent/student/{id}/leave-permit` | إصدار تصريح خروج وكود بوابة (`PASS-XXXX`) لمغادرة الطالب. |
| **تتبع الحافلة (`Bus Tracker`)** | زر "ابني لن يركب الحافلة اليوم" (`Bus Opt-Out`) | `POST /api/v1/parent/student/{id}/bus-opt-out` | إشعار فوري للسائق والمدرسة لشطب اسم الطالب من خط السير اليومي. |
| **الأنشطة والرحلات** | زر "سجّل الآن في الفعالية" (`Register Activity`) | `POST /api/v1/parent/student/{id}/activities/{act_id}/register` | تسجيل الطالب والموافقة على الفعالية وخصم الرسوم إن وجدت. |
| **المحفظة (`Wallet`)** | زر "تغيير رمز الدفع الآمن" | `POST /api/v1/parent/student/{id}/wallet/generate-qr-token` | إبطال التوكن القديم وتوليد توكن دفع مشفر مؤقت جديد للمقصف المدرسي. |
| **المصروفات والرسوم** | زر "تحميل الإيصال كـ PDF" | `GET /storage/receipts/{filename}.pdf` (Static link) | تحميل وتنزيل إيصال سداد الرسوم على ذاكرة الجوال. |
| **الدعم الفني (`Support`)** | زر "إرسال تذكرة الدعم" | `POST /api/v1/parent/support/ticket` | إرسال نوع وتفاصيل المشكلة لقسم التقنية بالمدرسة. |
| **استدعاء ولي الأمر (`Parent Summons`)**| زر "تأكيد الحضور للموعد المحدد" (`Confirm Summons`) | `POST /api/v1/parent/summons/{id}/respond` | تأكيد حضور المقابلة مع وكيل المدرسة وحجز الموعد في جدول الوكيل. |
| **صندوق الإشعارات (`Notifications`)** | زر "تحديد كافة الإشعارات كمقروءة" (`Mark All Read`) | `POST /api/v1/parent/notifications/mark-all-read` | تصفير عداد إشعارات التطبيق في الـ Header. |
| **المحفظة (`Wallet`)** | زر "شحن رصيد المقصف" (`Top-up Balance`) | `POST /api/v1/parent/student/{id}/wallet/top-up` | إنشاء جلسة دفع إلكتروني (Mada / Apple Pay) وإضافة الرصيد لحساب الطالب فوراً. |

---

## ✅ 5. الإضافات المكملة لسد الفجوات الفعلية في تطبيق ولي الأمر

> هذا القسم يكمّل الوثيقة بناءً على الشاشات الموجودة فعلياً في تطبيق Flutter، خصوصاً المتابعة اليومية، الواجبات، التقارير، الاستئناف، الرسائل، الدفع، والدعم التفصيلي.

### 5.1 جداول إضافية مطلوبة

```sql
-- ردود تذاكر الدعم الفني
CREATE TABLE support_ticket_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('parent', 'admin') NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- الواجبات والمهام الأكاديمية
CREATE TABLE assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    due_at DATETIME NOT NULL,
    attachment_path VARCHAR(255) NULL,
    assignment_type ENUM('homework', 'quiz', 'project', 'exam_reminder') DEFAULT 'homework',
    is_urgent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assignment_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    submitted_file_path VARCHAR(255) NULL,
    submitted_text TEXT NULL,
    status ENUM('submitted', 'late', 'reviewed') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (assignment_id, student_id)
);

-- طلبات مراجعة الدرجات
CREATE TABLE grade_appeals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NOT NULL,
    term_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    assessment_id BIGINT UNSIGNED NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
    admin_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- محادثات ولي الأمر مع المعلمين
CREATE TABLE parent_teacher_threads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE parent_teacher_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('parent', 'teacher', 'admin') NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جلسات الدفع الإلكتروني
CREATE TABLE payment_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    purpose ENUM('wallet_top_up', 'tuition_fee', 'activity_fee') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    provider ENUM('mada', 'apple_pay', 'visa_mastercard', 'stc_pay') NOT NULL,
    provider_session_id VARCHAR(120) UNIQUE NOT NULL,
    payment_url VARCHAR(255) NULL,
    status ENUM('pending', 'paid', 'failed', 'cancelled', 'expired') DEFAULT 'pending',
    callback_payload JSON NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- إشعارات داخلية قابلة للتوجيه داخل التطبيق
CREATE TABLE parent_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    notification_type ENUM('grade', 'homework', 'attendance', 'activity', 'bus', 'wallet', 'support', 'general') NOT NULL,
    action_route VARCHAR(160) NULL,
    action_payload JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- استدعاءات ولي الأمر
CREATE TABLE parent_summons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    meeting_at DATETIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('pending', 'confirmed', 'declined', 'completed', 'cancelled') DEFAULT 'pending',
    parent_response_note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 5.2 مسارات المصادقة والجلسة الناقصة

| الوظيفة | Method & Endpoint | ملاحظات |
| :--- | :--- | :--- |
| جلب الأبناء لتبديل الطالب النشط | `GET /api/v1/parent/children` | يعيد الأبناء المرتبطين بولي الأمر مع `is_default`. |
| تحديث FCM Token | `POST /api/v1/parent/device-token` | يحفظ أو يحدّث توكن الجهاز. |
| تجديد التوكن | `POST /api/v1/parent/auth/refresh` | يعيد Access Token جديد. |
| تسجيل الخروج | `POST /api/v1/parent/auth/logout` | يبطل التوكن ويحذف/يعطل توكن الجهاز للجلسة. |
| طلب استعادة كلمة المرور | `POST /api/v1/parent/auth/forgot-password` | يرسل OTP أو رابط تحقق. |
| تعيين كلمة مرور جديدة | `POST /api/v1/parent/auth/reset-password` | يعتمد على OTP/Reset Token. |

### 5.3 المتابعة اليومية والواجبات

| الوظيفة | Method & Endpoint | ملاحظات |
| :--- | :--- | :--- |
| جدول اليوم والمهام | `GET /api/v1/parent/student/{student_id}/academic/daily?date=2026-07-12` | يعيد جدول الحصص والواجبات اليومية. |
| النظرة الأسبوعية | `GET /api/v1/parent/student/{student_id}/academic/weekly?week_start=2026-07-12` | يعيد أحداث ومواعيد الأسبوع. |
| تفاصيل واجب | `GET /api/v1/parent/student/{student_id}/assignments/{assignment_id}` | يعرض الوصف، الموعد، المرفق، وحالة التسليم. |
| تحميل مرفق واجب | `GET /api/v1/parent/student/{student_id}/assignments/{assignment_id}/attachment` | يرجع ملف الواجب بعد التحقق من الملكية. |
| تسليم واجب | `POST /api/v1/parent/student/{student_id}/assignments/{assignment_id}/submit` | `Multipart/Form-Data` ويقبل `pdf/jpg/png/doc/docx`. |
| حالة Google Classroom | `GET /api/v1/parent/student/{student_id}/integrations/google-classroom/status` | يعرض حالة الربط. |
| بدء ربط Google Classroom | `POST /api/v1/parent/student/{student_id}/integrations/google-classroom/connect` | يبدأ تدفق الربط. |

### 5.4 التقارير والدرجات والاستئناف

| الشاشة | Endpoint | الوظيفة |
| :--- | :--- | :--- |
| تقرير منتصف الفصل | `GET /api/v1/parent/student/{student_id}/reports/midterm?term_id=1` | يعرض التقدير العام، أداء المواد، وملاحظات المعلمين. |
| أحدث الاختبارات | `GET /api/v1/parent/student/{student_id}/reports/recent-assessments?limit=10` | يعرض نتائج الاختبارات والتعليقات. |
| تقرير نهاية الفصل | `GET /api/v1/parent/student/{student_id}/reports/final?term_id=2` | يعرض النتيجة النهائية، المواد، وكلمة الإدارة. |
| تحميل الشهادة | `GET /api/v1/parent/student/{student_id}/reports/final/certificate?term_id=2` | يرجع شهادة PDF. |
| خيارات نموذج الاستئناف | `GET /api/v1/parent/student/{student_id}/grade-appeals/options` | يرجع الفصول والمواد والتقييمات المتاحة. |
| إرسال استئناف درجة | `POST /api/v1/parent/student/{student_id}/grade-appeals` | ينشئ طلب مراجعة درجة. |
| متابعة الاستئنافات | `GET /api/v1/parent/student/{student_id}/grade-appeals` | يعرض حالة كل طلب. |

**Payload إرسال استئناف درجة:**
```json
{
  "term_id": 1,
  "subject_id": 1,
  "assessment_id": 88,
  "reason": "أرغب في مراجعة درجة السؤال الثالث."
}
```

### 5.5 تفاصيل السلوك والرسائل

| الوظيفة | Method & Endpoint | ملاحظات |
| :--- | :--- | :--- |
| تفاصيل ملاحظة سلوكية | `GET /api/v1/parent/behavior/{record_id}` | يعرض التفاصيل، المعلم، السجل الزمني، والتوصيات. |
| فتح محادثة مع معلم | `POST /api/v1/parent/student/{student_id}/teachers/{teacher_id}/message-thread` | ينشئ أو يرجع `thread_id`. |
| قائمة المحادثات | `GET /api/v1/parent/messages/threads?student_id=3` | يعرض محادثات ولي الأمر. |
| رسائل محادثة | `GET /api/v1/parent/messages/threads/{thread_id}` | يعرض الرسائل مع pagination. |
| إرسال رسالة | `POST /api/v1/parent/messages/threads/{thread_id}/messages` | يدعم نص ومرفق اختياري. |

### 5.6 المحفظة والدفع الإلكتروني

| الوظيفة | Method & Endpoint | ملاحظات |
| :--- | :--- | :--- |
| إنشاء جلسة شحن محفظة | `POST /api/v1/parent/student/{student_id}/wallet/top-up` | يرجع `payment_url` أو بيانات SDK. |
| حالة الدفع | `GET /api/v1/parent/payments/{payment_session_id}/status` | يستخدمه التطبيق بعد العودة من الدفع. |
| Webhook الدفع | `POST /api/v1/payments/webhook/{provider}` | Server-to-Server مع توقيع مزود الدفع. |
| كل معاملات المحفظة | `GET /api/v1/parent/student/{student_id}/wallet/transactions?page=1` | يدعم زر "عرض الكل" والـ pagination. |

### 5.7 الدعم الفني والإشعارات والاستدعاءات

| الوظيفة | Method & Endpoint | ملاحظات |
| :--- | :--- | :--- |
| سجل تذاكر الدعم | `GET /api/v1/parent/support/tickets` | يعرض التذاكر السابقة وحالتها. |
| تفاصيل تذكرة | `GET /api/v1/parent/support/tickets/{ticket_id}` | يعرض الردود والمرفقات. |
| إضافة رد على تذكرة | `POST /api/v1/parent/support/tickets/{ticket_id}/replies` | يدعم مرفق اختياري. |
| إغلاق تذكرة | `PATCH /api/v1/parent/support/tickets/{ticket_id}/close` | يغيّر الحالة إلى `closed`. |
| تحديد كل الإشعارات كمقروءة | `POST /api/v1/parent/notifications/mark-all-read` | يكمل endpoint الإشعارات المذكور سابقاً. |
| قائمة استدعاءات ولي الأمر | `GET /api/v1/parent/summons` | يعرض المواعيد المطلوبة من الإدارة. |
| الرد على استدعاء | `POST /api/v1/parent/summons/{id}/respond` | يؤكد/يرفض الموعد مع ملاحظة اختيارية. |

### 5.8 أحداث إضافية يجب إضافتها للمصفوفة

| الشاشة | العنصر التفاعلي | Endpoint |
| :--- | :--- | :--- |
| المتابعة اليومية | فتح تبويب يومي | `GET /api/v1/parent/student/{id}/academic/daily` |
| المتابعة اليومية | فتح تبويب أسبوعي | `GET /api/v1/parent/student/{id}/academic/weekly` |
| المهام والواجبات | فتح تفاصيل واجب | `GET /api/v1/parent/student/{id}/assignments/{assignment_id}` |
| المهام والواجبات | تحميل مرفق | `GET /api/v1/parent/student/{id}/assignments/{assignment_id}/attachment` |
| المهام والواجبات | تسليم الواجب | `POST /api/v1/parent/student/{id}/assignments/{assignment_id}/submit` |
| التقارير | تقرير منتصف الفصل | `GET /api/v1/parent/student/{id}/reports/midterm` |
| التقارير | أحدث الاختبارات | `GET /api/v1/parent/student/{id}/reports/recent-assessments` |
| التقارير | تقرير نهاية الفصل | `GET /api/v1/parent/student/{id}/reports/final` |
| تقرير نهاية الفصل | تحميل الشهادة | `GET /api/v1/parent/student/{id}/reports/final/certificate` |
| مراجعة الدرجات | إرسال الاستئناف | `POST /api/v1/parent/student/{id}/grade-appeals` |
| تفاصيل السلوك | مراسلة المعلم | `POST /api/v1/parent/student/{id}/teachers/{teacher_id}/message-thread` |
| الرسائل | إرسال رسالة | `POST /api/v1/parent/messages/threads/{thread_id}/messages` |
| المحفظة | شحن الرصيد | `POST /api/v1/parent/student/{id}/wallet/top-up` |
| الدعم الفني | الرد على تذكرة | `POST /api/v1/parent/support/tickets/{ticket_id}/replies` |

---
## 🛡️ 6. ضوابط الأمان والتحقق في Laravel 11 (Security Checklist)
1. **التحقق الصارم من ملكية ولي الأمر للطالب (`ParentOwnershipPolicy`):**  
   يمنع منعاً باتاً وصول أي ولي أمر لبيانات طالب لا ينتمي إليه؛ يتم فحص وجود علاقة سارية في جدول `student_parent` قبل تنفيذ أي مسار `GET` أو `POST`.
2. **التحقق من صحة الملفات المرفقة (`Validation for Medical Reports`):**  
   أعذار الغياب تقبل فقط الامتدادات (`pdf, jpg, jpeg, png`) وبحجم أقصى `5MB`.
3. **تشفير أكواد البوابة (`Gate Pass Code Security`):**  
   كل كود خروج (`PASS-XXXX`) صالح للاستخدام مرة واحدة فقط، وينتهي تلقائياً بعد خروج الطالب من البوابة.
### ضوابط أمان إضافية للتحديث 2.5.0
4. **حماية الواجبات والمرفقات:** لا يسمح بتنزيل مرفق واجب أو رفع حل إلا لطالب تابع لولي الأمر، مع قبول الملفات التعليمية فقط (`pdf, jpg, jpeg, png, doc, docx`) وبحجم أقصى `10MB`.
5. **منع تكرار الاستئناف:** لا يسمح بأكثر من طلب مراجعة مفتوح لنفس الطالب/المادة/التقييم إلا بعد إغلاق الطلب السابق.
6. **حماية جلسات الدفع:** لا يتم تحديث رصيد المحفظة إلا بعد Webhook موثوق وموقّع من مزود الدفع.
7. **خصوصية الرسائل:** محادثات ولي الأمر لا تُفتح إلا مع معلم مرتبط فعلياً بفصل الطالب أو ملاحظة/مادة تخص الطالب.
8. **إشعارات قابلة للتوجيه:** كل إشعار يجب أن يحتوي `notification_type` و`action_route` و`action_payload` لفتح الشاشة الصحيحة داخل التطبيق.
