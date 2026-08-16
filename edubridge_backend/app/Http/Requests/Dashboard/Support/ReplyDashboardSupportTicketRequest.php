<?php

namespace App\Http\Requests\Dashboard\Support;

use Illuminate\Foundation\Http\FormRequest;

class ReplyDashboardSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:5000']];
    }
}
