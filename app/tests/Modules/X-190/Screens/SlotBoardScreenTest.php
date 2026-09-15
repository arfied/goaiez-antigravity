<?php

declare(strict_types=1);

namespace Tests\Modules\X190\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X190\Models\ReferralSlot;
use App\Modules\X190\Ui\SlotBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SlotBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-190.slot-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No referral slots yet.');

        Tenancy::set((int) $biz->id);
        ReferralSlot::create([
            'business_id' => $biz->id,
            'category' => 'electrical-4471',
            'territory_zip' => '75001',
        ]);
        Tenancy::forget();

        $this->get(route('x-190.slot-board'))
            ->assertOk()
            ->assertSee('electrical-4471')
            ->assertSee('75001')
            ->assertSee('open')
            ->assertDontSee('No referral slots yet.');

        Livewire::actingAs($owner)->test(SlotBoard::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-190.slot-board.admin'))->assertOk();

        Livewire::test(SlotBoard::class)->assertOk();
    }
}
