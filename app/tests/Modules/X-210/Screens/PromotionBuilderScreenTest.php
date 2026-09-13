<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Ui\PromotionBuilder;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionBuilderScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.promotion-builder'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('The code your customers type')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        Livewire::test(PromotionBuilder::class)
            ->set('code', 'spring25')
            ->set('discountType', 'percentage')
            ->set('amount', '25')
            ->set('maxUses', '40')
            ->call('save')
            ->assertSee('SPRING25 is saved and running')
            ->assertHasNoErrors();
        Livewire::test(PromotionBuilder::class)
            ->set('code', 'Spring25')
            ->set('discountType', 'fixed_cents')
            ->set('amount', '12.50')
            ->set('maxUses', '5')
            ->call('save')
            ->assertSee('You already have an offer with this code');
        Livewire::test(PromotionBuilder::class)
            ->set('code', 'NOLIMIT')
            ->set('amount', '10')
            ->set('maxUses', '0')
            ->call('save')
            ->assertSee('An offer needs a limit of at least one use');
        Tenancy::forget();

        $this->get(route('x-210.active-promotions'))
            ->assertOk()
            ->assertSee('SPRING25')
            ->assertSee('25% off')
            ->assertSee('0 of 40 used')
            ->assertDontSee('$12.50 off')
            ->assertDontSee('NOLIMIT');

        Livewire::test(PromotionBuilder::class)->assertOk();
    }
}
