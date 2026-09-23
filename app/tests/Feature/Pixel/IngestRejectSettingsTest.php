<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Pixel\IngestRejects;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('ingest rejects honours retention days and recent window hours', function () {
    PlatformSetting::write('pixel.rejects.retention_days', 45, 'test');
    PlatformSetting::write('pixel.rejects.recent_window_hours', 12, 'test');

    $service = app(IngestRejects::class);
    $this->assertEquals(45, $service->retentionDays());
    $this->assertEquals(12, $service->recentWindowHours());
});

it('ingest rejects honours tenant window and origins per hour', function () {
    PlatformSetting::write('pixel.rejects.tenant_window_hours', 120, 'test');
    PlatformSetting::write('pixel.rejects.origins_per_hour', 100, 'test');

    $service = app(IngestRejects::class);
    $this->assertEquals(120, $service->tenantWindowHours());
    $this->assertEquals(100, $service->originsPerHour());
});
