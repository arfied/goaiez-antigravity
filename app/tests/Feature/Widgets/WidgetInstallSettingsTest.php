<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\WidgetInstallState;
use App\Models\PlatformSetting;
use App\Models\Plugin;
use App\Models\User;
use App\Models\WidgetInstall;
use App\Services\Widgets\WidgetInstalls;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('honours stale hours', function () {
    PlatformSetting::write('widgets.install.stale_after_hours', 10, 'test');

    $plugin = Plugin::factory()->create(['business_id' => $this->biz->id]);

    WidgetInstall::forceCreate([
        'plugin_id' => $plugin->id,
        'business_id' => $this->biz->id,
        'host' => 'example.test',
        'first_seen_at' => CarbonImmutable::now()->subHours(12),
        'last_seen_at' => CarbonImmutable::now()->subHours(12),
    ]);

    $service = app(WidgetInstalls::class);
    $status = $service->statusFor($plugin);

    expect($status->state)->toBe(WidgetInstallState::Stopped);

    PlatformSetting::write('widgets.install.stale_after_hours', 24, 'test');

    $status2 = $service->statusFor($plugin);
    expect($status2->state)->toBe(WidgetInstallState::Working);
});

it('honours throttle seconds', function () {
    PlatformSetting::write('widgets.install.throttle_seconds', 4242, 'test');

    Cache::spy();
    Cache::shouldReceive('add')->andReturn(true);

    $plugin = Plugin::factory()->create(['business_id' => $this->biz->id, 'allowed_domains' => ['example.test']]);

    $service = app(WidgetInstalls::class);
    $service->record($plugin, 'https://example.test');

    Cache::shouldHaveReceived('add')->with(Mockery::any(), true, 4242);
});
