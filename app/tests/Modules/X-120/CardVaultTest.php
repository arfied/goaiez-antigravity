<?php

namespace Tests\Modules\X120;

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
        $action = new \App\Modules\X120\Actions\CardPresentAction();
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
        \Illuminate\Support\Facades\Event::fake([\App\Modules\X120\Events\CardExpiring::class]);
        
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Card Expiry Tenant', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $now = \Carbon\Carbon::now()->endOfMonth()->subDays(20);
        $expDate = $now->copy();
        
        $card = \App\Modules\X120\Models\CardToken::create([
            'business_id' => $biz->id,
            'gateway_customer_id' => 'cus_123',
            'gateway_payment_method_id' => 'tok_123',
            'brand' => 'visa',
            'last_four' => '4242',
            'exp_month' => $expDate->month,
            'exp_year' => $expDate->year,
            'alert_sent' => false
        ]);

        $action = new \App\Modules\X120\Actions\CardExpiringScanAction();
        // First scan sends the alert
        $res1 = $action->scan($biz->id, $now);
        $this->assertEquals(1, $res1['alerted_count']);
        
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Modules\X120\Events\CardExpiring::class);
        
        // Second scan sends NO alert (exactly one alert rule)
        $res2 = $action->scan($biz->id, $now);
        $this->assertEquals(0, $res2['alerted_count']);
    }
}
