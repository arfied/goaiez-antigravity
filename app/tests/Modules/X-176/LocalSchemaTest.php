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
 * [G8-22] Hyper-Local Schema
 */
final class LocalSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rendered_schema_contains_postal_address_when_address_present(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant([
            'name' => 'Local Tenant',
        ]);
        $biz->update(['address' => [
            'line1' => '123 Test St',
            'city' => 'Testville',
            'region' => 'TX',
            'postal_code' => '73301',
            'country' => 'US',
        ]]);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'local1.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_local1',
            businessName: 'Local Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $expectedAddressJson = json_encode([
            '@type' => 'PostalAddress',
            'streetAddress' => '123 Test St',
            'addressLocality' => 'Testville',
            'addressRegion' => 'TX',
            'postalCode' => '73301',
            'addressCountry' => 'US',
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Strip the outer {} so we can check if it's contained inside the schema
        $expectedAddressSubstring = substr($expectedAddressJson, 1, -1);

        $this->assertStringContainsString($expectedAddressSubstring, $html);
    }

    public function test_rendered_schema_has_no_postal_address_when_no_address(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant([
            'name' => 'Local Tenant 2',
        ]);
        $biz->update(['address' => null]);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'local2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_local2',
            businessName: 'Local Biz 2'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringNotContainsString('"@type":"PostalAddress"', $html);
    }
}
