# EduBridge Staging, Postman, and API Testing Guide

هذا الملف هو مسار التشغيل العملي بعد اكتمال التاسكات: نختبر محليًا، نجهز Postman/OpenAPI، ثم نرفع على بيئة Staging، وبعدها نعدل ونكرر.

> الهدف الآن ليس Production نهائي. الهدف هو Staging آمن نستطيع كسره وإصلاحه بسرعة بدون بيانات حقيقية.

## 1. ما الجاهز حاليًا؟

- Laravel backend مع Database-per-Tenant: قاعدة مركزية + قاعدة مستقلة لكل مدرسة.
- OpenAPI contract في `openapi/openapi.yaml`.
- أمر demo جاهز:

```powershell
.\.tools\php-8.5.8\php.exe artisan edubridge:demo-school --migrate --school-code=alpha --tenant-database=edubridge_tenant_alpha
```

- مستخدمو الديمو:

| الدور | البريد | كلمة المرور |
|---|---|---|
| Admin | `demo-admin@example.test` | `password` |
| Teacher | `demo-teacher@example.test` | `password` |
| Parent | `demo-parent@example.test` | `password` |
| Student | `demo-student@example.test` | `password` |
| Transport | `demo-transport@example.test` | `password` |

## 2. Quality gates قبل أي رفع

شغل هذه الأوامر من جذر المشروع:

```powershell
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar format:test
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar analyse
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar openapi:lint
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar test -- --filter DemoSchoolCommandTest
```

لو أردت تشغيل كل الاختبارات:

```powershell
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar test
```

ملاحظة: الاختبار الكامل قد يطول على Windows لأن المشروع به migrations كثيرة وسيناريوهات متعددة. في دورة Staging السريعة نستخدم gates أعلاه، ثم نشغل full suite قبل اعتماد نسخة أوسع.

## 3. تشغيل محلي سريع

أنشئ قواعد MySQL محلية:

```sql
CREATE DATABASE edubridge_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE edubridge_tenant_alpha CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

اضبط `.env` حسب `config/database.php` عندك. الفكرة العامة:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=central
DB_CENTRAL_DATABASE=edubridge_central
DB_CENTRAL_USERNAME=root
DB_CENTRAL_PASSWORD=

DB_TENANT_DATABASE=edubridge_tenant_alpha
DB_TENANT_USERNAME=root
DB_TENANT_PASSWORD=
```

ثم:

```powershell
.\.tools\php-8.5.8\php.exe artisan optimize:clear
.\.tools\php-8.5.8\php.exe artisan edubridge:demo-school --migrate --school-code=alpha --tenant-database=edubridge_tenant_alpha
.\.tools\php-8.5.8\php.exe artisan serve
```

Base URL المحلي:

```text
http://127.0.0.1:8000/api/v1
```

## 4. Postman

### الطريقة الموصى بها

1. افتح Postman.
2. Import.
3. اختار الملف:

```text
openapi/openapi.yaml
```

4. أنشئ Environment واستخدم القيم الموجودة في:

```text
postman/edubridge-local.postman_environment.json
```

5. أول request للتجربة:

```http
POST {{base_url}}/auth/login
```

Body:

```json
{
  "email": "demo-admin@example.test",
  "password": "password",
  "school_code": "alpha",
  "device_id": "postman-admin",
  "device_name": "Postman",
  "app_type": "dashboard"
}
```

احفظ `data.token` في متغير `token` ثم أرسل باقي الطلبات بـ:

```text
Authorization: Bearer {{token}}
Accept: application/json
```

### رفع Postman لحسابك

أنا أستطيع تجهيز الملفات، لكن رفعها فعليًا على Workspace في Postman يحتاج واحدًا من الآتي:

- تعمل import بنفسك من Postman UI.
- أو تعطيني Postman API key وWorkspace ID، وده يفضل لا يتم داخل الشات إلا لو متأكد من طريقة حفظ الأسرار.

الأكثر أمانًا الآن: نستورد الملف يدويًا من `openapi/openapi.yaml`.

## 5. Staging deployment

قبل الرفع نحتاج من الاستضافة:

- نوع السيرفر: VPS / shared hosting / Laravel Forge / Ploi / cPanel / Docker host.
- PHP 8.3+، ويفضل نفس بيئتنا PHP 8.5 إن متاح.
- Composer 2.
- MySQL 8 أو متوافق.
- Redis لو هنشغل queues/cache بجد.
- Domain أو subdomain للـ API، مثل:

```text
https://api-staging.edubridge.example
```

### متغيرات Staging الأساسية

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://api-staging.example.com

LOG_CHANNEL=stack
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

DB_CONNECTION=central
DB_CENTRAL_HOST=127.0.0.1
DB_CENTRAL_PORT=3306
DB_CENTRAL_DATABASE=edubridge_central
DB_CENTRAL_USERNAME=...
DB_CENTRAL_PASSWORD=...

DB_TENANT_HOST=127.0.0.1
DB_TENANT_PORT=3306
DB_TENANT_DATABASE=edubridge_tenant_alpha
DB_TENANT_USERNAME=...
DB_TENANT_PASSWORD=...
```

### أوامر الرفع بعد وصول الكود للسيرفر

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan optimize:clear
php artisan edubridge:demo-school --migrate --school-code=alpha --tenant-database=edubridge_tenant_alpha
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Smoke test بعد الرفع

1. افتح:

```text
GET https://api-staging.example.com/health
```

2. جرّب login من Postman:

```text
POST https://api-staging.example.com/api/v1/auth/login
```

3. جرّب:

```text
GET https://api-staging.example.com/api/v1/auth/me
```

4. جرّب endpoint tenant مثل:

```text
GET https://api-staging.example.com/api/v1/academic/structure
```

## 6. ماذا لو ظهرت مشكلة بعد الرفع؟

هذا طبيعي في Staging. نتعامل معها كالتالي:

1. نحدد هل المشكلة:
   - env/server config
   - database/migration
   - route/auth/permission
   - tenant connection
   - bug في الكود
2. نصلح محليًا.
3. نشغل gates السريعة.
4. نرفع نسخة جديدة.
5. نعيد Smoke test.

لا نضع بيانات طلاب حقيقية ولا أسرار دفع حقيقية في Staging.

## 7. القرار العملي

الخطوة التالية الأفضل:

1. تثبيت/تشغيل MySQL.
2. تشغيل demo command محليًا.
3. Import `openapi/openapi.yaml` في Postman.
4. تجربة login و`/auth/me` و`/academic/structure`.
5. تجهيز بيانات الاستضافة، ثم نرفع Staging.
