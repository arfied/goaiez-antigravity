<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * [G8-04] Breadcrumb Generation
 */
final class BreadcrumbSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rendered_schema_contains_breadcrumbs_when_hierarchy_present(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Breadcrumb Tenant']);
        Tenancy::set((int) $biz->id);

        Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => 'services']);
        $page = Page::create(['business_id' => $biz->id, 'title' => 'Plumbing', 'slug' => 'services/plumbing']);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'crumb1.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_123',
            businessName: 'My Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">\n(.*?)\n<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script tag should be present');

        $jsonLd = json_decode($matches[1], true);
        $this->assertIsArray($jsonLd);

        $this->assertArrayHasKey('breadcrumb', $jsonLd);
        $this->assertEquals('BreadcrumbList', $jsonLd['breadcrumb']['@type']);
        $this->assertEquals([
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Services',
                'item' => 'https://crumb1.example.com/services',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Plumbing',
                'item' => 'https://crumb1.example.com/services/plumbing',
            ],
        ], $jsonLd['breadcrumb']['itemListElement']);
    }

    public function test_rendered_schema_has_no_breadcrumbs_when_no_hierarchy(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Breadcrumb Tenant 2']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'crumb2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_124',
            businessName: 'My Biz 2'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('application/ld+json', $html, 'JSON-LD should still be emitted');

        preg_match('/<script type="application\/ld\+json">\n(.*?)\n<\/script>/s', $html, $matches);
        $jsonLd = json_decode($matches[1], true);

        $this->assertArrayNotHasKey('breadcrumb', $jsonLd);
    }

    public function test_rendered_schema_has_no_breadcrumbs_when_hierarchy_missing_parent(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Breadcrumb Tenant 3']);
        Tenancy::set((int) $biz->id);

        // Missing the 'services' page, so the hierarchy is unusable
        $page = Page::create(['business_id' => $biz->id, 'title' => 'Plumbing', 'slug' => 'services/plumbing']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'crumb3.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_125',
            businessName: 'My Biz 3'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('application/ld+json', $html, 'JSON-LD should still be emitted');

        preg_match('/<script type="application\/ld\+json">\n(.*?)\n<\/script>/s', $html, $matches);
        $jsonLd = json_decode($matches[1], true);

        $this->assertArrayNotHasKey('breadcrumb', $jsonLd);
    }
}
