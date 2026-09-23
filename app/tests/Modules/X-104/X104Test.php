<?php

declare(strict_types=1);

namespace Tests\Modules\X104;

use App\Modules\X104\Actions\PluginActivateAction;
use App\Modules\X104\Actions\PluginDeactivateAction;
use App\Modules\X104\Actions\PluginSyncAction;
use App\Modules\X104\Events\PluginInstalled;
use App\Modules\X104\Events\PluginSynced;
use App\Modules\X104\Models\PluginInstall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X104Test extends TestCase
{
    private PluginActivateAction $activateAction;

    private PluginDeactivateAction $deactivateAction;

    private PluginSyncAction $syncAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateAction = new PluginActivateAction;
        $this->deactivateAction = new PluginDeactivateAction;
        $this->syncAction = new PluginSyncAction;
    }

    /**
     * TEST ANCHOR
     * activation on a stock theme changes zero theme files and the four pillars respond;
     * a deactivation removes every injected asset
     */
    public function test_anchor_zero_theme_files_modified_four_pillars_and_clean_deactivation(): void
    {
        Event::fake([PluginInstalled::class, PluginSynced::class]);

        $biz = TestCase::provisionTenant(['name' => 'WordPress Client Site', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $siteUrl = 'https://plumbingking.com';
        $apiKey = 'wp_sec_live_key_99281';

        // 1. Activation changes ZERO theme files and the four pillars respond (TEST ANCHOR)
        $install = $this->activateAction->activate($biz->id, $siteUrl, $apiKey);

        $this->assertTrue($install->is_active);
        $this->assertEquals(0, $install->theme_files_modified_count, 'Stock theme activation must change 0 theme files');

        // Verify four pillars respond
        $this->assertTrue($install->pillars_active['chat']);
        $this->assertTrue($install->pillars_active['pixel']);
        $this->assertTrue($install->pillars_active['reviews_badge']);
        $this->assertTrue($install->pillars_active['form_hijack']);

        $this->assertNotEmpty($install->injected_assets);
        Event::assertDispatched(PluginInstalled::class);

        // 2. Deactivation removes EVERY injected asset (TEST ANCHOR)
        $deactivated = $this->deactivateAction->deactivate($biz->id, $siteUrl);

        $this->assertFalse($deactivated->is_active);
        $this->assertEmpty($deactivated->injected_assets, 'Deactivation must remove every injected asset');

        $saved = PluginInstall::where('business_id', $biz->id)->where('site_url', $siteUrl)->first();
        $this->assertNotNull($saved);
        $this->assertFalse($saved->is_active);
        $this->assertEmpty($saved->injected_assets);
    }

    /**
     * [G3-60], [G6-26], [G6-34], [G6-35], [G7-46], [G8-38], [G19-16]
     * Plugin agency branding, universal takeover path & sync
     */
}
