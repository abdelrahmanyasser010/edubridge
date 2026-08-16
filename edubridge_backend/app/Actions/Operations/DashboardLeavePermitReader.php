<?php

namespace App\Actions\Operations;

use App\Models\LeavePermit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardLeavePermitReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function permits(array $filters): LengthAwarePaginator
    {
        return LeavePermit::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->when($filters['student_id'] ?? null, fn ($query, mixed $studentId) => $query->where('student_id', $studentId))
            ->when($filters['section_id'] ?? null, function ($query, mixed $sectionId): void {
                $query->whereIn('student_id', DB::connection('tenant')->table('students')->where('section_id', $sectionId)->select('id'));
            })
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->where('requested_leave_at', '>=', Carbon::parse((string) $from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->where('requested_leave_at', '<=', Carbon::parse((string) $to)->endOfDay()))
            ->orderByDesc('requested_leave_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->through(fn (LeavePermit $permit): array => $this->item($permit));
    }

    /** @return array<string, mixed> */
    private function item(LeavePermit $permit): array
    {
        $row = DB::connection('tenant')
            ->table('leave_permits')
            ->join('students', 'students.id', '=', 'leave_permits.student_id')
            ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
            ->join('parents', 'parents.id', '=', 'leave_permits.parent_id')
            ->where('leave_permits.id', $permit->id)
            ->first([
                'students.full_name as student_name',
                'students.admission_number',
                'students.section_id',
                'sections.name as section_name',
                'parents.full_name as parent_name',
                'parents.phone as parent_phone',
            ]);

        return [
            'id' => (string) $permit->id,
            'student_id' => (string) $permit->student_id,
            'student_name' => $row?->student_name === null ? null : (string) $row->student_name,
            'admission_number' => $row?->admission_number === null ? null : (string) $row->admission_number,
            'section_id' => $row?->section_id === null ? null : (string) $row->section_id,
            'section_name' => $row?->section_name === null ? null : (string) $row->section_name,
            'parent_id' => (string) $permit->parent_id,
            'parent_name' => $row?->parent_name === null ? null : (string) $row->parent_name,
            'parent_phone' => $row?->parent_phone === null ? null : (string) $row->parent_phone,
            'reason' => $permit->reason,
            'requested_leave_at' => Carbon::parse($permit->requested_leave_at)->toISOString(),
            'status' => $permit->status,
            'review_note' => $permit->review_note,
            'reviewed_by_central_user_id' => $permit->reviewed_by_central_user_id === null ? null : (string) $permit->reviewed_by_central_user_id,
            'reviewed_at' => $permit->reviewed_at === null ? null : Carbon::parse($permit->reviewed_at)->toISOString(),
            'gate_token_expires_at' => $permit->gate_token_expires_at === null ? null : Carbon::parse($permit->gate_token_expires_at)->toISOString(),
            'used_at' => $permit->used_at === null ? null : Carbon::parse($permit->used_at)->toISOString(),
            'available_actions' => $this->availableActions($permit),
        ];
    }

    /** @return list<string> */
    private function availableActions(LeavePermit $permit): array
    {
        return match ($permit->status) {
            LeavePermit::STATUS_PENDING => ['approve', 'reject'],
            LeavePermit::STATUS_APPROVED => ['use-token'],
            default => [],
        };
    }
}
