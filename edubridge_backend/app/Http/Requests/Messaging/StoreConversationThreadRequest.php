<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'participant_central_user_id' => ['required', 'integer', 'min:1'],
            'subject' => ['nullable', 'string', 'max:180'],
        ];
    }
}
