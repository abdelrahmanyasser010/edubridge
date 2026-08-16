<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusOptOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(['pickup', 'dropoff'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
