<?php

namespace App\Actions\Behavior;

use App\Models\BehaviorNote;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardBehaviorNoteReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function notes(array $filters): LengthAwarePaginator
    {
        return BehaviorNote::query()
            ->with(['timeline', 'recommendations'])
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, mixed $severity) => $query->where('severity', $severity))
            ->when($filters['student_id'] ?? null, fn ($query, mixed $studentId) => $query->where('student_id', $studentId))
            ->when($filters['section_id'] ?? null, function ($query, mixed $sectionId): void {
                $query->whereIn('student_id', DB::connection('tenant')->table('students')->where('section_id', $sectionId)->select('id'));
            })
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->where('created_at', '>=', Carbon::parse((string) $from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->where('created_at', '<=', Carbon::parse((string) $to)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->through(fn (BehaviorNote $note): array => $this->item($note));
    }

    /** @return array<string, mixed> */
    private function item(BehaviorNote $note): array
    {
        $student = DB::connection('tenant')
            ->table('students')
            ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
            ->where('students.id', $note->student_id)
            ->first([
                'students.full_name as student_name',
                'students.admission_number',
                'students.section_id',
                'sections.name as section_name',
            ]);

        return [
            'id' => (string) $note->id,
            'student_id' => (string) $note->student_id,
            'student_name' => $student?->student_name === null ? null : (string) $student->student_name,
            'admission_number' => $student?->admission_number === null ? null : (string) $student->admission_number,
            'section_id' => $student?->section_id === null ? null : (string) $student->section_id,
            'section_name' => $student?->section_name === null ? null : (string) $student->section_name,
            'allocation_id' => (string) $note->allocation_id,
            'created_by_teacher_id' => (string) $note->created_by_teacher_id,
            'title' => $note->title,
            'body' => $note->body,
            'severity' => $note->severity,
            'status' => $note->status,
            'published_at' => $note->publishedAtString(),
            'created_at' => Carbon::parse((string) $note->created_at)->toISOString(),
            'version' => $note->version,
            'available_actions' => $this->availableActions($note),
            'timeline_count' => $note->timeline->count(),
            'recommendations_count' => $note->recommendations->count(),
        ];
    }

    /** @return list<string> */
    private function availableActions(BehaviorNote $note): array
    {
        return match ($note->status) {
            BehaviorNote::STATUS_PENDING_REVIEW => ['publish', 'reject'],
            BehaviorNote::STATUS_PUBLISHED, BehaviorNote::STATUS_ACKNOWLEDGED => ['recommendations', 'resolve'],
            BehaviorNote::STATUS_RESOLVED => ['recommendations'],
            default => [],
        };
    }
}
