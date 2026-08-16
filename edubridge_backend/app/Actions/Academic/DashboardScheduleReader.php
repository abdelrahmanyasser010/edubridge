<?php

namespace App\Actions\Academic;

use App\Models\ScheduleSlot;
use App\Models\TeacherSectionSubject;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardScheduleReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function schedules(array $filters): LengthAwarePaginator
    {
        return ScheduleSlot::query()
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'schedule_slots.allocation_id')
            ->when($filters['academic_term_id'] ?? null, fn ($query, mixed $termId) => $query->where('schedule_slots.academic_term_id', $termId))
            ->when($filters['section_id'] ?? null, fn ($query, mixed $sectionId) => $query->where('tss.section_id', $sectionId))
            ->when($filters['teacher_id'] ?? null, fn ($query, mixed $teacherId) => $query->where('tss.teacher_id', $teacherId))
            ->when($filters['weekday'] ?? null, fn ($query, mixed $weekday) => $query->where('schedule_slots.weekday', $weekday))
            ->where('schedule_slots.status', ScheduleSlot::STATUS_ACTIVE)
            ->orderBy('schedule_slots.weekday')
            ->orderBy('schedule_slots.starts_at')
            ->select('schedule_slots.*')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->through(fn (ScheduleSlot $slot): array => $this->slotItem($slot, $filters));
    }

    /** @param array<string, mixed> $data */
    public function conflictCheck(array $data): array
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($data['allocation_id']);
        $conflicts = $this->conflicts($allocation, $data, isset($data['ignore_slot_id']) ? (int) $data['ignore_slot_id'] : null);

        return [
            'has_conflict' => $conflicts !== [],
            'conflicts' => $conflicts,
        ];
    }

    /** @return array{has_conflict:bool,count:int,conflicts:list<array<string,mixed>>} */
    public function globalConflictCheck(int $academicTermId): array
    {
        $slots = DB::connection('tenant')
            ->table('schedule_slots')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'schedule_slots.allocation_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'tss.teacher_id')
            ->leftJoin('sections', 'sections.id', '=', 'tss.section_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->where('schedule_slots.academic_term_id', $academicTermId)
            ->where('schedule_slots.status', ScheduleSlot::STATUS_ACTIVE)
            ->orderBy('schedule_slots.weekday')
            ->orderBy('schedule_slots.starts_at')
            ->get([
                'schedule_slots.id', 'schedule_slots.weekday', 'schedule_slots.starts_at', 'schedule_slots.ends_at',
                'tss.teacher_id', 'teachers.full_name as teacher_name', 'tss.section_id', 'sections.name as section_name',
                'tss.subject_id', 'subjects.name as subject_name',
            ]);

        $conflicts = [];
        $count = $slots->count();
        for ($i = 0; $i < $count; $i++) {
            $left = $slots[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                $right = $slots[$j];
                if ((int) $right->weekday !== (int) $left->weekday) {
                    if ((int) $right->weekday > (int) $left->weekday) {
                        break;
                    }

                    continue;
                }
                if (! ((string) $left->starts_at < (string) $right->ends_at && (string) $left->ends_at > (string) $right->starts_at)) {
                    continue;
                }

                $types = [];
                if ((int) $left->teacher_id === (int) $right->teacher_id) {
                    $types[] = 'teacher_overlap';
                }
                if ((int) $left->section_id === (int) $right->section_id) {
                    $types[] = 'section_overlap';
                }
                if ($types === []) {
                    continue;
                }

                $conflicts[] = [
                    'types' => $types,
                    'weekday' => (int) $left->weekday,
                    'left' => [
                        'schedule_slot_id' => (string) $left->id,
                        'starts_at' => substr((string) $left->starts_at, 0, 5),
                        'ends_at' => substr((string) $left->ends_at, 0, 5),
                        'teacher_id' => (string) $left->teacher_id,
                        'teacher_name' => $left->teacher_name,
                        'section_id' => (string) $left->section_id,
                        'section_name' => $left->section_name,
                        'subject_id' => (string) $left->subject_id,
                        'subject_name' => $left->subject_name,
                    ],
                    'right' => [
                        'schedule_slot_id' => (string) $right->id,
                        'starts_at' => substr((string) $right->starts_at, 0, 5),
                        'ends_at' => substr((string) $right->ends_at, 0, 5),
                        'teacher_id' => (string) $right->teacher_id,
                        'teacher_name' => $right->teacher_name,
                        'section_id' => (string) $right->section_id,
                        'section_name' => $right->section_name,
                        'subject_id' => (string) $right->subject_id,
                        'subject_name' => $right->subject_name,
                    ],
                ];
            }
        }

        return ['has_conflict' => $conflicts !== [], 'count' => count($conflicts), 'conflicts' => $conflicts];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function slotItem(ScheduleSlot $slot, array $filters): array
    {
        $allocation = DB::connection('tenant')
            ->table('teacher_section_subject as tss')
            ->join('teachers', 'teachers.id', '=', 'tss.teacher_id')
            ->join('sections', 'sections.id', '=', 'tss.section_id')
            ->join('subjects', 'subjects.id', '=', 'tss.subject_id')
            ->leftJoin('grade_level_subject as gls', function ($join): void {
                $join->on('gls.subject_id', '=', 'tss.subject_id')
                    ->on('gls.grade_level_id', '=', 'sections.grade_level_id');
            })
            ->where('tss.id', $slot->allocation_id)
            ->first([
                'tss.teacher_id',
                'teachers.full_name as teacher_name',
                'tss.section_id',
                'sections.name as section_name',
                'tss.subject_id',
                'subjects.name as subject_name',
                'gls.weekly_periods',
            ]);

        $sessions = DB::connection('tenant')
            ->table('teaching_sessions')
            ->where('schedule_slot_id', $slot->id)
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->whereDate('session_date', '>=', Carbon::parse((string) $from)->toDateString()))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->whereDate('session_date', '<=', Carbon::parse((string) $to)->toDateString()))
            ->orderBy('session_date')
            ->limit(50)
            ->get(['id', 'session_date', 'starts_at', 'ends_at', 'status'])
            ->map(fn (object $session): array => [
                'id' => (string) $session->id,
                'session_date' => Carbon::parse((string) $session->session_date)->toDateString(),
                'starts_at' => substr((string) $session->starts_at, 0, 5),
                'ends_at' => substr((string) $session->ends_at, 0, 5),
                'status' => (string) $session->status,
            ])
            ->all();

        return [
            'schedule_slot_id' => (string) $slot->id,
            'academic_term_id' => (string) $slot->academic_term_id,
            'allocation_id' => (string) $slot->allocation_id,
            'teaching_session_ids' => array_map(fn (array $session): string => $session['id'], $sessions),
            'sessions' => $sessions,
            'weekday' => $slot->weekday,
            'starts_at' => substr($slot->starts_at, 0, 5),
            'ends_at' => substr($slot->ends_at, 0, 5),
            'room' => $slot->room,
            'status' => $slot->status,
            'teacher_id' => $allocation?->teacher_id === null ? null : (string) $allocation->teacher_id,
            'teacher_name' => $allocation?->teacher_name === null ? null : (string) $allocation->teacher_name,
            'section_id' => $allocation?->section_id === null ? null : (string) $allocation->section_id,
            'section_name' => $allocation?->section_name === null ? null : (string) $allocation->section_name,
            'subject_id' => $allocation?->subject_id === null ? null : (string) $allocation->subject_id,
            'subject_name' => $allocation?->subject_name === null ? null : (string) $allocation->subject_name,
            'weekly_periods' => $allocation?->weekly_periods === null ? null : (int) $allocation->weekly_periods,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function conflicts(TeacherSectionSubject $allocation, array $data, ?int $ignoreSlotId = null): array
    {
        $query = DB::connection('tenant')
            ->table('schedule_slots')
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'schedule_slots.allocation_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'tss.teacher_id')
            ->leftJoin('sections', 'sections.id', '=', 'tss.section_id')
            ->where('schedule_slots.academic_term_id', $data['academic_term_id'])
            ->where('schedule_slots.weekday', $data['weekday'])
            ->where('schedule_slots.status', ScheduleSlot::STATUS_ACTIVE)
            ->where(function ($query) use ($allocation): void {
                $query->where('tss.teacher_id', $allocation->teacher_id)
                    ->orWhere('tss.section_id', $allocation->section_id);
            })
            ->where('schedule_slots.starts_at', '<', $data['ends_at'])
            ->where('schedule_slots.ends_at', '>', $data['starts_at']);

        if ($ignoreSlotId !== null) {
            $query->where('schedule_slots.id', '!=', $ignoreSlotId);
        }

        return $query
            ->get([
                'schedule_slots.id',
                'schedule_slots.allocation_id',
                'schedule_slots.weekday',
                'schedule_slots.starts_at',
                'schedule_slots.ends_at',
                'tss.teacher_id',
                'teachers.full_name as teacher_name',
                'tss.section_id',
                'sections.name as section_name',
            ])
            ->map(fn (object $row): array => [
                'schedule_slot_id' => (string) $row->id,
                'allocation_id' => (string) $row->allocation_id,
                'weekday' => (int) $row->weekday,
                'starts_at' => substr((string) $row->starts_at, 0, 5),
                'ends_at' => substr((string) $row->ends_at, 0, 5),
                'teacher_id' => (string) $row->teacher_id,
                'teacher_name' => $row->teacher_name === null ? null : (string) $row->teacher_name,
                'section_id' => (string) $row->section_id,
                'section_name' => $row->section_name === null ? null : (string) $row->section_name,
            ])
            ->all();
    }
}
