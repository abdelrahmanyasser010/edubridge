<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceDraft;
use App\Models\Teacher;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;

class AttendanceDraftManager
{
    /** @param list<array<string, mixed>> $records */
    public function save(TeachingSession $session, Teacher $teacher, array $records): AttendanceDraft
    {
        return DB::connection('tenant')->transaction(function () use ($session, $teacher, $records): AttendanceDraft {
            $draft = AttendanceDraft::query()
                ->where('teaching_session_id', $session->id)
                ->where('teacher_id', $teacher->id)
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                return AttendanceDraft::query()->create([
                    'teaching_session_id' => $session->id,
                    'teacher_id' => $teacher->id,
                    'records' => $records,
                    'version' => 1,
                ])->refresh();
            }

            $draft->forceFill([
                'records' => $records,
                'version' => $draft->version + 1,
            ])->save();

            return $draft->refresh();
        });
    }
}
