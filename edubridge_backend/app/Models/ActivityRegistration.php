<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_activity_id', 'student_id', 'registered_by_central_user_id', 'finance_invoice_id', 'status', 'registered_at', 'cancelled_at'])]
class ActivityRegistration extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    public function activity(): BelongsTo
    {
        return $this->belongsTo(SchoolActivity::class, 'school_activity_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    protected function casts(): array
    {
        return [
            'school_activity_id' => 'integer',
            'student_id' => 'integer',
            'registered_by_central_user_id' => 'integer',
            'finance_invoice_id' => 'integer',
            'registered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
