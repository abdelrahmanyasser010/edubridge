<?php

namespace App\Http\Requests\Dashboard\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardStoreBusRouteRequest extends FormRequest
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
            'plate_number' => ['nullable', 'string', 'max:32'],
            'driver_phone' => ['nullable', 'string', 'max:32'],
            'supervisor_name' => ['nullable', 'string', 'max:120'],
            'estimated_arrival_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}
