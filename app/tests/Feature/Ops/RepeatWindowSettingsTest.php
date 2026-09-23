<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Ops\ScheduledRunMeter;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('platform health checks honours credential repeat days', function () {
    PlatformSetting::write('ops.health.credential_repeat_days', 45, 'test');

    $service = app(PlatformHealthChecks::class);
    $this->assertEquals(45, $service->credentialRepeatDays());
});

it('scheduled run meter honours failed run repeat hours', function () {
    PlatformSetting::write('ops.runs.failed_run_repeat_hours', 15, 'test');

    $service = app(ScheduledRunMeter::class);
    $this->assertEquals(15, $service->failedRunRepeatHours());
});
