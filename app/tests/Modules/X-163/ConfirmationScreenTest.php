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

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirmation Seam',
            'price_cents' => 62375,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $this->actingAs($owner);
        $this->get(route('x-163.confirmation-screen'))->assertOk()->assertSee('623.75');
    }

    public function test_a_cleared_price_is_refused_without_erasing_the_stored_one(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Existing Service',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->set('prices.'.$item->id, 0)
            ->call('confirm', $item->id)
            ->assertSee('Needs a price');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'price_cents' => 15000,
            'is_confirmed' => false,
        ]);
    }

    public function test_a_typed_price_is_still_written_and_confirmed(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Existing Service',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->set('prices.'.$item->id, 250.00)
            ->call('confirm', $item->id);

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'price_cents' => 25000,
            'is_confirmed' => true,
        ]);
    }

    public function test_confirmation_screen_prices_key_unset_on_confirm(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'To Confirm',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $component = Livewire::actingAs($owner)->test(ConfirmationScreen::class);
        $this->assertArrayHasKey($item->id, $component->get('prices'), 'Key must be present initially');

        $component->call('confirm', $item->id);

        $this->assertArrayNotHasKey($item->id, $component->get('prices'), 'Key must be unset after confirm');
    }

    public function test_confirmation_screen_prices_key_retained_for_other_items(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item1 = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'To Confirm',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $item2 = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'To Keep',
            'price_cents' => 20000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $component = Livewire::actingAs($owner)->test(ConfirmationScreen::class);
        $component->call('confirm', $item1->id);

        $this->assertArrayNotHasKey($item1->id, $component->get('prices'), 'Key must be unset for confirmed item');
        $this->assertArrayHasKey($item2->id, $component->get('prices'), 'Key must be retained for unconfirmed item');
    }
}
