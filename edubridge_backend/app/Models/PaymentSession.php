<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fee_id', 'finance_invoice_id', 'wallet_id', 'provider', 'provider_session_id', 'purpose', 'method', 'idempotency_key', 'amount', 'amount_minor', 'currency', 'provider_reference', 'status', 'expires_at', 'paid_at', 'failed_at', 'failure_reason', 'provider_payload'])]
class PaymentSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const PURPOSE_LEGACY_FEE = 'legacy_fee';

    public const PURPOSE_INVOICE = 'invoice';

    public const PURPOSE_WALLET_TOP_UP = 'wallet_top_up';

    protected $connection = 'tenant';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fee_id' => 'integer',
            'finance_invoice_id' => 'integer',
            'wallet_id' => 'integer',
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
