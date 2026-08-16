<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', 'exists:tenant.parents,id'],
            'relationship' => ['required', 'string', 'max:64'],
            'is_primary' => ['required', 'boolean'],
            'can_pickup' => ['required', 'boolean'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
        ];
    }
}
