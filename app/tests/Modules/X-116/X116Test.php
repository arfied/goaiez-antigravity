<?php

declare(strict_types=1);

namespace Tests\Modules\X116;

use App\Modules\X116\Actions\FunnelInstantiateAction;
use App\Modules\X116\Actions\TemplateGenerateAction;
use App\Modules\X116\Actions\TemplateScoreAction;
use App\Modules\X116\Events\TemplateGenerated;
use App\Modules\X116\Events\TemplateScored;
use App\Modules\X116\Models\Template;
use App\Modules\X116\Models\TemplateBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X116Test extends TestCase
{
    private TemplateGenerateAction $generateAction;

    private TemplateScoreAction $scoreAction;

    private FunnelInstantiateAction $funnelAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generateAction = new TemplateGenerateAction;
        $this->scoreAction = new TemplateScoreAction;
        $this->funnelAction = new FunnelInstantiateAction;
    }

    /**
     * TEST ANCHOR
     * every block on a generated page names the module and manifest it rendered from —
     * a block with no @renders source fails generation;
     * two industries from different families produce visibly different palette, type scale and rhythm, asserted on the design tokens
     */
    public function test_anchor_block_provenance_and_distinct_industry_design_tokens(): void
    {
        Event::fake([TemplateGenerated::class, TemplateScored::class]);

        $biz = TestCase::provisionTenant(['name' => 'Template Engine Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Generate template for Plumbing family (G6-28, G6-29)
        $plumbingTemplate = $this->generateAction->generateTemplate(
            businessId: $biz->id,
            industryCode: 'plumbing',
            funnelType: 'emergency',
            blocksDefinition: [
                ['module' => 'X-114', 'block' => 'brand_kit', 'order' => 1],
                ['module' => 'X-103', 'block' => 'booking_calendar', 'order' => 2],
            ]
        );

        // 2. Generate template for Legal family (G6-29)
        $legalTemplate = $this->generateAction->generateTemplate(
            businessId: $biz->id,
            industryCode: 'legal',
            funnelType: 'quote',
            blocksDefinition: [
                ['module' => 'X-114', 'block' => 'brand_kit', 'order' => 1],
                ['module' => 'X-194', 'block' => 'custom_report', 'order' => 2],
            ]
        );

        // 3. Assert visibly different palette, type scale, and rhythm (TEST ANCHOR)
        $plumbingTokens = $plumbingTemplate->design_tokens;
        $legalTokens = $legalTemplate->design_tokens;

        $this->assertNotEquals($plumbingTokens['palette']['primary'], $legalTokens['palette']['primary'], 'Palette differs across industry families');
        $this->assertNotEquals($plumbingTokens['type_scale'], $legalTokens['type_scale'], 'Type scale differs across industry families');
        $this->assertNotEquals($plumbingTokens['rhythm'], $legalTokens['rhythm'], 'Rhythm differs across industry families');

        // 4. Assert block source manifest provenance (TEST ANCHOR)
        $blocks = TemplateBlock::where('business_id', $biz->id)->where('template_id', $plumbingTemplate->id)->get();
        $this->assertCount(2, $blocks);
        foreach ($blocks as $block) {
            $this->assertNotEmpty($block->render_source_module);
            $this->assertNotEmpty($block->render_source_block);
        }

        // 5. Block with NO @renders source FAILS generation (TEST ANCHOR)
        $this->expectException(InvalidArgumentException::class);
        $this->generateAction->generateTemplate(
            businessId: $biz->id,
            industryCode: 'hvac',
            funnelType: 'book',
            blocksDefinition: [
                ['module' => '', 'block' => '', 'order' => 1], // Missing @renders source
            ]
        );
    }

    /**
     * [G6-03], [G6-12], [G6-23], [G6-28], [G6-29], [G6-30], [G8-19], [G8-35]
     */
    public function test_template_capabilities(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
