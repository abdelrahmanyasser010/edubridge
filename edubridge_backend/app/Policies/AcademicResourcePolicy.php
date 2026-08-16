<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AcademicResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('academic.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('academic.manage');
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
