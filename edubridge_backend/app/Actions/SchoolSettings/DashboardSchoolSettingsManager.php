<?php

namespace App\Actions\SchoolSettings;

use App\Models\School;
use App\Support\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class DashboardSchoolSettingsManager
{
    /** @var list<string> */
    private array $knownIntegrations = ['sms_gateway', 'push_notifications', 'payment_provider', 'storage'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
    ) {}

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $school = $this->school();

        return [
            'school' => [
                'id' => (string) $school->id,
                'code' => $school->code,
                'name' => $school->name,
                'timezone' => $school->timezone,
                'locale' => $school->locale,
                'currency' => $school->currency,
            ],
            'academic' => [
                'active_academic_year_id' => $this->stringOrNull($this->setting('academic.active_academic_year_id', $this->activeAcademicYearId())),
                'active_term_id' => $this->stringOrNull($this->setting('academic.active_term_id', $this->activeTermId())),
            ],
            'attendance' => [
                'late_after_minutes' => (int) $this->setting('attendance.late_after_minutes', 10),
                'absence_warning_threshold' => (int) $this->setting('attendance.absence_warning_threshold', 5),
            ],
            'notifications' => [
                'push_enabled' => (bool) $this->setting('notifications.push_enabled', true),
                'sms_enabled' => (bool) $this->setting('notifications.sms_enabled', true),
                'email_enabled' => (bool) $this->setting('notifications.email_enabled', false),
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateSettings(array $data): array
    {
        $before = $this->settings();
        $school = $this->school();

        if (isset($data['school']) && is_array($data['school'])) {
            $school->fill($data['school'])->save();
        }

        foreach (['academic', 'attendance', 'notifications'] as $group) {
            if (! isset($data[$group]) || ! is_array($data[$group])) {
                continue;
            }

            foreach ($data[$group] as $key => $value) {
                $this->putSetting($group.'.'.$key, $value);
            }
        }

        $after = $this->settings();
        $this->audit->record('school.settings.updated', School::class, (string) $school->id, $before, $after);

        return $after;
    }

    /** @return list<array<string, mixed>> */
    public function integrations(): array
    {
        $rows = DB::connection('tenant')->table('integration_settings')->get()->keyBy('key');

        return collect($this->knownIntegrations)
            ->map(function (string $key) use ($rows): array {
                $row = $rows->get($key);

                if ($row === null) {
                    return [
                        'key' => $key,
                        'provider' => null,
                        'status' => 'not_configured',
                        'masked_api_key' => null,
                        'config' => [],
                        'last_tested_at' => null,
                        'last_test_status' => null,
                    ];
                }

                $config = $row->config === null ? [] : json_decode((string) $row->config, true, 512, JSON_THROW_ON_ERROR);

                return [
                    'key' => (string) $row->key,
                    'provider' => $row->provider === null ? null : (string) $row->provider,
                    'status' => (string) $row->status,
                    'masked_api_key' => $config['masked_api_key'] ?? null,
                    'config' => $this->publicConfig($config),
                    'last_tested_at' => $row->last_tested_at === null ? null : (string) $row->last_tested_at,
                    'last_test_status' => $row->last_test_status === null ? null : (string) $row->last_test_status,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function updateIntegration(string $key, array $data): array
    {
        $before = $this->integrationByKey($key);
        $config = $this->sanitizeIntegrationConfig($data);
        $secretRef = array_key_exists('api_key', $data) && is_string($data['api_key']) && $data['api_key'] !== ''
            ? 'tenant:'.$this->tenantContext->schoolId().':integration:'.$key.':api_key'
            : ($before['secret_ref'] ?? null);

        DB::connection('tenant')->table('integration_settings')->updateOrInsert(
            ['key' => $key],
            [
                'provider' => $data['provider'] ?? $before['provider'] ?? null,
                'status' => $data['status'] ?? 'connected',
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
                'secret_ref' => $secretRef,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $after = $this->integrationByKey($key);
        $this->audit->record('school.integration.updated', 'integration_setting', $key, $this->withoutSecretRef($before), $this->withoutSecretRef($after));

        return $this->publicIntegration($after);
    }

    /** @return array<string, mixed> */
    public function testIntegration(string $key): array
    {
        $integration = $this->integrationByKey($key);
        $ok = ($integration['status'] ?? 'not_configured') === 'connected' && ($integration['secret_ref'] ?? null) !== null;
        $status = $ok ? 'ok' : 'not_ready';

        DB::connection('tenant')->table('integration_settings')->updateOrInsert(
            ['key' => $key],
            [
                'provider' => $integration['provider'] ?? null,
                'status' => $integration['status'] ?? 'not_configured',
                'config' => json_encode($integration['config'] ?? [], JSON_THROW_ON_ERROR),
                'secret_ref' => $integration['secret_ref'] ?? null,
                'last_tested_at' => now(),
                'last_test_status' => $status,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->audit->record('school.integration.tested', 'integration_setting', $key, null, ['status' => $status]);

        return [
            'key' => $key,
            'status' => $status,
            'message' => $ok ? 'Integration configuration is ready for sandbox verification.' : 'Integration is not configured with a server-side secret reference.',
        ];
    }

    private function school(): School
    {
        return School::query()->findOrFail($this->tenantContext->schoolId());
    }

    private function setting(string $key, mixed $default): mixed
    {
        $value = DB::connection('tenant')->table('school_settings')->where('key', $key)->value('value');

        if (! is_string($value)) {
            return $default;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function putSetting(string $key, mixed $value): void
    {
        DB::connection('tenant')->table('school_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function activeAcademicYearId(): ?int
    {
        $id = DB::connection('tenant')->table('academic_years')->where('status', 'active')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function activeTermId(): ?int
    {
        $id = DB::connection('tenant')->table('academic_terms')->where('status', 'active')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** @return array<string, mixed> */
    private function integrationByKey(string $key): array
    {
        $row = DB::connection('tenant')->table('integration_settings')->where('key', $key)->first();

        if ($row === null) {
            return ['key' => $key, 'provider' => null, 'status' => 'not_configured', 'config' => [], 'secret_ref' => null, 'last_tested_at' => null, 'last_test_status' => null];
        }

        return [
            'key' => (string) $row->key,
            'provider' => $row->provider === null ? null : (string) $row->provider,
            'status' => (string) $row->status,
            'config' => $row->config === null ? [] : json_decode((string) $row->config, true, 512, JSON_THROW_ON_ERROR),
            'secret_ref' => $row->secret_ref === null ? null : (string) $row->secret_ref,
            'last_tested_at' => $row->last_tested_at === null ? null : (string) $row->last_tested_at,
            'last_test_status' => $row->last_test_status === null ? null : (string) $row->last_test_status,
        ];
    }

    /** @param array<string, mixed> $data */
    private function sanitizeIntegrationConfig(array $data): array
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];

        foreach (['api_key', 'password', 'token', 'secret'] as $secretKey) {
            unset($config[$secretKey]);
        }

        if (isset($data['api_key']) && is_string($data['api_key']) && $data['api_key'] !== '') {
            $config['masked_api_key'] = $this->maskSecret($data['api_key']);
        }

        return $config;
    }

    private function maskSecret(string $secret): string
    {
        return '****-****-'.substr($secret, -4);
    }

    /** @param array<string, mixed> $config */
    private function publicConfig(array $config): array
    {
        unset($config['masked_api_key']);

        return $config;
    }

    /** @param array<string, mixed> $integration */
    private function withoutSecretRef(array $integration): array
    {
        unset($integration['secret_ref']);

        return $integration;
    }

    /** @param array<string, mixed> $integration */
    private function publicIntegration(array $integration): array
    {
        $config = is_array($integration['config'] ?? null) ? $integration['config'] : [];

        return [
            'key' => $integration['key'],
            'provider' => $integration['provider'],
            'status' => $integration['status'],
            'masked_api_key' => $config['masked_api_key'] ?? null,
            'config' => $this->publicConfig($config),
            'last_tested_at' => $integration['last_tested_at'] ?? null,
            'last_test_status' => $integration['last_test_status'] ?? null,
        ];
    }
}
