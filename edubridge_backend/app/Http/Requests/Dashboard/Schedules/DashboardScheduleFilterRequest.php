<?php

namespace App\Http\Requests\Dashboard\Schedules;

use Illuminate\Foundation\Http\FormRequest;

class DashboardScheduleFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_term_id' => ['nullable', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'teacher_id' => ['nullable', 'integer', 'min:1'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
