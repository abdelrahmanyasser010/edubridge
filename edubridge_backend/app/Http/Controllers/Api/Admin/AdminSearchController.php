<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\AdminSearchRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AdminSearchController
{
    public function __invoke(AdminSearchRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Student::class);

        $query = (string) $request->validated('q');
        $type = (string) ($request->validated('type') ?? 'all');
        $perPage = (int) ($request->validated('per_page') ?? 25);
        $results = [];

        if ($type === 'all' || $type === 'teachers') {
            $results = array_merge($results, $this->teachers($query, $perPage));
        }

        if ($type === 'all' || $type === 'parents') {
            $results = array_merge($results, $this->parents($query, $perPage));
        }

        if ($type === 'all' || $type === 'students') {
            $results = array_merge($results, $this->students($query, $perPage));
        }

        $results = array_slice($results, 0, $perPage);

        return ApiResponse::data($results, meta: [
            'per_page' => $perPage,
            'returned' => count($results),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function teachers(string $query, int $limit): array
    {
        return Teacher::query()
            ->where('status', Teacher::STATUS_ACTIVE)
            ->where(function ($builder) use ($query) {
                $builder->where('full_name', 'like', '%'.$query.'%')
                    ->orWhere('employee_number', 'like', '%'.$query.'%');
            })
            ->limit($limit)
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Teacher $teacher): array => [
                'type' => 'teacher',
                'id' => (string) $teacher->id,
                'label' => $teacher->full_name,
                'secondary' => $teacher->employee_number,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function parents(string $query, int $limit): array
    {
        return Guardian::query()
            ->where('status', Guardian::STATUS_ACTIVE)
            ->where(function ($builder) use ($query) {
                $builder->where('full_name', 'like', '%'.$query.'%')
                    ->orWhere('phone', 'like', '%'.$query.'%');
            })
            ->limit($limit)
            ->get(['id', 'full_name', 'phone'])
            ->map(fn (Guardian $guardian): array => [
                'type' => 'parent',
                'id' => (string) $guardian->id,
                'label' => $guardian->full_name,
                'secondary' => $guardian->phone,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function students(string $query, int $limit): array
    {
        return Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->where(function ($builder) use ($query) {
                $builder->where('full_name', 'like', '%'.$query.'%')
                    ->orWhere('admission_number', 'like', '%'.$query.'%');
            })
            ->limit($limit)
            ->get(['id', 'full_name', 'admission_number'])
            ->map(fn (Student $student): array => [
                'type' => 'student',
                'id' => (string) $student->id,
                'label' => $student->full_name,
                'secondary' => $student->admission_number,
            ])
            ->all();
    }
}
