<?php

namespace App\Http\Resources\Attendance;

use App\Models\AttendanceDraft;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AttendanceDraftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof AttendanceDraft) {
            throw new LogicException('AttendanceDraftResource expects an AttendanceDraft model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'teaching_session_id' => (string) $this->resource->teaching_session_id,
            'teacher_id' => (string) $this->resource->teacher_id,
            'records' => $this->resource->records,
            'version' => $this->resource->version,
        ];
    }
}
