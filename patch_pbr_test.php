<?php

$content = file_get_contents('app/tests/Modules/X-157/PublicBookingRouteTest.php');

$newTest = <<<'CODE'
    public function test_deployed_site_uses_site_variant_palette(): void
    {
        $owner = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $biz = self::provisionTenant(['owner_user_id' => $owner->id, 'industry' => 'trades', 'site_variant' => 'c']);
        
        \App\Models\IndustryStartingPoint::create([
            'family' => \App\Enums\IndustryFamily::Trades->value,
            'palette' => ['surface' => '#ffffff', 'ink' => '#000000', 'primary' => '#ff0000', 'accent' => '#0000ff'],
            'type_pairing' => ['heading' => 'serif', 'body' => 'sans'],
            'section_order' => ['hero', 'about', 'gallery', 'reviews_strip', 'contact'],
        ]);

        $zone = app(\App\Modules\X157\Actions\EdgeProvisionAction::class)->handle($biz->id, 'roofing.example.com', true);
        
        $page = \App\Modules\X103\Models\Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
            'is_published' => true,
        ]);
        
        $commitId = 'commit_'.\Illuminate\Support\Str::random(16);
        $version = \App\Modules\X103\Models\PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [['type' => 'hero', 'headline' => 'H']],
            'pixel_installed' => true,
        ]);
        $page->update(['current_version_id' => $version->id]);

        $deploy = app(\App\Modules\X157\Actions\EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: 'Roofing Corp'
        );

        $response = $this->get('/sites/'.$biz->id.'/'.$deploy->deploy_hash);
        $response->assertStatus(200);
        $response->assertSee('--color-accent: #ff0000', false);
    }
}
CODE;

$content = preg_replace('/\}\s*$/', $newTest, $content);
file_put_contents('app/tests/Modules/X-157/PublicBookingRouteTest.php', $content);
