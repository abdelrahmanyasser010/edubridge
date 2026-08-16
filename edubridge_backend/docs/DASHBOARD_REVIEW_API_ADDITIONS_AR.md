# إضافات Backend المرتبطة بجولة مراجعة Dashboard

هذه قائمة مختصرة بالإضافات التي تعتمد عليها الواجهة المعدلة، وهي مكملة للـ OpenAPI الحالي إلى حين إعادة توليده بالكامل.

- `GET /api/v1/residential-areas`
- `POST /api/v1/residential-areas`
- `DELETE /api/v1/residential-areas/{residentialArea}`
- `GET /api/v1/dashboard/parent-summons`
- `GET /api/v1/dashboard/teacher-substitutions`
- `GET /api/v1/dashboard/schedules/conflicts`
- API إتاحة البدلاء للحصة الفعلية ضمن مسارات Teacher Substitution/Scheduling المضافة بالمشروع.
- Dashboard Message Templates CRUD.
- Dashboard Attendance daily rollup / at-risk.
- Dashboard Early Warning explainable results.
- Dashboard Grade Appeals review/correction.

> المرجع التشغيلي النهائي هو Routes/Controllers/Policies الموجودة داخل هذه الحزمة. ملف OpenAPI الحالي يحتاج إعادة توليد/مزامنة بعد استعادة بيئة Laravel الكاملة.
