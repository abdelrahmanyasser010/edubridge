<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['central_user_id', 'full_name', 'email', 'phone', 'national_id_last4', 'residential_area_id', 'status'])]
class Guardian extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    protected $table = 'parents';

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_parent', 'parent_id', 'student_id')
            ->withPivot(['relationship', 'is_primary', 'can_pickup', 'valid_from', 'valid_until', 'status'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'central_user_id' => 'integer',
            'residential_area_id' => 'integer',
        ];
    }
}
