<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'starts_at', 'ends_at', 'location', 'organizer', 'capacity', 'fee_amount_minor', 'currency', 'registration_opens_at', 'registration_closes_at', 'status'])]
class SchoolActivity extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'tenant';

    public function registrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'fee_amount_minor' => 'integer',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }
}
