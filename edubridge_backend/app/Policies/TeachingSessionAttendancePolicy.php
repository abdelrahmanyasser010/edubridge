<?php

namespace App\Policies;

use App\Models\TeacherSectionSubject;
use App\Models\TeachingSession;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class TeachingSessionAttendancePolicy
{
    public function viewAttendanceRoster(User $user, TeachingSession $session): bool
    {
        return $this->ownsSession($user, $session)
            && Gate::forUser($user)->allows('attendance.view');
    }

    public function saveAttendanceDraft(User $user, TeachingSession $session): bool
    {
        return $this->ownsSession($user, $session)
            && Gate::forUser($user)->allows('attendance.draft');
    }

    public function submitAttendance(User $user, TeachingSession $session): bool
    {
        return $this->ownsSession($user, $session)
            && Gate::forUser($user)->allows('attendance.submit');
    }

    private function ownsSession(User $user, TeachingSession $session): bool
    {
        if (! app(TenantContext::class)->active()) {
            return false;
        }

        return TeacherSectionSubject::query()
            ->join('teachers', 'teachers.id', '=', 'teacher_section_subject.teacher_id')
            ->where('teacher_section_subject.id', $session->allocation_id)
            ->where('teachers.central_user_id', $user->id)
            ->exists();
    }
}
