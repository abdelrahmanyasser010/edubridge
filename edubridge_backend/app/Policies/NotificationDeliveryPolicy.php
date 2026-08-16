<?php

namespace App\Policies;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Tenancy\TenantContext;

class NotificationDeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->active();
    }

    public function markRead(User $user, NotificationDelivery $delivery): bool
    {
        return app(TenantContext::class)->active()
            && $delivery->central_user_id === $user->id
            && $delivery->channel === NotificationDelivery::CHANNEL_DATABASE;
    }
}
