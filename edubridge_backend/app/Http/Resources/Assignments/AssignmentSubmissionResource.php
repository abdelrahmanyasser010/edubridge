<?php

namespace App\Http\Resources\Assignments;

use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AssignmentSubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof AssignmentSubmission) {
            throw new LogicException('AssignmentSubmissionResource expects an AssignmentSubmission model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'assignment_id' => (string) $this->resource->assignment_id,
            'student_id' => (string) $this->resource->student_id,
            'submitted_by_central_user_id' => (string) $this->resource->submitted_by_central_user_id,
            'file_id' => (string) $this->resource->file_id,
            'status' => $this->resource->status,
            'submitted_at' => $this->resource->submittedAtString(),
            'version' => $this->resource->version,
        ];
    }
}
