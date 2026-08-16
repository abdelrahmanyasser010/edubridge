<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Analytics\EarlyWarningCalculator;
use App\Http\Requests\Dashboard\Analytics\DashboardEarlyWarningRequest;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AnalyticsDashboardController
{
    public function earlyWarnings(DashboardEarlyWarningRequest $request, EarlyWarningCalculator $calculator): JsonResponse
    {
        Gate::authorize('report.view');
        $filters = $request->validated();
        $min = (int) ($filters['min_score'] ?? 1);
        $students = Student::query()->where('status', Student::STATUS_ACTIVE)
            ->when($filters['section_id'] ?? null, fn ($q, $v) => $q->where('section_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($sq) use ($v) {
                $sq->where('full_name', 'like', '%'.$v.'%')->orWhere('admission_number', 'like', '%'.$v.'%');
            }))
            ->with('section:id,name')->orderBy('full_name')->get();
        $items = $students->map(function (Student $student) use ($calculator) {
            $risk = $calculator->calculate($student);

            return $risk + [
                'student' => ['id' => (string) $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
                'section' => ['id' => $student->section_id === null ? null : (string) $student->section_id, 'name' => $student->section?->name],
                'level' => $risk['score'] >= 70 ? 'high' : ($risk['score'] >= 30 ? 'medium' : 'low'),
            ];
        })->filter(fn ($item) => $item['score'] >= $min)->sortByDesc('score')->values()->all();

        return ApiResponse::data(['calculation_version' => EarlyWarningCalculator::VERSION, 'calculated_at' => now()->toJSON(), 'students' => $items]);
    }
}
