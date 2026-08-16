<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Actions\Assessment\ParentGradeReportManager;
use App\Http\Requests\Assessment\RequestCertificateRequest;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentGradeReportController
{
    public function recent(Request $request, int $student, ParentGradeReportManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $limit = min(25, max(1, (int) $request->query('limit', 10)));

        return ApiResponse::data($manager->recentAssessments(Student::query()->findOrFail($student), (int) $user->id, $limit));
    }

    public function terms(Request $request, int $student, ParentGradeReportManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->terms(Student::query()->findOrFail($student), (int) $user->id));
    }

    public function term(Request $request, int $student, int $term, ParentGradeReportManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->termReport(Student::query()->findOrFail($student), (int) $user->id, $term));
    }

    public function certificate(RequestCertificateRequest $request, int $student, ParentGradeReportManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data([
            'job_event_id' => $manager->requestCertificate(Student::query()->findOrFail($student), (int) $user->id, (int) $request->validated('academic_term_id')),
            'status' => 'queued',
        ]);
    }
}
