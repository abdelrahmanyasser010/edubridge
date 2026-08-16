<?php

namespace App\Support;

use App\Models\Teacher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CurrentTeacher
{
    public function resolve(int $centralUserId): Teacher
    {
        return Teacher::query()
            ->where('central_user_id', $centralUserId)
            ->where('status', Teacher::STATUS_ACTIVE)
            ->first() ?? throw new NotFoundHttpException;
    }
}
