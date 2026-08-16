<?php

namespace App\Providers;

use App\Auth\PermissionChecker;
use App\Infrastructure\Payments\FakePaymentGateway;
use App\Infrastructure\Payments\MoyasarPaymentGateway;
use App\Infrastructure\Payments\PaymentGateway;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\BehaviorNote;
use App\Models\ConversationThread;
use App\Models\FileObject;
use App\Models\FinanceDiscount;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\GradeAppeal;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\LeavePermit;
use App\Models\MedicalExcuse;
use App\Models\NotificationDelivery;
use App\Models\ParentSummons;
use App\Models\PersonalAccessToken;
use App\Models\ScheduleSlot;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\TeacherSubstitution;
use App\Models\TeachingSession;
use App\Policies\AcademicResourcePolicy;
use App\Policies\AssessmentPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\AssignmentSubmissionPolicy;
use App\Policies\AttendanceRecordPolicy;
use App\Policies\BehaviorNotePolicy;
use App\Policies\ConversationThreadPolicy;
use App\Policies\FileObjectPolicy;
use App\Policies\FinancePolicy;
use App\Policies\GradeAppealPolicy;
use App\Policies\LeavePermitPolicy;
use App\Policies\MedicalExcusePolicy;
use App\Policies\NotificationDeliveryPolicy;
use App\Policies\ParentSummonsPolicy;
use App\Policies\PeopleProfilePolicy;
use App\Policies\StudentParentPolicy;
use App\Policies\TeacherSubstitutionPolicy;
use App\Policies\TeachingSessionAttendancePolicy;
use App\Support\AuditLogger;
use App\Support\IdempotencyService;
use App\Support\Outbox;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantConnectionResolver::class);
        $this->app->singleton(TenantConnectionManager::class);
        $this->app->singleton(PermissionChecker::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(IdempotencyService::class);
        $this->app->singleton(Outbox::class);
        $this->app->singleton(PaymentGateway::class, function () {
            return config('payments.provider') === 'fake' ? new FakePaymentGateway : new MoyasarPaymentGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $accessToken, bool $isValid) => $isValid
                && ! $accessToken->isRevoked()
                && $accessToken->school_id !== null,
        );

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('payment-webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('support', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        Gate::before(function ($user, string $ability) {
            $checker = app(PermissionChecker::class);

            if (! $checker->isKnownPermission($ability)) {
                return null;
            }

            return $checker->allows($user, $ability);
        });

        Gate::policy(AcademicYear::class, AcademicResourcePolicy::class);
        Gate::policy(AcademicTerm::class, AcademicResourcePolicy::class);
        Gate::policy(GradeLevel::class, AcademicResourcePolicy::class);
        Gate::policy(Section::class, AcademicResourcePolicy::class);
        Gate::policy(Subject::class, AcademicResourcePolicy::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(GradeAppeal::class, GradeAppealPolicy::class);
        Gate::policy(Assignment::class, AssignmentPolicy::class);
        Gate::policy(AssignmentSubmission::class, AssignmentSubmissionPolicy::class);
        Gate::policy(BehaviorNote::class, BehaviorNotePolicy::class);
        Gate::policy(ConversationThread::class, ConversationThreadPolicy::class);
        Gate::policy(Teacher::class, PeopleProfilePolicy::class);
        Gate::policy(Guardian::class, PeopleProfilePolicy::class);
        Gate::policy(Student::class, PeopleProfilePolicy::class);
        Gate::policy(StudentParent::class, StudentParentPolicy::class);
        Gate::policy(TeacherSectionSubject::class, AcademicResourcePolicy::class);
        Gate::policy(ScheduleSlot::class, AcademicResourcePolicy::class);
        Gate::policy(TeachingSession::class, TeachingSessionAttendancePolicy::class);
        Gate::policy(AttendanceRecord::class, AttendanceRecordPolicy::class);
        Gate::policy(MedicalExcuse::class, MedicalExcusePolicy::class);
        Gate::policy(NotificationDelivery::class, NotificationDeliveryPolicy::class);
        Gate::policy(LeavePermit::class, LeavePermitPolicy::class);
        Gate::policy(ParentSummons::class, ParentSummonsPolicy::class);
        Gate::policy(TeacherSubstitution::class, TeacherSubstitutionPolicy::class);
        Gate::policy(FileObject::class, FileObjectPolicy::class);
        Gate::policy(FinanceInvoice::class, FinancePolicy::class);
        Gate::policy(FinancePayment::class, FinancePolicy::class);
        Gate::policy(FinanceDiscount::class, FinancePolicy::class);
    }
}
