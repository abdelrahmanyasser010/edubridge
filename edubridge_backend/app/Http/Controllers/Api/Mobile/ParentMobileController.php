<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\ParentMobileManager;
use App\Http\Requests\Mobile\UpdateParentProfileRequest;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentMobileController
{
    public function students(Request $request, ParentMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->students((int) $user->id));
    }

    public function overview(Request $request, int $student, ParentMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->overview(Student::query()->findOrFail($student), (int) $user->id));
    }

    public function profile(Request $request, ParentMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->profile((int) $user->id));
    }

    public function updateProfile(UpdateParentProfileRequest $request, ParentMobileManager $manager): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->updateProfile((int) $user->id, $request->validated()));
    }
}
