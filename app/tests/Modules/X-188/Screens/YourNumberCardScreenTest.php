<?php

declare(strict_types=1);

namespace Tests\Modules\X188\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X188\Ui\YourNumberCard;
use Livewire\Livewire;
use Tests\TestCase;

class YourNumberCardScreenTest extends TestCase
{
    /** @test */
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-188.your-number-card'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No active number assigned.');

        Livewire::test(YourNumberCard::class)->assertOk(); // standing check
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-188.your-number-card.admin'))->assertOk();

        Livewire::test(YourNumberCard::class)->assertOk(); // standing check
    }

    public function test_screen_shows_assigned_number(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $pool = NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+15555551234',
            'area_code' => '555',
            'carrier_name' => 'telnyx',
            'status' => 'assigned',
        ]);
        NumberAssignment::create([
            'business_id' => $biz->id,
            'phone_number_id' => $pool->id,
            'status' => 'active',
        ]);

        $this->get(route('x-188.your-number-card'))
            ->assertSee('+15555551234');
    }

    public function test_screen_component_exposes_number(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $pool = NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+15555551234',
            'area_code' => '555',
            'carrier_name' => 'telnyx',
            'status' => 'assigned',
        ]);
        NumberAssignment::create([
            'business_id' => $biz->id,
            'phone_number_id' => $pool->id,
            'status' => 'active',
        ]);

        Livewire::test(YourNumberCard::class)
            ->assertViewHas('number', '+15555551234');
    }
}
