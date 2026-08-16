<?php

namespace App\Http\Resources\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class DeviceSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof PersonalAccessToken) {
            throw new LogicException('DeviceSessionResource expects a PersonalAccessToken model.');
        }

        $deviceSession = $this->resource;

        return [
            'id' => (string) $deviceSession->id,
            'school_id' => $deviceSession->school_id === null ? null : (string) $deviceSession->school_id,
            'device_id' => $deviceSession->device_id,
            'app_type' => $deviceSession->app_type,
            'device_name' => $deviceSession->device_name,
            'last_used_at' => $deviceSession->last_used_at?->toISOString(),
            'expires_at' => $deviceSession->expires_at?->toISOString(),
            'revoked_at' => $deviceSession->revoked_at?->toISOString(),
        ];
    }
}
