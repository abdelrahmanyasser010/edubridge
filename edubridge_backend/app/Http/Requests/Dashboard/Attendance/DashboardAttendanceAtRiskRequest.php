<?php

namespace App\Http\Requests\Dashboard\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class DashboardAttendanceAtRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_term_id' => ['required', 'integer', 'min:1'], 'section_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
