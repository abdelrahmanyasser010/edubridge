<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequirementInventoryTest extends TestCase
{
    public function test_requirement_inventory_exists_and_has_no_empty_dispositions(): void
    {
        $inventory = $this->inventory();
        preg_match_all('/^\| REQ-\d{3} \|.+\|$/m', $inventory, $rows);

        $this->assertGreaterThanOrEqual(60, count($rows[0]));

        foreach ($rows[0] as $row) {
            $columns = array_map('trim', explode('|', trim($row, '|')));

            $this->assertCount(5, $columns, $row);
            $this->assertMatchesRegularExpression('/^REQ-\d{3}$/', $columns[0]);
            $this->assertNotSame('', $columns[1], $row);
            $this->assertNotSame('', $columns[2], $row);
            $this->assertNotSame('', $columns[3], $row);
            $this->assertMatchesRegularExpression('/(DONE|FND-|CORE-|ATT-|ASN-|NTF-|BEH-|MSG-|OPS-|GRD-|PAY-|WAL-|TRN-|RPT-|ANA-|REL-|DEC-|OUT-OF-SCOPE)/', $columns[4], $row);
        }
    }

    public function test_requirement_inventory_references_legacy_sources(): void
    {
        $inventory = $this->inventory();

        foreach ([
            'edubridge_full_system_specs.md',
            'apis doc dashboard COMPLETE.md',
            'apis doc teacher app.md',
            'apis doc parent app.md',
        ] as $source) {
            $this->assertStringContainsString($source, $inventory);
        }
    }

    private function inventory(): string
    {
        $path = base_path('docs/07_REQUIREMENT_INVENTORY.md');

        $this->assertFileExists($path);
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        return $contents;
    }
}
