<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
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
            'employee_number' => ['sometimes', 'string', 'max:64', Rule::unique('tenant.teachers', 'employee_number')->ignore($this->route('teacher'))],
            'full_name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'nullable', 'email', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'regex:/^\\+?[0-9\\- ]{7,20}$/'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:active,archived'],
            'section_ids' => ['sometimes', 'nullable', 'array'],
            'section_ids.*' => ['integer', 'exists:tenant.sections,id'],
            'subject_ids' => ['sometimes', 'nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:tenant.subjects,id'],
        ];
    }
}
