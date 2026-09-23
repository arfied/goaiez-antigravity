<?php

declare(strict_types=1);

namespace Tests\Modules\X120;

use App\Modules\X120\Actions\CardExpiringScanAction;
use App\Modules\X120\Actions\CardPresentAction;
use App\Modules\X120\Actions\CardRotateAction;
use App\Modules\X120\Actions\CardStoreAction;
use App\Modules\X120\Events\CardExpiring;
use App\Modules\X120\Events\CardStored;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X120Test extends TestCase
{
    private CardStoreAction $storeAction;

    private CardExpiringScanAction $scanAction;

    private CardRotateAction $rotateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeAction = new CardStoreAction;
        $this->scanAction = new CardExpiringScanAction;
        $this->rotateAction = new CardRotateAction;
    }

    /**
     * TEST ANCHOR
     * grep -rEi 'pan|cvv|cvc|card_number' database/migrations/ returns nothing, enforced by CI;
     * a card expiring in 20 days produces exactly one alert, not a sequence
     */
    public function test_anchor_expiring_card_produces_exactly_one_alert(): void
    {
        Event::fake([CardStored::class, CardExpiring::class]);

        $biz = TestCase::provisionTenant(['name' => 'Card Token Vault Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Current simulated date: Jan 11, 2026
        $simulatedNow = Carbon::parse('2026-01-11');

        // Card expires Jan 31, 2026 (20 days remaining)
        $token = $this->storeAction->store(
            businessId: $biz->id,
            gatewayCustomerId: 'cus_stripe_8849',
            gatewayPaymentMethodId: 'pm_card_visa_4242',
            brand: 'visa',
            lastFour: '4242',
            expMonth: 1,
            expYear: 2026,
            isDefault: true
        );

        $this->assertNotNull($token->id);
        $this->assertFalse($token->alert_sent);
        Event::assertDispatched(CardStored::class);

        // 1. First scan: 20 days remaining -> produces EXACTLY ONE alert (TEST ANCHOR)
        $scan1 = $this->scanAction->scan($biz->id, $simulatedNow);
        $this->assertEquals(1, $scan1['alerted_count']);
        $this->assertEquals(20, $scan1['alerted_cards'][0]['days_remaining']);

        Event::assertDispatched(CardExpiring::class, 1);

        $token->refresh();
        $this->assertTrue($token->alert_sent);

        // 2. Second scan (e.g. next day, 19 days remaining) -> produces ZERO additional alerts (not a sequence: TEST ANCHOR)
        $scan2 = $this->scanAction->scan($biz->id, $simulatedNow->copy()->addDay());
        $this->assertEquals(0, $scan2['alerted_count'], 'A card expiring in 20 days produces exactly one alert, not a sequence');

        // Assert no new event was dispatched on repeat scan
        Event::assertDispatched(CardExpiring::class, 1);

        // 3. Card rotation
        $token2 = $this->storeAction->store(
            businessId: $biz->id,
            gatewayCustomerId: 'cus_stripe_8849',
            gatewayPaymentMethodId: 'pm_card_mastercard_5555',
            brand: 'mastercard',
            lastFour: '5555',
            expMonth: 12,
            expYear: 2028,
            isDefault: false
        );

        $rotated = $this->rotateAction->rotateDefault($biz->id, $token2->id);
        $this->assertTrue($rotated->is_default);

        $token->refresh();
        $this->assertFalse($token->is_default);
    }

    /**
     * [N-120-01], [N-120-02]
     */
    public function test_card_present_action_derives_brand(): void
    {
        $action = new CardPresentAction;

        $visa = $action->handle('4242424242424242', 12, 2030, 'Test User');
        $this->assertEquals('visa', $visa['brand']);

        $mastercard = $action->handle('5555555555554444', 12, 2030, 'Test User');
        $this->assertEquals('mastercard', $mastercard['brand']);

        $amex = $action->handle('378282246310005', 12, 2030, 'Test User');
        $this->assertEquals('amex', $amex['brand']);

        $discover = $action->handle('6011111111111117', 12, 2030, 'Test User');
        $this->assertEquals('card', $discover['brand']);
    }

    public function test_card_present_action_names_only_the_brand_the_digits_identify(): void
    {
        $action = new CardPresentAction;

        $jcb = $action->handle('3530111333300000', 12, 2030, 'Test User');
        $this->assertEquals('card', $jcb['brand'], 'A JCB card starts 35 and is not an Amex; a leading 3 identifies no issuer.');

        $mastercard2 = $action->handle('2223003122003222', 12, 2030, 'Test User');
        $this->assertEquals('mastercard', $mastercard2['brand'], 'Mastercard has been 2221-2720 since 2016.');
    }
}
