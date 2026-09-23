<?php

declare(strict_types=1);

namespace Tests\Modules\X209\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X209\Models\FixerLadder;
use App\Modules\X209\Ui\LaddersOwnState;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class LaddersOwnStateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-209.ladders-own-state'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing has earned autonomy yet.');

        Tenancy::setUser($owner->id);
        FixerLadder::create([
            'business_id' => $biz->id,
            'action_name' => 'distinctive_eta_update_4631',
        ]);
        Tenancy::forget();

        $this->get(route('x-209.ladders-own-state'))
            ->assertOk()
            ->assertSee('distinctive_eta_update_4631')
            ->assertSee('level 3')
            ->assertDontSee('Nothing has earned autonomy yet.');

        Livewire::test(LaddersOwnState::class)->assertOk();
    }

    public function test_approve_control_ceiling(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Control: Approve failure (Empty)
        Livewire::test(LaddersOwnState::class)
            ->set('actionName', '')
            ->call('approveAction')
            ->assertSet('error', 'Action name is required.');

        // Control: Approve 1 -> Level 4
        Livewire::test(LaddersOwnState::class)
            ->set('actionName', 'repeated_action')
            ->call('approveAction')
            ->assertSet('actionName', '')
            ->assertSet('error', null)
            ->assertSee('Promoted action to level 4');

        // Control: Approve 2 -> Level 5
        Livewire::test(LaddersOwnState::class)
            ->set('actionName', 'repeated_action')
            ->call('approveAction')
            ->assertSet('actionName', '')
            ->assertSet('error', null)
            ->assertSee('Promoted action to level 5');

        // Control: Approve 3 -> Still Level 5
        Livewire::test(LaddersOwnState::class)
            ->set('actionName', 'repeated_action')
            ->call('approveAction')
            ->assertSet('actionName', '')
            ->assertSet('error', null)
            ->assertSee('Promoted action to level 5');

        $this->assertDatabaseHas((new FixerLadder)->getTable(), [
            'business_id' => $biz->id,
            'action_name' => 'repeated_action',
            'current_level' => 5,
        ]);

        $this->assertDatabaseMissing((new FixerLadder)->getTable(), [
            'business_id' => $biz->id,
            'action_name' => 'repeated_action',
            'current_level' => 6,
        ]);

        // Fan-out assertions
        $this->get(route('x-209.ladders-own-state'))
            ->assertOk()
            ->assertSee('repeated_action')
            ->assertSee('level 5')
            ->assertDontSee('Nothing has earned autonomy yet.');
    }
}
