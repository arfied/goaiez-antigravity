<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Models\PlatformSetting;
use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * [G8-14] Product schema from pricebook
 */
final class ProductSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rendered_schema_contains_derived_offers_in_catalog(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Schema Tenant']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'schema1.example.com', true);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Acme Service',
            'price_cents' => 15000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p1',
            businessName: 'My Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        // Parse JSON-LD and assert containment
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script tag not found');
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('hasOfferCatalog', $json);
        $catalog = $json['hasOfferCatalog'];
        $this->assertEquals('OfferCatalog', $catalog['@type']);
        $this->assertCount(1, $catalog['itemListElement']);

        $offer = $catalog['itemListElement'][0];
        $this->assertEquals('Offer', $offer['@type']);
        $this->assertEquals('Acme Service', $offer['itemOffered']['name']);
        $this->assertEquals(150, $offer['price']);
    }

    public function test_page_shows_what_schema_claims(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Schema Tenant 2']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'schema2.example.com', true);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'First Service',
            'price_cents' => 5000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Second Service',
            'price_cents' => 10000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p2',
            businessName: 'My Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $schemaOffers = [];
        foreach ($json['hasOfferCatalog']['itemListElement'] as $item) {
            $schemaOffers[] = $item['itemOffered']['name'];
        }

        preg_match_all('/<div class="offer-item" data-name="([^"]+)">/', $html, $visibleMatches);
        $visibleOffers = $visibleMatches[1];

        $this->assertCount(2, $schemaOffers);
        $this->assertEquals($schemaOffers, $visibleOffers);
    }

    public function test_falsifier_absence(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Schema Tenant 3']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'schema3.example.com', true);

        // No price book items

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p3',
            businessName: 'My Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script tag should still be present');

        $json = json_decode($matches[1], true);
        $this->assertArrayNotHasKey('hasOfferCatalog', $json);
        $this->assertStringNotContainsString('id="offers-x176"', $html);
    }

    public function test_falsifier_derivation(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Schema Tenant 4']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'schema4.example.com', true);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Derivation Service',
            'price_cents' => 20000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);

        $res1 = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p4_1',
            businessName: 'My Biz'
        );

        $html1 = Storage::disk('local')->get("sites/{$res1['deploy_hash']}.html");
        $this->assertStringContainsString('Derivation Service', $html1);
        $this->assertStringContainsString('200', $html1);

        // Change pricebook
        $item->delete();

        $res2 = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p4_2',
            businessName: 'My Biz'
        );

        $html2 = Storage::disk('local')->get("sites/{$res2['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html2, $matches);
        $json = json_decode($matches[1], true);
        $this->assertArrayNotHasKey('hasOfferCatalog', $json);
        $this->assertStringNotContainsString('Derivation Service', $html2);
    }

    public function test_a_priced_range_publishes_as_a_range_on_the_page_and_in_the_schema(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Schema Tenant']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'schema1.example.com', true);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Distinctive Range Service 4918',
            'price_cents' => 10000,
            'price_max_cents' => 30000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p1',
            businessName: 'My Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('Distinctive Range Service 4918 - $100 to $300', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script tag not found');
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('hasOfferCatalog', $json);
        $catalog = $json['hasOfferCatalog'];
        $this->assertEquals('OfferCatalog', $catalog['@type']);
        $this->assertCount(1, $catalog['itemListElement']);

        $offer = $catalog['itemListElement'][0];
        $this->assertEquals('AggregateOffer', $offer['@type']);
        $this->assertEquals('Distinctive Range Service 4918', $offer['itemOffered']['name']);
        $this->assertEquals(100, $offer['lowPrice']);
        $this->assertEquals(300, $offer['highPrice']);
        $this->assertArrayNotHasKey('price', $offer);
    }

    public function test_offers_print_the_configured_currency_through_the_platform_formatter(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Schema Tenant 5']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'schema5.example.com', true);

        PlatformSetting::write('billing.currency', 'CAD', 'test');

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Distinctive CAD Service 4936',
            'price_cents' => 15000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_p5',
            businessName: 'My Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('Distinctive CAD Service 4936 - CAD 150', $html);
        $this->assertStringNotContainsString('4936 - $', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script tag not found');
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('hasOfferCatalog', $json);
        $catalog = $json['hasOfferCatalog'];
        $this->assertEquals('OfferCatalog', $catalog['@type']);
        $this->assertCount(1, $catalog['itemListElement']);

        $offer = $catalog['itemListElement'][0];
        $this->assertEquals('Offer', $offer['@type']);
        $this->assertEquals('Distinctive CAD Service 4936', $offer['itemOffered']['name']);
        $this->assertEquals(150, $offer['price']);
        $this->assertEquals('CAD', $offer['priceCurrency']);
    }
}
