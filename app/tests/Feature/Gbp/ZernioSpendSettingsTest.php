<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Gbp\ZernioSpend;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('zernio spend honours free tier credit cents', function () {
    PlatformSetting::write('gbp.zernio.free_tier_credit_cents', 2500, 'test');

    $service = app(ZernioSpend::class);
    $this->assertEquals(2500, $service->freeTierCreditCents());
});
