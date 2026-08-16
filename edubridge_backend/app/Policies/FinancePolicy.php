<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class FinancePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('finance.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('finance.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function recordPayment(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('finance.payments.record');
    }

    public function viewReports(User $user): bool
    {
        return $this->tenantActive() && Gate::forUser($user)->allows('finance.reports.view');
    }

    private function tenantActive(): bool
    {
        return app(TenantContext::class)->active();
    }
}
