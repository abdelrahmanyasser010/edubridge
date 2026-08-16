<?php

namespace App\Actions\Academic;

use App\Models\AcademicTerm;
use App\Models\ScheduleSlot;
use App\Models\TeacherSectionSubject;
use App\Models\TeachingSession;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ScheduleManager
{
    /** @param array<string, mixed> $data */
    public function createSlot(array $data): ScheduleSlot
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($data['allocation_id']);

        if ((int) $allocation->academic_term_id !== (int) $data['academic_term_id']) {
            throw new ConflictHttpException('Schedule slot term must match allocation term.');
        }

        $this->ensureNoConflict($allocation, $data);

        return ScheduleSlot::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateSlot(ScheduleSlot $slot, array $data): ScheduleSlot
    {
        $merged = array_merge($slot->only(['academic_term_id', 'allocation_id', 'weekday', 'starts_at', 'ends_at']), $data);
        $allocation = TeacherSectionSubject::query()->findOrFail($merged['allocation_id']);

        $this->ensureNoConflict($allocation, $merged, $slot->id);
        $slot->fill($data)->save();

        return $slot->refresh();
    }

    public function archiveSlot(ScheduleSlot $slot): ScheduleSlot
    {
        $slot->forceFill(['status' => ScheduleSlot::STATUS_ARCHIVED])->save();

        return $slot->refresh();
    }

    /** @return array{created:int,total:int} */
    public function generateSessions(AcademicTerm $term): array
    {
        $created = 0;
        $total = 0;
        $slots = ScheduleSlot::query()
            ->where('academic_term_id', $term->id)
            ->where('status', ScheduleSlot::STATUS_ACTIVE)
            ->get();

        foreach ($slots as $slot) {
            $cursor = Carbon::parse($term->starts_on)->startOfDay();
            $end = Carbon::parse($term->ends_on)->startOfDay();

            while ($cursor->lte($end)) {
                if ($cursor->dayOfWeekIso === $slot->weekday) {
                    $session = TeachingSession::query()->firstOrCreate([
                        'schedule_slot_id' => $slot->id,
                        'session_date' => $cursor->toDateString(),
                    ], [
                        'allocation_id' => $slot->allocation_id,
                        'starts_at' => $slot->starts_at,
                        'ends_at' => $slot->ends_at,
                        'status' => TeachingSession::STATUS_SCHEDULED,
                    ]);

                    $created += $session->wasRecentlyCreated ? 1 : 0;
                    $total++;
                }

                $cursor->addDay();
            }
        }

        return ['created' => $created, 'total' => $total];
    }

    /** @param array<string, mixed> $data */
    private function ensureNoConflict(TeacherSectionSubject $allocation, array $data, ?int $ignoreSlotId = null): void
    {
        $query = ScheduleSlot::query()
            ->join('teacher_section_subject as tss', 'tss.id', '=', 'schedule_slots.allocation_id')
            ->where('schedule_slots.academic_term_id', $data['academic_term_id'])
            ->where('schedule_slots.weekday', $data['weekday'])
            ->where('schedule_slots.status', ScheduleSlot::STATUS_ACTIVE)
            ->where(function ($query) use ($allocation) {
                $query->where('tss.teacher_id', $allocation->teacher_id)
                    ->orWhere('tss.section_id', $allocation->section_id);
            })
            ->where('schedule_slots.starts_at', '<', $data['ends_at'])
            ->where('schedule_slots.ends_at', '>', $data['starts_at']);

        if ($ignoreSlotId !== null) {
            $query->where('schedule_slots.id', '!=', $ignoreSlotId);
        }

        if ($query->exists()) {
            throw new ConflictHttpException('Schedule slot conflicts with an existing teacher or section slot.');
        }
    }
}
