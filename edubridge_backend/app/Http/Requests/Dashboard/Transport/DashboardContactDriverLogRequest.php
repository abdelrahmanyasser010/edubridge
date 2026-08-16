<?php

namespace App\Http\Requests\Dashboard\Transport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardContactDriverLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['called', 'no_answer', 'message_sent', 'wrong_number'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
