<?php

namespace App\Policies;

use App\Models\FileObject;
use App\Models\User;
use App\Tenancy\TenantContext;

class FileObjectPolicy
{
    public function download(User $user, FileObject $file): bool
    {
        return app(TenantContext::class)->active()
            && $file->owner_central_user_id === $user->id;
    }
}
