# المجالات ونموذج البيانات المنطقي

هذه ليست migrations نهائية؛ هي قاموس الأسماء والعلاقات المعتمد. التفاصيل النهائية تنشأ في التاسك المختص وتثبت بالمigrations وOpenAPI.

## قاعدة Central: المنصة والهوية والتوجيه

- `schools`: المدرسة، code، timezone، locale، currency، status.
- `school_domains`: host/app identifier موثق يوجه إلى مدرسة.
- `users`: بيانات الدخول العامة فقط؛ لا تكرر profile fields المدرسية.
- `school_user`: العضوية، الدور المرجعي، الحالة وفترة الصلاحية.
- `personal_access_tokens`: Sanctum tokens لكل جهاز وبصلاحيات محدودة.
- `tenant_connections`: secret reference واتصال مشفر/مدار، دون password ظاهر في logs أو API.
- `platform_audit_logs`: أحداث المنصة مثل إنشاء مدرسة أو تبديل الدعم إلى tenant.

## قاعدة كل Tenant: الأساس المشترك للمدرسة

- `roles`, `permissions`, وتعيينات العضوية المحلية.
- `device_tokens`: central user reference، platform، token hash/unique، app type، last_seen، revoked_at.
- `audit_logs`: actor central ID، action، subject، before/after redacted، IP، correlation_id.
- `idempotency_keys`: actor/client، key، operation، request hash، response/status، expiry.
- `outbox_messages`: event id/type/payload/status/attempts/available_at.

## الهيكل الأكاديمي والأشخاص

- `academic_years` -> `academic_terms`.
- `grade_levels` -> `sections`.
- `subjects` وعلاقة `grade_level_subject` عند اختلاف المادة حسب المرحلة.
- `teachers`, `parents`, `students` كملفات مرتبطة بـ`users` عند وجود دخول.
- `student_parent`: relationship، primary، pickup permission، valid_from/to.
- `teacher_section_subject`: school/term/teacher/section/subject/quota/homeroom.

قاعدة: لا يوجد `students.parent_id` ولا `students.bus_route_id` كاختصار لعلاقات متعددة ومتغيرة زمنيًا.

## الجدول والحضور

- `schedule_slots`: قالب دوري للترم.
- `teaching_sessions`: occurrence بتاريخ فعلي وحالة `scheduled|active|completed|cancelled`.
- `substitute_assignments`: ربط الحصة بمعلم بديل مع workflow.
- `attendance_drafts`: مسودة واحدة للحصة والمعلم، payload مؤقت/version.
- `attendance_records`: unique `(teaching_session_id, student_id)`؛ status `present|absent|late|excused`، recorded_by، submitted_at، revision.
- `medical_excuses`: يغطي فترة/تواريخ، مرفق آمن، review workflow.
- `absence_warnings`: إنذار غير قابل للحذف مع delivery reference.

الحضور اليومي والنسبة تقارير مشتقة، ولا تخزن كحقيقة ثانية من دون آلية reconciliation.

## الواجبات والسلوك والرسائل

- `assignments`, `assignment_attachments`, `assignment_submissions`.
- assignment status: `draft|published|archived`; `is_urgent` يحسب من due_at ولا يخزن إلا إن ثبتت حاجة snapshot.
- `behavior_notes`, `behavior_note_timeline`, `behavior_recommendations`.
- `conversation_threads`, `conversation_participants`, `messages`, `message_receipts`.
- thread قد يرتبط بطالب/ملاحظة، لكن المشاركين والتحقق من علاقتهم صريحان.

## التقييم والدرجات

- `assessments`: النوع، الدرجة العظمى، الوزن، الشعبة/المادة/الترم، status.
- `grade_entries`: unique `(assessment_id, student_id)`، score، feedback، entered_by، revision.
- `grade_publications`: نطاق النشر ومن اعتمد ومتى.
- `grade_appeals`: طلب واحد مفتوح لكل student/entry حسب unique business rule.

لا تخزن “overall average” كمصدر حقيقة؛ يحسب أو يحفظ projection يعاد بناؤها.

## العمليات والنقل

- `leave_permits`: حالات `pending|approved|rejected|used|expired|cancelled`، وgate token hash أحادي الاستخدام.
- `parent_summons` و`summons_responses`.
- `bus_routes`, `bus_route_assignments` بتاريخ صلاحية، `bus_trips`, `bus_trip_passengers`, `bus_tracking_events`, `bus_opt_outs`, `transport_alerts`.
- GPS retention policy منفصلة؛ لا تحتفظ بدقة الحركة بلا نهاية.

## المحفظة والمدفوعات

- `wallets`: student، currency، cached_balance، limits، version.
- `wallet_transactions`: immutable ledger، type، signed amount، balance_after، reference، idempotency_key.
- `wallet_payment_tokens`: hash، amount/limit، expires_at، used_at؛ لا تخزن token الخام.
- `fees`, `payment_sessions`, `payments`, `refunds`, `payment_webhook_events`.

كل callback مزود يحفظ event id فريدًا قبل المعالجة. لا يزيد الرصيد من redirect العميل، بل من webhook موثوق ثم reconciliation.

## الملفات والإشعارات والتكامل

- `files`: owner/school، disk/key، original name، MIME الموثوق بعد الفحص، bytes، checksum، visibility، scan_status.
- `notifications`: الحدث المنطقي والـaction type/payload.
- `notification_deliveries`: recipient/channel/status/provider id/attempts/read_at.
- `integration_settings`: config مشفر أو secret reference، لا secrets خام في DB إن توفر vault.
- `support_tickets`, `support_ticket_replies`, `school_events`, `broadcast_messages`.

## قواعد naming وconstraints

- جداول وحقول `snake_case` وأسماء الجمع للجداول.
- foreign key باسم `{entity}_id`.
- status values إنجليزية ثابتة في PHP Enums؛ الترجمة عند العرض فقط.
- uniqueness داخل قاعدة tenant محلية للمدرسة تلقائيًا؛ لا توجد constraints أو foreign keys عابرة لقواعد البيانات.
- كل pivot ذات business attributes لها id وtimestamps وفترة صلاحية عند الحاجة.
