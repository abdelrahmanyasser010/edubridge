<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof User) {
            throw new LogicException('UserResource expects a User model.');
        }

        $user = $this->resource;

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
