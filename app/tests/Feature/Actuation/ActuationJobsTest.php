<?php

declare(strict_types=1);

use App\Enums\ActuationTier;
use App\Enums\AutopilotActionType;
use App\Enums\SiteChangeActor;
use App\Enums\SiteChangeVerdict;
use App\Enums\SpeedFix;
use App\Enums\SpeedFixStatus;
use App\Enums\UserRole;
use App\Jobs\Actuation\JudgeSpeedFixJob;
use App\Jobs\Actuation\MeasureSiteChangeJob;
use App\Jobs\Actuation\UndoSiteChangeJob;
use App\Models\ActivityFeedItem;
use App\Models\SiteChange;
use App\Models\SpeedChangeSet;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\Actuation\ChangeMeasurer;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SpeedDecider;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->location = $this->biz->locations()->first();
    $this->location->forceFill([
        'website_url' => 'https://example.test',
        'website_confirmed_at' => now(),
    ])->save();
    Mail::fake();
    Notification::fake();
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

afterEach(function () {
    Tenancy::forget();
});

// MeasureSiteChangeJob Tests
it('MeasureSiteChangeJob returns early on missing row -> nothing written', function () {
    Http::fake();

    $job = new MeasureSiteChangeJob((int) $this->biz->id, (int) $this->location->id, 9999);
    $job->handle(app(ChangeMeasurer::class), app(TenantPause::class), app(TenantSuspension::class));

    Http::assertNothingSent();
    expect(ActivityFeedItem::count())->toBe(0);
});

it('MeasureSiteChangeJob returns early on paused or suspended tenant -> nothing written', function () {
    Http::fake();

    $this->biz->forceFill(['paused_at' => now(), 'paused_by' => 'test'])->save();

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'url' => 'https://example.test/foo',
        'verdict' => SiteChangeVerdict::Pending,
        'applied_at' => now()->subDays(40),
    ]);

    $job = new MeasureSiteChangeJob((int) $this->biz->id, (int) $this->location->id, (int) $change->id);
    $job->handle(app(ChangeMeasurer::class), app(TenantPause::class), app(TenantSuspension::class));

    Http::assertNothingSent();
    expect(ActivityFeedItem::count())->toBe(0);
});

it('MeasureSiteChangeJob happy path', function () {
    Http::fake([
        '*' => Http::response(['rows' => []]),
    ]);

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'url' => 'https://example.test/foo',
        'verdict' => SiteChangeVerdict::Pending,
        'applied_at' => now()->subDays(40),
    ]);

    $job = new MeasureSiteChangeJob((int) $this->biz->id, (int) $this->location->id, (int) $change->id);
    $job->handle(app(ChangeMeasurer::class), app(TenantPause::class), app(TenantSuspension::class));

    $change->refresh();
    expect($change->verdict)->not->toBe(SiteChangeVerdict::Pending);
});

// JudgeSpeedFixJob Tests
it('JudgeSpeedFixJob returns early on missing row -> nothing written', function () {
    $job = new JudgeSpeedFixJob((int) $this->biz->id, (int) $this->location->id, 9999);
    $job->handle(app(SpeedDecider::class), app(TenantPause::class), app(TenantSuspension::class));
    expect(ActivityFeedItem::count())->toBe(0);
});

it('JudgeSpeedFixJob returns early on paused or suspended tenant -> nothing written', function () {
    $this->biz->forceFill(['suspended_at' => now(), 'suspended_by' => 'test', 'suspension_reason' => 'test'])->save();

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'url' => 'https://example.test/foo',
        'verdict' => SiteChangeVerdict::Pending,
        'applied_at' => now()->subDays(40),
    ]);

    $speedFix = SpeedChangeSet::create([
        'location_id' => $this->location->id,
        'change_set_id' => $change->id,
        'fix_key' => SpeedFix::FontDisplaySwap,
        'tier' => ActuationTier::T1,
        'status' => SpeedFixStatus::Measuring,
        'applied_at' => now()->subDays(40),
        'decided_at' => now(),
    ]);

    $job = new JudgeSpeedFixJob((int) $this->biz->id, (int) $this->location->id, (int) $speedFix->id);
    $job->handle(app(SpeedDecider::class), app(TenantPause::class), app(TenantSuspension::class));
    expect(ActivityFeedItem::count())->toBe(0);
});

it('JudgeSpeedFixJob happy path', function () {
    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'url' => 'https://example.test/foo',
        'verdict' => SiteChangeVerdict::Pending,
        'applied_at' => now()->subDays(40),
    ]);

    $speedFix = SpeedChangeSet::create([
        'location_id' => $this->location->id,
        'change_set_id' => $change->id,
        'fix_key' => SpeedFix::FontDisplaySwap,
        'tier' => ActuationTier::T1,
        'status' => SpeedFixStatus::Measuring,
        'applied_at' => now()->subDays(40),
        'decided_at' => now(),
    ]);

    $job = new JudgeSpeedFixJob((int) $this->biz->id, (int) $this->location->id, (int) $speedFix->id);
    $job->handle(app(SpeedDecider::class), app(TenantPause::class), app(TenantSuspension::class));

    $speedFix->refresh();
    expect($speedFix->status)->not->toBe(SpeedFixStatus::Measuring);
});

// UndoSiteChangeJob Tests
it('UndoSiteChangeJob happy path', function () {
    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'url' => 'https://example.test/foo',
        'undo_requested_at' => now(),
    ]);

    $job = new UndoSiteChangeJob((int) $this->biz->id, (int) $this->location->id, (int) $change->id, SiteChangeActor::Owner, $this->owner->id);
    $job->handle(app(SiteChanges::class), app(ActivityService::class));

    $change->refresh();
    expect($change->rolled_back_at)->not->toBeNull()
        ->and($change->undo_requested_at)->toBeNull();

    expect(ActivityFeedItem::where('action_type', AutopilotActionType::SiteChangeReverted->value)->count())->toBe(1);
});

it('UndoSiteChangeJob caught throw', function () {
    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'url' => 'https://example.test/foo',
        'undo_requested_at' => now(),
    ]);

    $job = new UndoSiteChangeJob((int) $this->biz->id, (int) $this->location->id, (int) $change->id, SiteChangeActor::Owner, $this->owner->id);

    $e = new RuntimeException('test failure');
    $job->failed($e);

    $change->refresh();
    expect($change->undo_requested_at)->toBeNull();

    $items = ActivityFeedItem::where('action_type', AutopilotActionType::OwnerActionNeeded->value)->get();
    expect($items->count())->toBe(1)
        ->and($items->first()->metadata['detail'])->toContain('RuntimeException');
});
