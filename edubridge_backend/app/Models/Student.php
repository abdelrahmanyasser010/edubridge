<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

#[Fillable(['central_user_id', 'admission_number', 'full_name', 'date_of_birth', 'gender', 'grade_level_id', 'section_id', 'residential_area_id', 'status'])]
class Student extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_parent', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary', 'can_pickup', 'valid_from', 'valid_until', 'status'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'central_user_id' => 'integer',
            'grade_level_id' => 'integer',
            'section_id' => 'integer',
            'residential_area_id' => 'integer',
            'date_of_birth' => 'date:Y-m-d',
        ];
    }

    public function birthDateString(): ?string
    {
        return $this->date_of_birth === null ? null : Carbon::parse($this->date_of_birth)->toDateString();
    }
}
