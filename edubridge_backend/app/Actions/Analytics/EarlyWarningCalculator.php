<?php

namespace App\Actions\Analytics;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class EarlyWarningCalculator
{
    public const VERSION = 'early-warning-v1';

    /** @return array{student_id:string,version:string,score:int,reasons:list<string>} */
    public function calculate(Student $student): array
    {
        $score = 0;
        $reasons = [];

        $absences = DB::connection('tenant')->table('attendance_records')->where('student_id', $student->id)->where('status', 'absent')->count();
        if ($absences >= 3) {
            $score += 30;
            $reasons[] = 'attendance_absences_ge_3';
        }

        $lowGrades = DB::connection('tenant')->table('grade_entries')
            ->join('assessments', 'assessments.id', '=', 'grade_entries.assessment_id')
            ->where('grade_entries.student_id', $student->id)
            ->where('assessments.status', 'published')
            ->whereRaw('(grade_entries.score * 100.0 / assessments.max_score) < 50')
            ->count();
        if ($lowGrades > 0) {
            $score += 40;
            $reasons[] = 'published_grade_below_50_percent';
        }

        $behavior = DB::connection('tenant')->table('behavior_notes')->where('student_id', $student->id)->whereNotNull('published_at')->whereIn('severity', ['high', 'critical'])->count();
        if ($behavior > 0) {
            $score += 30;
            $reasons[] = 'high_behavior_note';
        }

        return ['student_id' => (string) $student->id, 'version' => self::VERSION, 'score' => min(100, $score), 'reasons' => $reasons];
    }
}
