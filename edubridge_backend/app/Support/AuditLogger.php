<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AuditLogger
{
    /**
     * @var list<string>
     */
    private array $redactedKeys = ['password', 'token', 'access_token', 'secret', 'secret_ref', 'authorization'];

    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $action, ?string $subjectType = null, ?string $subjectId = null, ?array $before = null, ?array $after = null, ?Request $request = null): void
    {
        $this->tenantContext->tenant();
        $request ??= request();

        DB::connection('tenant')->table('audit_logs')->insert([
            'actor_central_user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before === null ? null : json_encode($this->redact($before), JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($this->redact($after), JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'request_id' => ApiResponse::requestId($request),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $this->redactedKeys, true)) {
                $data[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
