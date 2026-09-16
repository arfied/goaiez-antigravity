<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Models\AgentRefusal;
use App\Modules\CAgent\Ui\RefusalcodeDistributionPer;
use Livewire\Livewire;
use Tests\TestCase;

class RefusalcodeDistributionPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-agent.refusalcode-distribution-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Zero refusals logged.');

        Livewire::test(RefusalcodeDistributionPer::class)->assertOk();
    }

    /**
     * Proves the component renders the tenant's refusals by seeing the code and reason.
     */
    public function test_shows_tenant_refusals(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        AgentRefusal::create([
            'business_id' => $biz->id,
            'refusal_code' => 'UNDER_18',
            'reason' => 'Tenant requested something',
        ]);

        $this->get(route('c-agent.refusalcode-distribution-per'))
            ->assertSee('UNDER_18')
            ->assertSee('Tenant requested something');

        Livewire::test(RefusalcodeDistributionPer::class)
            ->assertSee('UNDER_18')
            ->assertSee('Tenant requested something');
    }
}
