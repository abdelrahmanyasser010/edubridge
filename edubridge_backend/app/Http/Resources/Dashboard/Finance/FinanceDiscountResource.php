<?php

namespace App\Http\Resources\Dashboard\Finance;

use App\Models\FinanceDiscount;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class FinanceDiscountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof FinanceDiscount) {
            throw new LogicException('FinanceDiscountResource expects a FinanceDiscount model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'student_id' => $this->resource->student_id === null ? null : (string) $this->resource->student_id,
            'student_name' => $this->studentName(),
            'title' => $this->resource->title,
            'amount' => round((float) $this->resource->amount, 2),
            'type' => $this->resource->type,
            'status' => $this->resource->status,
            'valid_from' => $this->dateString($this->resource->valid_from),
            'valid_until' => $this->dateString($this->resource->valid_until),
            'notes' => $this->resource->notes,
        ];
    }

    private function studentName(): ?string
    {
        if ($this->resource->student_id === null) {
            return null;
        }

        if ($this->resource->relationLoaded('student')) {
            return $this->resource->student?->full_name;
        }

        $studentName = DB::connection('tenant')->table('students')->where('id', $this->resource->student_id)->value('full_name');

        return is_string($studentName) ? $studentName : null;
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
