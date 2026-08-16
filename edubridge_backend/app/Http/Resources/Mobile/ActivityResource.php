<?php

namespace App\Http\Resources\Mobile;

use App\Models\ActivityRegistration;
use App\Models\SchoolActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof SchoolActivity) {
            throw new LogicException('ActivityResource expects a SchoolActivity model.');
        }

        /** @var ActivityRegistration|null $registration */
        $registration = $this->resource->relationLoaded('registrations') ? $this->resource->registrations->first() : null;
        $activeCount = (int) ($this->resource->active_registrations_count ?? 0);
        $capacity = $this->resource->capacity;

        return [
            'id' => (string) $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'starts_at' => $this->resource->starts_at?->toJSON(),
            'ends_at' => $this->resource->ends_at?->toJSON(),
            'location' => $this->resource->location,
            'organizer' => $this->resource->organizer,
            'capacity' => $capacity,
            'remaining_seats' => $capacity === null ? null : max(0, $capacity - $activeCount),
            'registration_opens_at' => $this->resource->registration_opens_at?->toJSON(),
            'registration_closes_at' => $this->resource->registration_closes_at?->toJSON(),
            'fee_amount_minor' => (int) $this->resource->fee_amount_minor,
            'currency' => $this->resource->currency,
            'status' => $this->resource->status,
            'registration' => $registration === null ? null : [
                'id' => (string) $registration->id,
                'status' => $registration->status,
                'invoice_id' => $registration->finance_invoice_id === null ? null : (string) $registration->finance_invoice_id,
                'registered_at' => $registration->registered_at?->toJSON(),
            ],
        ];
    }
}
