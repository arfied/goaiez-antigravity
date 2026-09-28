<?php

declare(strict_types=1);

namespace Tests\Modules\X08\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X08\Actions\ChurnScoreAction;
use App\Modules\X08\Models\ChurnScore;
use App\Modules\X08\Ui\SortedView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SortedViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $otherBiz = $this->provisionTenant();
        Tenancy::set($otherBiz->id);
        ChurnScore::create([
            'business_id' => $otherBiz->id,
            'tenant_identifier' => 'OTHER_TENANT_XYZ',
            'login_decay_days' => 5,
            'roi_open_rate_rising' => true,
            'risk_level' => 'Other Risk',
            'recommendation_note' => 'Other note',
        ]);

        $this->actingAs($owner);
        Tenancy::set($biz->id);

        ChurnScore::create([
            'business_id' => $biz->id,
            'tenant_identifier' => 'MY_TENANT_ABC',
            'login_decay_days' => 5,
            'roi_open_rate_rising' => true,
            'risk_level' => 'My High Risk',
            'recommendation_note' => 'My note',
        ]);

        $this->get(route('x-08.sorted'))
            ->assertOk()
            ->assertSee('Sorted Risk Rankings')
            ->assertSee('Identifier:')
            ->assertSee('Risk Level:')
            ->assertSee('MY_TENANT_ABC')
            ->assertSee('My High Risk')
            ->assertDontSee('OTHER_TENANT_XYZ');

        Livewire::test(SortedView::class)->assertOk();
    }

    public function test_sorted_risk_rankings_puts_high_risk_first(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $action = new ChurnScoreAction;
        $action->evaluate($biz->id, 'Distinctive T_MED 4671', 5, false);
        $action->evaluate($biz->id, 'Distinctive T_LOW 4672', 0, true);
        $action->evaluate($biz->id, 'Distinctive T_HIGH 4673', 20, false);

        Tenancy::set($biz->id);

        $this->actingAs($owner)->get(route('x-08.sorted'))->assertSeeInOrder([
            'Distinctive T_HIGH 4673',
            'Distinctive T_MED 4671',
            'Distinctive T_LOW 4672',
        ]);
    }
}
