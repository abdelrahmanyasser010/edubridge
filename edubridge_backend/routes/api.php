<?php

use App\Http\Controllers\Api\Academic\AcademicStructureController;
use App\Http\Controllers\Api\Academic\AcademicTermController;
use App\Http\Controllers\Api\Academic\AcademicYearController;
use App\Http\Controllers\Api\Academic\GradeLevelController;
use App\Http\Controllers\Api\Academic\ScheduleSlotController;
use App\Http\Controllers\Api\Academic\SectionController;
use App\Http\Controllers\Api\Academic\SubjectController;
use App\Http\Controllers\Api\Academic\TeacherSectionSubjectController;
use App\Http\Controllers\Api\Admin\AdminDashboardSummaryController;
use App\Http\Controllers\Api\Admin\AdminSearchController;
use App\Http\Controllers\Api\Assessment\GradeAppealController;
use App\Http\Controllers\Api\Assessment\ParentGradeReportController;
use App\Http\Controllers\Api\Assessment\TeacherAssessmentController;
use App\Http\Controllers\Api\Assignments\RecipientAssignmentController;
use App\Http\Controllers\Api\Assignments\TeacherAssignmentController;
use App\Http\Controllers\Api\Attendance\MedicalExcuseController;
use App\Http\Controllers\Api\Attendance\ParentAttendanceController;
use App\Http\Controllers\Api\Attendance\TeacherAttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Behavior\BehaviorNoteController;
use App\Http\Controllers\Api\Dashboard\AnalyticsDashboardController;
use App\Http\Controllers\Api\Dashboard\AssessmentDashboardController;
use App\Http\Controllers\Api\Dashboard\AttendanceDashboardController;
use App\Http\Controllers\Api\Dashboard\AuditLogController;
use App\Http\Controllers\Api\Dashboard\BehaviorNoteDashboardController;
use App\Http\Controllers\Api\Dashboard\BroadcastController;
use App\Http\Controllers\Api\Dashboard\CalendarEventController;
use App\Http\Controllers\Api\Dashboard\DashboardCanvasConfigController;
use App\Http\Controllers\Api\Dashboard\DashboardSupportTicketController;
use App\Http\Controllers\Api\Dashboard\DashboardTransportController;
use App\Http\Controllers\Api\Dashboard\FinanceController;
use App\Http\Controllers\Api\Dashboard\LeavePermitDashboardController;
use App\Http\Controllers\Api\Dashboard\MessageTemplateController;
use App\Http\Controllers\Api\Dashboard\RbacController;
use App\Http\Controllers\Api\Dashboard\ReportExportController;
use App\Http\Controllers\Api\Dashboard\ScheduleDashboardController;
use App\Http\Controllers\Api\Dashboard\SchoolActivityController;
use App\Http\Controllers\Api\Dashboard\SchoolSettingsController;
use App\Http\Controllers\Api\FileDownloadController;
use App\Http\Controllers\Api\Messaging\ConversationThreadController;
use App\Http\Controllers\Api\Mobile\MobilePaymentController;
use App\Http\Controllers\Api\Mobile\ParentActivityController;
use App\Http\Controllers\Api\Mobile\ParentFinanceController;
use App\Http\Controllers\Api\Mobile\ParentMobileController;
use App\Http\Controllers\Api\Mobile\PaymentWebhookController;
use App\Http\Controllers\Api\Mobile\SupportTicketController;
use App\Http\Controllers\Api\Mobile\TeacherMobileController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\Operations\LeavePermitController;
use App\Http\Controllers\Api\Operations\ParentSummonsController;
use App\Http\Controllers\Api\Operations\TeacherSubstitutionController;
use App\Http\Controllers\Api\People\GuardianController;
use App\Http\Controllers\Api\People\ResidentialAreaController;
use App\Http\Controllers\Api\People\StudentController;
use App\Http\Controllers\Api\People\StudentParentController;
use App\Http\Controllers\Api\People\TeacherController;
use App\Http\Controllers\Api\SchoolLookupController;
use App\Http\Controllers\Api\Transport\TransportController;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Login routes with tenant resolver (subdomain detection + fallback)
Route::middleware(['tenant.resolver'])->group(function () {
    // App-specific login endpoints (sets app_type in middleware)
    Route::post('dashboard/auth/login', [AuthController::class, 'login'])->middleware(['app.type:dashboard', 'throttle:login']);
    Route::post('teacher/auth/login', [AuthController::class, 'login'])->middleware(['app.type:teacher', 'throttle:login']);
    Route::post('parent/auth/login', [AuthController::class, 'login'])->middleware(['app.type:parent', 'throttle:login']);
    Route::post('student/auth/login', [AuthController::class, 'login'])->middleware(['app.type:student', 'throttle:login']);
    Route::post('transport/auth/login', [AuthController::class, 'login'])->middleware(['app.type:transport', 'throttle:login']);
});

