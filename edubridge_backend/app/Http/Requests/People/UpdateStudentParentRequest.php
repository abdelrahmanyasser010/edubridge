<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'relationship' => ['sometimes', 'string', 'max:64'],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'valid_from' => ['sometimes', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after:valid_from'],
        ];
    }
}
