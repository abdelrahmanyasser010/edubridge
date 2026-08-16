<?php

namespace App\Http\Resources\Auth;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof School) {
            throw new LogicException('SchoolResource expects a School model.');
        }

        $school = $this->resource;

        return [
            'id' => (string) $school->id,
            'code' => $school->code,
            'name' => $school->name,
            'timezone' => $school->timezone,
            'locale' => $school->locale,
            'currency' => $school->currency,
        ];
    }
}
