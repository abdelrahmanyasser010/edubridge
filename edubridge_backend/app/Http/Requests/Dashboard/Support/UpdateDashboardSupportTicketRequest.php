<?php

namespace App\Http\Requests\Dashboard\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardSupportTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'pending', 'answered', 'resolved', 'closed'])],
        ];
    }
}
