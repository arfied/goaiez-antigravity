<?php

declare(strict_types=1);

namespace Tests\Modules\X190\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X190\Models\ReferralSlot;
use App\Modules\X190\Ui\SlotBoard;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_owner_opens_a_slot_and_it_lists_as_open(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(SlotBoard::class)
            ->set('category', 'electrical-4471')
            ->set('territoryZip', '75001')
            ->call('openSlot')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('referral_slots', [
            'business_id' => $biz->id,
            'category' => 'electrical-4471',
            'territory_zip' => '75001',
            'status' => 'open',
        ]);

        $this->get(route('x-190.slot-board'))
            ->assertOk()
            ->assertSee('electrical-4471')
            ->assertSee('open');
    }

    public function test_owner_proposes_a_partner_and_the_slot_reads_proposed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $slot = ReferralSlot::create([
            'business_id' => $biz->id,
            'category' => 'electrical-4471',
            'territory_zip' => '75001',
        ]);

        Livewire::test(SlotBoard::class)
            ->set('partnerName.'.$slot->id, 'distinctive-partner-7731')
            ->call('proposePartner', $slot->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('partner_pool', [
            'business_id' => $biz->id,
            'company_name' => 'distinctive-partner-7731',
            'category' => $slot->category,
            'territory_zip' => $slot->territory_zip,
        ]);

        $slot->refresh();
        $this->assertSame('proposed', $slot->status);

        $this->get(route('x-190.network-map'))
            ->assertOk()
            ->assertSee('distinctive-partner-7731');
    }

    public function test_another_tenants_slot_cannot_be_proposed_from_here(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::setUser((int) $ownerB->id);
        Tenancy::set((int) $bizB->id);

        $slotB = ReferralSlot::create([
            'business_id' => $bizB->id,
            'category' => 'electrical-4471',
            'territory_zip' => '75001',
        ]);

        Tenancy::setUser((int) $owner->id);
        Tenancy::set((int) $biz->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(SlotBoard::class)
            ->set('partnerName.'.$slotB->id, 'x')
            ->call('proposePartner', $slotB->id);
    }
}
