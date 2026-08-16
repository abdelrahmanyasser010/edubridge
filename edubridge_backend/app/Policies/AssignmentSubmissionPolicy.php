<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignmentSubmissionPolicy
{
    public function viewForStudent(User $user, Assignment $assignment, Student $student): bool
    {
        return $this->canAccessStudentAssignment($user, $assignment, $student)
            && Gate::forUser($user)->allows('assignment.view');
    }

    public function submitForStudent(User $user, Assignment $assignment, Student $student): bool
    {
        return $this->canAccessStudentAssignment($user, $assignment, $student)
            && Gate::forUser($user)->allows('assignment.view');
    }

    private function canAccessStudentAssignment(User $user, Assignment $assignment, Student $student): bool
    {
        if (! app(TenantContext::class)->active() || $assignment->status !== Assignment::STATUS_PUBLISHED) {
            return false;
        }

        $matchesSection = DB::connection('tenant')->table('assignments')
            ->join('teacher_section_subject', 'teacher_section_subject.id', '=', 'assignments.allocation_id')
            ->where('assignments.id', $assignment->id)
            ->where('teacher_section_subject.section_id', $student->section_id)
            ->exists();

        if (! $matchesSection) {
            return false;
        }

        if ($student->central_user_id === $user->id) {
            return true;
        }

        return DB::connection('tenant')->table('student_parent')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $student->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.central_user_id', $user->id)
            ->where('parents.status', 'active')
            ->whereDate('student_parent.valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('student_parent.valid_until')
                ->orWhereDate('student_parent.valid_until', '>=', now()->toDateString()))
            ->exists();
    }
}
