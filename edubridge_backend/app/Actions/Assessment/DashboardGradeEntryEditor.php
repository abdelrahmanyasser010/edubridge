<?php

namespace App\Actions\Assessment;

use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\Student;
use App\Models\TeacherSectionSubject;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardGradeEntryEditor
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{entries:list<array{student_id:int,score?:numeric|null,feedback?:string|null,note?:string|null,revision?:int|null}>}  $data
     * @return Collection<int, GradeEntry>
     */
    public function update(Assessment $assessment, array $data): Collection
    {
        if ($assessment->locked_at !== null || $assessment->published_at !== null || $assessment->status === Assessment::STATUS_LOCKED) {
            throw new ConflictHttpException('Published or locked assessment grades cannot be edited from the dashboard.');
        }

        $allocation = TeacherSectionSubject::query()->findOrFail($assessment->allocation_id);

        return DB::connection('tenant')->transaction(function () use ($assessment, $allocation, $data): Collection {
            $allowedStudentIds = Student::query()
                ->where('section_id', $allocation->section_id)
                ->where('status', Student::STATUS_ACTIVE)
                ->pluck('id')
                ->map(fn (int $id): int => $id)
                ->all();

            $saved = new Collection;

            foreach ($data['entries'] as $entryData) {
                $studentId = (int) $entryData['student_id'];
                if (! in_array($studentId, $allowedStudentIds, true)) {
                    throw new NotFoundHttpException;
                }

                if (array_key_exists('score', $entryData) && $entryData['score'] !== null && (float) $entryData['score'] > (float) $assessment->max_score) {
                    throw new ConflictHttpException('Grade score cannot exceed assessment max_score.');
                }

                $entry = GradeEntry::query()
                    ->where('assessment_id', $assessment->id)
                    ->where('student_id', $studentId)
                    ->lockForUpdate()
                    ->first();

                if ($entry instanceof GradeEntry && isset($entryData['revision']) && (int) $entryData['revision'] !== (int) $entry->revision) {
                    throw new ConflictHttpException('Grade entry revision is stale.');
                }

                $feedback = $entryData['feedback'] ?? ($entryData['note'] ?? null);
                if (! $entry instanceof GradeEntry) {
                    $entry = GradeEntry::query()->create([
                        'assessment_id' => $assessment->id,
                        'student_id' => $studentId,
                        'score' => $entryData['score'] ?? null,
                        'feedback' => $feedback,
                        'entered_by_teacher_id' => $allocation->teacher_id,
                        'revision' => 1,
                    ]);
                } else {
                    $entry->forceFill([
                        'score' => $entryData['score'] ?? null,
                        'feedback' => $feedback,
                        'entered_by_teacher_id' => $allocation->teacher_id,
                        'revision' => $entry->revision + 1,
                    ])->save();
                }

                $saved->push($entry->refresh());
            }

            $this->audit->record('dashboard.grade_entries.updated', Assessment::class, (string) $assessment->id, null, [
                'entry_count' => $saved->count(),
                'status' => $assessment->status,
            ]);

            return $saved;
        });
    }
}
