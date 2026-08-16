<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'title', 'body', 'data', 'actor_central_user_id'])]
class NotificationMessage extends Model
{
    protected $connection = 'tenant';

    protected $table = 'notifications';

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notification_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'actor_central_user_id' => 'integer',
        ];
    }
}
