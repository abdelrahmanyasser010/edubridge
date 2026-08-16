<?php

namespace App\Actions\Assessment;

use App\Actions\Notifications\NotificationManager;
use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssessmentManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationManager $notifications,
    ) {}

    /** @param array{allocation_id:int,title:string,type:string,max_score:numeric,weight?:numeric|null} $data */
    public function create(array $data, Teacher $teacher): Assessment
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($data['allocation_id']);

        if ((int) $allocation->teacher_id !== (int) $teacher->id || $allocation->status !== TeacherSectionSubject::STATUS_ACTIVE) {
            throw new NotFoundHttpException;
        }

        $assessment = Assessment::query()->create([
            'academic_term_id' => $allocation->academic_term_id,
            'allocation_id' => $allocation->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'max_score' => $data['max_score'],
            'weight' => $data['weight'] ?? 1,
            'status' => Assessment::STATUS_DRAFT,
        ]);

        $this->audit->record('assessment.created', Assessment::class, (string) $assessment->id, null, [
            'allocation_id' => (string) $assessment->allocation_id,
            'max_score' => $assessment->max_score,
            'status' => $assessment->status,
        ]);

        return $assessment->refresh();
    }

    /** @return Collection<int, Student> */
    public function roster(Assessment $assessment): Collection
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($assessment->allocation_id);

        return Student::query()
            ->where('section_id', $allocation->section_id)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @param  array{entries:list<array{student_id:int,score?:numeric|null,feedback?:string|null,revision?:int|null}>}  $data
     * @return Collection<int, GradeEntry>
     */
    public function saveEntries(Assessment $assessment, Teacher $teacher, array $data): Collection
    {
        if ($assessment->status !== Assessment::STATUS_DRAFT) {
            throw new ConflictHttpException('Assessment grades cannot be edited in the current state.');
        }

        return DB::connection('tenant')->transaction(function () use ($assessment, $teacher, $data): Collection {
            $allowedStudentIds = $this->roster($assessment)->pluck('id')->map(fn (int $id): int => $id)->all();
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

                if ($entry !== null && (int) ($entryData['revision'] ?? 0) !== (int) $entry->revision) {
                    throw new ConflictHttpException('Grade entry revision is stale.');
                }

                if ($entry === null) {
                    $entry = GradeEntry::query()->create([
                        'assessment_id' => $assessment->id,
                        'student_id' => $studentId,
                        'score' => $entryData['score'] ?? null,
                        'feedback' => $entryData['feedback'] ?? null,
                        'entered_by_teacher_id' => $teacher->id,
                        'revision' => 1,
                    ]);
                } else {
                    $entry->forceFill([
                        'score' => $entryData['score'] ?? null,
                        'feedback' => $entryData['feedback'] ?? null,
                        'entered_by_teacher_id' => $teacher->id,
                        'revision' => $entry->revision + 1,
                    ])->save();
                }

                $saved->push($entry->refresh());
            }

            $this->audit->record('grade_entries.saved', Assessment::class, (string) $assessment->id, null, [
                'entry_count' => $saved->count(),
            ]);

            return $saved;
        });
    }

    public function submitForApproval(Assessment $assessment): Assessment
    {
        if ($assessment->status !== Assessment::STATUS_DRAFT) {
            throw new ConflictHttpException('Assessment is not draft.');
        }

        $assessment->forceFill([
            'status' => Assessment::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ])->save();

        $this->audit->record('assessment.submitted', Assessment::class, (string) $assessment->id, ['status' => Assessment::STATUS_DRAFT], ['status' => $assessment->status]);

        return $assessment->refresh();
    }

    public function approve(Assessment $assessment, int $approverCentralUserId): Assessment
    {
        if ($assessment->status !== Assessment::STATUS_PENDING_APPROVAL) {
            throw new ConflictHttpException('Assessment is not pending approval.');
        }

        $assessment->forceFill([
            'status' => Assessment::STATUS_APPROVED,
            'approved_by_central_user_id' => $approverCentralUserId,
            'approved_at' => now(),
        ])->save();

        $this->audit->record('assessment.approved', Assessment::class, (string) $assessment->id, ['status' => Assessment::STATUS_PENDING_APPROVAL], ['status' => $assessment->status]);

        return $assessment->refresh();
    }

    public function publish(Assessment $assessment, int $actorCentralUserId): Assessment
    {
        if ($assessment->status !== Assessment::STATUS_APPROVED || $assessment->approved_at === null) {
            throw new ConflictHttpException('Assessment must be approved before publishing.');
        }

        $missingScores = $this->missingScoreCount($assessment);
        if ($missingScores > 0) {
            throw new ConflictHttpException(sprintf('Assessment has %d student(s) without a score.', $missingScores));
        }

        $assessment->forceFill([
            'status' => Assessment::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        $recipientIds = $this->publishedRecipientIds($assessment);
        if ($recipientIds !== []) {
            $this->notifications->create(
                'assessment.published',
                'Assessment grades published',
                $assessment->title,
                $recipientIds,
                ['assessment_id' => (string) $assessment->id],
                $actorCentralUserId,
            );
        }

        $this->audit->record('assessment.published', Assessment::class, (string) $assessment->id, null, ['recipient_count' => count($recipientIds)]);

        return $assessment->refresh();
    }

    public function lock(Assessment $assessment): Assessment
    {
        if ($assessment->published_at === null) {
            throw new ConflictHttpException('Assessment must be published before locking.');
        }

        $assessment->forceFill([
            'status' => Assessment::STATUS_LOCKED,
            'locked_at' => now(),
        ])->save();

        $this->audit->record('assessment.locked', Assessment::class, (string) $assessment->id, null, ['status' => $assessment->status]);

        return $assessment->refresh();
    }

    private function missingScoreCount(Assessment $assessment): int
    {
        $allocation = TeacherSectionSubject::query()->findOrFail($assessment->allocation_id);

        $expectedStudentIds = Student::query()
            ->where('section_id', $allocation->section_id)
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id');

        if ($expectedStudentIds->isEmpty()) {
            return 0;
        }

        $scoredStudentIds = GradeEntry::query()
            ->where('assessment_id', $assessment->id)
            ->whereIn('student_id', $expectedStudentIds->all())
            ->whereNotNull('score')
            ->pluck('student_id')
            ->unique();

        return max($expectedStudentIds->unique()->count() - $scoredStudentIds->count(), 0);
    }

    /** @return list<int> */
    private function publishedRecipientIds(Assessment $assessment): array
    {
        $studentIds = GradeEntry::query()
            ->where('assessment_id', $assessment->id)
            ->whereNotNull('score')
            ->pluck('student_id')
            ->map(fn (int $id): int => $id)
            ->all();

        if ($studentIds === []) {
            return [];
        }

        return StudentParent::query()
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->whereIn('student_parent.student_id', $studentIds)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.status', 'active')
            ->whereNotNull('parents.central_user_id')
            ->pluck('parents.central_user_id')
            ->map(fn (int $id): int => $id)
            ->unique()
            ->values()
            ->all();
    }
}
