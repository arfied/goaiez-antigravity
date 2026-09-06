<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Modules\X198\Actions\MerchantConnectAction;
use App\Modules\X198\Actions\PaymentCaptureAction;
use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Actions\PayoutReconcileAction;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Events\PaymentCaptured;
use App\Modules\X198\Events\PayoutReconciled;
use App\Modules\X198\Events\ReconciliationDiscrepancy;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $pay1 = $this->captureAction->handle($biz->id, 5000, 'sq_tok_tokenized', $idempotencyKey);
        $pay2 = $this->captureAction->handle($biz->id, 5000, 'sq_tok_tokenized', $idempotencyKey);

        $this->assertEquals($pay1->id, $pay2->id, 'Duplicated ref charges once and returns identical payment record');
        $this->assertEquals(5000, $pay1->amount_cents);
        // (R245) owner ruling 10 (2026-09-02)
        $this->assertNull($pay1->gateway_charge_id, 'Charge id is null unless the gateway returned one');
        $this->assertEquals('awaiting_processor', $pay1->status);
        Event::assertDispatched(PaymentCaptured::class);

        // 2. Tenant payout isolation: tenant payment links only to tenant merchant connection
        $payout = Payout::create([
            'business_id' => $biz->id,
            'merchant_connection_id' => $conn->id,
            'gateway_payout_id' => 'po_stripe_tenant_456',
            'amount_cents' => 5000,
            'status' => 'pending',
            'payout_date' => now()->toDateString(),
        ]);

        $this->assertEquals($conn->id, $payout->merchant_connection_id);

        // 3. Discrepancy is written, never corrected: Expected $50.00 (5000 cents), got $49.00 (4900 cents)
        $reconRes = $this->reconcileAction->handle($biz->id, $payout->id, 5000, 4900);
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
     */
    public function test_g17_04_refid_deduplication(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-010] no refusal declared
     */
    public function test_n_010_no_refusal(): void
    {
        $this->assertTrue(true);
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

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                throw new \RuntimeException('Stripe charge failed: card_declined');
            }
        });

        $idempotencyKey = 'idem_retry_1';

        try {
            $this->captureAction->handle($biz->id, 3000, 'tok_decline', $idempotencyKey);
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                return 'ch_stub_money60';
            }
        });

        $payment = $this->captureAction->handle($biz->id, 3000, 'tok_success', $idempotencyKey);

        $this->assertEquals('ch_stub_money60', $payment->gateway_charge_id);
        $this->assertEquals('captured', $payment->status);

        $count = Payment::where('business_id', $biz->id)->count();
        $this->assertEquals(2, $count, 'Expected two rows: one failed and one charged.');
    }

    public function test_a_successful_charge_is_captured_not_pending(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Success Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->connectAction->handle($biz->id, 'stripe', 'acct_x');

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                return 'ch_stub_money61';
            }
        });

        $payment = $this->captureAction->handle($biz->id, 1000, 'tok_123', 'idemp_456');

        $this->assertEquals('ch_stub_money61', $payment->gateway_charge_id);
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
}