// School lookup (public endpoint)
Route::post('school/lookup', SchoolLookupController::class);

Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware(['tenant.resolver', 'throttle:payment-webhook']);

Route::prefix('auth')->group(function () {
    Route::middleware(['auth:sanctum', 'tenant.auth'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('device-sessions', [AuthController::class, 'deviceSessions']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::delete('device-sessions/{deviceSession}/revoke', [AuthController::class, 'revokeDeviceSession']);
        Route::post('device-sessions/{deviceSession}/revoke', [AuthController::class, 'revokeDeviceSession']);
        Route::put('device/push-token', [AuthController::class, 'updatePushToken']);
    });
});

Route::middleware(['auth:sanctum', 'tenant.auth', 'signed', 'token.ability:app:dashboard,app:teacher,app:parent,app:student,app:transport'])
    ->get('files/{publicId}/download', FileDownloadController::class)
    ->name('api.files.download');

Route::middleware(['auth:sanctum', 'tenant.auth'])->group(function () {

    // 1. Shared / Common routes (requires token with any valid app ability)
    Route::middleware('token.ability:app:dashboard,app:teacher,app:parent,app:student,app:transport')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{delivery}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('notification-preferences', [NotificationController::class, 'preferences']);
        Route::patch('notification-preferences', [NotificationController::class, 'updatePreference']);

        Route::get('conversations', [ConversationThreadController::class, 'index']);
        Route::post('conversations', [ConversationThreadController::class, 'store']);
        Route::get('conversations/{thread}/messages', [ConversationThreadController::class, 'messages']);
        Route::post('conversations/{thread}/send', [ConversationThreadController::class, 'send']);
        Route::post('conversations/{thread}/messages', [ConversationThreadController::class, 'send']);
        Route::post('conversations/{thread}/read', [ConversationThreadController::class, 'markRead']);

        Route::middleware(['token.ability:app:teacher,app:parent,app:student'])->group(function () {
            Route::get('support/categories', [SupportTicketController::class, 'categories']);
            Route::get('support/tickets', [SupportTicketController::class, 'index']);
            Route::post('support/tickets', [SupportTicketController::class, 'store'])->middleware('throttle:support');
            Route::get('support/tickets/{ticket}', [SupportTicketController::class, 'show']);
            Route::post('support/tickets/{ticket}/messages', [SupportTicketController::class, 'reply'])->middleware('throttle:support');
        });
    });

    // 2. Teacher-only routes
    Route::middleware('token.ability:app:teacher')->group(function () {
        Route::get('teacher/dashboard/summary', [TeacherMobileController::class, 'summary']);
        Route::get('teacher/classes', [TeacherMobileController::class, 'classes']);
        Route::get('teacher/classes/{section}', [TeacherMobileController::class, 'classDetail']);
        Route::get('teacher/classes/{section}/students', [TeacherMobileController::class, 'classStudents']);
        Route::get('teacher/schedule', [TeacherMobileController::class, 'schedule']);
        Route::get('teacher/assessments', [TeacherMobileController::class, 'assessments']);
        Route::get('teacher/classes/{section}/gradebook', [TeacherMobileController::class, 'gradebook']);

        Route::get('teacher/attendance/sessions/{session}/roster', [TeacherAttendanceController::class, 'roster']);
        Route::put('teacher/attendance/sessions/{session}/draft', [TeacherAttendanceController::class, 'saveDraft']);
        Route::post('teacher/attendance/sessions/{session}/submit', [TeacherAttendanceController::class, 'submit']);

        Route::get('teacher/assignments', [TeacherAssignmentController::class, 'index']);
        Route::post('teacher/assignments', [TeacherAssignmentController::class, 'store']);
        Route::patch('teacher/assignments/{assignment}', [TeacherAssignmentController::class, 'update']);
        Route::delete('teacher/assignments/{assignment}', [TeacherAssignmentController::class, 'destroy']);
        Route::post('teacher/assignments/{assignment}/publish', [TeacherAssignmentController::class, 'publish']);

        Route::post('teacher/behavior-notes', [BehaviorNoteController::class, 'store']);

        Route::get('teacher/substitutions', [TeacherSubstitutionController::class, 'teacherIndex']);
        Route::post('teacher/substitutions/{substitution}/accept', [TeacherSubstitutionController::class, 'accept']);
        Route::post('teacher/substitutions/{substitution}/decline', [TeacherSubstitutionController::class, 'decline']);

        Route::post('teacher/assessments', [TeacherAssessmentController::class, 'store']);
        Route::get('teacher/assessments/{assessment}/roster', [TeacherAssessmentController::class, 'roster']);
        Route::put('teacher/assessments/{assessment}/grades', [TeacherAssessmentController::class, 'saveGrades']);
        Route::post('teacher/assessments/{assessment}/submit', [TeacherAssessmentController::class, 'submit']);
    });

    // 3. Parent-only routes
    Route::middleware('token.ability:app:parent')->group(function () {
        Route::get('parent/students', [ParentMobileController::class, 'students']);
        Route::get('parent/profile', [ParentMobileController::class, 'profile']);
        Route::patch('parent/profile', [ParentMobileController::class, 'updateProfile']);
        Route::get('parent/students/{student}/overview', [ParentMobileController::class, 'overview']);

        Route::get('parent/students/{student}/activities', [ParentActivityController::class, 'index']);
        Route::get('parent/students/{student}/activities/{activity}', [ParentActivityController::class, 'show']);
        Route::post('parent/students/{student}/activities/{activity}/register', [ParentActivityController::class, 'register']);
        Route::delete('parent/students/{student}/activities/{activity}/registration', [ParentActivityController::class, 'cancel']);

        Route::get('parent/students/{student}/finance/summary', [ParentFinanceController::class, 'summary']);
        Route::get('parent/students/{student}/invoices', [ParentFinanceController::class, 'invoices']);
        Route::get('parent/students/{student}/invoices/{invoice}', [ParentFinanceController::class, 'invoice']);
        Route::get('parent/students/{student}/wallet', [ParentFinanceController::class, 'wallet']);
        Route::get('parent/students/{student}/wallet/transactions', [ParentFinanceController::class, 'walletTransactions']);
        Route::post('parent/students/{student}/wallet/payment-token', [ParentFinanceController::class, 'walletToken'])->middleware('throttle:payment');
        Route::post('parent/students/{student}/wallet/top-up-sessions', [MobilePaymentController::class, 'createWalletTopUp'])->middleware('throttle:payment');
        Route::post('parent/students/{student}/invoices/{invoice}/payment-sessions', [MobilePaymentController::class, 'createInvoiceSession'])->middleware('throttle:payment');
        Route::get('payments/methods', [MobilePaymentController::class, 'methods']);
        Route::get('payments/{payment}', [MobilePaymentController::class, 'show']);
        Route::get('payments/{payment}/receipt', [MobilePaymentController::class, 'receipt']);

        Route::get('parent/students/{student}/attendance', ParentAttendanceController::class);
        Route::post('parent/students/{student}/medical-excuses', [MedicalExcuseController::class, 'store']);
        Route::get('parent/students/{student}/assignments', [RecipientAssignmentController::class, 'parentIndex']);
        Route::post('parent/students/{student}/assignments/{assignment}/submissions', [RecipientAssignmentController::class, 'parentSubmit']);
        Route::get('parent/students/{student}/assignments/{assignment}/attachments/{file}/download', [RecipientAssignmentController::class, 'parentDownload']);
        Route::post('parent/behavior-notes/{note}/acknowledge', [BehaviorNoteController::class, 'acknowledge']);
        Route::get('parent/students/{student}/behavior-notes', [BehaviorNoteController::class, 'parentIndex']);
        Route::post('parent/students/{student}/leave-permits', [LeavePermitController::class, 'store']);
        Route::get('parent/students/{student}/summons', [ParentSummonsController::class, 'parentIndex']);
        Route::post('parent/summons/{summons}/respond', [ParentSummonsController::class, 'respond']);
        Route::get('parent/students/{student}/reports/recent-assessments', [ParentGradeReportController::class, 'recent']);
        Route::get('parent/students/{student}/reports/terms', [ParentGradeReportController::class, 'terms']);
        Route::get('parent/students/{student}/reports/terms/{term}', [ParentGradeReportController::class, 'term']);
        Route::post('parent/students/{student}/reports/certificate', [ParentGradeReportController::class, 'certificate']);
        Route::post('parent/grade-entries/{entry}/appeals', [GradeAppealController::class, 'store']);
        Route::get('parent/students/{student}/transport/live-status', [TransportController::class, 'parentLiveStatus']);
        Route::post('parent/students/{student}/transport/opt-outs', [TransportController::class, 'optOut']);
    });

    // 4. Student-only routes
    Route::middleware('token.ability:app:student')->group(function () {
        Route::get('student/assignments', [RecipientAssignmentController::class, 'studentIndex']);
        Route::post('student/assignments/{assignment}/submissions', [RecipientAssignmentController::class, 'studentSubmit']);
        Route::get('student/assignments/{assignment}/attachments/{file}/download', [RecipientAssignmentController::class, 'studentDownload']);
    });

    // 5. Transport-only routes
    Route::middleware('token.ability:app:transport')->group(function () {
        Route::post('transport/routes', [TransportController::class, 'storeRoute']);
        Route::post('transport/routes/{route}/assignments', [TransportController::class, 'assignStudent']);
        Route::post('transport/routes/{route}/trips', [TransportController::class, 'storeTrip']);
        Route::post('transport/trips/{trip}/tracking-events', [TransportController::class, 'ingestTracking']);
        Route::post('transport/routes/{route}/alerts', [TransportController::class, 'alert']);
    });

    // 6. Dashboard/Admin routes
    Route::middleware('token.ability:app:dashboard')->group(function () {
        Route::get('academic/structure', AcademicStructureController::class);
        Route::get('admin/search', AdminSearchController::class);
        Route::get('admin/dashboard/summary', AdminDashboardSummaryController::class);
        Route::get('dashboard/activities', [SchoolActivityController::class, 'index']);
        Route::post('dashboard/activities', [SchoolActivityController::class, 'store']);
        Route::get('dashboard/activities/{activity}', [SchoolActivityController::class, 'show']);
        Route::patch('dashboard/activities/{activity}', [SchoolActivityController::class, 'update']);
        Route::delete('dashboard/activities/{activity}', [SchoolActivityController::class, 'destroy']);

        Route::get('dashboard/support/tickets', [DashboardSupportTicketController::class, 'index']);
        Route::get('dashboard/support/tickets/{ticket}', [DashboardSupportTicketController::class, 'show']);
        Route::post('dashboard/support/tickets/{ticket}/messages', [DashboardSupportTicketController::class, 'reply'])->middleware('throttle:support');
        Route::patch('dashboard/support/tickets/{ticket}', [DashboardSupportTicketController::class, 'update']);

        Route::post('academic-years', [AcademicYearController::class, 'store']);
        Route::patch('academic-years/{academicYear}', [AcademicYearController::class, 'update']);
        Route::delete('academic-years/{academicYear}', [AcademicYearController::class, 'destroy']);
        Route::post('academic-years/{academicYear}/terms', [AcademicTermController::class, 'store']);
        Route::patch('academic-terms/{academicTerm}', [AcademicTermController::class, 'update']);
        Route::post('academic-terms/{academicTerm}/activate', [AcademicTermController::class, 'activate']);
        Route::post('grade-levels', [GradeLevelController::class, 'store']);
        Route::patch('grade-levels/{gradeLevel}', [GradeLevelController::class, 'update']);
        Route::delete('grade-levels/{gradeLevel}', [GradeLevelController::class, 'destroy']);
        Route::post('subjects', [SubjectController::class, 'store']);
        Route::patch('subjects/{subject}', [SubjectController::class, 'update']);
        Route::delete('subjects/{subject}', [SubjectController::class, 'destroy']);
        Route::post('sections', [SectionController::class, 'store']);
        Route::patch('sections/{section}', [SectionController::class, 'update']);
        Route::delete('sections/{section}', [SectionController::class, 'destroy']);
        Route::get('academic/allocations', [TeacherSectionSubjectController::class, 'index']);
        Route::post('academic/allocations', [TeacherSectionSubjectController::class, 'store']);
        Route::patch('academic/allocations/{allocation}', [TeacherSectionSubjectController::class, 'update']);
        Route::delete('academic/allocations/{allocation}', [TeacherSectionSubjectController::class, 'destroy']);
        Route::get('schedule-slots', [ScheduleSlotController::class, 'index']);
        Route::post('schedule-slots', [ScheduleSlotController::class, 'store']);
        Route::patch('schedule-slots/{slot}', [ScheduleSlotController::class, 'update']);
        Route::delete('schedule-slots/{slot}', [ScheduleSlotController::class, 'destroy']);
        Route::post('academic-terms/{academicTerm}/generate-sessions', [ScheduleSlotController::class, 'generate']);

        Route::get('medical-excuses', [MedicalExcuseController::class, 'index']);
        Route::post('medical-excuses/{excuse}/approve', [MedicalExcuseController::class, 'approve']);
        Route::post('medical-excuses/{excuse}/reject', [MedicalExcuseController::class, 'reject']);

        Route::post('behavior-notes/{note}/publish', [BehaviorNoteController::class, 'publish']);
        Route::post('behavior-notes/{note}/reject', [BehaviorNoteController::class, 'reject']);
        Route::post('behavior-notes/{note}/resolve', [BehaviorNoteController::class, 'resolve']);
        Route::post('behavior-notes/{note}/recommendations', [BehaviorNoteController::class, 'recommend']);

        Route::post('leave-permits/{permit}/approve', [LeavePermitController::class, 'approve']);
        Route::post('leave-permits/{permit}/reject', [LeavePermitController::class, 'reject']);
        Route::post('leave-permits/use-token', [LeavePermitController::class, 'use']);

        Route::post('parent-summons', [ParentSummonsController::class, 'store']);
        Route::get('dashboard/teaching-sessions/{session}/available-substitutes', [TeacherSubstitutionController::class, 'available']);
        Route::post('teacher-substitutions', [TeacherSubstitutionController::class, 'store']);

        Route::post('assessments/{assessment}/approve', [TeacherAssessmentController::class, 'approve']);
        Route::post('assessments/{assessment}/publish', [TeacherAssessmentController::class, 'publish']);
        Route::post('assessments/{assessment}/lock', [TeacherAssessmentController::class, 'lock']);

        Route::get('dashboard/grade-appeals', [GradeAppealController::class, 'index']);
        Route::post('grade-appeals/{appeal}/approve', [GradeAppealController::class, 'approve']);
        Route::post('grade-appeals/{appeal}/reject', [GradeAppealController::class, 'reject']);
        Route::post('grade-appeals/{appeal}/correct', [GradeAppealController::class, 'correct']);

        Route::get('teachers', [TeacherController::class, 'index']);
        Route::post('teachers', [TeacherController::class, 'store']);
        Route::get('teachers/{teacher}', [TeacherController::class, 'show']);
        Route::patch('teachers/{teacher}', [TeacherController::class, 'update']);
        Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy']);

        Route::get('residential-areas', [ResidentialAreaController::class, 'index']);
        Route::post('residential-areas', [ResidentialAreaController::class, 'store']);
        Route::delete('residential-areas/{residentialArea}', [ResidentialAreaController::class, 'destroy']);

        Route::get('parents', [GuardianController::class, 'index']);
        Route::post('parents', [GuardianController::class, 'store']);
        Route::get('parents/{parent}', [GuardianController::class, 'show']);
        Route::patch('parents/{parent}', [GuardianController::class, 'update']);
        Route::delete('parents/{parent}', [GuardianController::class, 'destroy']);

        Route::get('students', [StudentController::class, 'index']);
        Route::post('students', [StudentController::class, 'store']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::patch('students/{student}', [StudentController::class, 'update']);
        Route::delete('students/{student}', [StudentController::class, 'destroy']);

        Route::get('students/{student}/parents', [StudentParentController::class, 'index']);
        Route::post('students/{student}/parents', [StudentParentController::class, 'store']);
        Route::patch('students/{student}/parents/{studentParent}', [StudentParentController::class, 'updateForStudent']);
        Route::delete('students/{student}/parents/{studentParent}', [StudentParentController::class, 'destroyForStudent']);
        Route::patch('student-parents/{studentParent}', [StudentParentController::class, 'update']);
        Route::delete('student-parents/{studentParent}', [StudentParentController::class, 'destroy']);

        Route::get('dashboard/finance/summary', [FinanceController::class, 'summary']);
        Route::get('dashboard/finance/invoices', [FinanceController::class, 'invoices']);
        Route::post('dashboard/finance/invoices', [FinanceController::class, 'storeInvoice']);
        Route::get('dashboard/finance/invoices/{invoice}', [FinanceController::class, 'showInvoice']);
        Route::patch('dashboard/finance/invoices/{invoice}', [FinanceController::class, 'updateInvoice']);
        Route::delete('dashboard/finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice']);
        Route::get('dashboard/finance/payments', [FinanceController::class, 'payments']);
        Route::post('dashboard/finance/payments', [FinanceController::class, 'storePayment']);
        Route::get('dashboard/finance/payments/{payment}', [FinanceController::class, 'showPayment']);
        Route::post('dashboard/finance/payments/{payment}/refunds', [FinanceController::class, 'storeRefund']);
        Route::get('dashboard/finance/refunds', [FinanceController::class, 'refunds']);
        Route::get('dashboard/finance/discounts', [FinanceController::class, 'discounts']);
        Route::post('dashboard/finance/discounts', [FinanceController::class, 'storeDiscount']);
        Route::patch('dashboard/finance/discounts/{discount}', [FinanceController::class, 'updateDiscount']);
        Route::delete('dashboard/finance/discounts/{discount}', [FinanceController::class, 'destroyDiscount']);
        Route::get('dashboard/finance/reports/collections', [FinanceController::class, 'collectionsReport']);
        Route::get('dashboard/finance/reports/outstanding', [FinanceController::class, 'outstandingReport']);
        Route::get('dashboard/finance/reports/student-statement/{student}', [FinanceController::class, 'studentStatement']);

        Route::get('dashboard/transport/summary', [DashboardTransportController::class, 'summary']);
        Route::get('dashboard/transport/routes', [DashboardTransportController::class, 'routes']);
        Route::post('dashboard/transport/routes', [DashboardTransportController::class, 'storeRoute']);
        Route::get('dashboard/transport/routes/{route}', [DashboardTransportController::class, 'showRoute']);
        Route::patch('dashboard/transport/routes/{route}', [DashboardTransportController::class, 'updateRoute']);
        Route::delete('dashboard/transport/routes/{route}', [DashboardTransportController::class, 'archiveRoute']);
        Route::get('dashboard/transport/routes/{route}/passengers', [DashboardTransportController::class, 'passengers']);
        Route::post('dashboard/transport/routes/{route}/assignments', [DashboardTransportController::class, 'assignStudent']);
        Route::patch('dashboard/transport/routes/{route}/assignments/{assignment}', [DashboardTransportController::class, 'updateAssignment']);
        Route::delete('dashboard/transport/routes/{route}/assignments/{assignment}', [DashboardTransportController::class, 'archiveAssignment']);
        Route::get('dashboard/transport/routes/{route}/events', [DashboardTransportController::class, 'events']);
        Route::post('dashboard/transport/routes/{route}/delay-alert', [DashboardTransportController::class, 'delayAlert']);
        Route::post('dashboard/transport/routes/{route}/contact-driver-log', [DashboardTransportController::class, 'contactDriverLog']);

        Route::get('dashboard/school/settings', [SchoolSettingsController::class, 'settings']);
        Route::patch('dashboard/school/settings', [SchoolSettingsController::class, 'updateSettings']);
        Route::get('dashboard/school/integrations', [SchoolSettingsController::class, 'integrations']);
        Route::patch('dashboard/school/integrations/{integration}', [SchoolSettingsController::class, 'updateIntegration']);
        Route::post('dashboard/school/integrations/{integration}/test', [SchoolSettingsController::class, 'testIntegration']);

        Route::get('dashboard/audit-logs', [AuditLogController::class, 'index']);
        Route::get('dashboard/audit-logs/{auditLog}', [AuditLogController::class, 'show']);

        Route::get('dashboard/rbac/roles', [RbacController::class, 'roles']);
        Route::post('dashboard/rbac/roles', [RbacController::class, 'storeRole']);
        Route::get('dashboard/rbac/permissions', [RbacController::class, 'permissions']);
        Route::get('dashboard/rbac/matrix', [RbacController::class, 'matrix']);
        Route::patch('dashboard/rbac/matrix', [RbacController::class, 'updateMatrix']);
        Route::get('dashboard/admin-accounts', [RbacController::class, 'adminAccounts']);
        Route::post('dashboard/admin-accounts', [RbacController::class, 'storeAdminAccount']);
        Route::patch('dashboard/admin-accounts/{account}/role', [RbacController::class, 'updateAdminRole']);
        Route::patch('dashboard/admin-accounts/{account}/status', [RbacController::class, 'updateAdminStatus']);

        Route::get('dashboard/message-templates', [MessageTemplateController::class, 'index']);
        Route::post('dashboard/message-templates', [MessageTemplateController::class, 'store']);
        Route::patch('dashboard/message-templates/{template}', [MessageTemplateController::class, 'update']);
        Route::delete('dashboard/message-templates/{template}', [MessageTemplateController::class, 'destroy']);

        Route::get('dashboard/broadcasts', [BroadcastController::class, 'index']);
        Route::post('dashboard/broadcasts', [BroadcastController::class, 'store']);
        Route::get('dashboard/broadcasts/{broadcast}', [BroadcastController::class, 'show']);
        Route::post('dashboard/broadcasts/{broadcast}/send', [BroadcastController::class, 'send']);
        Route::post('dashboard/broadcasts/{broadcast}/cancel', [BroadcastController::class, 'cancel']);
        Route::get('dashboard/broadcasts/{broadcast}/deliveries', [BroadcastController::class, 'deliveries']);

        Route::get('dashboard/behavior-notes', [BehaviorNoteDashboardController::class, 'index']);
        Route::get('dashboard/leave-permits', [LeavePermitDashboardController::class, 'index']);
        Route::get('dashboard/parent-summons', [ParentSummonsController::class, 'dashboardIndex']);
        Route::get('dashboard/teacher-substitutions', [TeacherSubstitutionController::class, 'dashboardIndex']);
        Route::get('dashboard/attendance/daily', [AttendanceDashboardController::class, 'daily']);
        Route::get('dashboard/attendance/at-risk', [AttendanceDashboardController::class, 'atRisk']);
        Route::get('dashboard/analytics/early-warnings', [AnalyticsDashboardController::class, 'earlyWarnings']);
        Route::get('dashboard/schedules', [ScheduleDashboardController::class, 'index']);
        Route::post('dashboard/schedules/conflicts/check', [ScheduleDashboardController::class, 'conflictCheck']);
        Route::get('dashboard/schedules/conflicts', [ScheduleDashboardController::class, 'globalConflictCheck']);
        Route::get('dashboard/calendar/events', [CalendarEventController::class, 'index']);
        Route::post('dashboard/calendar/events', [CalendarEventController::class, 'store']);
        Route::get('dashboard/calendar/events/{event}', [CalendarEventController::class, 'show']);
        Route::patch('dashboard/calendar/events/{event}', [CalendarEventController::class, 'update']);
        Route::delete('dashboard/calendar/events/{event}', [CalendarEventController::class, 'destroy']);
        Route::get('dashboard/assessments', [AssessmentDashboardController::class, 'index']);
        Route::get('dashboard/assessments/{assessment}', [AssessmentDashboardController::class, 'show']);
        Route::put('dashboard/assessments/{assessment}/grades', [AssessmentDashboardController::class, 'updateGrades']);
        Route::post('dashboard/assessments/{assessment}/exports', [ReportExportController::class, 'storeAssessmentExport']);
        Route::get('dashboard/reports/exports/{export}', [ReportExportController::class, 'show']);
        Route::get('dashboard/canvas-configs/{key}', [DashboardCanvasConfigController::class, 'show']);
        Route::put('dashboard/canvas-configs/{key}', [DashboardCanvasConfigController::class, 'save']);
    });
});

if (app()->environment('testing')) {
    Route::get('_test/success', function () {
        return ApiResponse::data([
            'locale' => app()->getLocale(),
        ]);
    });

    Route::post('_test/validation', function (Request $request) {
        return ApiResponse::data($request->validate([
            'name' => ['required', 'string'],
        ]));
    });

    Route::get('_test/authenticated', function () {
        return ApiResponse::data(['ok' => true]);
    })->middleware('auth');

    Route::get('_test/forbidden', function () {
        throw new AuthorizationException('This action is unauthorized.');
    });

    Route::get('_test/throttled', function () {
        return ApiResponse::data(['ok' => true]);
    })->middleware('throttle:1,1');

    Route::middleware(['auth:sanctum', 'tenant.auth'])->group(function () {
        Route::get('_test/tenant-context', function () {
            return ApiResponse::data([
                'school_id' => app(TenantContext::class)->schoolId(),
            ]);
        });

        Route::get('_test/permissions/attendance-submit', function () {
            return ApiResponse::data(['allowed' => true]);
        })->middleware('permission:attendance.submit');

        Route::get('_test/permissions/payment-refund', function () {
            return ApiResponse::data(['allowed' => true]);
        })->middleware('permission:payment.refund');
    });
}
