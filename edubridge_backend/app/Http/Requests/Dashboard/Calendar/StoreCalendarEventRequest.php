<?php

namespace App\Http\Requests\Dashboard\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:event,holiday,exam,meeting,deadline'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'audience_type' => ['required', 'string', 'in:all,grade_level,section,roles,custom_users'],
            'audience_ids' => ['nullable', 'array', 'max:500'],
            'audience_ids.*' => ['required', 'string', 'max:128'],
            'location' => ['nullable', 'string', 'max:180'],
        ];
    }
}
