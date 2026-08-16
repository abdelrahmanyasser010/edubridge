<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Connection('central')]
#[Fillable(['public_id', 'code', 'name', 'timezone', 'locale', 'currency', 'status'])]
class School extends Model
{
    // Central platform model. Tenant operational data lives in each school database.

    public function generateInvitationToken(int $expiresInDays = 7): string
    {
        return Crypt::encryptString(json_encode([
            'school_id' => $this->id,
            'expires_at' => now()->addDays($expiresInDays)->timestamp,
        ]));
    }
}
