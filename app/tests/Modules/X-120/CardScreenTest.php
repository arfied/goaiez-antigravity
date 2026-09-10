<?php

declare(strict_types=1);

namespace Tests\Modules\X120;

use App\Models\User;
use App\Modules\X120\Models\CardToken;
use App\Modules\X120\Ui\CardScreen;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CardScreenTest extends TestCase
{
    public function test_card_screen_shows_cards_and_can_make_default(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($bizB->id);
        CardToken::create([
            'business_id' => $bizB->id,
            'gateway_payment_method_id' => 'tok_9',
            'gateway_customer_id' => 'cus_9',
            'brand' => 'Visa',
            'last_four' => '9999',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => true,
        ]);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $card1 = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_1',
            'gateway_customer_id' => 'cus_1',
            'brand' => 'Visa',
            'last_four' => '4242',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => true,
        ]);

        $card2 = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_2',
            'gateway_customer_id' => 'cus_2',
            'brand' => 'MasterCard',
            'last_four' => '5555',
            'exp_month' => 10,
            'exp_year' => now()->year - 1, // Expired
            'is_default' => false,
        ]);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->assertSee('4242')
            ->assertSee('5555')
            ->assertDontSee('9999')
            ->assertSee('Card Expiring Soon')
            ->call('makeDefault', $card2->id)
            ->assertSee('Card set as default');

        $this->assertTrue($card2->fresh()->is_default);
        $this->assertFalse($card1->fresh()->is_default);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->call('makeDefault', 999999)
            ->assertSee('Card not found in this account')
            ->assertSee('Try again');
    }

    public function test_add_card_opens_the_form_and_shows_no_waiting_state_yet(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->call('addCard')
            ->assertSeeHtml('autocomplete="cc-number"')
            ->assertSee('Check this card')
            ->assertDontSee('Keep this card')
            ->assertDontSee('Waiting on Stripe tokenisation:');
    }

    /**
     * [N-046] the security code is never taken, so it is never persisted;
     * [N-047] the number is not on the page after it is presented.
     */
    public function test_card_door_takes_number_expiry_and_name_stores_nothing_and_never_shows_the_number_again(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_ctrl',
            'gateway_customer_id' => 'cus_ctrl',
            'brand' => 'Visa',
            'last_four' => '1111',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => true,
        ]);

        $screen = Livewire::actingAs($owner)->test(CardScreen::class)
            ->call('addCard')
            ->assertDontSee('Waiting on Stripe tokenisation:')
            ->assertSeeHtml('autocomplete="cc-number"')
            ->assertSeeHtml('autocomplete="cc-exp-month"')
            ->assertSeeHtml('autocomplete="cc-name"')
            ->assertSee('1111')
            ->assertDontSeeHtml('cvv')
            ->assertSee('1111')
            ->assertDontSeeHtml('cvc')
            ->assertSee('1111')
            ->assertDontSeeHtml('CVV')
            ->set('number', '4242 4242 4242 4242')
            ->set('expMonth', '1')
            ->set('expYear', '2020')
            ->set('name', 'A Plumber')
            ->call('present')
            ->assertSee('expired 01/2020')
            ->assertSee('1111')
            ->assertDontSee('4242424242424242')
            ->assertSee('1111')
            ->assertDontSeeHtml('4242 4242 4242 4242')
            ->set('number', '4242424242424241')
            ->set('expMonth', '12')
            ->set('expYear', (string) (now()->year + 2))
            ->call('present')
            ->assertSee('does not check out')
            ->set('number', '4242424242424242')
            ->set('expMonth', '12')
            ->set('expYear', (string) (now()->year + 2))
            ->set('name', 'A Plumber')
            ->call('present')
            ->assertSee('Waiting on Stripe tokenisation: the visa ending 4242')
            ->assertSee('A Plumber')
            ->assertSee('1111')
            ->assertDontSeeHtml('4242424242424242');

        $this->assertSame(
            0,
            CardToken::where('business_id', $biz->id)
                ->where('gateway_payment_method_id', '!=', 'tok_ctrl')
                ->count(),
            'the door stores nothing beyond the seeded control'
        );
        $this->assertSame('', $screen->get('number'), 'the number is cleared before the page goes back');
    }

    public function test_card_screen_filters_expiring_cards_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-06 12:00:00'));
        $now = now();

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $expiredCard = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_exp',
            'gateway_customer_id' => 'cus_exp',
            'brand' => 'Visa',
            'last_four' => '1111',
            'exp_month' => $now->copy()->subMonth()->month,
            'exp_year' => $now->copy()->subMonth()->year,
            'is_default' => true,
        ]);

        $soonCard = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_soon',
            'gateway_customer_id' => 'cus_soon',
            'brand' => 'Visa',
            'last_four' => '2222',
            'exp_month' => $now->copy()->addDays(15)->month,
            'exp_year' => $now->copy()->addDays(15)->year,
            'is_default' => false,
        ]);

        $farCard = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_far',
            'gateway_customer_id' => 'cus_far',
            'brand' => 'Visa',
            'last_four' => '3333',
            'exp_month' => $now->copy()->addYears(2)->month,
            'exp_year' => $now->copy()->addYears(2)->year,
            'is_default' => false,
        ]);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->assertViewHas('cards', fn ($cards) => $cards->count() === 3)
            ->assertViewHas('expiringCards', fn ($cards) => $cards->count() === 2
                && $cards->contains(fn ($c) => $c->id === $expiredCard->id)
                && $cards->contains(fn ($c) => $c->id === $soonCard->id)
                && ! $cards->contains(fn ($c) => $c->id === $farCard->id));

        Carbon::setTestNow();
    }

    public function test_the_empty_card_vault_says_what_it_waits_on(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->assertOk()
            ->assertSee('No cards on file')
            ->assertSee('No card has ever been kept on this account')
            ->assertSee('Keeping one waits on Stripe tokenisation')
            ->assertDontSee('Please add a card');
    }

    public function test_the_card_list_holds_its_order_when_a_row_is_rewritten(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $first = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_1',
            'gateway_customer_id' => 'cus_1',
            'brand' => 'Visa',
            'last_four' => '1111',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => false,
        ]);

        CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_2',
            'gateway_customer_id' => 'cus_2',
            'brand' => 'Visa',
            'last_four' => '2222',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => false,
        ]);

        CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_3',
            'gateway_customer_id' => 'cus_3',
            'brand' => 'Visa',
            'last_four' => '3333',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => false,
        ]);

        $first->update(['brand' => 'MasterCard']);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->assertSeeInOrder(['1111', '2222', '3333']);
    }

    public function test_present_clears_the_four_fields_after_submission(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->set('number', '4242424242424242')
            ->set('expMonth', '12')
            ->set('expYear', '2028')
            ->set('name', 'John Doe')
            ->call('present')
            ->assertSet('number', '')
            ->assertSet('expMonth', '')
            ->assertSet('expYear', '')
            ->assertSet('name', '');
    }

    public function test_make_default_forgets_partially_typed_fields(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $card = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_9',
            'gateway_customer_id' => 'cus_9',
            'brand' => 'Visa',
            'last_four' => '9999',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => false,
        ]);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->set('number', '1234123412341234')
            ->set('expMonth', '11')
            ->set('expYear', '2029')
            ->set('name', 'Incomplete Name')
            ->call('makeDefault', $card->id)
            ->assertDontSeeHtml('1234123412341234')
            ->assertSet('expMonth', '')
            ->assertSet('expYear', '')
            ->assertSet('name', '');
    }

    public function test_add_card_forgets_partially_typed_fields(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->set('number', '1234123412341234')
            ->set('expMonth', '11')
            ->set('expYear', '2029')
            ->set('name', 'Incomplete Name')
            ->call('addCard')
            ->assertDontSeeHtml('1234123412341234')
            ->assertSet('expMonth', '')
            ->assertSet('expYear', '')
            ->assertSet('name', '');
    }

    public function test_the_make_default_button_carries_the_double_send_guard(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $card = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_9',
            'gateway_customer_id' => 'cus_9',
            'brand' => 'Visa',
            'last_four' => '9999',
            'exp_month' => 12,
            'exp_year' => now()->year + 1,
            'is_default' => false,
        ]);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->assertOk()
            ->assertSeeHtml('wire:target="makeDefault('.$card->id.')"');
    }

    public function test_making_a_card_default_does_not_leave_a_stale_tokenisation_notice(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $card = CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'tok_stale',
            'gateway_customer_id' => 'cus_stale',
            'brand' => 'Visa',
            'last_four' => '1111',
            'exp_month' => 12,
            'exp_year' => now()->year + 2,
            'is_default' => false,
        ]);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->assertOk()
            ->set('number', '4242424242424242')
            ->set('expMonth', '12')
            ->set('expYear', (string) (now()->year + 2))
            ->set('name', 'A Plumber')
            ->call('present')
            ->assertSee('Waiting on Stripe tokenisation:')
            ->call('makeDefault', $card->id)
            ->assertSee('Card set as default')
            ->assertDontSee('Waiting on Stripe tokenisation:');
    }
}
