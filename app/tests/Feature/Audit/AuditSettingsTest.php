<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Audit\Checks\ReviewStatsCheck;
use App\Services\Audit\PublicAuditStarter;
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

it('public audit starter honours reuse window seconds', function () {
    PlatformSetting::write('audit.public.reuse_window_seconds', 3600, 'test');

    $service = app(PublicAuditStarter::class);
    $this->assertEquals(3600, $service->reuseWindowSeconds());
});

it('review stats check honours thin review count', function () {
    PlatformSetting::write('audit.reviews.thin_count', 3, 'test');

    $service = app(ReviewStatsCheck::class);
    $this->assertEquals(3, $service->thinReviewCount());
});
