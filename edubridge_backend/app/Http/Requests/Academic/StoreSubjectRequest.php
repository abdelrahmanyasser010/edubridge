<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', 'unique:tenant.subjects,code'],
            'grade_level_ids' => ['nullable', 'array'],
            'grade_level_ids.*' => ['integer', 'exists:tenant.grade_levels,id'],
            'weekly_periods' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
