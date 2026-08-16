<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['central_user_id', 'type', 'channel', 'enabled'])]
class NotificationPreference extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'central_user_id' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
