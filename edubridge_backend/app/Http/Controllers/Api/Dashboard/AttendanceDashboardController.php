<?php
namespace App\Http\Controllers\Api\Dashboard;
use App\Actions\Attendance\DashboardAttendanceReader;
use App\Http\Requests\Dashboard\Attendance\DashboardAttendanceAtRiskRequest;
use App\Http\Requests\Dashboard\Attendance\DashboardDailyAttendanceRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
class AttendanceDashboardController {
 public function daily(DashboardDailyAttendanceRequest $request, DashboardAttendanceReader $reader): JsonResponse { Gate::authorize('attendance.view'); return ApiResponse::data($reader->daily($request->validated())); }
 public function atRisk(DashboardAttendanceAtRiskRequest $request, DashboardAttendanceReader $reader): JsonResponse { Gate::authorize('attendance.view'); return ApiResponse::data($reader->atRisk($request->validated())); }
}
