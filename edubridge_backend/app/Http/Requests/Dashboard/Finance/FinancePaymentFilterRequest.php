<?php

namespace App\Http\Requests\Dashboard\Finance;

use Illuminate\Foundation\Http\FormRequest;

class FinancePaymentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'integer', 'exists:tenant.finance_invoices,id'],
            'student_id' => ['nullable', 'integer', 'exists:tenant.students,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
