<?php

namespace App\Http\Resources\People;

use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use LogicException;

class GuardianResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Guardian) {
            throw new LogicException('GuardianResource expects a Guardian model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'central_user_id' => $this->resource->central_user_id === null ? null : (string) $this->resource->central_user_id,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'national_id_last4' => $this->resource->national_id_last4,
            'residential_area_id' => $this->resource->residential_area_id === null ? null : (string) $this->resource->residential_area_id,
            'status' => $this->resource->status,
            'children' => $this->children((int) $this->resource->id),
        ];
    }

    /**
     * @return list<array{id: string, full_name: string}>
     */
    private function children(int $guardianId): array
    {
        return DB::connection('tenant')
            ->table('student_parent')
            ->join('students', 'students.id', '=', 'student_parent.student_id')
            ->where('student_parent.parent_id', $guardianId)
            ->where('student_parent.status', 'active')
            ->select(['students.id', 'students.full_name'])
            ->orderBy('students.full_name')
            ->get()
            ->map(fn (object $student): array => [
                'id' => (string) $student->id,
                'full_name' => (string) $student->full_name,
            ])
            ->values()
            ->all();
    }
}
