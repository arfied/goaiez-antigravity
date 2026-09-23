<?php

$content = file_get_contents('/home/goaiez/agents/grs-antig-site/app/tests/Modules/X-157/X157Test.php');

$newTest = <<<'TEST'

    public function test_tenant_media_route_serves_image_and_enforces_auth_and_existence(): void
    {
        Http::fake();
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme.com', true);

        $deploy = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'test_hash_media',
            'status' => 'deployed',
            'measured_ttfb_ms' => 100,
            'speed_budget_ms' => 1500,
        ]);

        Storage::disk('local')->put("site-inventory/{$biz->id}/image.jpg", 'IMAGE_CONTENT');

        $response = $this->get("/sites/{$biz->id}/test_hash_media/media/image.jpg");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');

        $this->get("/sites/{$biz->id}/test_hash_media/media/missing.jpg")->assertStatus(404);

        $biz2 = TestCase::provisionTenant(['name' => 'Foreign', 'currency' => 'USD']);
        $this->get("/sites/{$biz2->id}/test_hash_media/media/image.jpg")->assertStatus(404);
    }
TEST;

$content = preg_replace('/}\s*$/', $newTest."\n}\n", $content);

// Modify test_the_published_route_carries_all_seven_elements
$replacement = <<<'REP'
        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
                ['type' => 'hero', 'image_path' => '/some-image.jpg'],
            ]);
REP;
$content = preg_replace('/\$site = app\(SitePublishAction::class\)\s*->handle\(\$biz->id,\s*\$page->id,\s*\[\s*\[\'type\' => \'chat\'\],\s*\[\'type\' => \'form_capture\'\],\s*\[\'type\' => \'dni\'\],\s*\]\);/ms', $replacement, $content);

$assertReplacement = <<<'REP'
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('<img src="/sites/' . $biz->id . '/' . $deploy['deploy_hash'] . '/media/some-image.jpg"', $html);
REP;
$content = str_replace("\$this->assertStringContainsString('application/ld+json', \$html);", $assertReplacement, $content);

file_put_contents('/home/goaiez/agents/grs-antig-site/app/tests/Modules/X-157/X157Test.php', $content);
