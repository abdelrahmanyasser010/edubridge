<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_id', 'file_id'])]
class AssignmentAttachment extends Model
{
    protected $connection = 'tenant';

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assignment_id' => 'integer',
            'file_id' => 'integer',
        ];
    }
}
