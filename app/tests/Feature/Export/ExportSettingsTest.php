<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Tenancy;
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
use App\Services\Export\ExportBuilder;

it('export builder honours request cooldown minutes', function () {
    PlatformSetting::write('export.request_cooldown_minutes', 30, 'test');
    $service = app(ExportBuilder::class);
    $this->assertEquals(30, $service->requestCooldownMinutes());
});

it('export builder honours in flight reuse minutes', function () {
    PlatformSetting::write('export.in_flight_reuse_minutes', 20, 'test');
    $service = app(ExportBuilder::class);
    $this->assertEquals(20, $service->inFlightReuseMinutes());
});
