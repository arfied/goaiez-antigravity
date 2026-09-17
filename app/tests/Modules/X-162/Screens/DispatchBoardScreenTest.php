<?php

declare(strict_types=1);

namespace Tests\Modules\X162\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Ui\DispatchBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-162.dispatch-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No dispatch assignments today');

        Tenancy::setUser($owner->id);
        DispatchAssignment::create([
            'business_id' => $biz->id,
            'job_id' => 4610,
            'tech_id' => $owner->id,
            'status' => 'dispatched',
            'is_sample' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-162.dispatch-board'))
            ->assertOk()
            ->assertSee('Job #4610')
            ->assertSee('Tech ID: ')
            ->assertSee('Mark en route')
            ->assertDontSee('No dispatch assignments today');

        Livewire::test(DispatchBoard::class)->assertOk();
    }
}
