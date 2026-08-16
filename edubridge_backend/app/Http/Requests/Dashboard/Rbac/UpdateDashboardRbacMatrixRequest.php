<?php

namespace App\Http\Requests\Dashboard\Rbac;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardRbacMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1', 'max:50'],
            'roles.*.key' => ['required', 'string', 'max:64'],
            'roles.*.permissions' => ['required', 'array', 'max:200'],
            'roles.*.permissions.*' => ['required', 'string', 'max:128'],
        ];
    }
}
