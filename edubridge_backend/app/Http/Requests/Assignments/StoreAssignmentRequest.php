<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allocation_id' => ['required', 'integer', 'exists:tenant.teacher_section_subject,id'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'attachment_file_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_file_ids.*' => ['integer', 'distinct', 'exists:tenant.files,id'],
        ];
    }
}
