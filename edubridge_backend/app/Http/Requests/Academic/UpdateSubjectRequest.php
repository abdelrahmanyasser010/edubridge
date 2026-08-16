<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('tenant.subjects', 'code')->ignore($this->route('subject'))],
            'grade_level_ids' => ['sometimes', 'array'],
            'grade_level_ids.*' => ['integer', 'exists:tenant.grade_levels,id'],
            'weekly_periods' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
