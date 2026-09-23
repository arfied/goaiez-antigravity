<?php

use App\Enums\SpeedFixStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\SiteChange;
use App\Models\SpeedChangeSet;
use App\Services\Actuation\SpeedFixes;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

it('reads the revert backoff schedule from settings', function () {
    $business = Business::factory()->create();
    Tenancy::set((int) $business->id);

    PlatformSetting::write('sites.revert.backoff_hours', '1,2', 'test', 'desc');

    $location = Location::factory()->create(['business_id' => $business->id]);
    $change = SiteChange::factory()->create(['location_id' => $location->id]);

    $row = SpeedChangeSet::factory()->create([
        'location_id' => $location->id,
        'change_set_id' => $change->id,
        'status' => SpeedFixStatus::Measuring,
        'revert_attempts' => 1,
    ]);

    $service = app(SpeedFixes::class);
    $service->countRevertAttempt((int) $row->id, CarbonImmutable::now());

    $row->refresh();

    expect(abs(round($row->revert_attempt_after->diffInRealHours(CarbonImmutable::now()))))->toBe(2.0);

    Tenancy::forget();
});
