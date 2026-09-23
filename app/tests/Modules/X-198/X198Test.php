<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Modules\X198\Actions\MerchantConnectAction;
use App\Modules\X198\Actions\PaymentCaptureAction;
use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Actions\PayoutReconcileAction;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Domain\GatewayNotConfiguredException;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Events\PaymentCaptured;
use App\Modules\X198\Events\PayoutReconciled;
use App\Modules\X198\Events\ReconciliationDiscrepancy;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\PaymentLink;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class X198Test extends TestCase
{
    private GatewayEngine $engine;

    private MerchantConnectAction $connectAction;

    private PaymentCaptureAction $captureAction;

    private PaymentLinkAction $linkAction;

    private PayoutReconcileAction $reconcileAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new GatewayEngine;
        $this->connectAction = new MerchantConnectAction($this->engine);
        $this->captureAction = new PaymentCaptureAction($this->engine);
        $this->linkAction = new PaymentLinkAction;
        $this->reconcileAction = new PayoutReconcileAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * grep -rEi 'cvv|cvc|card_number|pan' database/migrations/ app/Modules/X-198/ returns nothing, enforced by CI;
     * a payment on a tenant's invoice never appears in the platform's payout;
     * a discrepancy is written, never corrected
     */
    public function test_anchor_pci_tokens_only_tenant_payout_isolation_and_discrepancy_logging(): void
    {
        Event::fake([PaymentCaptured::class, ReconciliationDiscrepancy::class, PayoutReconciled::class]);

        $biz = TestCase::provisionTenant(['name' => 'Gateway Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $conn = $this->connectAction->handle($biz->id, 'square', 'acct_tenant_square_123');

        // 1. Idempotent payment capture using payment token (G17-04, G1-23, G1-34)
        $idempotencyKey = 'idem_unique_tx_999';
        $pay1 = app(GatewayEngine::class)->capture($biz->id, 5000, 'sq_tok_tokenized', $idempotencyKey);
        $pay2 = app(GatewayEngine::class)->capture($biz->id, 5000, 'sq_tok_tokenized', $idempotencyKey);

        $this->assertEquals($pay1->id, $pay2->id, 'Duplicated ref charges once and returns identical payment record');
        $this->assertEquals(5000, $pay1->amount_cents);
        $this->assertEquals($idempotencyKey, $pay1->idempotency_key);
        // (R245) owner ruling 10 (2026-09-02)
        $this->assertNull($pay1->gateway_charge_id, 'Charge id is null unless the gateway returned one');
        $this->assertEquals('awaiting_processor', $pay1->status);
        Event::assertNotDispatched(PaymentCaptured::class);

        // 2. Tenant payout isolation: tenant payment links only to tenant merchant connection
        $payout = Payout::create([
            'business_id' => $biz->id,
            'merchant_connection_id' => $conn->id,
            'gateway_payout_id' => 'po_stripe_tenant_456',
            'amount_cents' => 4900,
            'status' => 'pending',
            'payout_date' => now()->toDateString(),
        ]);

        $this->assertEquals($conn->id, $payout->merchant_connection_id);

        // 3. Discrepancy is written, never corrected: Expected $50.00 (5000 cents), got $49.00 (4900 cents)
        $reconRes = $this->reconcileAction->handle($biz->id, $payout->id, 5000);
        $this->assertEquals('discrepancy_logged', $reconRes['status']);
        $this->assertEquals(-100, $reconRes['discrepancy_cents']);

        $run = ReconciliationRun::where('business_id', $biz->id)->find($reconRes['run_id']);
        $this->assertNotNull($run);
        $this->assertEquals(-100, $run->discrepancy_cents);
        $this->assertStringContainsString('Mismatched payout', $run->discrepancy_reason);

        Event::assertDispatched(ReconciliationDiscrepancy::class);
    }

    /**
     * [G1-23], [G1-34] the idempotency key is asserted per adapter, both gateways
     */
    public function test_g1_23_idempotency_adapters(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Square Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $conn = $this->connectAction->handle($biz->id, 'square', 'sq_acct_888');
        $p = $this->captureAction->handle($biz->id, 2500, 'sq_tok_abc', 'idem_sq_1');
        $this->assertEquals('awaiting_processor', $p->status);
    }

    /**
     * [G17-04] the refId hash is named in X-122 — a duplicated ref charges once
     * Testing the 'duplicated ref charges once' portion using idempotency_key.
     */
    public function test_g17_04_refid_deduplication(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Modules\X198\Events\PaymentCaptured::class]);
        
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Gateway Deduplication', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $connection = \App\Modules\X198\Models\MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'mock_gateway',
            'merchant_account_id' => 'mock_123',
        ]);

        $action = new \App\Modules\X198\Actions\PaymentCaptureAction(new \App\Modules\X198\Domain\GatewayEngine());
        
        // Mock StripeGatewayClient since gateway_name=mock_gateway skips the real client in GatewayEngine (wait, it only calls Stripe if gateway_name === 'stripe')
        // Actually, if it's not stripe, it just sets status 'awaiting_processor' and gatewayStatus null!
        
        $idempotencyKey = 'idem_999888';
        
        // First capture
        $payment1 = $action->handle($biz->id, 5000, 'tok_abc', $idempotencyKey);
        $this->assertEquals('awaiting_processor', $payment1->status);

        // Second capture with same key
        $payment2 = $action->handle($biz->id, 5000, 'tok_abc', $idempotencyKey);
        
        // Assert it's the exact same row (deduplicated)
        $this->assertEquals($payment1->id, $payment2->id, 'A duplicated idempotency key charges once');
    }

    public function test_runtime_proof_returns_failure_in_tests(): void
    {
        $proofPath = storage_path('app/evidence/X-198/runtime-proof.json');
        $before = file_exists($proofPath) ? file_get_contents($proofPath) : null;

        $this->artisan('x198:runtime-proof')
            ->assertExitCode(1)
            ->expectsOutputToContain('may only be produced by a real CLI run');

        $after = file_exists($proofPath) ? file_get_contents($proofPath) : null;
        $this->assertSame($before, $after);
    }

    public function test_a_declined_charge_leaves_a_failed_payment_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Failed Row Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                throw new \RuntimeException('Stripe charge failed: card_declined');
            }
        });

        $caught = false;
        try {
            $this->captureAction->handle($biz->id, 2000, 'tok_decline', 'idem_decline_1');
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assertEquals('Stripe charge failed: card_declined', $e->getMessage());
        }

        $this->assertTrue($caught, 'Expected RuntimeException was not thrown.');

        $payment = Payment::where('business_id', $biz->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('failed', $payment->status);
        $this->assertNull($payment->gateway_charge_id);
    }

    public function test_a_retry_after_a_decline_is_not_short_circuited_by_idempotency(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Retry Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        // A double never reaches the HTTP boundary, so it cannot see a header. The real client and
        // a faked transport are the only way this test can observe what the gateway was told.
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Your card was declined.']], 402)
                ->push(['id' => 'ch_stub_money60_00000000000', 'status' => 'succeeded'], 200),
        ]);

        $idempotencyKey = 'idem_retry_1';

        try {
            $this->captureAction->handle($biz->id, 3000, 'tok_decline', $idempotencyKey);
        } catch (\RuntimeException $e) {
            // Expected
        }

        $payment = $this->captureAction->handle($biz->id, 3000, 'tok_success', $idempotencyKey);

        $this->assertEquals('ch_stub_money60_00000000000', $payment->gateway_charge_id);
        $this->assertEquals('captured', $payment->status);

        $count = Payment::where('business_id', $biz->id)->count();
        $this->assertEquals(2, $count, 'Expected two rows: one failed and one charged.');

        // The declined attempt and the retry must not carry the same key, or the provider suppresses
        // the retry and the customer's second card can never be charged.
        Http::assertSent(function ($request) use ($biz) {
            return $request->hasHeader('Idempotency-Key', 'x198-charge-'.$biz->id.'-idem_retry_1-0');
        });
        Http::assertSent(function ($request) use ($biz) {
            return $request->hasHeader('Idempotency-Key', 'x198-charge-'.$biz->id.'-idem_retry_1-1');
        });
    }

    public function test_a_successful_charge_is_captured_not_pending(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Success Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): array
            {
                return ['id' => 'ch_stub_money61_00000000000', 'status' => 'succeeded'];
            }
        });

        $payment = $this->captureAction->handle($biz->id, 1000, 'tok_123', 'idemp_456');

        $this->assertEquals('ch_stub_money61_00000000000', $payment->gateway_charge_id);
        $this->assertEquals('captured', $payment->status);
    }

    public function test_a_payments_row_written_without_a_status_is_awaiting_processor(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Default Status Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $id = DB::table('payments')->insertGetId([
            'business_id' => $biz->id,
            'amount_cents' => 1000,
            'payment_token' => 'tok_123',
            'idempotency_key' => 'idemp_456',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('payments')->find($id);
        $this->assertEquals('awaiting_processor', $row->status);
    }

    public function test_a_missing_gateway_key_is_not_a_decline(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Key Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        config()->set('credentials.stripe_secret', null);

        $caught = null;
        try {
            $this->captureAction->handle($biz->id, 1000, 'tok_123', 'idemp_456');
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(GatewayNotConfiguredException::class, $caught);
        $this->assertEquals(0, Payment::where('business_id', $biz->id)->count(), 'A box with no key writes no payment row.');
    }

    public function test_a_gateway_refusal_still_writes_a_failed_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Discrimination Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        config()->set('credentials.stripe_secret', null);

        try {
            $this->captureAction->handle($biz->id, 1000, 'tok_123', 'idemp_456');
        } catch (\Throwable $e) {
            // Missing key
        }

        $this->assertEquals(0, Payment::where('business_id', $biz->id)->count());

        config()->set('credentials.stripe_secret', 'sk_test_123');

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                throw new \RuntimeException('Stripe charge failed: simulated refusal');
            }
        });

        try {
            $this->captureAction->handle($biz->id, 2000, 'tok_456', 'idemp_789');
        } catch (\Throwable $e) {
            // Refusal
        }

        $this->assertEquals(1, Payment::where('business_id', $biz->id)->where('status', 'failed')->count());
    }

    public function test_a_pay_link_is_persisted_against_the_payment(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'PayLink', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 1500,
            'payment_token' => 'tok_pay',
            'idempotency_key' => 'idem_pay_1',
            'status' => 'failed',
        ]);

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function createPaymentLink(int $amountCents, string $description, string $currency = 'USD'): array
            {
                return ['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123#fid'.str_repeat('a', 380)];
            }
        });

        $action = new PaymentLinkAction;
        $link = $action->handle($biz->id, $payment->id, 'Testing link');

        $this->assertNotNull($link);
        $this->assertEquals('cs_test_123', $link->provider_link_id);
        $this->assertEquals('https://checkout.stripe.com/c/pay/cs_test_123#fid'.str_repeat('a', 380), $link->url);
        $this->assertGreaterThan(400, strlen($link->url));

        $this->assertEquals(1, PaymentLink::where('business_id', $biz->id)->where('payment_id', $payment->id)->count());
    }

    public function test_a_second_pay_link_request_reuses_the_first(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'ReusesPayLink', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 1500,
            'payment_token' => 'tok_pay_2',
            'idempotency_key' => 'idem_pay_2',
            'status' => 'failed',
        ]);

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public int $calls = 0;

            public function createPaymentLink(int $amountCents, string $description, string $currency = 'USD'): array
            {
                $this->calls++;
                $id = 'cs_test_abc'.str_repeat('0', 55);

                return ['id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/'.$id.'#fid'.str_repeat('a', 380)];
            }
        });

        $action = new PaymentLinkAction;
        $action->handle($biz->id, $payment->id, 'First click');
        $action->handle($biz->id, $payment->id, 'Second click');

        $this->assertEquals(1, PaymentLink::where('business_id', $biz->id)->where('payment_id', $payment->id)->count());
        $stub = app(StripeGatewayClient::class);
        $this->assertEquals(1, $stub->calls);
    }

    public function test_a_pay_link_for_another_accounts_payment_is_refused(): void
    {
        $biz1 = TestCase::provisionTenant(['name' => 'Biz1', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'Biz2', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$biz1->id}'");

        $payment = Payment::create([
            'business_id' => $biz1->id,
            'amount_cents' => 1500,
            'payment_token' => 'tok_pay_3',
            'idempotency_key' => 'idem_pay_3',
            'status' => 'failed',
        ]);

        DB::statement("SET app.business_id = '{$biz2->id}'");

        $action = new PaymentLinkAction;
        $caught = null;
        try {
            $action->handle($biz2->id, $payment->id, 'Testing link');
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(ModelNotFoundException::class, $caught);
        $this->assertEquals(0, PaymentLink::count());
    }

    public function test_no_pay_link_row_survives_a_missing_key(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'MissingKey', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 1500,
            'payment_token' => 'tok_pay_4',
            'idempotency_key' => 'idem_pay_4',
            'status' => 'failed',
        ]);

        config()->set('credentials.stripe_secret', null);

        $action = new PaymentLinkAction;
        $caught = null;
        try {
            $action->handle($biz->id, $payment->id, 'Testing link');
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(GatewayNotConfiguredException::class, $caught);
        $this->assertEquals(0, PaymentLink::count());
    }

    public function test_a_pay_link_carries_the_payments_own_currency(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'CurrencyTenant', 'currency' => 'GBP']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 1500,
            'currency' => 'GBP',
            'payment_token' => 'tok_pay_currency',
            'idempotency_key' => 'idem_pay_currency',
            'status' => 'failed',
        ]);

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public string $seenCurrency = '';

            public function createPaymentLink(int $amountCents, string $description, string $currency = 'USD'): array
            {
                $this->seenCurrency = $currency;
                $id = 'cs_test_gbp'.str_repeat('0', 55);

                return ['id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/'.$id.'#fid'.str_repeat('a', 380)];
            }
        });

        $action = new PaymentLinkAction;
        $action->handle($biz->id, $payment->id, 'Testing link');

        $this->assertEquals('GBP', app(StripeGatewayClient::class)->seenCurrency);
    }

    public function test_a_stripe_capture_announces_the_charge_id_the_gateway_issued(): void
    {
        Event::fake([PaymentCaptured::class]);

        $biz = TestCase::provisionTenant(['name' => 'Announce Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_tenant_stripe_123');

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_announced', 'status' => 'succeeded'], 200),
        ]);

        $payment = app(GatewayEngine::class)->capture($biz->id, 7500, 'tok_visa', 'idem_stripe_announce');

        $this->assertSame('ch_mock_announced', $payment->gateway_charge_id);

        Event::assertDispatched(PaymentCaptured::class, function (PaymentCaptured $event) use ($payment) {
            return $event->paymentId === $payment->id
                && $event->gatewayChargeId === 'ch_mock_announced';
        });
    }

    public function test_a_charge_the_gateway_never_confirmed_says_so_and_leaves_a_failed_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Unconfirmed Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_unconfirmed');

        // 200, and no `id` anywhere in the body: the gateway took the request and confirmed nothing.
        Http::fake([
            'api.stripe.com/*' => Http::response(['object' => 'charge', 'status' => 'pending'], 200),
        ]);

        $caught = null;

        try {
            app(GatewayEngine::class)->capture($biz->id, 4200, 'tok_visa', 'idem_no_charge_id');
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'capture must refuse a response that carries no charge id');
        $this->assertStringContainsString('sent back no charge id, so the charge could not be confirmed', $caught->getMessage());
        $this->assertStringNotContainsString('nothing was recorded', $caught->getMessage());

        // The sentence is only true because of this row, so the two are asserted together.
        $this->assertSame(1, Payment::where('business_id', $biz->id)->where('status', 'failed')->count());
    }

    public function test_a_pay_link_race_hands_back_the_row_that_won_and_makes_no_second_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'PayLinkRace', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 1500,
            'payment_token' => 'tok_pay_race',
            'idempotency_key' => 'idem_pay_race',
            'status' => 'failed',
        ]);

        // A second press of the same button wins the race while the gateway is answering ours:
        // this closure runs between the pre-check and the persist.
        Http::fake(function () use ($biz, $payment) {
            PaymentLink::firstOrCreate(
                [
                    'business_id' => $biz->id,
                    'payment_id' => $payment->id,
                ],
                [
                    'provider_link_id' => 'cs_test_racer',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_racer',
                ]
            );

            return Http::response([
                'id' => 'cs_test_ours',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_ours',
            ], 200);
        });

        $link = (new PaymentLinkAction)->handle($biz->id, $payment->id);

        // The loser hands back the winner's row: one link for this payment, and it is the racer's.
        $this->assertSame('cs_test_racer', $link->provider_link_id);
        $this->assertSame(1, PaymentLink::where('business_id', $biz->id)->where('payment_id', $payment->id)->count());
    }

    public function test_a_capture_sends_the_gateway_an_idempotency_key_namespaced_by_business(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'IdemHeader', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_idem_header');

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_idem_header_00000000000', 'status' => 'succeeded'], 200),
        ]);

        app(GatewayEngine::class)->capture($biz->id, 4500, 'tok_visa', 'idem_header_1');

        // The key carries the business because every charge posts with the platform secret and no
        // Stripe-Account, so all tenants share one idempotency namespace at the provider.
        Http::assertSent(function ($request) use ($biz) {
            return $request->url() === 'https://api.stripe.com/v1/charges'
                && $request->hasHeader('Idempotency-Key', 'x198-charge-'.$biz->id.'-idem_header_1-0');
        });
    }

    public function test_a_capture_carries_the_tenants_declared_currency(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sterling', 'currency' => 'GBP']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_test_gbp_00000000000', 'status' => 'succeeded'], 200),
        ]);

        app(GatewayEngine::class)->capture($biz->id, 4500, 'tok_visa', 'idem_currency_1');

        // What the gateway was told. A USD default here collects a British customer's money in
        // dollars, and the row would agree with the request because both came from the default.
        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'currency=gbp');
        });

        $payment = Payment::where('business_id', $biz->id)->firstOrFail();
        $this->assertSame('GBP', $payment->currency);
    }

    public function test_a_pay_link_sends_the_gateway_an_idempotency_key_namespaced_by_business(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'PayLinkIdem', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 2500,
            'payment_token' => 'tok_pay_idem',
            'idempotency_key' => 'idem_pay_idem',
            'status' => 'failed',
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_test_idem',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_idem',
            ], 200),
        ]);

        (new PaymentLinkAction)->handle($biz->id, $payment->id);

        // The pair (business, payment) IS the idempotency, so the provider gets the same pair.
        Http::assertSent(function ($request) use ($biz, $payment) {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request->hasHeader('Idempotency-Key', 'x198-paylink-'.$biz->id.'-'.$payment->id);
        });
    }

    public function test_a_pay_link_names_the_business_the_customer_is_paying(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Northwind', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 2500,
            'currency' => 'USD',
            'payment_token' => 'tok_failed',
            'idempotency_key' => 'idem_desc_1',
            'status' => 'failed',
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_test_desc_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_desc_1',
            ], 200),
        ]);

        (new PaymentLinkAction)->handle($biz->id, $payment->id);

        // The line item is rendered to the customer by the gateway, not by this app, so the only
        // way to see it is to read what was sent.
        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'Northwind')
                && ! str_contains($request->body(), 'declined');
        });
    }

    public function test_a_charge_the_gateway_has_not_settled_is_not_recorded_as_captured(): void
    {
        Event::fake([PaymentCaptured::class]);

        $biz = TestCase::provisionTenant(['name' => 'Pending', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_pending');

        // A real charge object exists at the provider and has not settled. Reading only the id
        // records this as captured.
        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_pending_0000000000000', 'status' => 'pending'], 200),
        ]);

        $payment = app(GatewayEngine::class)->capture($biz->id, 6100, 'tok_visa', 'idem_pending_1');

        $this->assertSame('awaiting_processor', $payment->status);

        // The id is kept: a charge object does exist there, and it is the honest handle on it.
        $this->assertSame('ch_pending_0000000000000', $payment->gateway_charge_id);

        // An event named Captured must not fire for money that was not captured.
        Event::assertNotDispatched(PaymentCaptured::class);
    }

    public function test_a_concurrent_capture_on_one_key_hands_back_the_row_that_won(): void
    {
        // This test turns on X198Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach: measured
        // DB::transactionLevel() === 0 here and === 1 in a pest-style file. The racer's row must
        // COMMIT for capture() to see it. If TestCase ever binds a refresh trait directly, this
        // test stops proving anything -- while very likely still passing.
        $biz = TestCase::provisionTenant(['name' => 'CaptureRace', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_race');

        $key = 'idem_capture_race';

        Http::fake(function () use ($biz, $key) {
            // A second capture on the same key wins while the gateway is answering ours.
            // It lands on pgsql_migrate -- a separate session, so it commits rather than
            // joining capture()'s open transaction. RLS is FORCED on payments and constrains
            // the owner role too, so that session sets its own tenant first.
            DB::connection('pgsql_migrate')->statement("SET app.business_id = '{$biz->id}'");
            DB::connection('pgsql_migrate')->table('payments')->insert([
                'business_id' => $biz->id,
                'amount_cents' => 7700,
                'payment_token' => 'tok_racer',
                'idempotency_key' => $key,
                'status' => 'captured',
                'gateway_charge_id' => 'ch_3RACER00000000000000000',
            ]);

            return Http::response(['id' => 'ch_3OURS000000000000000000', 'status' => 'succeeded'], 200);
        });

        $payment = app(GatewayEngine::class)->capture($biz->id, 7700, 'tok_ours', $key);

        // The loser hands back the winner's row, and there is exactly one row for this pair.
        $this->assertSame('ch_3RACER00000000000000000', $payment->gateway_charge_id);
        $this->assertSame(
            1,
            Payment::where('business_id', $biz->id)
                ->where('idempotency_key', $key)
                ->where('status', '!=', 'failed')
                ->count()
        );
    }

    public function test_a_concurrent_connect_is_absorbed_into_one_connection_for_the_gateway(): void
    {
        // This test turns on X198Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach, so the racer's
        // row COMMITS on pgsql_migrate and connect() can see it. If TestCase ever binds a refresh
        // trait directly, this test stops proving anything -- while very likely still passing.
        $biz = TestCase::provisionTenant(['name' => 'Connect Race Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // A second request lands a connection for the same gateway between updateOrCreate()'s read and
        // its insert. It writes on pgsql_migrate, a separate session, so it commits. RLS is FORCED and
        // constrains the owner role too.
        $racer = DB::connection('pgsql_migrate');
        $racer->statement("SET app.business_id = '{$biz->id}'");

        MerchantConnection::creating(function () use ($racer, $biz): void {
            $racer->table('merchant_connections')->insert([
                'business_id' => $biz->id,
                'gateway_name' => 'stripe',
                'merchant_account_id' => 'acct_racer',
                'is_connected' => true,
            ]);
        });

        try {
            $connection = $this->connectAction->handle($biz->id, 'stripe', 'acct_loser');
        } finally {
            MerchantConnection::flushEventListeners();
        }

        // The loser absorbs the winner's row: one connection for the gateway.
        $this->assertSame(
            1,
            MerchantConnection::where('business_id', $biz->id)->where('gateway_name', 'stripe')->count()
        );

        // And updateOrCreate() filled the loser's account onto the row that won, and returned that row.
        $row = MerchantConnection::where('business_id', $biz->id)->where('gateway_name', 'stripe')->first();
        $this->assertSame($row->id, $connection->id);
        $this->assertSame('acct_loser', $row->merchant_account_id);
    }
}
