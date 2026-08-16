<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject', 'created_by_central_user_id', 'status'])]
class ConversationThread extends Model
{
    public const STATUS_ACTIVE = 'active';

    protected $connection = 'tenant';

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_by_central_user_id' => 'integer',
        ];
    }
}
