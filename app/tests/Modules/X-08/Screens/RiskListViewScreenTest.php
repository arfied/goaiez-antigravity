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
}
