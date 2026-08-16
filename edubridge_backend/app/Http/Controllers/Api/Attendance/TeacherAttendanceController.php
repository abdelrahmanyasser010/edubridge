<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Actions\Attendance\AttendanceDraftManager;
use App\Actions\Attendance\SubmitAttendance;
use App\Http\Requests\Attendance\SaveAttendanceDraftRequest;
use App\Http\Requests\Attendance\SubmitAttendanceRequest;
use App\Http\Resources\Attendance\AttendanceDraftResource;
use App\Models\AttendanceDraft;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use App\Models\TeachingSession;
use App\Support\ApiResponse;
use App\Support\IdempotencyResult;
use App\Support\IdempotencyService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeacherAttendanceController
{
    public function roster(int $session): JsonResponse
    {
        $session = TeachingSession::query()->findOrFail($session);
        Gate::authorize('viewAttendanceRoster', $session);

        $allocation = TeacherSectionSubject::query()->findOrFail($session->allocation_id);
        $draft = $this->draftForCurrentTeacher($session);

        return ApiResponse::data([
            'session' => [
                'id' => (string) $session->id,
                'session_date' => $session->sessionDateString(),
                'status' => $session->status,
            ],
            'students' => Student::query()
                ->where('section_id', $allocation->section_id)
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'admission_number'])
                ->map(fn (Student $student): array => [
                    'id' => (string) $student->id,
                    'full_name' => $student->full_name,
                    'admission_number' => $student->admission_number,
                ])->all(),
            'draft' => $draft === null ? null : (new AttendanceDraftResource($draft))->resolve(),
        ]);
    }

    public function saveDraft(SaveAttendanceDraftRequest $request, int $session, AttendanceDraftManager $manager): JsonResponse
    {
        $session = TeachingSession::query()->findOrFail($session);
        Gate::authorize('saveAttendanceDraft', $session);

        $teacher = $this->currentTeacher($session);

        return ApiResponse::data(
            (new AttendanceDraftResource($manager->save($session, $teacher, $request->validated('records'))))->resolve($request),
        );
    }

    public function submit(SubmitAttendanceRequest $request, int $session, SubmitAttendance $submit, IdempotencyService $idempotency): JsonResponse
    {
        $session = TeachingSession::query()->findOrFail($session);
        Gate::authorize('submitAttendance', $session);

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['The Idempotency-Key header is required.'],
            ]);
        }

        $teacher = $this->currentTeacher($session);
        $result = $idempotency->run(
            clientKey: $key,
            operation: 'attendance.submit.'.$session->id,
            payload: $request->validated(),
            callback: fn (): IdempotencyResult => new IdempotencyResult(
                payload: $submit->handle($session, $teacher, $request->validated('records')),
                status: 200,
                replayed: false,
            ),
            actorCentralUserId: $request->user()?->id,
        );

        return ApiResponse::data($result->payload, $result->status, [
            'idempotency_replayed' => $result->replayed,
        ]);
    }

    private function currentTeacher(TeachingSession $session): Teacher
    {
        $user = request()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $allocation = TeacherSectionSubject::query()->findOrFail($session->allocation_id);
        $teacher = Teacher::query()
            ->where('id', $allocation->teacher_id)
            ->where('central_user_id', $user->id)
            ->first();

        return $teacher ?? throw new NotFoundHttpException;
    }

    private function draftForCurrentTeacher(TeachingSession $session): ?AttendanceDraft
    {
        return AttendanceDraft::query()
            ->where('teaching_session_id', $session->id)
            ->where('teacher_id', $this->currentTeacher($session)->id)
            ->first();
    }
}
