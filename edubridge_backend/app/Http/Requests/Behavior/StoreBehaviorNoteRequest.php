<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBehaviorNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'allocation_id' => ['required', 'integer', 'exists:tenant.teacher_section_subject,id'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(['info', 'warning', 'serious'])],
        ];
    }
}
