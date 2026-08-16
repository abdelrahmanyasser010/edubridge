<?php

namespace App\Actions\Reporting;

use App\Models\Assessment;
use App\Models\ReportExport;
use App\Support\AuditLogger;
use App\Support\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardGradeExportManager
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function requestAssessmentExport(Assessment $assessment, int $actorCentralUserId): array
    {
        return DB::connection('tenant')->transaction(function () use ($assessment, $actorCentralUserId): array {
            $export = ReportExport::query()->create([
                'public_id' => 'exp_'.Str::lower((string) Str::ulid()),
                'report_type' => ReportExport::TYPE_ASSESSMENT_GRADE_SHEET,
                'status' => ReportExport::STATUS_QUEUED,
                'payload' => [
                    'assessment_id' => (string) $assessment->id,
                    'academic_term_id' => (string) $assessment->academic_term_id,
                    'allocation_id' => (string) $assessment->allocation_id,
                ],
                'requested_by_central_user_id' => $actorCentralUserId,
            ]);

            $eventId = $this->outbox->publishAfterCommit('report.grade_sheet_export_requested', [
                'export_id' => $export->public_id,
                'assessment_id' => (string) $assessment->id,
                'actor_central_user_id' => (string) $actorCentralUserId,
            ]);

            $export->forceFill(['outbox_event_id' => $eventId])->save();
            $this->audit->record('dashboard.grade_sheet_export.requested', ReportExport::class, (string) $export->id, null, [
                'export_id' => $export->public_id,
                'assessment_id' => (string) $assessment->id,
            ]);

            return $this->item($export->refresh());
        });
    }

    /** @return array<string, mixed> */
    public function export(string $publicId): array
    {
        $export = ReportExport::query()->where('public_id', $publicId)->first();

        if (! $export instanceof ReportExport) {
            throw new NotFoundHttpException;
        }

        return $this->item($export);
    }

    /** @return array<string, mixed> */
    private function item(ReportExport $export): array
    {
        return [
            'export_id' => $export->public_id,
            'report_type' => $export->report_type,
            'status' => $export->status,
            'download_url' => $export->download_url,
            'payload' => $export->payload,
            'outbox_event_id' => $export->outbox_event_id,
            'requested_by_central_user_id' => (string) $export->requested_by_central_user_id,
            'completed_at' => $export->completed_at === null ? null : Carbon::parse($export->completed_at)->toJSON(),
            'created_at' => $export->created_at === null ? null : Carbon::parse($export->created_at)->toJSON(),
        ];
    }
}
