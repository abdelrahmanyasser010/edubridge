<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_central_user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip_address', 'request_id'])]
class AuditLog extends Model
{
    protected $connection = 'tenant';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_central_user_id' => 'integer',
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
