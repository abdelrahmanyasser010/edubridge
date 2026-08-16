<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    private string $contractPath = 'openapi/openapi.yaml';

    public function test_openapi_skeleton_defines_required_contract_sections(): void
    {
        $contract = $this->contract();

        $this->assertStringContainsString('openapi: 3.1.0', $contract);
        $this->assertStringContainsString('url: /api/v1', $contract);
        $this->assertStringContainsString('securitySchemes:', $contract);
        $this->assertStringContainsString('bearerAuth:', $contract);
        $this->assertStringContainsString('SuccessEnvelope:', $contract);
        $this->assertStringContainsString('CollectionEnvelope:', $contract);
        $this->assertStringContainsString('ErrorEnvelope:', $contract);
        $this->assertStringContainsString('PaginationLinks:', $contract);
        $this->assertStringContainsString('PaginationMeta:', $contract);
    }

    public function test_openapi_skeleton_documents_current_foundation_routes(): void
    {
        $contract = $this->contract();

        foreach ([
            '/auth/login:',
            '/auth/me:',
            '/auth/device-sessions:',
            '/auth/logout:',
            '/auth/device-sessions/{deviceSession}/revoke:',
            '/files/{publicId}/download:',
        ] as $path) {
            $this->assertStringContainsString($path, $contract);
        }
    }

    public function test_openapi_operation_ids_are_unique(): void
    {
        preg_match_all('/^\s*operationId:\s*([A-Za-z0-9_]+)/m', $this->contract(), $matches);

        $operationIds = $matches[1];

        $this->assertNotEmpty($operationIds);
        $this->assertSame($operationIds, array_values(array_unique($operationIds)));
    }

    public function test_openapi_refs_target_existing_components(): void
    {
        $contract = $this->contract();
        preg_match_all('/components\/(schemas|responses|parameters|securitySchemes)\/([A-Za-z0-9_]+)/', $contract, $refs, PREG_SET_ORDER);

        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            $this->assertStringContainsString('  '.$ref[1].':', $contract, 'Missing section '.$ref[1]);
            $this->assertStringContainsString('    '.$ref[2].':', $contract, 'Missing component '.$ref[1].'/'.$ref[2]);
        }
    }

    public function test_openapi_file_uses_spaces_and_no_tabs(): void
    {
        $this->assertStringNotContainsString(chr(9), $this->contract());
    }

    private function contract(): string
    {
        $path = base_path($this->contractPath);

        $this->assertFileExists($path);
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertNotSame('', trim($contents));

        return $contents;
    }
}
