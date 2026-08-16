<?php

namespace App\Actions\Academic;

use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSectionSubject;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TeacherSectionSubjectManager
{
    /** @param array<string, mixed> $data */
    public function create(array $data): TeacherSectionSubject
    {
        $this->ensureActiveProfiles($data);
        $this->ensureSubjectBelongsToSectionGrade($data);

        return TeacherSectionSubject::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function update(TeacherSectionSubject $allocation, array $data): TeacherSectionSubject
    {
        $merged = array_merge($allocation->only([
            'academic_term_id',
            'teacher_id',
            'section_id',
            'subject_id',
            'weekly_quota',
            'is_homeroom',
        ]), $data);

        $this->ensureActiveProfiles($merged);
        $this->ensureSubjectBelongsToSectionGrade($merged);

        $allocation->fill($data)->save();

        return $allocation->refresh();
    }

    public function archive(TeacherSectionSubject $allocation): TeacherSectionSubject
    {
        $allocation->forceFill(['status' => TeacherSectionSubject::STATUS_ARCHIVED])->save();

        return $allocation->refresh();
    }

    /** @param array<string, mixed> $data */
    private function ensureActiveProfiles(array $data): void
    {
        $term = AcademicTerm::query()->findOrFail($data['academic_term_id']);
        $teacher = Teacher::query()->findOrFail($data['teacher_id']);
        $section = Section::query()->findOrFail($data['section_id']);
        $subject = Subject::query()->findOrFail($data['subject_id']);

        if (
            $term->status === AcademicTerm::STATUS_CLOSED
            || $teacher->status !== Teacher::STATUS_ACTIVE
            || $section->status !== Section::STATUS_ACTIVE
            || $subject->status !== Subject::STATUS_ACTIVE
        ) {
            throw new ConflictHttpException('Only active term, teacher, section, and subject can be allocated.');
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureSubjectBelongsToSectionGrade(array $data): void
    {
        $section = Section::query()->findOrFail($data['section_id']);
        $gradeLevel = GradeLevel::query()->findOrFail($section->grade_level_id);

        if (! $gradeLevel->subjects()->where('subjects.id', $data['subject_id'])->exists()) {
            throw new ConflictHttpException('Subject is not assigned to the section grade level.');
        }
    }
}
