<?php

declare(strict_types=1);

namespace Tests\Modules\X08\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X08\Models\ChurnScore;
use App\Modules\X08\Ui\RiskListView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RiskListViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $this->get(route('x-08.risk-list'))
            ->assertOk()
            ->assertSee('No churn scores yet.');

        ChurnScore::create([
            'business_id' => $biz->id,
            'tenant_identifier' => 'DISTINCT_TENANT_XYZ',
            'login_decay_days' => 5,
            'roi_open_rate_rising' => true,
            'risk_level' => 'High Risk',
            'recommendation_note' => 'Follow up',
        ]);

        $this->get(route('x-08.risk-list'))
            ->assertOk()
            ->assertSee('DISTINCT_TENANT_XYZ')
            ->assertSee('High Risk');

        Livewire::test(RiskListView::class)->assertOk();
    }

    public function test_control_evaluates_tenant_and_updates_screens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. empty state
        $this->get(route('x-08.risk-list'))
            ->assertSee('No churn scores yet.');

        $this->get(route('x-08.sorted'))
            ->assertSee('No churn scores yet.');

        // 2. drive control
        Livewire::test(RiskListView::class)
            ->set('tenantIdentifier', 'NEW_TENANT_123')
            ->set('loginDecayDays', '10')
            ->set('roiOpenRateRising', true)
            ->call('evaluate')
            ->assertSet('success', 'Evaluated churn score for tenant NEW_TENANT_123.')
            ->assertSet('tenantIdentifier', '')
            ->assertSet('loginDecayDays', '')
            ->assertSet('roiOpenRateRising', false);

        // 3. assert row exists
        $this->assertDatabaseHas((new ChurnScore)->getTable(), [
            'business_id' => $biz->id,
            'tenant_identifier' => 'NEW_TENANT_123',
            'login_decay_days' => 10,
            'roi_open_rate_rising' => 1, // boolean might be stored as 1
        ]);

        // 4. GET control's screen and assert new value is visible
        $this->get(route('x-08.risk-list'))
            ->assertSee('NEW_TENANT_123')
            ->assertDontSee('No churn scores yet.');

        // 5. GET one of the other screens it feeds and assert it is no longer empty
        $this->get(route('x-08.sorted'))
            ->assertSee('NEW_TENANT_123')
            ->assertDontSee('No churn scores yet.');
    }

    public function test_control_refuses_invalid_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(RiskListView::class)
            ->set('tenantIdentifier', '')
            ->set('loginDecayDays', '10')
            ->call('evaluate')
            ->assertSet('error', 'Tenant Identifier is required.');

        $this->assertDatabaseMissing((new ChurnScore)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
