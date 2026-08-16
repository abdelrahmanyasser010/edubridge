<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentParent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ParentStudentAccess
{
    public function parentForCentralUser(int $centralUserId): Guardian
    {
        return Guardian::query()
            ->where('central_user_id', $centralUserId)
            ->where('status', Guardian::STATUS_ACTIVE)
            ->first() ?? throw new NotFoundHttpException;
    }

    public function student(int $studentId, int $centralUserId): Student
    {
        $student = Student::query()->findOrFail($studentId);
        $parent = $this->parentForCentralUser($centralUserId);

        if (! $this->owns($parent->id, $student->id)) {
            throw new NotFoundHttpException;
        }

        return $student;
    }

    public function owns(int $parentId, int $studentId): bool
    {
        return StudentParent::query()
            ->where('student_id', $studentId)
            ->where('parent_id', $parentId)
            ->where('status', StudentParent::STATUS_ACTIVE)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()))
            ->exists();
    }
}
