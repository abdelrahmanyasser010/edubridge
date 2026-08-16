<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['allocation_id', 'assigned_by_teacher_id', 'title', 'body', 'due_at', 'status', 'published_at', 'version'])]
class Assignment extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(TeacherSectionSubject::class, 'allocation_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assigned_by_teacher_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'allocation_id' => 'integer',
            'assigned_by_teacher_id' => 'integer',
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function dueAtString(): ?string
    {
        return $this->due_at === null ? null : Carbon::parse($this->due_at)->toJSON();
    }

    public function publishedAtString(): ?string
    {
        return $this->published_at === null ? null : Carbon::parse($this->published_at)->toJSON();
    }
}
