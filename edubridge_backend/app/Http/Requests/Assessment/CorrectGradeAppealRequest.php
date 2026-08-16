<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class CorrectGradeAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:4000'],
            'correction_reason' => ['required', 'string', 'min:3', 'max:2000'],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
