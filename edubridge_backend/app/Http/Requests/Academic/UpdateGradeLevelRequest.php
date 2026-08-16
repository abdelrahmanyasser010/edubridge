<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeLevelRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('tenant.grade_levels', 'code')->ignore($this->route('gradeLevel'))],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }
}
