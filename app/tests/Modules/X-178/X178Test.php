<?php

declare(strict_types=1);

namespace Tests\Modules\X178;

use App\Models\User;
use App\Modules\X178\Actions\DesignChangeAction;
use App\Modules\X178\Actions\DesignUndoAction;
use App\Modules\X178\Actions\FormGenerateAction;
use App\Modules\X178\Events\BlockAdded;
use App\Modules\X178\Events\DesignChanged;
use App\Modules\X178\Events\DesignUndone;
use App\Modules\X178\Models\DesignChange;
use App\Support\Tenancy;
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
        Tenancy::set((int) $biz->id);

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
        $path = base_path('app/Modules/X-178');
        $output = shell_exec(sprintf('grep -rnE %s %s', escapeshellarg('G5-08|G6-05|G6-31'), escapeshellarg($path)));
        $this->assertNotEmpty($output, 'Header capabilities must be named in the module.');
    }

    /**
     * [G5-21] design tokens
     */
    public function test_g5_21_design_tokens(): void
    {
        $action = new DesignChangeAction;
        $biz = TestCase::provisionTenant(['name' => 'Design Tokens', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $tokens = ['primary_color' => '#ff0000', 'font_size' => '16px'];
        $res = $action->handle($biz->id, 1, 'update_tokens', 'block_theme', $tokens);

        $this->assertEquals('applied', $res['status']);
        $change = DesignChange::find($res['change_id']);
        $this->assertEquals($tokens, $change->new_state);
    }

    /**
     * [G6-21] profile builder for unmapped niche — [fill-me] only
     */
    public function test_g6_21_unmapped_niche_fill_me_only(): void
    {
        $action = new FormGenerateAction;
        $biz = TestCase::provisionTenant(['name' => 'Niche Form', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $action->handle($biz->id, 1, 'unmapped_niche');

        $this->assertEquals('generated', $res['status']);
        $this->assertEquals('[fill-me]', $res['form_config']['pricing_display'], 'Pricing must be [fill-me] only for unmapped niches (P-092)');
    }

    public function test_screen_renders_only_for_authenticated_users(): void
    {
        $response = $this->get(route('x-178.site-editor-assistant'));
        $response->assertRedirect('/login');
    }

    public function test_screen_renders(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Design Tenant 2', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $user = User::find($biz->owner_user_id) ?? User::first();

        $response = $this->actingAs($user)->get(route('x-178.site-editor-assistant'));
        $response->assertOk();
    }
}
