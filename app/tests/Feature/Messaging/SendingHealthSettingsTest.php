<?php

declare(strict_types=1);

use App\Enums\OutreachChannel;
use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\SendingHealthWindow;
use App\Models\User;
use App\Services\Messaging\Composer\NameNormaliser;
use App\Services\Messaging\SendingHealth;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('a written name_max_length of 3 refuses a 4-letter token', function () {
    PlatformSetting::write('messaging.composer.name_max_length', 3, 'test');

    $result = app(NameNormaliser::class)->firstName('John');
    expect($result)->toBeNull();
});

it('a written window hours limits the rates calculation', function () {
    PlatformSetting::write('messaging.health.window_hours', 2, 'test');

    $now = CarbonImmutable::now();
    CarbonImmutable::setTestNow($now);

    SendingHealthWindow::query()->create([
        'business_id' => $this->biz->id,
        'channel' => OutreachChannel::Sms,
        'window_start' => $now->startOfHour()->subHours(5),
        'sent' => 10,
        'delivered' => 10,
        'failed' => 0,
        'opted_out' => 0,
        'complaints' => 0,
    ]);

    SendingHealthWindow::query()->create([
        'business_id' => $this->biz->id,
        'channel' => OutreachChannel::Sms,
        'window_start' => $now->startOfHour()->subHours(1),
        'sent' => 5,
        'delivered' => 5,
        'failed' => 0,
        'opted_out' => 0,
        'complaints' => 0,
    ]);

    $rates = app(SendingHealth::class)->rates(OutreachChannel::Sms);
    expect($rates->sent)->toBe(5);

    CarbonImmutable::setTestNow();
});
