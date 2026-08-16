<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;

class StoreBehaviorRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
