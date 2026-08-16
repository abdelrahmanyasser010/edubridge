<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeavePermitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'requested_leave_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
