<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Contracts\IndexNowKeys;
use App\Enums\IndexingRefusal;
use App\Models\Location;
use App\Services\Indexing\HostedIndexNowKeys;
use App\Services\Tenant\LocationWebsite;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * IndexNow for sites this platform hosts (owner, 2026-10-05): the key file is served at every host's root, and a location whose
 * confirmed website is a custom domain verified to point at us gets a key; every other location keeps the refusal.
 */
class HostedIndexNowKeysTest extends TestCase
{
    use RefreshesTenantDatabase;

    /**
     * A business with a verified custom domain — fixture taken from VariantCookieServingTest::setUp — and one location.
     *
     * @return array{0: int, 1: Location}
     */
    private function hostedBusiness(): array
    {
        $biz = TestCase::provisionTenant(['name' => 'Hosted 9981', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing-9981.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [(int) $biz->id, Location::factory()->create(['business_id' => $biz->id, 'name' => 'Main 9982'])];
    }

    public function test_the_key_file_is_served_at_the_root_of_our_host_and_of_a_verified_custom_domain(): void
    {
        $this->hostedBusiness();
        $key = HostedIndexNowKeys::key();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);

        $this->assertSame($key, $this->get('/'.$key.'.txt')->assertOk()->getContent());
        $this->assertSame($key, $this->get('http://acme-roofing-9981.test/'.$key.'.txt')->assertOk()->getContent());
        $this->get('/'.str_repeat('0', 32).'.txt')->assertNotFound();
    }

    public function test_a_location_whose_website_is_our_hosted_domain_gets_a_key_and_every_other_keeps_the_refusal(): void
    {
        [, $location] = $this->hostedBusiness();
        $this->assertInstanceOf(HostedIndexNowKeys::class, app(IndexNowKeys::class));

        // No website confirmed yet: the refusal the unhosted provider gives.
        $this->assertSame(IndexingRefusal::WebsiteNotConfirmed, app(IndexNowKeys::class)->for($location)->refusalOrFail());

        app(LocationWebsite::class)->confirm($location, 'https://acme-roofing-9981.test', 'user:1', true);
        $outcome = app(IndexNowKeys::class)->for($location->refresh());
        $this->assertNotNull($outcome->key);
        $this->assertSame('acme-roofing-9981.test', $outcome->key->host);
        $this->assertSame(HostedIndexNowKeys::key(), $outcome->key->key);
        $this->assertSame('https://acme-roofing-9981.test/'.HostedIndexNowKeys::key().'.txt', $outcome->key->keyLocation);
        $this->assertTrue($outcome->key->isWellFormed());

        // `www.` is a different host: a site we do not serve keeps the refusal.
        app(LocationWebsite::class)->confirm($location, 'https://www.acme-roofing-9981.test', 'user:1', true);
        $this->assertNull(app(IndexNowKeys::class)->for($location->refresh())->key);
    }
}
