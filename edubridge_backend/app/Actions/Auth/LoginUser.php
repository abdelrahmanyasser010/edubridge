<?php

namespace App\Actions\Auth;

use App\Auth\ApplicationAccessMatrix;
use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use App\Support\Exceptions\AppAccessDeniedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, school: School, access_token: PersonalAccessToken, plain_text_token: string}
     */
    public function handle(array $data, Request $request): array
    {
        $appType = $request->attributes->get('app_type') ?? ($request->input('app_type') ?? null);
        if (! $appType) {
            throw new \InvalidArgumentException('App type is required.');
        }

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user instanceof User || $user->status !== 'active' || ! Hash::check((string) $data['password'], $user->password)) {
            $this->throwInvalidCredentials();
        }

        $school = $request->attributes->get('resolved_school');
        if (! $school) {
            $schoolCode = $request->attributes->get('school_code') ?? ($request->input('school_code') ?? null);
            if (! $schoolCode) {
                $this->throwInvalidCredentials();
            }
            $school = School::query()
                ->where('code', $schoolCode)
                ->where('status', 'active')
                ->first();
        }

        if (! $school instanceof School || ! $this->hasActiveMembership($user, $school) || ! $this->hasActiveTenantConnection($school)) {
            $this->throwInvalidCredentials();
        }

        $roleKey = DB::connection('central')->table('school_user')
            ->where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->value('role_key');

        if (! ApplicationAccessMatrix::isAllowed((string) $roleKey, $appType)) {
            throw new AppAccessDeniedException;
        }

        $this->revokeExistingDeviceToken($user, $school, (string) $data['device_id']);

        $expiresAt = now()->addDays((int) config('sanctum.personal_access_token_expiration_days', 30));
        $tokenName = $appType.'@'.$data['device_id'];
        $abilities = ['app:'.$appType];
        $plainTextToken = $user->createToken($tokenName, $abilities, $expiresAt);
        $accessToken = $plainTextToken->accessToken;

        if (! $accessToken instanceof PersonalAccessToken) {
            throw new \LogicException('Sanctum is not using the configured personal access token model.');
        }

        $accessToken->forceFill([
            'school_id' => $school->id,
            'device_id' => $data['device_id'],
            'app_type' => $appType,
            'device_name' => $data['device_name'] ?? null,
            'last_ip_address' => $request->ip(),
        ])->save();

        return [
            'user' => $user,
            'school' => $school,
            'access_token' => $accessToken,
            'plain_text_token' => $plainTextToken->plainTextToken,
        ];
    }

    private function hasActiveMembership(User $user, School $school): bool
    {
        return DB::connection('central')->table('school_user')
            ->where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->exists();
    }

    private function hasActiveTenantConnection(School $school): bool
    {
        return DB::connection('central')->table('tenant_connections')
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->exists();
    }

    private function revokeExistingDeviceToken(User $user, School $school, string $deviceId): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('school_id', $school->id)
            ->where('device_id', $deviceId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function throwInvalidCredentials(): never
    {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are invalid.'],
        ]);
    }
}
