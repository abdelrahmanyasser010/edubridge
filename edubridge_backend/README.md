# EduBridge Backend

Backend موحد للوحة التحكم وتطبيق المعلم وتطبيق ولي الأمر/الطالب.

ابدأ من [الخطة الرئيسية](docs/00_MASTER_PLAN.md)، ثم اتبع [خطة التاسكات](docs/06_AGENT_TASKS.md). تعليمات أي Agent موجودة في [AGENTS.md](AGENTS.md).

مواصفات التطبيقات الأصلية محفوظة داخل `docs/` كمرجع متطلبات، وليست مصدر الحقيقة للتنفيذ.

## Local development

الأدوات الحالية محلية داخل `.tools` لأن الجهاز لا يملك PHP/Composer على `PATH`.

```powershell
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar install
.\.tools\php-8.5.8\php.exe .\.tools\composer.phar ci
.\.tools\php-8.5.8\php.exe artisan test
.\.tools\php-8.5.8\php.exe vendor\bin\pint --test
.\.tools\php-8.5.8\php.exe artisan serve
```
