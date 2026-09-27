<?php

use App\Enums\FetchOutcome;
use App\Enums\FetchTier;
use App\Models\FetchAttempt;
use App\Services\Fetch\DirectFetchGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('cools down only the host that blocked a tenant-site fetch', function () {
    FetchAttempt::create([
        'source_key' => 'tenant_site',
        'url_hash' => hash('sha256', 'https://blocked-4881.example/'),
        'host_hash' => hash('sha256', 'blocked-4881.example'),
        'tier' => FetchTier::F0,
        'outcome' => FetchOutcome::Blocked,
        'cooldown_until' => Carbon::now()->addHours(6),
        'created_at' => Carbon::now(),
    ]);
    Http::fake();
    $gateway = app(DirectFetchGateway::class);

    expect($gateway->fetch('tenant_site', 'https://other-4882.example/')->outcome)->not->toBe(FetchOutcome::Refused)
        ->and($gateway->fetch('tenant_site', 'https://blocked-4881.example/about')->outcome)->toBe(FetchOutcome::Refused);
});

it('keeps the per-source cool-down for every other source', function () {
    DB::table('fetch_sources')->insert(['key' => 'test_src', 'method_ceiling' => 'full_ladder']);
    FetchAttempt::create([
        'source_key' => 'test_src',
        'url_hash' => hash('sha256', 'https://blocked-4883.example/'),
        'host_hash' => hash('sha256', 'blocked-4883.example'),
        'tier' => FetchTier::F0,
        'outcome' => FetchOutcome::Blocked,
        'cooldown_until' => Carbon::now()->addHours(6),
        'created_at' => Carbon::now(),
    ]);
    Http::fake();

    expect(app(DirectFetchGateway::class)->fetch('test_src', 'https://other-4884.example/')->outcome)->toBe(FetchOutcome::Refused);
});

it('records the host of every attempt', function () {
    Http::fake();
    app(DirectFetchGateway::class)->fetch('tenant_site', 'https://recorded-4885.example/page');

    expect(FetchAttempt::query()->latest('id')->value('host_hash'))->toBe(hash('sha256', 'recorded-4885.example'));
});
