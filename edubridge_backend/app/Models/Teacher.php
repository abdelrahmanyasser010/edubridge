<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['central_user_id', 'employee_number', 'full_name', 'email', 'phone', 'specialization', 'status'])]
class Teacher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    public function allocations(): HasMany
    {
        return $this->hasMany(TeacherSectionSubject::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'central_user_id' => 'integer',
        ];
    }
}
