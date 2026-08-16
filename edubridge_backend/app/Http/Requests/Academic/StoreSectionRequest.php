<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grade_level_id' => ['required', 'integer', 'exists:tenant.grade_levels,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', Rule::unique('tenant.sections', 'code')->where('grade_level_id', $this->integer('grade_level_id'))],
            'room_number' => ['nullable', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
        ];
    }
}
