<?php

declare(strict_types=1);

namespace Tests\Modules\X120;

use App\Models\User;
use App\Modules\X120\Models\CardToken;
use App\Modules\X120\Ui\CardScreen;
use App\Support\Tenancy;
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

    public function test_add_card_shows_waiting_state(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(CardScreen::class)
            ->call('addCard')
            ->assertSee('waiting on Stripe tokenisation');
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
            ->assertSee('waiting on Stripe tokenisation')
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
            ->assertDontSee('4242 4242 4242 4242')
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
            ->assertDontSee('4242424242424242');

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
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-06 12:00:00'));
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

        \Carbon\Carbon::setTestNow();
    }
}
