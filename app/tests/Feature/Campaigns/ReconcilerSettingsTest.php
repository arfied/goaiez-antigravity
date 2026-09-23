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
use App\Services\Campaigns\UnknownSendReconciler;

it('reconciler honours vendor log window hours', function () {
    PlatformSetting::write('campaigns.unknown_send.vendor_log_window_hours', 72, 'test');
    $service = app(UnknownSendReconciler::class);
    $this->assertEquals(72, $service->vendorLogWindowHours());
});
