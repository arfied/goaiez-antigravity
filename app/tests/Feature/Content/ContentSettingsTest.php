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
use App\Services\Content\ContentSelfAudit;
use App\Services\Content\Publishing;

it('content self audit honours impression window days', function () {
    PlatformSetting::write('content.self_audit.impression_window_days', 120, 'test');
    $service = app(ContentSelfAudit::class);
    $this->assertEquals(120, $service->impressionWindowDays());
});

it('publishing honours hold hours', function () {
    PlatformSetting::write('content.publishing.hold_hours', 48, 'test');
    $service = app(Publishing::class);
    $this->assertEquals(48, $service->holdHours());
});
