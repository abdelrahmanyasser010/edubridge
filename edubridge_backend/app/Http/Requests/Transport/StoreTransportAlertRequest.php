<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransportAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bus_trip_id' => ['nullable', 'integer', 'exists:tenant.bus_trips,id'],
            'type' => ['required', Rule::in(['delay', 'cancelled', 'info'])],
            'message' => ['required', 'string', 'max:255'],
        ];
    }
}
