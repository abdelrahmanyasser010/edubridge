<?php

namespace App\Http\Requests\Dashboard\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:issue_date'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['open', 'partial', 'overdue', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lines' => ['sometimes', 'array', 'min:1', 'max:30'],
            'lines.*.title' => ['required_with:lines', 'string', 'max:160'],
            'lines.*.amount' => ['required_with:lines', 'numeric', 'min:0.01'],
        ];
    }
}
