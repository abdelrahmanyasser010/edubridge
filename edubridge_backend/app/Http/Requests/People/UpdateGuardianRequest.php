<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'central_user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'full_name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'nullable', 'email', 'max:160'],
            'phone' => ['sometimes', 'regex:/^\\+?[0-9\\- ]{7,20}$/'],
            'national_id_last4' => ['sometimes', 'nullable', 'digits:4'],
            'residential_area_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.residential_areas,id'],
            'status' => ['sometimes', 'in:active,archived'],
        ];
    }
}
