<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminDashboardSummaryController
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', Student::class);

        $students = Student::query()->where('status', Student::STATUS_ACTIVE)->count();
        $attendance = $this->attendanceToday((int) $students);

        return ApiResponse::data([
            'teachers' => Teacher::query()->where('status', Teacher::STATUS_ACTIVE)->count(),
            'parents' => Guardian::query()->where('status', Guardian::STATUS_ACTIVE)->count(),
            'students' => $students,
            'sections' => Section::query()->where('status', Section::STATUS_ACTIVE)->count(),
            'attendance_today' => $attendance,
            'pending' => [
                'behavior_notes' => $this->countWhere('behavior_notes', 'status', 'pending_review'),
                'medical_excuses' => $this->countWhere('medical_excuses', 'status', 'pending'),
                'grade_appeals' => $this->countWhere('grade_appeals', 'status', 'open'),
                'leave_permits' => $this->countWhere('leave_permits', 'status', 'pending'),
            ],
            'transport' => [
                'routes' => $this->countWhere('bus_routes', 'status', 'active'),
                'on_route' => $this->countWhere('bus_trips', 'status', 'on_route'),
                'delayed' => $this->countWhere('bus_trips', 'status', 'delayed'),
            ],
        ]);
    }

    /**
     * @return array{total: int, present: int, absent: int, late: int, excused: int, rate: float}
     */
    private function attendanceToday(int $activeStudents): array
    {
        $counts = DB::connection('tenant')
            ->table('attendance_records')
            ->whereDate('submitted_at', now()->toDateString())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $recorded = $present + $late + $excused + $absent;

        return [
            'total' => max($activeStudents, $recorded),
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'rate' => $recorded === 0 ? 0.0 : round((($present + $late) / $recorded) * 100, 1),
        ];
    }

    private function countWhere(string $table, string $column, string $value): int
    {
        return (int) DB::connection('tenant')->table($table)->where($column, $value)->count();
    }
}
