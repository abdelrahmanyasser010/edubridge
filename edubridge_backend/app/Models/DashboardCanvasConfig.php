<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'payload', 'version', 'updated_by_central_user_id'])]
class DashboardCanvasConfig extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'version' => 'integer',
            'updated_by_central_user_id' => 'integer',
        ];
    }
}
