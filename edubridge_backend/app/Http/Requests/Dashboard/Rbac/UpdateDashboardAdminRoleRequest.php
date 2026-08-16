<?php

namespace App\Http\Requests\Dashboard\Rbac;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_key' => ['required', 'string', 'max:64'],
        ];
    }
}
