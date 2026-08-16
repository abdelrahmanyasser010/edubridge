<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
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
            'employee_number' => ['required', 'string', 'max:64', 'unique:tenant.teachers,employee_number'],
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'regex:/^\\+?[0-9\\- ]{7,20}$/'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,archived'],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer', 'exists:tenant.sections,id'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:tenant.subjects,id'],
        ];
    }
}
