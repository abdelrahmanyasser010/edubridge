<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondParentSummonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'response' => ['required', Rule::in(['accepted', 'declined'])],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
