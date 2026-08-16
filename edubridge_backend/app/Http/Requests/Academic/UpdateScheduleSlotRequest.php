<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'weekday' => ['sometimes', 'integer', 'between:1,7'],
            'starts_at' => ['sometimes', 'date_format:H:i'],
            'ends_at' => ['sometimes', 'date_format:H:i', 'after:starts_at'],
            'room' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
