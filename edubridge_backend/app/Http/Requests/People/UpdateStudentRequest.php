<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'central_user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'admission_number' => ['sometimes', 'string', 'max:64', Rule::unique('tenant.students', 'admission_number')->ignore($this->route('student'))],
            'full_name' => ['sometimes', 'string', 'max:160'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'grade_level_id' => ['sometimes', 'integer', 'exists:tenant.grade_levels,id'],
            'section_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.sections,id'],
            'residential_area_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.residential_areas,id'],
            'status' => ['sometimes', 'in:active,archived'],
            'parent_ids' => ['sometimes', 'nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:tenant.parents,id'],
        ];
    }
}
