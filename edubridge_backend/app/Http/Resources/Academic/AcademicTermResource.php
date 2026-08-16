<?php

namespace App\Http\Resources\Academic;

use App\Models\AcademicTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class AcademicTermResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof AcademicTerm) {
            throw new LogicException('AcademicTermResource expects an AcademicTerm model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'academic_year_id' => (string) $this->resource->academic_year_id,
            'name' => $this->resource->name,
            'starts_on' => Carbon::parse($this->resource->starts_on)->toDateString(),
            'ends_on' => Carbon::parse($this->resource->ends_on)->toDateString(),
            'status' => $this->resource->status,
        ];
    }
}
