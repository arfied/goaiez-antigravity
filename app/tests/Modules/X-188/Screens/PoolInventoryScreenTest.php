<?php

declare(strict_types=1);

namespace Tests\Modules\X188\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X188\Ui\PoolInventory;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PoolInventoryScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-188.pool-inventory'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Pool empty.');

        Tenancy::setUser($owner->id);
        NumberPool::create(['business_id' => $biz->id, 'phone_number' => '+15125554471', 'area_code' => '512', 'carrier_name' => 'telnyx', 'status' => 'available', 'complaint_count' => 0]);
        Tenancy::forget();

        $this->get(route('x-188.pool-inventory'))
            ->assertOk()
            ->assertSee('+15125554471')
            ->assertSee('Area Code: 512')
            ->assertSee('(available)')
            ->assertDontSee('Pool empty.');

        Livewire::test(PoolInventory::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-188.pool-inventory.admin'))->assertOk();

        Livewire::test(PoolInventory::class)->assertOk();
    }

    public function test_claiming_assigns_a_number(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->assertDatabaseMissing('number_pool', ['business_id' => $biz->id]);

        Livewire::test(PoolInventory::class)
            ->call('claimNumber')
            ->assertOk();

        $this->assertDatabaseHas('number_pool', [
            'business_id' => $biz->id,
            'status' => 'assigned',
        ]);
    }

    public function test_claiming_twice_yields_same_number(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->assertDatabaseMissing('number_pool', ['business_id' => $biz->id]);

        $component = Livewire::test(PoolInventory::class);

        $component->call('claimNumber')->assertOk();
        $this->assertEquals(1, NumberPool::where('business_id', $biz->id)->count());

        $assignedNumber = NumberPool::where('business_id', $biz->id)->first()->phone_number;

        $component->call('claimNumber')->assertOk();

        $this->assertEquals(1, NumberPool::where('business_id', $biz->id)->count());
        $this->assertDatabaseHas('number_pool', [
            'business_id' => $biz->id,
            'phone_number' => $assignedNumber,
        ]);
    }

    public function test_non_owner_is_refused(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->provisionTenant();
        $this->actingAs($staff);

        Livewire::test(PoolInventory::class)
            ->call('claimNumber')
            ->assertForbidden();
    }
}
