<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'report_type', 'status', 'payload', 'outbox_event_id', 'download_url', 'requested_by_central_user_id', 'completed_at'])]
class ReportExport extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const TYPE_ASSESSMENT_GRADE_SHEET = 'assessment_grade_sheet';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'requested_by_central_user_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
