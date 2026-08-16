<?php

namespace App\Http\Requests\Dashboard\SchoolSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', Rule::in(['not_configured', 'connected', 'disabled', 'error'])],
            'api_key' => ['nullable', 'string', 'max:500'],
            'config' => ['nullable', 'array'],
            'config.endpoint_url' => ['nullable', 'url', 'max:500'],
            'config.sender_id' => ['nullable', 'string', 'max:120'],
            'config.webhook_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
