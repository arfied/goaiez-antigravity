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
}
