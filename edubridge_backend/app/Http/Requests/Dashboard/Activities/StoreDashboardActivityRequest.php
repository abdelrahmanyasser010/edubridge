<?php

namespace App\Http\Requests\Dashboard\Activities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:180'],
            'organizer' => ['nullable', 'string', 'max:180'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'fee_amount_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'registration_opens_at' => ['nullable', 'date', 'before:starts_at'],
            'registration_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at', 'after_or_equal:registration_opens_at'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fee_amount_minor' => $this->input('fee_amount_minor', 0),
            'currency' => strtoupper((string) $this->input('currency', config('payments.currency', 'SAR'))),
            'status' => $this->input('status', 'draft'),
        ]);
    }
}
