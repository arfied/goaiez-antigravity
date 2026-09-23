<?php

declare(strict_types=1);

use App\Enums\FetchOutcome;
use App\Enums\ReviewSource;
use App\Enums\UserRole;
use App\Jobs\ProbeLocationSiteJob;
use App\Jobs\RecomputeProofNumbersJob;
use App\Jobs\RecordQueueHeartbeat;
use App\Models\Business;
use App\Models\FetchAttempt;
use App\Models\Location;
use App\Models\ProofNumber;
use App\Models\Review;
use App\Models\TriageConversation;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
    Mail::fake();
    Notification::fake();
});

afterEach(function () {
    Tenancy::forget();
});

it('probes location site', function () {
    $location = Location::factory()->create([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
        'website_scanned_at' => null,
        'wordpress_detected_at' => null,
        'cloudflare_detected_at' => null,
    ]);

    // Reachable 200 -> recorded outcome
    Http::fake([
        '*' => Http::response('<html><head><link rel="https://api.w.org/" href="https://example.com/wp-json/" /></head></html>', 200, [
            'server' => 'cloudflare',
            'link' => 'https://example.com/wp-json/; rel="https://api.w.org/"'
        ]),
    ]);

    (new ProbeLocationSiteJob((int) $this->biz->id, (int) $location->id))->handle(
        app(\App\Services\Actuation\SiteProbe::class),
        app(\App\Services\Tenant\TenantPause::class),
        app(\App\Services\Tenant\TenantSuspension::class)
    );

    $location->refresh();
    expect($location->website_scanned_at)->not->toBeNull()
        ->and($location->wordpress_detected_at)->not->toBeNull()
        ->and($location->cloudflare_detected_at)->not->toBeNull();

    // Reset for next test
    $location->forceFill([
        'website_scanned_at' => null,
        'wordpress_detected_at' => null,
        'cloudflare_detected_at' => null,
    ])->save();

    $location2 = Location::factory()->create([
        'website_url' => 'https://example2.com',
        'website_confirmed_at' => now(),
    ]);

    // 500 / timeout -> the recorded failure (fetch_attempts)
    Http::fake([
        'https://example2.com' => function () {
            throw new ConnectionException();
        }
    ]);

    (new ProbeLocationSiteJob((int) $this->biz->id, (int) $location2->id))->handle(
        app(\App\Services\Actuation\SiteProbe::class),
        app(\App\Services\Tenant\TenantPause::class),
        app(\App\Services\Tenant\TenantSuspension::class)
    );

    $location2->refresh();
    expect($location2->website_scanned_at)->toBeNull();

    $attempt = FetchAttempt::query()->orderByDesc('id')->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->outcome->value)->toBe(FetchOutcome::Error->value);

    // Missing location -> returns, nothing written
    (new ProbeLocationSiteJob((int) $this->biz->id, 999999))->handle(
        app(\App\Services\Actuation\SiteProbe::class),
        app(\App\Services\Tenant\TenantPause::class),
        app(\App\Services\Tenant\TenantSuspension::class)
    );
});

it('recomputes proof numbers', function () {
    // Non-zero inputs
    Review::factory()->create([
        'source' => ReviewSource::Google,
        'created_at' => now(),
    ]);
    Review::factory()->create([
        'source' => ReviewSource::FirstParty,
        'created_at' => now(),
    ]);
    TriageConversation::factory()->resolved()->create([
        'updated_at' => now(),
    ]);

    $job = new RecomputeProofNumbersJob((int) $this->biz->id, ['all']);
    $job->handle(app(\App\Services\Proof\ProofNumbers::class));

    $proof = ProofNumber::query()->where('period', 'all')->first();
    expect($proof)->not->toBeNull()
        ->and($proof->google_reviews)->toBe(1)
        ->and($proof->leads)->toBe(2)
        ->and($proof->recovered)->toBe(1);

    // Idempotent
    $job->handle(app(\App\Services\Proof\ProofNumbers::class));
    expect(ProofNumber::query()->where('period', 'all')->count())->toBe(1);

    // Suspended/paused tenant -> whatever the job does (computes anyway)
    $this->biz->update([
        'paused_at' => now(),
        'paused_by' => 'tester',
        'pause_reason' => 'test'
    ]);

    Review::factory()->create([
        'source' => ReviewSource::Google,
        'created_at' => now(),
    ]);

    $job->handle(app(\App\Services\Proof\ProofNumbers::class));

    $proof->refresh();
    expect($proof->google_reviews)->toBe(2);
});

it('records queue heartbeat', function () {
    $job = new RecordQueueHeartbeat();
    
    // First run
    $job->handle(app(\App\Services\Ops\PlatformHealth::class));
    $lastAt = app(\App\Services\Ops\PlatformHealth::class)->lastBeat('queue');
    expect($lastAt)->not->toBeNull();

    // Overwrites rather than duplicates
    $count = DB::table('platform_health_windows')
        ->where('signal', \App\Enums\PlatformHealthSignal::Heartbeat->value)
        ->where('source', 'queue')
        ->count();

    // Second run
    Carbon::setTestNow(now()->addMinutes(5));
    $job->handle(app(\App\Services\Ops\PlatformHealth::class));

    $count2 = DB::table('platform_health_windows')
        ->where('signal', \App\Enums\PlatformHealthSignal::Heartbeat->value)
        ->where('source', 'queue')
        ->count();

    expect($count2)->toBe($count);
    Carbon::setTestNow();
});
