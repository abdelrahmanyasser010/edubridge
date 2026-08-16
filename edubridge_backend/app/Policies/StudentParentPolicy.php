<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class StudentParentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('people.view');
    }

    public function create(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('people.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    private function tenantActive(): bool
    {
        return app(TenantContext::class)->active();
    }
}
