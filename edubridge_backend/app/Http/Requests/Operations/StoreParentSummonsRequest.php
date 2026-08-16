<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreParentSummonsRequest extends FormRequest
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
            'parent_id' => ['required', 'integer', 'exists:tenant.parents,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
