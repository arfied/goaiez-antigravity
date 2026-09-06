<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Models\User;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\ConfirmationScreen;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConfirmationScreenTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(ConfirmationScreen::class)->assertForbidden();
    }

    public function test_owner_can_view_and_interact(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Test Service',
            'price_cents' => 10000,
            'is_sample' => true,
            'is_confirmed' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->assertOk()
            ->assertSee('Test Service')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>')
            ->assertSee('1 left to review');

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->call('confirm', $item->id);

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);
    }

    public function test_confirm_with_zero_price_shows_needs_price(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Zero Price Service',
            'price_cents' => 0,
            'is_sample' => true,
            'is_confirmed' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->call('confirm', $item->id)
            ->assertSee('Needs a price');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'is_confirmed' => false,
        ]);
    }

    public function test_empty_state_renders(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->assertSee('Nothing left to confirm');
    }

    public function test_callout_fee_is_saved_in_cents(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->set('calloutFeeDollars', 85);

        $this->assertDatabaseHas('callout_fees', [
            'business_id' => $biz->id,
            'callout_fee_cents' => 8500,
        ]);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->assertSee('callout fee: set');
    }
}
