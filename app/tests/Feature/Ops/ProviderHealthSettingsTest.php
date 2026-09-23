<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Tenancy;
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
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

use App\Modules\X219\Actions\ProviderHealthAction;
use App\Modules\X219\Models\AiProvider;

it('respects provider health degraded rate setting', function () {
    PlatformSetting::write('ai.provider.degraded_error_rate_pct', 10, 'test');
    $action = app(ProviderHealthAction::class);
    $provider = AiProvider::create(['business_id' => $this->biz->id, 'provider_name' => 'openai', 'status' => 'healthy', 'latency_p95_ms' => 100, 'error_rate_pct' => 0]);
    $res = $action->updateHealth($this->biz->id, $provider->id, 100, 15);
    expect($res->status)->toBe('down');
});
