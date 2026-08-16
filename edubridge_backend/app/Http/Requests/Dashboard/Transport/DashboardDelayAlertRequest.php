<?php

namespace App\Http\Requests\Dashboard\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardDelayAlertRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:255'],
            'delay_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'channels' => ['nullable', 'array', 'min:1'],
            'channels.*' => [Rule::in(['database', 'push', 'sms'])],
        ];
    }
}
