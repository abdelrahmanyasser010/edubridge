<?php

namespace App\Actions\Academic;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcademicStructureManager
{
    /** @param array<string, mixed> $data */
    public function createYear(array $data): AcademicYear
    {
        return AcademicYear::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateYear(AcademicYear $year, array $data): AcademicYear
    {
        $year->fill($data)->save();

        return $year->refresh();
    }

    public function closeYear(AcademicYear $year): AcademicYear
    {
        $year->forceFill(['status' => AcademicYear::STATUS_CLOSED])->save();

        return $year->refresh();
    }

    /** @param array<string, mixed> $data */
    public function createTerm(AcademicYear $year, array $data): AcademicTerm
    {
        $this->ensureTermWithinYear($year, $data);

        return AcademicTerm::query()->create(array_merge($data, [
            'academic_year_id' => $year->id,
        ]))->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateTerm(AcademicTerm $term, array $data): AcademicTerm
    {
        $year = AcademicYear::query()->findOrFail($data['academic_year_id'] ?? $term->academic_year_id);
        $this->ensureTermWithinYear($year, array_merge($term->only(['starts_on', 'ends_on']), $data));

        $term->fill($data)->save();

        return $term->refresh();
    }

    public function activateTerm(AcademicTerm $term): AcademicTerm
    {
        return DB::connection('tenant')->transaction(function () use ($term): AcademicTerm {
            $term = AcademicTerm::query()->lockForUpdate()->findOrFail($term->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($term->academic_year_id);

            if ($year->status === AcademicYear::STATUS_CLOSED) {
                throw new ConflictHttpException('Cannot activate a term in a closed academic year.');
            }

            $activeTermExists = AcademicTerm::query()
                ->where('academic_year_id', $term->academic_year_id)
                ->where('id', '!=', $term->id)
                ->where('status', AcademicTerm::STATUS_ACTIVE)
                ->exists();

            if ($activeTermExists) {
                throw new ConflictHttpException('Only one term can be active per academic year.');
            }

            AcademicYear::query()
                ->where('id', '!=', $year->id)
                ->where('status', AcademicYear::STATUS_ACTIVE)
                ->update(['status' => AcademicYear::STATUS_CLOSED]);

            $year->forceFill(['status' => AcademicYear::STATUS_ACTIVE])->save();
            $term->forceFill(['status' => AcademicTerm::STATUS_ACTIVE])->save();

            return $term->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function createGradeLevel(array $data): GradeLevel
    {
        return GradeLevel::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateGradeLevel(GradeLevel $gradeLevel, array $data): GradeLevel
    {
        $gradeLevel->fill($data)->save();

        return $gradeLevel->refresh();
    }

    public function archiveGradeLevel(GradeLevel $gradeLevel): GradeLevel
    {
        $gradeLevel->forceFill(['status' => GradeLevel::STATUS_ARCHIVED])->save();

        return $gradeLevel->refresh();
    }

    /** @param array<string, mixed> $data */
    public function createSubject(array $data): Subject
    {
        $subject = Subject::query()->create($data)->refresh();
        $this->syncGradeLevels($subject, $data);

        return $subject->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        $subject->fill($data)->save();
        $this->syncGradeLevels($subject, $data);

        return $subject->refresh();
    }

    public function archiveSubject(Subject $subject): Subject
    {
        $subject->forceFill(['status' => Subject::STATUS_ARCHIVED])->save();

        return $subject->refresh();
    }

    /** @param array<string, mixed> $data */
    public function createSection(array $data): Section
    {
        return Section::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateSection(Section $section, array $data): Section
    {
        $section->fill($data)->save();

        return $section->refresh();
    }

    public function archiveSection(Section $section): Section
    {
        $section->forceFill(['status' => Section::STATUS_ARCHIVED])->save();

        return $section->refresh();
    }

    /** @param array<string, mixed> $data */
    private function ensureTermWithinYear(AcademicYear $year, array $data): void
    {
        $startsOn = (string) $data['starts_on'];
        $endsOn = (string) $data['ends_on'];

        if ($startsOn < Carbon::parse($year->starts_on)->toDateString() || $endsOn > Carbon::parse($year->ends_on)->toDateString()) {
            throw ValidationException::withMessages([
                'starts_on' => ['The academic term must be within its academic year dates.'],
                'ends_on' => ['The academic term must be within its academic year dates.'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function syncGradeLevels(Subject $subject, array $data): void
    {
        if (array_key_exists('grade_level_ids', $data)) {
            $weeklyPeriods = isset($data['weekly_periods']) ? (int) $data['weekly_periods'] : null;
            $sync = collect($data['grade_level_ids'] ?? [])->mapWithKeys(fn (mixed $id): array => [(int) $id => ['weekly_periods' => $weeklyPeriods]])->all();
            $subject->gradeLevels()->sync($sync);
        } elseif (array_key_exists('weekly_periods', $data)) {
            DB::connection('tenant')->table('grade_level_subject')->where('subject_id', $subject->id)->update(['weekly_periods' => $data['weekly_periods']]);
        }
    }
}
