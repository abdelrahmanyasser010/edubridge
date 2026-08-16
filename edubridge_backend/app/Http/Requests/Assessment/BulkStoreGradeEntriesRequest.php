<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreGradeEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1', 'max:100'],
            'entries.*.student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'entries.*.score' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'entries.*.feedback' => ['nullable', 'string', 'max:1000'],
            'entries.*.revision' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
