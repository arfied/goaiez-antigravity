<?php

declare(strict_types=1);

use App\Enums\DataClassification;
use App\Models\Location;
use App\Models\SiteChange;
use App\Support\Tenancy;
use Illuminate\Support\Str;

it('serves t3 payload for known key', function () {
    $business = pixelTenant('Ledger', 'ledger.test');
    $key = pixelKeyFor($business);

    Tenancy::actingAs($business->id, function () {
        // Create a T3 change set
        $location = Location::query()->first();
        // Since measurementChange creates a URL with ledgerplumbing.test, we use it directly
        $changeId = measurementChange($location, now()->toImmutable(), 'meta', '/services/boiler-service');
        SiteChange::query()->whereKey($changeId)->update([
            'after_snapshot' => json_encode(['name' => 'description', 'content' => 'hello']),
        ]);
    });

    $response = $this->get('/api/site/'.$key);
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertHeader('Access-Control-Allow-Origin', '*');

    $response->assertJsonPath('p.0.h', 'ledgerplumbing.test');
    $response->assertJsonPath('p.0.u', '/services/boiler-service');

    $etag = $response->headers->get('ETag');
    $this->assertNotNull($etag);

    // b) same request with If-None-Match: <that ETag> -> 304
    $response2 = $this->get('/api/site/'.$key, ['If-None-Match' => $etag]);
    $response2->assertStatus(304);
});

it('serves empty payload for unknown key', function () {
    $response = $this->get('/api/site/'.Str::uuid()->toString());

    $response->assertStatus(200);
    // empty payload assert it carries nothing tenant-specific
    $response->assertExactJson(['p' => []]);
});

it('serves non-empty module script for known key', function () {
    $business = pixelTenant('Ledger', 'ledger.test');
    $key = pixelKeyFor($business);

    Tenancy::actingAs($business->id, function () {
        $location = Location::query()->first();
        $changeId = measurementChange($location, now()->toImmutable(), 'meta', '/services/boiler-service');
        SiteChange::query()->whereKey($changeId)->update([
            'after_snapshot' => json_encode(['name' => 'description', 'content' => 'hello']),
        ]);
    });

    $response = $this->get('/s/'.$key.'.js');
    $response->assertStatus(200);
    // check it is non-empty javascript
    $this->assertNotEmpty($response->getContent());
});

it('serves empty module script for unknown key', function () {
    $response = $this->get('/s/'.Str::uuid()->toString().'.js');
    $response->assertStatus(200);
    $this->assertEquals('', $response->getContent());
});

it('serves empty payload for PHI tenant', function () {
    $business = pixelTenant('Ledger', 'ledger.test');
    $key = pixelKeyFor($business);

    $business->forceFill(['data_classification' => DataClassification::Phi])->save();

    Tenancy::actingAs($business->id, function () {
        $location = Location::query()->first();
        $changeId = measurementChange($location, now()->toImmutable(), 'meta', '/services/boiler-service');
        SiteChange::query()->whereKey($changeId)->update([
            'after_snapshot' => json_encode(['name' => 'description', 'content' => 'hello']),
        ]);
    });

    $response = $this->get('/api/site/'.$key);
    $response->assertStatus(200);
    $response->assertExactJson(['p' => []]);
});
