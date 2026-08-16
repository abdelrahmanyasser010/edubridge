<?php

namespace Tests\Feature;

use Tests\TestCase;

class RunbookDocumentationTest extends TestCase
{
    public function test_observability_runbook_has_required_sections(): void
    {
        $content = file_get_contents(base_path('docs/runbooks/observability.md'));

        $this->assertIsString($content);
        $this->assertStringContainsString('## Signals', $content);
        $this->assertStringContainsString('## Alerts', $content);
        $this->assertStringContainsString('## Incident flow', $content);
        $this->assertStringContainsString('## Deploy checklist', $content);
    }

    public function test_performance_security_hardening_has_required_checks(): void
    {
        $content = file_get_contents(base_path('docs/runbooks/performance_security_hardening.md'));

        $this->assertIsString($content);
        $this->assertStringContainsString('## Dependency scan', $content);
        $this->assertStringContainsString('## Query review', $content);
        $this->assertStringContainsString('## Load and abuse checks', $content);
        $this->assertStringContainsString('## Threat checks', $content);
    }

    public function test_backup_restore_release_runbook_has_rehearsal_evidence(): void
    {
        $content = file_get_contents(base_path('docs/runbooks/backup_restore_release.md'));

        $this->assertIsString($content);
        $this->assertStringContainsString('## Restore rehearsal', $content);
        $this->assertStringContainsString('measured restore duration', $content);
        $this->assertStringContainsString('## Smoke after restore', $content);
        $this->assertStringContainsString('## Rollback rehearsal', $content);
    }

    public function test_legacy_docs_are_archived_with_deprecated_readme(): void
    {
        $readme = file_get_contents(base_path('docs/legacy/README.md'));

        $this->assertIsString($readme);
        $this->assertStringContainsString('Status: Deprecated', $readme);
        $this->assertFileExists(base_path('docs/legacy/edubridge_full_system_specs.md'));
        $this->assertFileExists(base_path('docs/legacy/apis doc dashboard COMPLETE.md'));
        $this->assertFileExists(base_path('docs/legacy/apis doc teacher app.md'));
        $this->assertFileExists(base_path('docs/legacy/apis doc parent app.md'));
    }
}
