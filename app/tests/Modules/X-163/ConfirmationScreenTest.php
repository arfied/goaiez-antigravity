<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Actions\CalloutLookupAction;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\ConfirmationScreen;
use App\Support\Tenancy;
use Livewire\Exceptions\MethodNotFoundException;
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

    public function test_empty_state_offers_no_remedy(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(ConfirmationScreen::class)
            ->assertOk()
            ->assertSee('All your prices have been confirmed.')
            ->assertDontSee('Open Pricebook');
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(ConfirmationScreen::class)->assertForbidden();
    }

    public function test_manager_is_admitted(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Manager;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(ConfirmationScreen::class)->assertOk();
    }

    public function test_ticking_deducted_with_no_fee_set_writes_no_fee_and_the_agent_refuses(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ConfirmationScreen::class)->set('calloutFeeDeducted', true);

        $row = CalloutFee::where('business_id', $biz->id)->first();
        $this->assertNotNull($row, 'T1 A1: the row was not created');
        $this->assertSame(0, $row->callout_fee_cents, 'T1 A2: callout_fee_cents is not 0');
        $this->assertTrue($row->deducted_if_proceeding, 'T1 A3: deducted_if_proceeding is not true');

        $res = app(CalloutLookupAction::class)->handle($biz->id);
        $this->assertSame('NO_FACT', $res['refusal_code'] ?? null, 'T1 A4: refusal_code is not NO_FACT');
    }

    public function test_typing_a_fee_with_deducted_unticked_writes_deducted_false(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ConfirmationScreen::class)->set('calloutFeeDollars', 85);

        $row = CalloutFee::where('business_id', $biz->id)->firstOrFail();
        $this->assertSame(8500, $row->callout_fee_cents, 'T2 A1: callout_fee_cents is not 8500');
        $this->assertFalse($row->deducted_if_proceeding, 'T2 A2: deducted_if_proceeding is not false');
    }

    public function test_a_negative_callout_fee_is_refused(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        CalloutFee::create(['business_id' => $biz->id, 'callout_fee_cents' => -5000, 'deducted_if_proceeding' => false]);

        $res = app(CalloutLookupAction::class)->handle($biz->id);
        $this->assertSame('NO_FACT', $res['refusal_code'] ?? null, 'T3 A1: refusal_code is not NO_FACT');
    }

    public function test_a_one_cent_callout_fee_is_quoted(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        CalloutFee::create(['business_id' => $biz->id, 'callout_fee_cents' => 1, 'deducted_if_proceeding' => false]);

        $res = app(CalloutLookupAction::class)->handle($biz->id);
        $this->assertSame('$0.01', $res['formatted_fee'] ?? null, 'T4 A1: formatted_fee is not $0.01');
    }

    public function test_a_refused_confirm_keeps_confirm_clickable(): void
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

        $component = Livewire::actingAs($owner)->test(ConfirmationScreen::class)->call('confirm', $item->id);

        $component->assertSee('Needs a price');
        $this->assertDoesNotMatchRegularExpression('/wire:click="confirm\('.$item->id.'\)"\s+disabled/', $component->html(), 'T2 A2 Confirm is disabled on a row that needs a price');
    }

    public function test_a_browser_call_cannot_rewrite_a_confirmed_price_on_the_confirmation_screen(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Unblock',
            'price_cents' => 12000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $component = Livewire::actingAs($owner)->test(ConfirmationScreen::class);

        $refused = false;
        try {
            $component->call('updatePrice', $item->id, 0);
        } catch (MethodNotFoundException $e) {
            $refused = true;
        }

        $this->assertEquals(12000, $item->fresh()->price_cents, 'T1 A1 a browser call rewrote a confirmed price');
        $this->assertTrue($refused, 'T1 A2 updatePrice answered a browser call');
    }

    public function test_screen_renders_in_the_owner_shell_for_tenant(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->get(route('x-163.confirmation-screen'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing left to confirm');

        Tenancy::setUser($owner->id);
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Unique Service Name For Owner Shell Test',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-163.confirmation-screen'))
            ->assertSee('Unique Service Name For Owner Shell Test')
            ->assertDontSee('Nothing left to confirm');
    }
}
