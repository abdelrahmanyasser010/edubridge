<?php

namespace App\Actions\Mobile;

use App\Actions\Finance\FinanceDashboardManager;
use App\Models\ActivityRegistration;
use App\Models\FinanceInvoice;
use App\Models\SchoolActivity;
use App\Models\Student;
use App\Support\Money;
use App\Support\ParentStudentAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ParentActivityManager
{
    public function __construct(
        private readonly ParentStudentAccess $access,
        private readonly FinanceDashboardManager $finance,
    ) {}

    /** @return LengthAwarePaginator<int, SchoolActivity> */
    public function list(Student $student, int $centralUserId, array $filters): LengthAwarePaginator
    {
        $student = $this->access->student($student->id, $centralUserId);
        $status = (string) ($filters['status'] ?? 'upcoming');

        return SchoolActivity::query()
            ->whereIn('status', [SchoolActivity::STATUS_PUBLISHED, SchoolActivity::STATUS_COMPLETED])
            ->when($status === 'upcoming', fn (Builder $query) => $query->where('starts_at', '>=', now()))
            ->when($status === 'past', fn (Builder $query) => $query->where('starts_at', '<', now()))
            ->with(['registrations' => fn ($query) => $query->where('student_id', $student->id)])
            ->withCount(['registrations as active_registrations_count' => fn ($query) => $query->where('status', '!=', ActivityRegistration::STATUS_CANCELLED)])
            ->orderBy($status === 'past' ? 'starts_at' : 'starts_at', $status === 'past' ? 'desc' : 'asc')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function detail(Student $student, SchoolActivity $activity, int $centralUserId): SchoolActivity
    {
        $student = $this->access->student($student->id, $centralUserId);

        if (! in_array($activity->status, [SchoolActivity::STATUS_PUBLISHED, SchoolActivity::STATUS_COMPLETED], true)) {
            throw new NotFoundHttpException;
        }

        return $activity->load(['registrations' => fn ($query) => $query->where('student_id', $student->id)])
            ->loadCount(['registrations as active_registrations_count' => fn ($query) => $query->where('status', '!=', ActivityRegistration::STATUS_CANCELLED)]);
    }

    public function register(Student $student, SchoolActivity $activity, int $centralUserId): ActivityRegistration
    {
        $student = $this->access->student($student->id, $centralUserId);
        $this->ensureRegistrationOpen($activity);

        return DB::connection('tenant')->transaction(function () use ($student, $activity, $centralUserId): ActivityRegistration {
            $activity = SchoolActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $this->ensureRegistrationOpen($activity);

            $existing = ActivityRegistration::query()
                ->where('school_activity_id', $activity->id)
                ->where('student_id', $student->id)
                ->first();

            if ($existing !== null && $existing->status !== ActivityRegistration::STATUS_CANCELLED) {
                return $existing->load('invoice');
            }

            $activeCount = ActivityRegistration::query()
                ->where('school_activity_id', $activity->id)
                ->where('status', '!=', ActivityRegistration::STATUS_CANCELLED)
                ->count();

            if ($activity->capacity !== null && $activeCount >= $activity->capacity) {
                throw new ConflictHttpException('Activity capacity has been reached.');
            }

            $invoice = null;
            $registrationStatus = ActivityRegistration::STATUS_CONFIRMED;

            if ((int) $activity->fee_amount_minor > 0) {
                $invoice = $this->finance->createInvoice([
                    'student_id' => $student->id,
                    'issue_date' => now()->toDateString(),
                    'due_date' => $activity->registration_closes_at?->toDateString() ?? $activity->starts_at->toDateString(),
                    'currency' => $activity->currency,
                    'notes' => 'Activity registration: '.$activity->title,
                    'lines' => [[
                        'title' => $activity->title,
                        'amount' => Money::fromMinor((int) $activity->fee_amount_minor, $activity->currency),
                    ]],
                    'discount' => 0,
                    'tax' => 0,
                ]);
                $registrationStatus = ActivityRegistration::STATUS_AWAITING_PAYMENT;
            }

            if ($existing !== null) {
                $existing->forceFill([
                    'registered_by_central_user_id' => $centralUserId,
                    'finance_invoice_id' => $invoice?->id,
                    'status' => $registrationStatus,
                    'registered_at' => now(),
                    'cancelled_at' => null,
                ])->save();

                return $existing->refresh()->load('invoice');
            }

            return ActivityRegistration::query()->create([
                'school_activity_id' => $activity->id,
                'student_id' => $student->id,
                'registered_by_central_user_id' => $centralUserId,
                'finance_invoice_id' => $invoice?->id,
                'status' => $registrationStatus,
                'registered_at' => now(),
            ])->load('invoice');
        });
    }

    public function cancel(Student $student, SchoolActivity $activity, int $centralUserId): ActivityRegistration
    {
        $student = $this->access->student($student->id, $centralUserId);

        return DB::connection('tenant')->transaction(function () use ($student, $activity): ActivityRegistration {
            $registration = ActivityRegistration::query()
                ->where('school_activity_id', $activity->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->status === ActivityRegistration::STATUS_CANCELLED) {
                return $registration;
            }

            if ($activity->starts_at->isPast()) {
                throw new ConflictHttpException('Registration cannot be cancelled after the activity starts.');
            }

            if ($registration->finance_invoice_id !== null) {
                $invoice = FinanceInvoice::query()->lockForUpdate()->findOrFail($registration->finance_invoice_id);
                if ((float) $invoice->paid_total > 0) {
                    throw new ConflictHttpException('Paid activity registrations require an administrative refund workflow.');
                }
                $this->finance->cancelInvoice($invoice);
            }

            $registration->forceFill([
                'status' => ActivityRegistration::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ])->save();

            return $registration->refresh()->load('invoice');
        });
    }

    private function ensureRegistrationOpen(SchoolActivity $activity): void
    {
        if ($activity->status !== SchoolActivity::STATUS_PUBLISHED) {
            throw new ConflictHttpException('Activity registration is not available.');
        }
        if ($activity->registration_opens_at !== null && now()->lessThan($activity->registration_opens_at)) {
            throw new ConflictHttpException('Activity registration has not opened yet.');
        }
        if ($activity->registration_closes_at !== null && now()->greaterThan($activity->registration_closes_at)) {
            throw new ConflictHttpException('Activity registration is closed.');
        }
        if (now()->greaterThanOrEqualTo($activity->starts_at)) {
            throw new ConflictHttpException('Activity registration is closed.');
        }
    }
}
