<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class SendConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment_file_ids'],
            'attachment_file_ids' => ['sometimes', 'array', 'max:5'],
            'attachment_file_ids.*' => ['integer', 'distinct', 'exists:tenant.files,id'],
        ];
    }
}
