<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class AssessmentPolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('grade.enter')
            && $this->teacherId($user) !== null;
    }

    public function enterGrades(User $user, Assessment $assessment): bool
    {
        $teacherId = $this->teacherId($user);

        if (! app(TenantContext::class)->active() || $teacherId === null || ! Gate::forUser($user)->allows('grade.enter')) {
            return false;
        }

        return TeacherSectionSubject::query()
            ->where('id', $assessment->allocation_id)
            ->where('teacher_id', $teacherId)
            ->where('status', TeacherSectionSubject::STATUS_ACTIVE)
            ->exists();
    }

    public function submit(User $user, Assessment $assessment): bool
    {
        return $assessment->status === Assessment::STATUS_DRAFT
            && $this->enterGrades($user, $assessment);
    }

    public function approve(User $user, Assessment $assessment): bool
    {
        return app(TenantContext::class)->active()
            && $assessment->status === Assessment::STATUS_PENDING_APPROVAL
            && Gate::forUser($user)->allows('grade.approve');
    }

    public function publish(User $user, Assessment $assessment): bool
    {
        return app(TenantContext::class)->active()
            && $assessment->status === Assessment::STATUS_APPROVED
            && $assessment->approved_at !== null
            && Gate::forUser($user)->allows('grade.publish');
    }

    public function lock(User $user, Assessment $assessment): bool
    {
        return app(TenantContext::class)->active()
            && $assessment->published_at !== null
            && $assessment->status === Assessment::STATUS_PUBLISHED
            && Gate::forUser($user)->allows('grade.lock');
    }

    private function teacherId(User $user): ?int
    {
        $teacherId = Teacher::query()
            ->where('central_user_id', $user->id)
            ->where('status', Teacher::STATUS_ACTIVE)
            ->value('id');

        return $teacherId === null ? null : (int) $teacherId;
    }
}
