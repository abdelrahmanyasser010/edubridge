<?php

namespace App\Actions\Reporting;

use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

class OperationalReportManager
{
    public function __construct(private readonly Outbox $outbox) {}

    /** @return array<string, mixed> */
    public function dailyOverview(string $date): array
    {
        return [
            'date' => $date,
            'attendance_absent' => DB::connection('tenant')->table('attendance_records')->where('status', 'absent')->whereDate('submitted_at', $date)->count(),
            'open_behavior_notes' => DB::connection('tenant')->table('behavior_notes')->whereIn('status', ['pending_review', 'published', 'acknowledged'])->count(),
            'open_fees' => DB::connection('tenant')->table('fees')->where('status', 'open')->count(),
            'active_bus_trips' => DB::connection('tenant')->table('bus_trips')->where('status', 'active')->count(),
        ];
    }

    public function requestExport(string $report, string $date, int $actorCentralUserId): string
    {
        return $this->outbox->publishAfterCommit('report.export_requested', [
            'report' => $report,
            'date' => $date,
            'actor_central_user_id' => (string) $actorCentralUserId,
        ]);
    }
}
