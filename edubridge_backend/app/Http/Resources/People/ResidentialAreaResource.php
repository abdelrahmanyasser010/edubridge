<?php

namespace App\Http\Resources\People;

use App\Models\ResidentialArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ResidentialAreaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ResidentialArea) {
            throw new LogicException('ResidentialAreaResource expects a ResidentialArea model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'city' => $this->resource->city,
            'name' => $this->resource->name,
            'status' => $this->resource->status,
        ];
    }
}
