<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class CreateWalletPaymentTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'max_amount_minor' => [
                'nullable', 'integer', 'min:1',
                'max:'.(int) config('payments.wallet.qr_max_purchase_minor', 50000),
            ],
        ];
    }
}
