<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Actions\Assessment\AssessmentManager;
use App\Http\Requests\Assessment\BulkStoreGradeEntriesRequest;
use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Http\Resources\Assessment\AssessmentResource;
use App\Http\Resources\Assessment\GradeEntryResource;
use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\Teacher;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeacherAssessmentController
{
    public function store(StoreAssessmentRequest $request, AssessmentManager $manager): JsonResponse
    {
        Gate::authorize('create', Assessment::class);
        $teacher = $this->currentTeacher($request);

        return ApiResponse::data(
            (new AssessmentResource($manager->create($request->validated(), $teacher)))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function roster(Request $request, int $assessment, AssessmentManager $manager): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        Gate::authorize('enterGrades', $assessment);

        return ApiResponse::data($manager->roster($assessment)->map(fn ($student): array => [
            'id' => (string) $student->id,
            'full_name' => $student->full_name,
            'entry' => ($entry = GradeEntry::query()->where('assessment_id', $assessment->id)->where('student_id', $student->id)->first()) === null
                ? null
                : (new GradeEntryResource($entry))->resolve($request),
        ])->all());
    }

    public function saveGrades(BulkStoreGradeEntriesRequest $request, int $assessment, AssessmentManager $manager): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        Gate::authorize('enterGrades', $assessment);
        $teacher = $this->currentTeacher($request);

        return ApiResponse::data(GradeEntryResource::collection(
            $manager->saveEntries($assessment, $teacher, $request->validated())
        )->resolve($request));
    }

    public function submit(Request $request, int $assessment, AssessmentManager $manager): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        Gate::authorize('submit', $assessment);

        return ApiResponse::data((new AssessmentResource($manager->submitForApproval($assessment)))->resolve($request));
    }

    public function approve(Request $request, int $assessment, AssessmentManager $manager): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        Gate::authorize('approve', $assessment);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new AssessmentResource($manager->approve($assessment, (int) $user->id)))->resolve($request));
    }

    public function publish(Request $request, int $assessment, AssessmentManager $manager): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        Gate::authorize('publish', $assessment);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new AssessmentResource($manager->publish($assessment, (int) $user->id)))->resolve($request));
    }

    public function lock(Request $request, int $assessment, AssessmentManager $manager): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        Gate::authorize('lock', $assessment);

        return ApiResponse::data((new AssessmentResource($manager->lock($assessment)))->resolve($request));
    }

    private function currentTeacher(Request $request): Teacher
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return Teacher::query()
            ->where('central_user_id', $user->id)
            ->where('status', Teacher::STATUS_ACTIVE)
            ->first() ?? throw new NotFoundHttpException;
    }
}
