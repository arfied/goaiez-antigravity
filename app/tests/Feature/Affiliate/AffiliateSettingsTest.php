<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X205\Domain\AffiliateEngine;
use App\Support\Tenancy;
use Carbon\Carbon;
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

it('a referral count of 15 reads gold when gold is written as 12', function () {
    PlatformSetting::write('affiliate.tier.gold_referrals', 12, 'test');

    $engine = app(AffiliateEngine::class);
    $tier = $engine->getTier(15);

    expect($tier)->toBe('Gold');
});

it('a 40-day cookie is stale when the lifetime is written as 30', function () {
    PlatformSetting::write('affiliate.cookie_lifetime_days', 30, 'test');

    $engine = app(AffiliateEngine::class);

    $clickTime = Carbon::now()->subDays(40);
    $saleTime = Carbon::now();

    expect($engine->isWithinAttributionWindow($clickTime, $saleTime))->toBeFalse();
    expect($engine->isCookieValid(40))->toBeFalse();
});
