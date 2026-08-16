<?php

namespace App\Actions\Mobile;

use App\Models\Assignment;
use App\Models\FinanceInvoice;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentParent;
use App\Support\Money;
use App\Support\ParentStudentAccess;
use Illuminate\Support\Facades\DB;

class ParentMobileManager
{
    public function __construct(private readonly ParentStudentAccess $access) {}

    /** @return list<array<string, mixed>> */
    public function students(int $centralUserId): array
    {
        $parent = $this->access->parentForCentralUser($centralUserId);

        return StudentParent::query()
            ->join('students', 'students.id', '=', 'student_parent.student_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'students.grade_level_id')
            ->join('sections', 'sections.id', '=', 'students.section_id')
            ->where('student_parent.parent_id', $parent->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('students.status', Student::STATUS_ACTIVE)
            ->whereDate('student_parent.valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('student_parent.valid_until')->orWhereDate('student_parent.valid_until', '>=', now()->toDateString()))
            ->orderByDesc('student_parent.is_primary')
            ->orderBy('students.full_name')
            ->get([
                'students.id', 'students.full_name', 'students.admission_number', 'students.status',
                'grade_levels.id as grade_level_id', 'grade_levels.name as grade_level_name',
                'sections.id as section_id', 'sections.name as section_name',
                'student_parent.relationship', 'student_parent.is_primary',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'public_id' => null,
                'full_name' => (string) $row->full_name,
                'admission_number' => (string) $row->admission_number,
                'avatar_url' => null,
                'grade_level' => ['id' => (string) $row->grade_level_id, 'name' => (string) $row->grade_level_name],
                'section' => ['id' => (string) $row->section_id, 'name' => (string) $row->section_name],
                'relationship' => (string) $row->relationship,
                'is_default' => (bool) $row->is_primary,
                'status' => (string) $row->status,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function overview(Student $student, int $centralUserId): array
    {
        $student = $this->access->student($student->id, $centralUserId);

        $attendance = $this->attendanceSummary($student);
        $pendingAssignments = $this->pendingAssignments($student);
        $latestBehavior = DB::connection('tenant')->table('behavior_notes')
            ->where('student_id', $student->id)
            ->whereIn('status', ['published', 'acknowledged', 'resolved'])
            ->orderByDesc('published_at')
            ->first(['id', 'title', 'severity', 'body', 'status', 'published_at']);

        $latestAssessment = DB::connection('tenant')->table('grade_entries')
            ->join('assessments', 'assessments.id', '=', 'grade_entries.assessment_id')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'assessments.allocation_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->where('grade_entries.student_id', $student->id)
            ->whereIn('assessments.status', ['published', 'locked'])
            ->whereNotNull('grade_entries.score')
            ->orderByDesc('assessments.published_at')
            ->first(['assessments.id', 'assessments.title', 'assessments.max_score', 'subjects.name as subject_name', 'grade_entries.score']);

        $invoiceSummary = FinanceInvoice::query()
            ->where('student_id', $student->id)
            ->whereNotIn('status', [FinanceInvoice::STATUS_CANCELLED, FinanceInvoice::STATUS_PAID])
            ->get(['total', 'paid_total', 'currency', 'due_date']);
        $currency = (string) ($invoiceSummary->first()?->currency ?? config('payments.currency', 'SAR'));
        $wallet = DB::connection('tenant')->table('wallets')->where('student_id', $student->id)->first(['cached_balance', 'currency']);

        $unreadNotifications = DB::connection('tenant')->table('notification_deliveries')
            ->where('central_user_id', $centralUserId)
            ->whereNull('read_at')
            ->count();

        $transport = $this->transportSummary($student);

        return [
            'student' => $this->studentPayload($student),
            'attendance' => $attendance,
            'assignments' => ['pending_count' => $pendingAssignments],
            'notifications' => ['unread_count' => $unreadNotifications],
            'behavior' => ['latest_note' => $latestBehavior === null ? null : [
                'id' => (string) $latestBehavior->id,
                'title' => $latestBehavior->title,
                'severity' => $latestBehavior->severity,
                'body' => $latestBehavior->body,
                'status' => $latestBehavior->status,
                'published_at' => $latestBehavior->published_at,
            ]],
            'assessment' => ['latest' => $latestAssessment === null ? null : [
                'id' => (string) $latestAssessment->id,
                'title' => $latestAssessment->title,
                'subject_name' => $latestAssessment->subject_name,
                'score' => $latestAssessment->score,
                'max_score' => $latestAssessment->max_score,
                'percentage' => (float) $latestAssessment->max_score > 0 ? round(((float) $latestAssessment->score / (float) $latestAssessment->max_score) * 100, 2) : null,
            ]],
            'transport' => $transport,
            'finance' => [
                'currency' => $currency,
                'total_due_minor' => $invoiceSummary->sum(fn (FinanceInvoice $invoice): int => Money::toMinor(max(0, (float) $invoice->total - (float) $invoice->paid_total), $currency)),
                'unpaid_invoices_count' => $invoiceSummary->count(),
                'wallet_balance_minor' => Money::toMinor($wallet?->cached_balance ?? 0, (string) ($wallet?->currency ?? $currency)),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function profile(int $centralUserId): array
    {
        $parent = $this->access->parentForCentralUser($centralUserId);

        return $this->parentPayload($parent);
    }

    /** @param array{full_name?:string,phone?:string|null} $data */
    public function updateProfile(int $centralUserId, array $data): array
    {
        $parent = $this->access->parentForCentralUser($centralUserId);
        $parent->fill($data)->save();

        return $this->parentPayload($parent->refresh());
    }

    /** @return array<string, mixed> */
    private function parentPayload(Guardian $parent): array
    {
        return [
            'id' => (string) $parent->id,
            'full_name' => $parent->full_name,
            'email' => $parent->email,
            'phone' => $parent->phone,
            'status' => $parent->status,
        ];
    }

    /** @return array<string, mixed> */
    private function studentPayload(Student $student): array
    {
        $student->loadMissing(['gradeLevel', 'section']);

        return [
            'id' => (string) $student->id,
            'full_name' => $student->full_name,
            'admission_number' => $student->admission_number,
            'grade_level' => $student->gradeLevel === null ? null : ['id' => (string) $student->gradeLevel->id, 'name' => $student->gradeLevel->name],
            'section' => $student->section === null ? null : ['id' => (string) $student->section->id, 'name' => $student->section->name],
        ];
    }

    /** @return array{percentage:float|null,total:int,present:int,absent:int,late:int,excused:int,today_status:string|null} */
    private function attendanceSummary(Student $student): array
    {
        $counts = DB::connection('tenant')->table('attendance_records')
            ->where('student_id', $student->id)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $total = (int) $counts->sum();
        $present = (int) ($counts['present'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $todayStatus = DB::connection('tenant')->table('attendance_records')
            ->join('teaching_sessions', 'teaching_sessions.id', '=', 'attendance_records.teaching_session_id')
            ->where('attendance_records.student_id', $student->id)
            ->whereDate('teaching_sessions.session_date', now()->toDateString())
            ->orderByDesc('teaching_sessions.starts_at')
            ->value('attendance_records.status');

        return [
            'percentage' => $total === 0 ? null : round((($present + $excused) / $total) * 100, 2),
            'total' => $total,
            'present' => $present,
            'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'excused' => $excused,
            'today_status' => is_string($todayStatus) ? $todayStatus : null,
        ];
    }

    private function pendingAssignments(Student $student): int
    {
        return DB::connection('tenant')->table('assignments')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'assignments.allocation_id')
            ->leftJoin('assignment_submissions', function ($join) use ($student): void {
                $join->on('assignment_submissions.assignment_id', '=', 'assignments.id')
                    ->where('assignment_submissions.student_id', '=', $student->id);
            })
            ->where('tss.section_id', $student->section_id)
            ->where('assignments.status', Assignment::STATUS_PUBLISHED)
            ->whereNull('assignment_submissions.id')
            ->where(fn ($query) => $query->whereNull('assignments.due_at')->orWhere('assignments.due_at', '>=', now()))
            ->count();
    }

    /** @return array<string, mixed> */
    private function transportSummary(Student $student): array
    {
        $assignment = DB::connection('tenant')->table('bus_route_assignments')
            ->join('bus_routes', 'bus_routes.id', '=', 'bus_route_assignments.bus_route_id')
            ->where('bus_route_assignments.student_id', $student->id)
            ->where('bus_route_assignments.status', 'active')
            ->whereDate('bus_route_assignments.valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('bus_route_assignments.valid_until')->orWhereDate('bus_route_assignments.valid_until', '>=', now()->toDateString()))
            ->first(['bus_routes.id', 'bus_routes.name', 'bus_routes.code', 'bus_routes.driver_name']);

        if ($assignment === null) {
            return ['enabled' => false, 'route' => null, 'trip_status' => null];
        }

        $trip = DB::connection('tenant')->table('bus_trips')
            ->where('bus_route_id', $assignment->id)
            ->whereDate('service_date', now()->toDateString())
            ->orderByDesc('started_at')
            ->first(['id', 'direction', 'status']);

        return [
            'enabled' => true,
            'route' => ['id' => (string) $assignment->id, 'name' => $assignment->name, 'code' => $assignment->code, 'driver_name' => $assignment->driver_name],
            'trip_status' => $trip?->status,
            'trip_id' => $trip === null ? null : (string) $trip->id,
            'direction' => $trip?->direction,
        ];
    }
}
