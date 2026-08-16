<?php

namespace App\Http\Requests\Dashboard\Canvas;

class SaveDashboardCanvasConfigRequest extends DashboardCanvasConfigKeyRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['nullable', 'string', 'max:120'],
            'payload' => ['required', 'array'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
