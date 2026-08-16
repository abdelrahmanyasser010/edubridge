<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'central_user_id' => ['nullable', 'integer', 'min:1'],
            'admission_number' => ['required', 'string', 'max:64', 'unique:tenant.students,admission_number'],
            'full_name' => ['required', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'grade_level_id' => ['required', 'integer', 'exists:tenant.grade_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:tenant.sections,id'],
            'residential_area_id' => ['nullable', 'integer', 'exists:tenant.residential_areas,id'],
            'status' => ['nullable', 'in:active,archived'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:tenant.parents,id'],
        ];
    }
}
