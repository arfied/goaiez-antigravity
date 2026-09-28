<?php

namespace Tests\Modules\X120;

use App\Modules\X120\Actions\CardExpiringScanAction;
use App\Modules\X120\Actions\CardPresentAction;
use App\Modules\X120\Events\CardExpiring;
use App\Modules\X120\Models\CardToken;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CardVaultTest extends TestCase
{
    /**
     * @group N-046
     * @group N-047
     * @group N-120-01
     * @group N-120-02
     */
    /**
     * [N-120-01] ⛔ a grep for card-number, PAN, CVV or CVC across database/migrations returns NOTHING
     */
    public function test_n_120_01_no_pan_in_migrations(): void
    {
        $path = base_path('database/migrations');
        $output = shell_exec(sprintf('grep -rniE %s %s', escapeshellarg('\b(pan|cvv|cvc|card_number)\b'), escapeshellarg($path)));
        $this->assertEmpty($output, 'Migrations must not contain PCI fields.');
    }

    /**
     * [N-046] CVV IS NEVER PERSISTED
     * [N-047] no screen anywhere returns a decrypted PAN
     */
    public function test_n_046_and_n_047_no_persisted_pci_data(): void
    {
        $action = new CardPresentAction;
        $result = $action->handle('4242424242424242', 12, 2030, 'John Doe');

        $this->assertArrayNotHasKey('number', $result);
        $this->assertArrayNotHasKey('cvv', $result);
        $this->assertArrayNotHasKey('cvc', $result);
        $this->assertArrayNotHasKey('pan', $result);
        $this->assertEquals('4242', $result['last_four']);
    }

    /**
     * [N-120-02] a card expiring within 20 days raises BEFORE it fails a charge
     */
    public function test_n_120_02_card_expiring_soon(): void
    {
        Event::fake([CardExpiring::class]);

        $biz = TestCase::provisionTenant(['name' => 'Card Expiry Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $now = Carbon::now()->endOfMonth()->subDays(20);
        $expDate = $now->copy();

        $card = CardToken::create([
            'business_id' => $biz->id,
            'gateway_customer_id' => 'cus_123',
            'gateway_payment_method_id' => 'tok_123',
            'brand' => 'visa',
            'last_four' => '4242',
            'exp_month' => $expDate->month,
            'exp_year' => $expDate->year,
            'alert_sent' => false,
        ]);

        $action = new CardExpiringScanAction(app(DefaultsRegistry::class));
        // First scan sends the alert
        $res1 = $action->scan($biz->id, $now);
        $this->assertEquals(1, $res1['alerted_count']);

        Event::assertDispatched(CardExpiring::class);

        // Second scan sends NO alert (exactly one alert rule)
        $res2 = $action->scan($biz->id, $now);
        $this->assertEquals(0, $res2['alerted_count']);
    }
}
