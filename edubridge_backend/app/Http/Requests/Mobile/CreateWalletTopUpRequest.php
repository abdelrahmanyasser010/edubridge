<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWalletTopUpRequest extends FormRequest
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
            'amount_minor' => [
                'required', 'integer',
                'min:'.(int) config('payments.wallet.top_up_min_minor', 1000),
                'max:'.(int) config('payments.wallet.top_up_max_minor', 100000),
            ],
            'payment_method' => ['required', 'string', Rule::in($methods)],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
