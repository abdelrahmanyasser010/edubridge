<?php

namespace App\Auth;

final class ApplicationAccessMatrix
{
    /**
     * @return list<string>
     */
    public static function rolesFor(string $appType): array
    {
        return match ($appType) {
            'teacher' => ['teacher'],
            'parent' => ['parent'],
            'student' => ['student'],
            'transport' => ['transport_supervisor', 'driver'],
            'dashboard' => ['school_admin', 'academic_admin', 'student_affairs', 'finance_officer'],
            default => [],
        };
    }

    public static function isAllowed(string $roleKey, string $appType): bool
    {
        return in_array($roleKey, self::rolesFor($appType), true);
    }
}
