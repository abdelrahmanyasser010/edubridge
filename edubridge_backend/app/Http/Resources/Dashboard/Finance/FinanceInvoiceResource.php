<?php

namespace App\Http\Resources\Dashboard\Finance;

use App\Models\FinanceInvoice;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class FinanceInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof FinanceInvoice) {
            throw new LogicException('FinanceInvoiceResource expects a FinanceInvoice model.');
        }

        $remaining = max(0, $this->money($this->resource->total) - $this->money($this->resource->paid_total));

        return [
            'id' => (string) $this->resource->id,
            'invoice_number' => $this->resource->invoice_number,
            'student_id' => (string) $this->resource->student_id,
            'student_name' => $this->studentName(),
            'parent_name' => $this->parentName((int) $this->resource->student_id),
            'issue_date' => $this->dateString($this->resource->issue_date),
            'due_date' => $this->dateString($this->resource->due_date),
            'subtotal' => $this->money($this->resource->subtotal),
            'discount' => $this->money($this->resource->discount_total),
            'tax' => $this->money($this->resource->tax_total),
            'total' => $this->money($this->resource->total),
            'paid' => $this->money($this->resource->paid_total),
            'remaining' => round($remaining, 2),
            'status' => $this->resource->status,
            'currency' => $this->resource->currency,
            'notes' => $this->resource->notes,
            'lines' => $this->lines(),
        ];
    }

    private function studentName(): ?string
    {
        return $this->resource->relationLoaded('student')
            ? $this->resource->student?->full_name
            : DB::connection('tenant')->table('students')->where('id', $this->resource->student_id)->value('full_name');
    }

    private function parentName(int $studentId): ?string
    {
        $parent = DB::connection('tenant')
            ->table('student_parent')
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $studentId)
            ->where('student_parent.status', 'active')
            ->orderByDesc('student_parent.is_primary')
            ->orderBy('parents.full_name')
            ->value('parents.full_name');

        return is_string($parent) ? $parent : null;
    }

    /** @return list<array{title: string, amount: float}> */
    private function lines(): array
    {
        $lines = $this->resource->relationLoaded('lines')
            ? $this->resource->lines
            : $this->resource->lines()->get();

        return $lines->map(fn ($line): array => [
            'title' => (string) $line->title,
            'amount' => $this->money($line->amount),
        ])->values()->all();
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->toDateString();
        }

        return null;
    }
}
