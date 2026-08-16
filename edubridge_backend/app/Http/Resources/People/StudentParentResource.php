<?php

namespace App\Http\Resources\People;

use App\Models\StudentParent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class StudentParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof StudentParent) {
            throw new LogicException('StudentParentResource expects a StudentParent model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'student_id' => (string) $this->resource->student_id,
            'parent_id' => (string) $this->resource->parent_id,
            'relationship' => $this->resource->relationship,
            'is_primary' => $this->resource->is_primary,
            'can_pickup' => $this->resource->can_pickup,
            'valid_from' => $this->resource->validFromString(),
            'valid_until' => $this->resource->validUntilString(),
            'status' => $this->resource->status,
        ];
    }
}
