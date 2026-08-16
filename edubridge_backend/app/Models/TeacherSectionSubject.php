<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academic_term_id', 'teacher_id', 'section_id', 'subject_id', 'weekly_quota', 'is_homeroom', 'status'])]
class TeacherSectionSubject extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    protected $table = 'teacher_section_subject';

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'academic_term_id' => 'integer',
            'teacher_id' => 'integer',
            'section_id' => 'integer',
            'subject_id' => 'integer',
            'weekly_quota' => 'integer',
            'is_homeroom' => 'boolean',
        ];
    }
}
