<?php

namespace App\Actions\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RevokeDeviceSession
{
    public function handle(User $user, int $tokenId): void
    {
        $updated = PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($updated === 0) {
            throw new NotFoundHttpException('Device session not found.');
        }
    }
}
