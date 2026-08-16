<?php

namespace App\Http\Resources\Academic;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class AcademicYearResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof AcademicYear) {
            throw new LogicException('AcademicYearResource expects an AcademicYear model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'starts_on' => Carbon::parse($this->resource->starts_on)->toDateString(),
            'ends_on' => Carbon::parse($this->resource->ends_on)->toDateString(),
            'status' => $this->resource->status,
            'terms' => $this->resource->relationLoaded('terms')
                ? AcademicTermResource::collection($this->resource->terms)->resolve($request)
                : [],
        ];
    }
}
