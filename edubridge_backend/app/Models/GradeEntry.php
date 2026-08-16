<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['assessment_id', 'student_id', 'score', 'feedback', 'entered_by_teacher_id', 'revision'])]
class GradeEntry extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'student_id' => 'integer',
            'score' => 'decimal:2',
            'entered_by_teacher_id' => 'integer',
            'revision' => 'integer',
        ];
    }
}
