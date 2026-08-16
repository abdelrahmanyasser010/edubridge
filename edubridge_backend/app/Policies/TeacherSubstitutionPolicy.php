<?php

namespace App\Policies;

use App\Models\TeacherSubstitution;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeacherSubstitutionPolicy
{
    public function create(User $user): bool
    {
        return app(TenantContext::class)->active()
            && Gate::forUser($user)->allows('operations.substitution_manage');
    }

    public function viewForTeacher(User $user): bool
    {
        return app(TenantContext::class)->active()
            && $this->teacherId($user) !== null;
    }

    public function respond(User $user, TeacherSubstitution $substitution): bool
    {
        return app(TenantContext::class)->active()
            && $substitution->status === TeacherSubstitution::STATUS_PENDING
            && $this->teacherId($user) === (int) $substitution->substitute_teacher_id;
    }

    private function teacherId(User $user): ?int
    {
        $teacherId = DB::connection('tenant')->table('teachers')
            ->where('central_user_id', $user->id)
            ->where('status', 'active')
            ->value('id');

        return $teacherId === null ? null : (int) $teacherId;
    }
}
