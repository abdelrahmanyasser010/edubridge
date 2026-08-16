<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'title', 'body', 'type', 'default_target_type', 'is_active', 'created_by_central_user_id', 'updated_by_central_user_id'])]
class MessageTemplate extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_by_central_user_id' => 'integer',
            'updated_by_central_user_id' => 'integer',
        ];
    }
}
