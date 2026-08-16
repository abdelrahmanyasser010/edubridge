<?php

namespace App\Http\Requests\Dashboard\AuditLogs;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:128'],
            'entity_type' => ['nullable', 'string', 'max:128'],
            'entity_id' => ['nullable', 'string', 'max:128'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
