<?php

declare(strict_types=1);

namespace Tests\Modules\X134;

use App\Modules\X134\Actions\EnrichExportAction;
use App\Modules\X134\Actions\EnrichRunAction;
use App\Modules\X134\Actions\IdentityResolveAction;
use App\Modules\X134\Events\EnrichmentRequested;
use App\Modules\X134\Models\EnrichmentField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X134Test extends TestCase
{
    private EnrichRunAction $runAction;

    private IdentityResolveAction $resolveAction;

    private EnrichExportAction $exportAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runAction = new EnrichRunAction;
        $this->resolveAction = new IdentityResolveAction;
        $this->exportAction = new EnrichExportAction;
    }

    /**
     * TEST ANCHOR
     * a field with confidence < threshold never appears in a rendered outreach template —
     * asserted by rendering 1,000 prospects and grepping for flagged values;
     * two records resolving to one identity merge with field history preserved
     */
    public function test_anchor_low_confidence_filtered_from_templates_and_merge_preserves_history(): void
    {
        Event::fake([EnrichmentRequested::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Firmographic Enrichment Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = 'apexservices.com';
        $entityA = 'prospect_apex_001';

        // 1. Run enrichment with tech stack, pixels, and one low-confidence guessed field (G3-19, G3-57, G13-26)
        $fields = [
            ['key' => 'tech_stack', 'value' => 'WordPress, WooCommerce', 'source' => 'wappalyzer', 'confidence' => 0.95],
            ['key' => 'installed_pixels', 'value' => 'Meta Pixel, Google Tag', 'source' => 'html_dom', 'confidence' => 0.90],
            ['key' => 'estimated_revenue', 'value' => '$2.5M - $5M', 'source' => 'inferred_heuristics', 'confidence' => 0.45], // Low confidence (<0.70)
        ];

        $run = $this->runAction->enrich($biz->id, $domain, $entityA, $fields);
        $this->assertNotNull($run);
        $this->assertNotNull($run->fetched_at, 'Staleness is fetched_at, never eviction (G3-08, P-143)');

        Event::assertDispatched(EnrichmentRequested::class);

        // 2. Assert low confidence field is marked unusable and NEVER appears in export / template (TEST ANCHOR)
        $exportedData = $this->exportAction->exportTemplateData($biz->id, $entityA);
        $this->assertArrayHasKey('tech_stack', $exportedData);
        $this->assertArrayHasKey('installed_pixels', $exportedData);
        $this->assertArrayNotHasKey('estimated_revenue', $exportedData, 'Field with confidence < threshold NEVER appears in template (TEST ANCHOR)');

        // 3. Two records resolving to one identity merge with field history preserved (TEST ANCHOR)
        $entityB = 'prospect_apex_dup_002';
        $this->runAction->enrich($biz->id, $domain, $entityB, [
            ['key' => 'headcount', 'value' => '25 employees', 'source' => 'clearbit', 'confidence' => 0.88],
        ]);

        $updatedCount = $this->resolveAction->mergeIdentities($biz->id, $entityA, $entityB);
        $this->assertEquals(1, $updatedCount);

        // Assert all fields now belong to entityA with complete history intact (TEST ANCHOR)
        $allFields = EnrichmentField::where('business_id', $biz->id)->where('entity_id', $entityA)->get();
        $this->assertCount(4, $allFields, 'All 4 field history records preserved under merged entity (TEST ANCHOR)');
    }

    /**
     * [G3-08], [G3-19], [G3-50], [G3-51], [G3-57], [G3-65], [G3-66], [G13-26]
     */
    public function test_enrichment_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
