<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allocation_id' => ['required', 'integer', 'exists:tenant.teacher_section_subject,id'],
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['quiz', 'exam', 'assignment', 'project', 'participation'])],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'weight' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}
