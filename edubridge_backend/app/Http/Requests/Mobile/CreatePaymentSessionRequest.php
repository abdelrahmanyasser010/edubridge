<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $methods = collect((array) config('payments.methods', []))
            ->filter(fn (mixed $enabled): bool => (bool) $enabled)
            ->keys()
            ->values()
            ->all();

        return [
            'method' => ['required', 'string', Rule::in($methods)],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
