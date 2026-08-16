<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\RevokeDeviceSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePushTokenRequest;
use App\Http\Resources\Auth\DeviceSessionResource;
use App\Http\Resources\Auth\SchoolResource;
use App\Http\Resources\Auth\UserResource;
use App\Models\DeviceToken;
use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginUser $login): JsonResponse
    {
        $result = $login->handle($request->validated(), $request);

        $response = ApiResponse::data([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['access_token']->expires_at?->toISOString(),
            'user' => (new UserResource($result['user']))->resolve($request),
            'school' => (new SchoolResource($result['school']))->resolve($request),
            'device_session' => (new DeviceSessionResource($result['access_token']))->resolve($request),
        ]);

        if ($request->is('api/v1/auth/login')) {
            $response->headers->set('Deprecation-Warning', 'The /auth/login endpoint is deprecated. Use the app-specific endpoint like /teacher/auth/login.');
        }

        return $response;
    }

    public function me(Request $request): JsonResponse
    {
        $accessToken = $this->currentAccessToken($request);
        $school = School::query()->findOrFail($accessToken->school_id);

        return ApiResponse::data([
            'user' => (new UserResource($request->user()))->resolve($request),
            'school' => (new SchoolResource($school))->resolve($request),
            'role' => $this->rolePayload($accessToken, (int) $request->user()->id),
            'permissions' => $this->permissionKeys($accessToken, (int) $request->user()->id),
            'device_session' => (new DeviceSessionResource($accessToken))->resolve($request),
        ]);
    }

    public function deviceSessions(Request $request): JsonResponse
    {
        $sessions = PersonalAccessToken::query()
            ->where('tokenable_type', $request->user()::class)
            ->where('tokenable_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->latest('created_at')
            ->get();

        return ApiResponse::data(DeviceSessionResource::collection($sessions)->resolve($request));
    }

    public function logout(Request $request): Response
    {
        $this->currentAccessToken($request)->forceFill(['revoked_at' => now()])->save();

        return response()->noContent();
    }

    public function revokeDeviceSession(Request $request, int $deviceSession, RevokeDeviceSession $revoke): Response
    {
        DB::connection('central')->transaction(function () use ($request, $deviceSession, $revoke) {
            $revoke->handle($request->user(), $deviceSession);
        });

        return response()->noContent();
    }

    public function updatePushToken(UpdatePushTokenRequest $request): Response
    {
        $user = $request->user();
        $token = $request->input('token');
        $platform = $request->input('platform');
        $appType = $this->currentAccessToken($request)->app_type;

        $tokenHash = hash('sha256', $token);

        DeviceToken::updateOrCreate(
            ['token_hash' => $tokenHash],
            [
                'central_user_id' => $user->id,
                'app_type' => $appType,
                'platform' => $platform,
                'token' => $token,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return response()->noContent();
    }

    /**
     * @return array{key: string|null, label: string|null}
     */
    private function rolePayload(PersonalAccessToken $accessToken, int $userId): array
    {
        $now = now();
        $roleKey = DB::connection('central')->table('school_user')
            ->where('school_id', $accessToken->school_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $now);
            })
            ->value('role_key');

        if (! is_string($roleKey)) {
            return ['key' => null, 'label' => null];
        }

        return [
            'key' => $roleKey,
            'label' => str($roleKey)->replace('_', ' ')->title()->toString(),
        ];
    }

    /**
     * @return list<string>
     */
    private function permissionKeys(PersonalAccessToken $accessToken, int $userId): array
    {
        try {
            if (! Schema::connection('tenant')->hasTable('user_roles')) {
                return [];
            }

            $now = now();
            $membership = DB::connection('central')->table('school_user')
                ->where('school_id', $accessToken->school_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('valid_until')->orWhere('valid_until', '>', $now);
                })
                ->first(['role_key']);

            if ($membership === null || empty($membership->role_key)) {
                return [];
            }

            return DB::connection('tenant')
                ->table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('user_roles.central_user_id', $userId)
                ->where('roles.key', $membership->role_key)
                ->where(function ($query) use ($now) {
                    $query->whereNull('user_roles.valid_from')->orWhere('user_roles.valid_from', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('user_roles.valid_until')->orWhere('user_roles.valid_until', '>', $now);
                })
                ->orderBy('permissions.key')
                ->distinct()
                ->pluck('permissions.key')
                ->map(fn (string $permission): string => $permission)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function currentAccessToken(Request $request): PersonalAccessToken
    {
        $accessToken = $request->user()?->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            throw new UnauthorizedHttpException('Bearer', 'A bearer token is required.');
        }

        return $accessToken;
    }
}
