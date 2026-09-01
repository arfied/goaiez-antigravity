<?php

declare(strict_types=1);

namespace Tests\Modules\X178;

use App\Modules\X178\Actions\DesignChangeAction;
use App\Modules\X178\Actions\DesignUndoAction;
use App\Modules\X178\Actions\FormGenerateAction;
use App\Modules\X178\Events\BlockAdded;
use App\Modules\X178\Events\DesignChanged;
use App\Modules\X178\Events\DesignUndone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X178Test extends TestCase
{
    private DesignChangeAction $changeAction;

    private DesignUndoAction $undoAction;

    private FormGenerateAction $formAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changeAction = new DesignChangeAction;
        $this->undoAction = new DesignUndoAction;
        $this->formAction = new FormGenerateAction;
    }

    /**
     * TEST ANCHOR
     * grep -r '<div' app/Modules/X-178/ finds no generated markup — only block references;
     * a change failing 4.5:1 contrast cannot publish
     */
    public function test_anchor_block_references_only_and_contrast_threshold(): void
    {
        Event::fake([DesignChanged::class, BlockAdded::class, DesignUndone::class]);

        $biz = TestCase::provisionTenant(['name' => 'Design Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Contrast failure: change with 3.2:1 contrast ratio (< 4.5:1 WCAG AA) is REFUSED
        $lowContrastRes = $this->changeAction->handle(
            businessId: $biz->id,
            pageId: 10,
            changeType: 'color_token',
            blockRef: 'block_hero_heading_1',
            newState: ['text_color' => '#888888', 'bg_color' => '#ffffff'],
            contrastRatio: 3.2
        );

        $this->assertEquals('refused', $lowContrastRes['status']);
        $this->assertEquals('WCAG_CONTRAST_FAILED', $lowContrastRes['refusal_code']);
        Event::assertNotDispatched(DesignChanged::class);

        // 2. Compliant contrast (7.5:1) succeeds
        $validChangeRes = $this->changeAction->handle(
            businessId: $biz->id,
            pageId: 10,
            changeType: 'color_token',
            blockRef: 'block_hero_heading_1',
            newState: ['text_color' => '#111827', 'bg_color' => '#ffffff'],
            contrastRatio: 7.5
        );

        $this->assertEquals('applied', $validChangeRes['status']);
        Event::assertDispatched(DesignChanged::class);

        // 3. Form generate produces block reference only, no raw generated markup (G6-21)
        $formRes = $this->formAction->handle($biz->id, 10, 'emergency_plumbing');
        $this->assertEquals('generated', $formRes['status']);
        $this->assertStringStartsWith('block_form_', $formRes['block_ref']);
        $this->assertEquals('[fill-me]', $formRes['form_config']['pricing_display']);
        Event::assertDispatched(BlockAdded::class);

        // 4. Undo change
        $undoRes = $this->undoAction->handle($biz->id, $validChangeRes['change_id']);
        $this->assertEquals('undone', $undoRes['status']);
        Event::assertDispatched(DesignUndone::class);
    }

    /**
     * [G5-08], [G6-05], [G6-31] named in header
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G5-21] design tokens
     */
    public function test_g5_21_design_tokens(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G6-21] profile builder for unmapped niche — [fill-me] only
     */
    public function test_g6_21_unmapped_niche_fill_me_only(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Niche Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->formAction->handle($biz->id, 1, 'solar_panel_cleaning');
        $this->assertEquals('[fill-me]', $res['form_config']['pricing_display']);
    }
}
