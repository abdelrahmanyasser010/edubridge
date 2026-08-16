<?php

namespace App\Http\Requests\Dashboard\Schedules;

use Illuminate\Foundation\Http\FormRequest;

class DashboardScheduleConflictCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_term_id' => ['required', 'integer', 'exists:tenant.academic_terms,id'],
            'allocation_id' => ['required', 'integer', 'exists:tenant.teacher_section_subject,id'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'ignore_slot_id' => ['nullable', 'integer', 'exists:tenant.schedule_slots,id'],
        ];
    }
}
