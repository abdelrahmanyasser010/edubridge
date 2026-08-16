<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('assignment.view');
    }

    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('assignment.create');
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $assignment->status === Assignment::STATUS_DRAFT
            && Gate::forUser($user)->allows('assignment.update');
    }

    public function publish(User $user, Assignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $assignment->status === Assignment::STATUS_DRAFT
            && Gate::forUser($user)->allows('assignment.publish');
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->ownsAssignment($user, $assignment)
            && $assignment->status !== Assignment::STATUS_ARCHIVED
            && Gate::forUser($user)->allows('assignment.archive');
    }

    private function ownsAssignment(User $user, Assignment $assignment): bool
    {
        if (! app(TenantContext::class)->active()) {
            return false;
        }

        return DB::connection('tenant')->table('assignments')
            ->join('teachers', 'teachers.id', '=', 'assignments.assigned_by_teacher_id')
            ->where('assignments.id', $assignment->id)
            ->where('teachers.central_user_id', $user->id)
            ->exists();
    }
}
