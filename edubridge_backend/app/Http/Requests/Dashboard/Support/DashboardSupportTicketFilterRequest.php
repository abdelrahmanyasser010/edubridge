<?php

namespace App\Http\Requests\Dashboard\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardSupportTicketFilterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'pending', 'answered', 'resolved', 'closed'])],
            'category_key' => ['nullable', Rule::in(['general', 'technical', 'academic', 'attendance', 'transport', 'finance'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
