<?php

namespace App\Actions\Dashboard;

use App\Models\DashboardCanvasConfig;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DashboardCanvasConfigManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        $config = DashboardCanvasConfig::query()->where('key', $key)->first();

        if (! $config instanceof DashboardCanvasConfig) {
            return [
                'key' => $key,
                'exists' => false,
                'name' => null,
                'payload' => null,
                'version' => null,
                'updated_by_central_user_id' => null,
                'updated_at' => null,
            ];
        }

        return $this->item($config);
    }

    /**
     * @param  array{name?:string|null,payload:array<string, mixed>,expected_version?:int|null}  $data
     * @return array<string, mixed>
     */
    public function save(string $key, array $data, int $actorCentralUserId): array
    {
        return DB::connection('tenant')->transaction(function () use ($key, $data, $actorCentralUserId): array {
            $config = DashboardCanvasConfig::query()->where('key', $key)->lockForUpdate()->first();

            if ($config instanceof DashboardCanvasConfig && isset($data['expected_version']) && (int) $data['expected_version'] !== (int) $config->version) {
                throw new ConflictHttpException('Dashboard canvas config version is stale.');
            }

            $before = $config instanceof DashboardCanvasConfig ? $this->item($config) : null;
            if (! $config instanceof DashboardCanvasConfig) {
                $config = new DashboardCanvasConfig([
                    'key' => $key,
                    'version' => 0,
                ]);
            }

            $config->forceFill([
                'name' => $data['name'] ?? $config->name,
                'payload' => $data['payload'],
                'version' => (int) $config->version + 1,
                'updated_by_central_user_id' => $actorCentralUserId,
            ])->save();

            $after = $this->item($config->refresh());
            $this->audit->record('dashboard.canvas_config.saved', DashboardCanvasConfig::class, (string) $config->id, $before, [
                'key' => $key,
                'version' => $after['version'],
            ]);

            return $after;
        });
    }

    /** @return array<string, mixed> */
    private function item(DashboardCanvasConfig $config): array
    {
        return [
            'id' => (string) $config->id,
            'key' => $config->key,
            'exists' => true,
            'name' => $config->name,
            'payload' => $config->payload,
            'version' => $config->version,
            'updated_by_central_user_id' => (string) $config->updated_by_central_user_id,
            'updated_at' => $config->updated_at?->toJSON(),
        ];
    }
}
