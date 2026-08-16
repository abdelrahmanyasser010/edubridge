<?php

namespace App\Http\Requests\Dashboard\Rbac;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardAdminStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:active,suspended'],
        ];
    }
}
