<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'central_user_id' => ['nullable', 'integer', 'min:1'],
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['required', 'regex:/^\\+?[0-9\\- ]{7,20}$/'],
            'national_id_last4' => ['nullable', 'digits:4'],
            'residential_area_id' => ['nullable', 'integer', 'exists:tenant.residential_areas,id'],
            'status' => ['nullable', 'in:active,archived'],
        ];
    }
}
