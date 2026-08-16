<?php

namespace App\Http\Requests\Dashboard\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.students,id'],
            'title' => ['sometimes', 'string', 'max:160'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'type' => ['sometimes', Rule::in(['fixed', 'percentage'])],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
