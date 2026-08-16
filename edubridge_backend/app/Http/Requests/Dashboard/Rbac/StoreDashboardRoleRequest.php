<?php

namespace App\Http\Requests\Dashboard\Rbac;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:120'],
            'permissions' => ['nullable', 'array', 'max:200'],
            'permissions.*' => ['required', 'string', 'max:128'],
        ];
    }
}
