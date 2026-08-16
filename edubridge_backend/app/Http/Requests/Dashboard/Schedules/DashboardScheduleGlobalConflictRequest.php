<?php

namespace App\Http\Requests\Dashboard\Schedules;

use Illuminate\Foundation\Http\FormRequest;

class DashboardScheduleGlobalConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_term_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
