<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentialAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:120'],
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('tenant.residential_areas', 'name')->where(fn ($query) => $query->where('city', $this->string('city')->trim()->toString())),
            ],
        ];
    }
}
