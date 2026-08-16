<?php

namespace App\Http\Requests\Dashboard\SchoolSettings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'school' => ['sometimes', 'array'],
            'school.name' => ['sometimes', 'string', 'max:255'],
            'school.timezone' => ['sometimes', 'string', 'timezone'],
            'school.locale' => ['sometimes', 'string', 'max:8'],
            'school.currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'academic' => ['sometimes', 'array'],
            'academic.active_academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.academic_years,id'],
            'academic.active_term_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.academic_terms,id'],
            'attendance' => ['sometimes', 'array'],
            'attendance.late_after_minutes' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'attendance.absence_warning_threshold' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'notifications' => ['sometimes', 'array'],
            'notifications.push_enabled' => ['sometimes', 'boolean'],
            'notifications.sms_enabled' => ['sometimes', 'boolean'],
            'notifications.email_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
