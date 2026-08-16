<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['student_id', 'parent_id', 'relationship', 'is_primary', 'can_pickup', 'valid_from', 'valid_until', 'status'])]
class StudentParent extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    protected $table = 'student_parent';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'parent_id' => 'integer',
            'is_primary' => 'boolean',
            'can_pickup' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    public function validFromString(): string
    {
        return Carbon::parse($this->valid_from)->toDateString();
    }

    public function validUntilString(): ?string
    {
        return $this->valid_until === null ? null : Carbon::parse($this->valid_until)->toDateString();
    }
}
