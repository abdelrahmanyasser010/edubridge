<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherSubstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'teaching_session_id' => ['required', 'integer', 'exists:tenant.teaching_sessions,id'],
            'substitute_teacher_id' => ['required', 'integer', 'exists:tenant.teachers,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
