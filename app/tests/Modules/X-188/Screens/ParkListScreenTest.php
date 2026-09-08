<?php

declare(strict_types=1);

namespace Tests\Modules\X188\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X188\Ui\ParkList;
use Livewire\Livewire;
use Tests\TestCase;

class ParkListScreenTest extends TestCase
{
    /**
     * Proves the component wires the tenant's number_parks to the view.
     * BUILD PROPOSAL: X-188 owes the cancellation trigger to call NumberParkAction.
     */
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Given a parked number
        $pool = \App\Modules\X188\Models\NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+15551234567',
            'area_code' => '555',
        ]);
        $park = \App\Modules\X188\Models\NumberPark::create([
            'business_id' => $biz->id,
            'phone_number_id' => $pool->id,
            'parked_at' => now(),
            'park_until' => now()->addDays(14),
        ]);

        $this->get(route('x-188.park-list'))->assertOk();

        Livewire::test(ParkList::class)
            ->assertOk()
            ->assertSee($park->park_until);
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-188.park-list.admin'))->assertOk();

        Livewire::test(ParkList::class)->assertOk();
    }
}
