<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $connection = 'tenant';

    protected $table = 'device_tokens';

    protected $fillable = [
        'central_user_id',
        'app_type',
        'platform',
        'token',
        'token_hash',
        'last_seen_at',
        'revoked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'central_user_id' => 'integer',
            'token' => 'encrypted',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
