<?php

namespace App\Actions\Assessment;

use App\Actions\Notifications\NotificationManager;
use App\Models\Assessment;
use App\Models\GradeAppeal;
use App\Models\GradeEntry;
use App\Models\Guardian;
use App\Models\StudentParent;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GradeAppealManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationManager $notifications,
    ) {}

    public function create(GradeEntry $entry, int $parentCentralUserId, string $reason): GradeAppeal
    {
        $parent = Guardian::query()->where('central_user_id', $parentCentralUserId)->where('status', Guardian::STATUS_ACTIVE)->first();

        if ($parent === null || ! $this->ownsStudent((int) $parent->id, (int) $entry->student_id) || ! $this->isPublished($entry)) {
            throw new NotFoundHttpException;
        }

        if (GradeAppeal::query()->where('grade_entry_id', $entry->id)->where('status', GradeAppeal::STATUS_OPEN)->exists()) {
            throw new ConflictHttpException('Grade entry already has an open appeal.');
        }

        $appeal = GradeAppeal::query()->create([
            'grade_entry_id' => $entry->id,
            'student_id' => $entry->student_id,
            'parent_id' => $parent->id,
            'reason' => $reason,
            'status' => GradeAppeal::STATUS_OPEN,
        ]);

        $this->audit->record('grade_appeal.created', GradeAppeal::class, (string) $appeal->id, null, ['grade_entry_id' => (string) $entry->id]);

        return $appeal->refresh();
    }

    public function approve(GradeAppeal $appeal, int $reviewerCentralUserId, ?string $note): GradeAppeal
    {
        return $this->review($appeal, GradeAppeal::STATUS_APPROVED, $reviewerCentralUserId, $note);
    }

    public function reject(GradeAppeal $appeal, int $reviewerCentralUserId, ?string $note): GradeAppeal
    {
        return $this->review($appeal, GradeAppeal::STATUS_REJECTED, $reviewerCentralUserId, $note);
    }

    /** @param array{score:int|float|string,feedback?:string|null,correction_reason:string,revision:int} $data */
    public function correct(GradeAppeal $appeal, array $data, int $actorCentralUserId): GradeAppeal
    {
        return DB::connection('tenant')->transaction(function () use ($appeal, $data, $actorCentralUserId): GradeAppeal {
            $appeal = GradeAppeal::query()->lockForUpdate()->findOrFail($appeal->id);
            if ($appeal->status !== GradeAppeal::STATUS_APPROVED) {
                throw new ConflictHttpException('Only an approved grade appeal can be corrected.');
            }

            $entry = GradeEntry::query()->lockForUpdate()->findOrFail($appeal->grade_entry_id);
            $assessment = Assessment::query()->findOrFail($entry->assessment_id);
            if ($assessment->published_at === null || ! in_array($assessment->status, [Assessment::STATUS_PUBLISHED, Assessment::STATUS_LOCKED], true)) {
                throw new ConflictHttpException('The assessment must be published before a correction can be applied.');
            }

            if ((int) $entry->revision !== (int) $data['revision']) {
                throw new ConflictHttpException('The grade entry was changed by another operation. Refresh and try again.');
            }

            $score = (float) $data['score'];
            if ($score > (float) $assessment->max_score) {
                throw new ConflictHttpException('Corrected score cannot exceed the assessment maximum score.');
            }

            $before = [
                'score' => $entry->score,
                'feedback' => $entry->feedback,
                'revision' => (int) $entry->revision,
            ];

            $entry->forceFill([
                'score' => $score,
                'feedback' => $data['feedback'] ?? $entry->feedback,
                'revision' => ((int) $entry->revision) + 1,
            ])->save();

            $appeal->forceFill([
                'status' => GradeAppeal::STATUS_CORRECTED,
            ])->save();

            $this->audit->record('grade_appeal.grade_corrected', GradeEntry::class, (string) $entry->id, $before, [
                'score' => $entry->score,
                'feedback' => $entry->feedback,
                'revision' => (int) $entry->revision,
                'appeal_id' => (string) $appeal->id,
                'correction_reason' => $data['correction_reason'],
                'appeal_status' => GradeAppeal::STATUS_CORRECTED,
            ]);

            $parent = Guardian::query()->find($appeal->parent_id);
            if ($parent?->central_user_id !== null) {
                $this->notifications->create(
                    'grade.corrected_after_appeal',
                    'تم تصحيح الدرجة',
                    'تمت مراجعة الاعتراض وتصحيح الدرجة المنشورة. يمكنك مراجعة النتيجة المحدثة من التطبيق.',
                    [(int) $parent->central_user_id],
                    [
                        'appeal_id' => (string) $appeal->id,
                        'grade_entry_id' => (string) $entry->id,
                        'assessment_id' => (string) $assessment->id,
                    ],
                    $actorCentralUserId,
                );
            }

            return $appeal->refresh();
        });
    }

    private function review(GradeAppeal $appeal, string $status, int $reviewerCentralUserId, ?string $note): GradeAppeal
    {
        return DB::connection('tenant')->transaction(function () use ($appeal, $status, $reviewerCentralUserId, $note): GradeAppeal {
            $appeal = GradeAppeal::query()->lockForUpdate()->findOrFail($appeal->id);

            if ($appeal->status !== GradeAppeal::STATUS_OPEN) {
                throw new ConflictHttpException('Grade appeal has already been reviewed.');
            }

            $appeal->forceFill([
                'status' => $status,
                'reviewed_by_central_user_id' => $reviewerCentralUserId,
                'review_note' => $note,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->record('grade_appeal.'.$status, GradeAppeal::class, (string) $appeal->id, ['status' => GradeAppeal::STATUS_OPEN], ['status' => $status]);

            return $appeal->refresh();
        });
    }

    private function ownsStudent(int $parentId, int $studentId): bool
    {
        return StudentParent::query()->where('parent_id', $parentId)->where('student_id', $studentId)->where('status', StudentParent::STATUS_ACTIVE)->exists();
    }

    private function isPublished(GradeEntry $entry): bool
    {
        return Assessment::query()->where('id', $entry->assessment_id)->where('status', Assessment::STATUS_PUBLISHED)->whereNotNull('published_at')->exists();
    }
}
