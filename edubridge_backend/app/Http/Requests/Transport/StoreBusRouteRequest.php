<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', Rule::unique('tenant.bus_routes', 'code')],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'driver_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
