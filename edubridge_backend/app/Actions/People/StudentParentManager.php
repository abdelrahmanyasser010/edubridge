<?php

namespace App\Actions\People;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StudentParentManager
{
    /** @param array<string, mixed> $data */
    public function attach(Student $student, array $data): StudentParent
    {
        $guardian = Guardian::query()->findOrFail($data['parent_id']);
        $this->ensureActiveProfiles($student, $guardian);
        $this->ensureSinglePrimary($student, (bool) ($data['is_primary'] ?? false));

        return StudentParent::query()->create(array_merge($data, [
            'student_id' => $student->id,
        ]))->refresh();
    }

    /** @param array<string, mixed> $data */
    public function update(StudentParent $link, array $data): StudentParent
    {
        $student = Student::query()->findOrFail($link->student_id);

        if (($data['is_primary'] ?? false) === true && ! $link->is_primary) {
            $this->ensureSinglePrimary($student, true);
        }

        $link->fill($data)->save();

        return $link->refresh();
    }

    public function archive(StudentParent $link): StudentParent
    {
        $link->forceFill(['status' => StudentParent::STATUS_ARCHIVED])->save();

        return $link->refresh();
    }

    private function ensureActiveProfiles(Student $student, Guardian $guardian): void
    {
        if ($student->status !== Student::STATUS_ACTIVE || $guardian->status !== Guardian::STATUS_ACTIVE) {
            throw new ConflictHttpException('Only active student and parent profiles can be linked.');
        }
    }

    private function ensureSinglePrimary(Student $student, bool $requestedPrimary): void
    {
        if (! $requestedPrimary) {
            return;
        }

        $exists = StudentParent::query()
            ->where('student_id', $student->id)
            ->where('is_primary', true)
            ->where('status', StudentParent::STATUS_ACTIVE)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'is_primary' => ['A student can have only one active primary guardian.'],
            ]);
        }
    }
}
