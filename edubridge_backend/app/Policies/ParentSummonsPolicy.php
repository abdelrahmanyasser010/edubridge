<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\ParentSummons;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ParentSummonsPolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('operations.summons_manage');
    }

    public function viewForParent(User $user, Student $student): bool
    {
        return app(TenantContext::class)->active()
            && $this->parentOwnsStudent($user, (int) $student->id);
    }

    public function respond(User $user, ParentSummons $summons): bool
    {
        return app(TenantContext::class)->active()
            && $summons->status === ParentSummons::STATUS_PENDING
            && $this->parentOwnsStudent($user, (int) $summons->student_id, (int) $summons->parent_id);
    }

    private function parentOwnsStudent(User $user, int $studentId, ?int $parentId = null): bool
    {
        $query = DB::connection('tenant')->table('parents')
            ->join('student_parent', 'student_parent.parent_id', '=', 'parents.id')
            ->where('parents.central_user_id', $user->id)
            ->where('parents.status', Guardian::STATUS_ACTIVE)
            ->where('student_parent.student_id', $studentId)
            ->where('student_parent.status', 'active');

        if ($parentId !== null) {
            $query->where('parents.id', $parentId);
        }

        return $query->exists();
    }
}
