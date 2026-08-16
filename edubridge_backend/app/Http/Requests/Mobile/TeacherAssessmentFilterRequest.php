<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherAssessmentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'pending_approval', 'approved', 'published', 'locked'])],
            'allocation_id' => ['nullable', 'integer', 'exists:tenant.teacher_section_subject,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:tenant.academic_terms,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
