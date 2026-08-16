<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grade_level_id' => ['sometimes', 'integer', 'exists:tenant.grade_levels,id'],
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => ['sometimes', 'string', 'max:64'],
            'room_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'homeroom_teacher_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.teachers,id'],
        ];
    }
}
