<?php

namespace App\Http\Requests\Dashboard\Rbac;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardAdminAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'role_key' => ['required', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
