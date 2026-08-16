<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:180'],
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'attachment_file_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_file_ids.*' => ['integer', 'distinct', 'exists:tenant.files,id'],
        ];
    }
}
