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
            ->assertSee('Card not found in this account');
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
}
