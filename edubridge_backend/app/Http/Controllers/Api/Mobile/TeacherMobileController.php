<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\TeacherMobileManager;
use App\Http\Requests\Mobile\TeacherAssessmentFilterRequest;
use App\Http\Requests\Mobile\TeacherClassFilterRequest;
use App\Http\Requests\Mobile\TeacherScheduleRequest;
use App\Models\Assessment;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TeacherMobileController
{
    public function summary(Request $request, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->summary((int) $user->id));
    }

    public function classes(TeacherClassFilterRequest $request, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $paginator = $manager->classes((int) $user->id, $request->validated());

        return ApiResponse::data(
            collect($paginator->items())->map(fn (object $row): array => $this->classRow($row))->all(),
            meta: $this->paginationMeta($paginator),
        );
    }

    public function classDetail(Request $request, int $section, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->classDetail((int) $user->id, $section));
    }

    public function classStudents(Request $request, int $section, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $paginator = $manager->classStudents((int) $user->id, $section, $perPage);

        return ApiResponse::data(
            collect($paginator->items())->map(fn (Student $student): array => [
                'id' => (string) $student->id,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'gender' => $student->gender,
                'status' => $student->status,
            ])->all(),
            meta: $this->paginationMeta($paginator),
        );
    }

    public function schedule(TeacherScheduleRequest $request, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $date = (string) ($request->validated('date') ?? now()->toDateString());

        return ApiResponse::data($manager->schedule((int) $user->id, $date));
    }

    public function assessments(TeacherAssessmentFilterRequest $request, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $paginator = $manager->assessments((int) $user->id, $request->validated());

        return ApiResponse::data(
            collect($paginator->items())->map(fn (Assessment $assessment): array => [
                'id' => (string) $assessment->id,
                'academic_term_id' => (string) $assessment->academic_term_id,
                'allocation_id' => (string) $assessment->allocation_id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'max_score' => $assessment->max_score,
                'weight' => $assessment->weight,
                'status' => $assessment->status,
                'submitted_at' => $assessment->submitted_at?->toJSON(),
                'published_at' => $assessment->published_at?->toJSON(),
            ])->all(),
            meta: $this->paginationMeta($paginator),
        );
    }

    public function gradebook(Request $request, int $section, TeacherMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->gradebook((int) $user->id, $section));
    }

    /** @return array<string, mixed> */
    private function classRow(object $row): array
    {
        return [
            'allocation_id' => (string) $row->allocation_id,
            'is_homeroom' => (bool) $row->is_homeroom,
            'weekly_quota' => (int) $row->weekly_quota,
            'students_count' => (int) $row->students_count,
            'section' => [
                'id' => (string) $row->section_id,
                'name' => $row->section_name,
                'code' => $row->section_code,
                'grade_level' => ['id' => (string) $row->grade_level_id, 'name' => $row->grade_level_name],
            ],
            'subject' => ['id' => (string) $row->subject_id, 'name' => $row->subject_name, 'code' => $row->subject_code],
            'academic_term' => ['id' => (string) $row->academic_term_id, 'name' => $row->academic_term_name],
        ];
    }

    /** @return array<string, mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return ['pagination' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]];
    }
}
