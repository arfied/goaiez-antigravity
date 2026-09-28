<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Fetch\RobotsPolicy;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('a written cache seconds is what the policy hands Cache', function () {
    PlatformSetting::write('fetch.robots.cache_seconds', 4242, 'test');

    Cache::spy();
    Http::fake([
        '*' => Http::response("User-agent: *\nDisallow: /", 200),
    ]);

    $policy = app(RobotsPolicy::class);
    $policy->verdict('https://example.com/test');

    Cache::shouldHaveReceived('put')->with(Mockery::any(), Mockery::any(), 4242);
});
