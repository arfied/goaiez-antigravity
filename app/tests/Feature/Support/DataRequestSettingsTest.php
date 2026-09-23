<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Support\DataRequests;
use App\Support\Tenancy;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('data requests honours statutory due days', function () {
    PlatformSetting::write('support.data_requests.statutory_due_days', 60, 'test');

    $service = app(DataRequests::class);
    $this->assertEquals(60, $service->statutoryDueDays());
});
