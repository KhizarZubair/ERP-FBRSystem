<?php

namespace Tests\Unit;

use App\Services\FbrErrorCatalog;
use PHPUnit\Framework\TestCase;

class FbrErrorCatalogTest extends TestCase
{
    private FbrErrorCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new FbrErrorCatalog;
    }

    public function test_catalog_contains_all_108_official_fbr_error_codes(): void
    {
        $all = $this->catalog->all();
        $this->assertCount(108, $all);

        // Core official error codes are all present
        foreach (['0001', '0002', '0003', '0005', '0046', '0052', '0056', '0057', '0058', '0077', '0078', '0079', '0080', '0082', '0083', '0100', '0102', '0104', '0106', '0107', '0108', '0300', '0401', '0156', '0177'] as $code) {
            $this->assertTrue($this->catalog->has($code), "Catalog missing official error code: $code");
        }
    }

    public function test_every_catalog_entry_has_complete_structured_attributes(): void
    {
        $validCategories = $this->catalog->categories();

        foreach ($this->catalog->all() as $code => $entry) {
            $this->assertNotEmpty($entry['category'], "Error $code missing category");
            $this->assertContains($entry['category'], $validCategories, "Error $code has invalid category {$entry['category']}");
            $this->assertNotEmpty($entry['official'], "Error $code missing official text");
            $this->assertNotEmpty($entry['brief'], "Error $code missing brief text");
            $this->assertNotEmpty($entry['guidance'], "Error $code missing guidance");
            $this->assertNotEmpty($entry['solution'], "Error $code missing actionable solution");
        }
    }

    public function test_baseline_guidance_strings_remain_exact_for_backwards_compatibility(): void
    {
        $this->assertSame(
            'Reload and select an FBR-approved rate for the selected sale type.',
            $this->catalog->guidance('0046')
        );
        $this->assertSame(
            'Ensure the seller NTN matches the owner of the configured token.',
            $this->catalog->guidance('0083')
        );
        $this->assertSame(
            'Review the item HS code and sale type combination.',
            $this->catalog->guidance('0052')
        );
        $this->assertSame(
            'Check seller NTN, token ownership, and IP approval.',
            $this->catalog->guidance('0401')
        );
    }

    public function test_sector_context_customizes_guidance(): void
    {
        // FMCG 3rd Schedule
        $fmcgGuidance = $this->catalog->guidance('0102', 'FMCG');
        $this->assertStringContainsString('FMCG 3rd Schedule Goods', $fmcgGuidance);
        $this->assertStringContainsString('Retail Price', $fmcgGuidance);

        // Steel Sector
        $steelGuidance = $this->catalog->guidance('0056', 'Steel');
        $this->assertStringContainsString('Steel Sector Rule', $steelGuidance);

        // Textile Sector
        $textileGuidance = $this->catalog->guidance('0100', 'Textile');
        $this->assertStringContainsString('Textile Sector Rule', $textileGuidance);
    }

    public function test_entry_helper_returns_structured_payload(): void
    {
        $entry = $this->catalog->entry('0080');
        $this->assertNotNull($entry);
        $this->assertSame('0080', $entry['code']);
        $this->assertSame('CALCULATION', $entry['category']);
        $this->assertStringContainsString('Further Tax', $entry['official']);
        $this->assertStringContainsString('Further Tax', $entry['solution']);

        $nullEntry = $this->catalog->entry('9999');
        $this->assertNull($nullEntry);
    }
}
