# ADR-005: حالات وانتقالات العمليات الأساسية

- الحالة: Accepted
- التاريخ: 2026-07-12
- Task: DEC-004

## قاعدة عامة

لا يوجد endpoint عام لتعيين `status` اعتباطيًا. كل انتقال business له Action مسمى، يتحقق من الحالة الحالية والصلاحية والملكية، ويكتب timeline/audit داخل transaction. الإشعارات تخرج بعد commit.

## الحضور

حالة رصد الحصة: `not_started -> draft -> submitted -> amended -> locked`.

- `not_started -> draft`: معلم الحصة/البديل.
- `draft -> submitted`: المعلم مع سجل لكل طالب وidempotency key.
- `submitted -> amended`: صلاحية `attendance.amend` مع reason وrevision؛ لا overwrite بلا أثر.
- `submitted|amended -> locked`: إدارة شؤون الطلاب أو إغلاق الترم.
- عذر معتمد يغير قيمة سجل الطالب إلى `excused` عبر amendment مسجل، ولا يعدل submission الأصلي بصمت.
- إلغاء الحصة يمنع الرصد؛ إعادة فتح locked تحتاج إجراء إداري audited مستقل.

## الملاحظة السلوكية

`draft -> pending_review -> published -> acknowledged -> resolved` مع فرعي `pending_review -> rejected` و`published|acknowledged -> resolved`.

- المعلم ينشئ draft ويرسله للمراجعة.
- المنخفض/الإيجابي قد يسمح للمدرسة بنشره تلقائيًا بسياسة موثقة؛ العالي يحتاج `behavior.review`.
- ولي الأمر يقر فقط بـpublished تخص ابنه؛ الإقرار لا يعني الموافقة على المحتوى.
- resolved لا يحذف السجل أو timeline.
- rejected يرجع بسبب، ويمكن إنشاء revision/draft جديد بدل محو قرار المراجعة.

## الدرجات

حالة التقييم: `draft -> open -> submitted -> approved -> published -> locked` مع `submitted -> returned` و`returned -> submitted`.

- `open`: يسمح للمعلم بالإدخال لطلابه وفي نطاق الدرجة العظمى.
- `submitted`: sheet كاملة أو موثقة النواقص؛ تمنع تعديلات عادية حتى تعاد.
- `approved`: اعتماد أكاديمي، ولا تظهر لولي الأمر بعد.
- `published`: snapshot منشور يظهر للمستفيدين ويرسل إشعارًا بعد commit.
- `locked`: يمنع التغيير؛ التصحيح بعده adjustment/version مع سبب واعتماد، لا تعديل تاريخي صامت.
- الاستئناف workflow منفصل ولا يغير الدرجة إلا بقرار adjustment.

## إذن الخروج

`pending -> approved -> used` أو `pending -> rejected|cancelled`، و`approved -> expired|cancelled`.

- الطلب لا يولد gate token صالحًا قبل الموافقة.
- الموافقة تولد token عشوائيًا، يخزن hash فقط، بمدة صلاحية وسياسة pickup.
- الاستخدام atomic وأحادي المرة، ويسجل مستخدم/جهاز البوابة والوقت.
- الرفض يحتاج reason، والإلغاء بعد الموافقة يبطل token.
- انتهاء الوقت job idempotent، والتحقق من حالة الطالب/اليوم يتم عند الاستخدام أيضًا.

## التزامن

كل aggregate mutable يحمل `version`. يرفض stale write بـ`409 STATE_CONFLICT`. تستخدم unique constraints وrow locks في submit/publish/use عندما يمكن وصول طلبين في الوقت نفسه.

