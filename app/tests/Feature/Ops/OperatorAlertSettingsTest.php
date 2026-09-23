<?php

declare(strict_types=1);

use App\Enums\AlertOrigin;
use App\Enums\OperatorAlertKind;
use App\Enums\UserRole;
use App\Models\OperatorAlert;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ops\OperatorAlerts;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('refuses the N+1th push inside the window', function () {
    PlatformSetting::write('ops.alerts.push_budget_per_kind', 2, 'test');

    // Seed 2 alerts that were emailed
    OperatorAlert::create([
        'kind' => OperatorAlertKind::PixelIngestRejects->value,
        'subject' => 'sub1',
        'summary' => 'summary 1',
        'context' => [],
        'fired_at' => CarbonImmutable::now(),
        'emailed_at' => CarbonImmutable::now(),
    ]);

    OperatorAlert::create([
        'kind' => OperatorAlertKind::PixelIngestRejects->value,
        'subject' => 'sub2',
        'summary' => 'summary 2',
        'context' => [],
        'fired_at' => CarbonImmutable::now(),
        'emailed_at' => CarbonImmutable::now(),
    ]);

    $alerts = app(OperatorAlerts::class);
    $third = $alerts->raise(OperatorAlertKind::PixelIngestRejects, 'sub3', 'summary 3', [], AlertOrigin::Request);

    $this->assertNotNull($third->push_withheld_at);
});
