<?php

declare(strict_types=1);

use App\Contracts\PlacesClient;
use App\Contracts\SearchConsoleClient;
use App\Enums\AuditStatus;
use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use App\Enums\UserRole;
use App\Exceptions\ProviderNotConnected;
use App\Jobs\PublicAuditJob;
use App\Jobs\RefreshOauthTokensJob;
use App\Jobs\Visibility\SyncSearchConsoleJob;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\PublicAudit;
use App\Models\User;
use App\Services\Gsc\SearchAnalyticsResult;
use App\Services\Places\PlaceSummary;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
    PublicAudit::retrieved(function ($model) {
        if (array_key_exists('audit_rating', $model->getAttributes())) {
            $model->setAttribute('score', $model->getAttribute('audit_rating'));
        }
    });
    PublicAudit::saving(function ($model) {
        if (array_key_exists('score', $model->getAttributes())) {
            $model->setAttribute('audit_rating', $model->getAttribute('score'));
            unset($model->score);
        }
    });

    Mail::fake();
    Notification::fake();
    Http::fake();
});

afterEach(function () {
    Tenancy::forget();
    PublicAudit::flushEventListeners();
});

function callProtectedExecute($object, $method = 'execute')
{
    $execute = function () use ($method) {
        return $this->$method();
    };

    return $execute->call($object);
}

it('refreshes oauth tokens', function () {
    DB::table('oauth_connections')->delete();

    $job = new RefreshOauthTokensJob((int) $this->biz->id, null);

    // (a) no OauthConnection rows
    $result = callProtectedExecute($job);
    expect($result)->toHaveKey('considered', 0)
        ->toHaveKey('refreshed', 0)
        ->toHaveKey('reconnect_needed', 0);

    // (b) an Active connection that TokenService::isDue() says is NOT due
    $conn1 = OauthConnection::query()->create([
        'business_id' => $this->biz->id,
        'provider' => OauthProvider::Google,
        'status' => ConnectionStatus::Active,
        'access_token_enc' => Crypt::encryptString('access'),
        'refresh_token_enc' => Crypt::encryptString('refresh'),
        'token_expires_at' => now()->addDays(2),
    ]);

    $resultNotDue = callProtectedExecute($job);
    expect($resultNotDue)->toHaveKey('considered', 0)
        ->toHaveKey('refreshed', 0)
        ->toHaveKey('reconnect_needed', 0);

    // (c) a due connection whose refresh() throws ProviderNotConnected
    $conn1->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token_enc' => null, // missing refresh token
    ]);

    $resultMissing = callProtectedExecute($job);
    expect($resultMissing)->toHaveKey('considered', 1)
        ->toHaveKey('refreshed', 0)
        ->toHaveKey('reconnect_needed', 1);

    // (d) handoff()
    $handoff = callProtectedExecute($job, 'handoff');
    expect($handoff)->toHaveKey('skipped', 'no provider access');
});

it('runs public audit', function () {
    $fakePlaces = new class implements PlacesClient
    {
        public function autocomplete(string $query, ?string $regionCode = null): array
        {
            return [];
        }

        public function textSearch(string $query, ?string $regionCode = null): array
        {
            return [];
        }

        public function details(string $placeId): ?PlaceSummary
        {
            return null;
        }

        public function nearby(float $latitude, float $longitude, string $primaryType, int $limit = 3): array
        {
            return [];
        }
    };
    $this->instance(PlacesClient::class, $fakePlaces);

    // (a) unknown token -> returns, Http::assertNothingSent()
    $job = new PublicAuditJob('unknown-token');
    app()->call([$job, 'handle']);
    Http::assertNothingSent();

    // (b) a PublicAudit whose status->isTerminal() -> returns, row untouched
    $auditTerminal = PublicAudit::query()->forceCreate([
        'token' => 'term-token',
        'place_id' => 'ChIJ123',
        'status' => AuditStatus::Complete,
        'expires_at' => now()->addDays(7),
    ]);

    $jobTerminal = new PublicAuditJob('term-token');
    app()->call([$jobTerminal, 'handle']);
    expect($auditTerminal->fresh()->status)->toBe(AuditStatus::Complete);

    // (c) a live audit -> AuditEngine::run()
    $auditLive = PublicAudit::query()->forceCreate([
        'token' => 'live-token',
        'place_id' => 'ChIJ456',
        'status' => AuditStatus::Queued,
        'expires_at' => now()->addDays(7),
    ]);
    $jobLive = new PublicAuditJob('live-token');
    app()->call([$jobLive, 'handle']);
    expect($auditLive->fresh()->status)->toBe(AuditStatus::Failed);
});

it('syncs search console', function () {
    $job = new SyncSearchConsoleJob((int) $this->biz->id, 99999);

    // (a) location_missing
    $resultMissingLoc = callProtectedExecute($job);
    expect($resultMissingLoc)->toHaveKey('outcome', 'unavailable')
        ->toHaveKey('reason', 'location_missing');

    $loc = Location::query()->create(['business_id' => $this->biz->id, 'name' => 'Loc']);

    // (b) no_property_chosen
    $jobNoProp = new SyncSearchConsoleJob((int) $this->biz->id, (int) $loc->id);
    $resultNoProp = callProtectedExecute($jobNoProp);
    expect($resultNoProp)->toHaveKey('outcome', 'unavailable')
        ->toHaveKey('reason', 'no_property_chosen');

    // Setup for (c) and (d)
    DB::table('gsc_site_properties')->insert([
        'business_id' => $this->biz->id,
        'location_id' => $loc->id,
        'site_url' => 'sc-domain:example.com',
        'permission_level' => 'siteOwner',
        'chosen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // (c) a chosen property with a revoked connection -> connection_revoked
    $fakeClientRevoked = Mockery::mock(SearchConsoleClient::class);
    $fakeClientRevoked->shouldReceive('dailyMetrics')->andThrow(ProviderNotConnected::unusable(OauthProvider::Gsc));
    $this->instance(SearchConsoleClient::class, $fakeClientRevoked);

    $jobRevoked = new SyncSearchConsoleJob((int) $this->biz->id, (int) $loc->id);
    $resultRevoked = callProtectedExecute($jobRevoked);
    expect($resultRevoked)->toHaveKey('outcome', 'unavailable')
        ->toHaveKey('reason', 'connection_revoked');

    // (d) the metrics path
    $constructor = (new ReflectionClass(SearchAnalyticsResult::class))->getConstructor();
    $constructor->setAccessible(true);
    $fakeResult = (new ReflectionClass(SearchAnalyticsResult::class))->newInstanceWithoutConstructor();
    $constructor->invoke($fakeResult, [], null, 0);

    $fakeClientSuccess = Mockery::mock(SearchConsoleClient::class);
    $fakeClientSuccess->shouldReceive('dailyMetrics')->andReturn($fakeResult);
    $this->instance(SearchConsoleClient::class, $fakeClientSuccess);

    $jobSuccess = new SyncSearchConsoleJob((int) $this->biz->id, (int) $loc->id);
    $resultSuccess = callProtectedExecute($jobSuccess);
    expect($resultSuccess)->toHaveKey('outcome', 'measured')
        ->toHaveKey('days_written', 0)
        ->toHaveKey('days_dropped', 0);
});
