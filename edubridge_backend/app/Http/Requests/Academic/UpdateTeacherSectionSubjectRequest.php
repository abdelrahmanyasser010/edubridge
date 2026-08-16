<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherSectionSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'weekly_quota' => ['sometimes', 'integer', 'min:1', 'max:40'],
            'is_homeroom' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,archived'],
        ];
    }
}
