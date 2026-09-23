<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Billing\PurchaseReconciliation;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('purchase reconciliation honours seeded thresholds', function () {
    PlatformSetting::write('billing.reconciliation.minimum_age_minutes', 140, 'test');
    PlatformSetting::write('billing.reconciliation.lookback_days', 80, 'test');
    PlatformSetting::write('billing.reconciliation.default_batch', 200, 'test');

    $service = app(PurchaseReconciliation::class);
    $this->assertEquals(140, $service->minimumAgeMinutes());
    $this->assertEquals(80, $service->lookbackDays());
    $this->assertEquals(200, $service->defaultBatch());
});
