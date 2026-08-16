<?php

namespace App\Http\Controllers\Api\Operations;

use App\Actions\Operations\LeavePermitManager;
use App\Http\Requests\Operations\ReviewLeavePermitRequest;
use App\Http\Requests\Operations\StoreLeavePermitRequest;
use App\Http\Requests\Operations\UseLeavePermitTokenRequest;
use App\Http\Resources\Operations\LeavePermitResource;
use App\Models\LeavePermit;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LeavePermitController
{
    public function store(StoreLeavePermitRequest $request, int $student, LeavePermitManager $manager): JsonResponse
    {
        Gate::authorize('create', LeavePermit::class);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data(
            (new LeavePermitResource($manager->create(Student::query()->findOrFail($student), (int) $user->id, $request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function approve(ReviewLeavePermitRequest $request, int $permit, LeavePermitManager $manager): JsonResponse
    {
        $permit = LeavePermit::query()->findOrFail($permit);
        Gate::authorize('review', $permit);
        $user = $request->user() ?? throw new AuthenticationException;
        $result = $manager->approve($permit, (int) $user->id, $request->validated('review_note'));

        return ApiResponse::data([
            ...((new LeavePermitResource($result['permit']))->resolve($request)),
            'gate_token' => $result['token'],
        ]);
    }

    public function reject(ReviewLeavePermitRequest $request, int $permit, LeavePermitManager $manager): JsonResponse
    {
        $permit = LeavePermit::query()->findOrFail($permit);
        Gate::authorize('review', $permit);
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data((new LeavePermitResource($manager->reject($permit, (int) $user->id, $request->validated('review_note'))))->resolve($request));
    }

    public function use(UseLeavePermitTokenRequest $request, LeavePermitManager $manager): JsonResponse
    {
        Gate::authorize('useToken', LeavePermit::class);

        return ApiResponse::data((new LeavePermitResource($manager->useToken($request->validated('token'))))->resolve($request));
    }
}
