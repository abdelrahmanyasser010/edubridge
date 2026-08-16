<?php

namespace App\Http\Requests\Dashboard\Behavior;

use Illuminate\Foundation\Http\FormRequest;

class DashboardBehaviorNoteFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:pending_review,published,acknowledged,resolved,rejected'],
            'severity' => ['nullable', 'string', 'in:info,warning,serious'],
            'student_id' => ['nullable', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
