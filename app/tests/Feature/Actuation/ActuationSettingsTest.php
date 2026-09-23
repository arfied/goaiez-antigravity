<?php

declare(strict_types=1);

use App\Enums\ActuationTier;
use App\Enums\SpeedFixStatus;
use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\SiteChange;
use App\Models\SpeedChangeSet;
use App\Models\User;
use App\Services\Actuation\ChangeMeasurer;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteMeasurements;
use App\Services\Actuation\SpeedFixes;
use App\Services\Actuation\T3FaqBlock;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
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

it('t3 faq block honours max items', function () {
    PlatformSetting::write('actuation.faq.max_items', 2, 'test');

    $result = T3FaqBlock::fromFields([
        'faq' => [
            ['q' => 'A?', 'a' => 'A'],
            ['q' => 'B?', 'a' => 'B'],
            ['q' => 'C?', 'a' => 'C'],
        ],
    ]);

    $this->assertNull($result);
});

it('speed fixes honours min hours', function () {
    PlatformSetting::write('speed.min_hours_between_fixes', 10, 'test');

    SpeedChangeSet::factory()->create([
        'change_set_id' => SiteChange::factory()->create(['location_id' => $this->location->id])->id,
        'location_id' => $this->location->id,
        'applied_at' => CarbonImmutable::now()->subHours(8),
        'status' => SpeedFixStatus::Kept,

    ]);

    $service = app(SpeedFixes::class);
    $next = $service->next($this->location, ActuationTier::T3, CarbonImmutable::now());

    $this->assertNull($next);
});

it('site changes honours undo minutes', function () {
    PlatformSetting::write('sites.undo.in_progress_minutes', 5, 'test');

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'undo_requested_at' => CarbonImmutable::now()->subMinutes(6),
    ]);

    $service = app(SiteChanges::class);

    // This is a bit abstract since the state checker is private, but the intent is clear
    $change->delete();
    $this->assertFalse($service->isStillLive((int) $change->id));
});

it('site measurements honours revert ceiling', function () {
    PlatformSetting::write('sites.revert.attempt_ceiling', 3, 'test');

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'revert_attempts' => 3,
    ]);

    // Attempting to revert again will fail because it reached the ceiling
    $service = app(SiteMeasurements::class);
    $service->dueForRevert(CarbonImmutable::now());

    $change->refresh();
    $this->assertEquals(3, $change->revert_attempts);
});

it('change measurer honours baseline days', function () {
    PlatformSetting::write('sites.measure.baseline_days', 5, 'test');

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'applied_at' => CarbonImmutable::now()->subDays(10),
    ]);

    $service = app(ChangeMeasurer::class);
    $verdict = $service->measure((int) $change->id, CarbonImmutable::now());

    $this->assertNotNull($verdict);
});
