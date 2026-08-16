<?php

namespace App\Http\Requests\Dashboard\Canvas;

use Illuminate\Foundation\Http\FormRequest;

class DashboardCanvasConfigKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9._-]{1,79}$/'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [
            ...$this->all(),
            'key' => $this->route('key'),
        ];
    }
}
