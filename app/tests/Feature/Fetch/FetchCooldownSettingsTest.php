<?php

use App\Enums\FetchOutcome;
use App\Enums\FetchTier;
use App\Models\FetchAttempt;
use App\Models\PlatformSetting;
use App\Services\Fetch\DirectFetchGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('reads the cooldown schedule from settings', function () {
    PlatformSetting::write('fetch.cooldown_hours', '2,4', 'test', 'desc');

    DB::table('fetch_sources')->insert(['key' => 'test_src', 'method_ceiling' => 'full_ladder']);
    $source = (object) ['key' => 'test_src'];

    FetchAttempt::create([
        'source_key' => $source->key,
        'url_hash' => hash('sha256', 'https://example.com'),
        'tier' => FetchTier::F0,
        'outcome' => FetchOutcome::Blocked,
        'cooldown_until' => Carbon::now()->addHours(2),
        'created_at' => Carbon::now(),
    ]);

    test()->travel(1)->hours();
    Http::fake();

    $gateway = app(DirectFetchGateway::class);
    $result = $gateway->fetch($source->key, 'https://example.com', FetchTier::F0);

    expect($result->outcome)->toBe(FetchOutcome::Refused);

    test()->travel(2)->hours();
    $result2 = $gateway->fetch($source->key, 'https://example.com', FetchTier::F0);
    expect($result2->outcome)->not->toBe(FetchOutcome::Refused);
});
