<?php

namespace App\Http\Resources\Operations;

use App\Models\LeavePermit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class LeavePermitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof LeavePermit) {
            throw new LogicException('LeavePermitResource expects a LeavePermit model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'student_id' => (string) $this->resource->student_id,
            'parent_id' => (string) $this->resource->parent_id,
            'reason' => $this->resource->reason,
            'requested_leave_at' => Carbon::parse($this->resource->requested_leave_at)->toJSON(),
            'status' => $this->resource->status,
            'review_note' => $this->resource->review_note,
            'gate_token_expires_at' => $this->resource->gate_token_expires_at === null ? null : Carbon::parse($this->resource->gate_token_expires_at)->toJSON(),
            'used_at' => $this->resource->used_at === null ? null : Carbon::parse($this->resource->used_at)->toJSON(),
        ];
    }
}
