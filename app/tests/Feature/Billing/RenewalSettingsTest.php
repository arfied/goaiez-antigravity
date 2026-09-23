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
use App\Services\Billing\RenewalReminders;
use App\Services\Billing\Subscriptions;
use App\Services\Billing\TrialEligibility;

it('renewal reminders honours opens days before', function () {
    PlatformSetting::write('billing.renewal.opens_days_before', 40, 'test');
    $service = app(RenewalReminders::class);
    $this->assertEquals(40, $service->opensDaysBefore());
});

it('renewal reminders honours closes days before', function () {
    PlatformSetting::write('billing.renewal.closes_days_before', 10, 'test');
    $service = app(RenewalReminders::class);
    $this->assertEquals(10, $service->closesDaysBefore());
});

it('subscriptions honours claim minutes', function () {
    PlatformSetting::write('billing.renewal.claim_minutes', 120, 'test');
    $service = app(Subscriptions::class);
    $this->assertEquals(120, $service->renewalReminderClaimMinutes());
});

it('trial eligibility honours origin window days', function () {
    PlatformSetting::write('billing.trial.signup_origin_window_days', 60, 'test');
    $service = app(TrialEligibility::class);
    $this->assertEquals(60, $service->signupOriginWindowDays());
});
