<?php

namespace App\Actions\Messaging;

use App\Models\ConversationThread;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConversationThreadManager
{
    public function create(int $creatorCentralUserId, int $participantCentralUserId, ?string $subject): ConversationThread
    {
        if ($creatorCentralUserId === $participantCentralUserId || ! $this->allowedRelation($creatorCentralUserId, $participantCentralUserId)) {
            throw new ConflictHttpException('Conversation participants are not authorized by school relationships.');
        }

        return DB::connection('tenant')->transaction(function () use ($creatorCentralUserId, $participantCentralUserId, $subject): ConversationThread {
            $thread = ConversationThread::query()->create([
                'subject' => $subject,
                'created_by_central_user_id' => $creatorCentralUserId,
                'status' => ConversationThread::STATUS_ACTIVE,
            ]);

            foreach ([$creatorCentralUserId, $participantCentralUserId] as $userId) {
                $thread->participants()->create(['central_user_id' => $userId]);
            }

            return $thread->load('participants');
        });
    }

    private function allowedRelation(int $a, int $b): bool
    {
        return $this->parentTeacherRelation($a, $b) || $this->parentTeacherRelation($b, $a);
    }

    private function parentTeacherRelation(int $parentCentralUserId, int $teacherCentralUserId): bool
    {
        return DB::connection('tenant')->table('parents')
            ->join('student_parent', 'student_parent.parent_id', '=', 'parents.id')
            ->join('students', 'students.id', '=', 'student_parent.student_id')
            ->join('teacher_section_subject', 'teacher_section_subject.section_id', '=', 'students.section_id')
            ->join('teachers', 'teachers.id', '=', 'teacher_section_subject.teacher_id')
            ->where('parents.central_user_id', $parentCentralUserId)
            ->where('teachers.central_user_id', $teacherCentralUserId)
            ->where('parents.status', 'active')
            ->where('students.status', 'active')
            ->where('student_parent.status', 'active')
            ->where('teacher_section_subject.status', 'active')
            ->exists();
    }
}
