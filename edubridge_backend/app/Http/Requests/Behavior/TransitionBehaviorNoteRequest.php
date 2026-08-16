<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;

class TransitionBehaviorNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
