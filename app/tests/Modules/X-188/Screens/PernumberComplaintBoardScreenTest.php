<?php

declare(strict_types=1);

namespace Tests\Modules\X188\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X188\Ui\PernumberComplaintBoard;
use Livewire\Livewire;
use Tests\TestCase;

class PernumberComplaintBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-188.pernumber-complaint-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('All numbers complaint rate below 0.1% threshold.');

        NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+15125554498',
            'area_code' => '512',
            'complaint_count' => 3,
        ]);

        $this->get(route('x-188.pernumber-complaint-board'))
            ->assertOk()
            ->assertSee('+15125554498: 3 complaints')
            ->assertDontSee('All numbers complaint rate below 0.1% threshold.');

        Livewire::test(PernumberComplaintBoard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-188.pernumber-complaint-board.admin'))->assertOk();

        Livewire::test(PernumberComplaintBoard::class)->assertOk();
    }
}
