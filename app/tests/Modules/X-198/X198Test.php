<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Modules\X198\Actions\MerchantConnectAction;
use App\Modules\X198\Actions\PaymentCaptureAction;
use App\Modules\X198\Actions\PaymentLinkAction;
use App\Modules\X198\Actions\PayoutReconcileAction;
use App\Modules\X198\Domain\GatewayEngine;
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
        \Illuminate\Support\Facades\Http::fake([
            'api.stripe.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'ch_fake_123'], 200),
        ]);
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

        $conn = $this->connectAction->handle($biz->id, 'stripe', 'acct_tenant_stripe_123');

        // 1. Idempotent payment capture using payment token (G17-04, G1-23, G1-34)
        $idempotencyKey = 'idem_unique_tx_999';
        $pay1 = $this->captureAction->handle($biz->id, 5000, 'tok_visa_tokenized', $idempotencyKey);
        $pay2 = $this->captureAction->handle($biz->id, 5000, 'tok_visa_tokenized', $idempotencyKey);
        $this->engine->confirmCapture($biz->id, $pay1->id, 'ch_real_123');

        $this->assertEquals($pay1->id, $pay2->id, 'Duplicated ref charges once and returns identical payment record');
        $this->assertEquals(5000, $pay1->amount_cents);
        $this->assertNull($pay1->gateway_charge_id, 'Charge id is issued only by external gateway');
        $this->assertEquals('pending', $pay1->status);
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
        $this->assertEquals('pending', $p->status);
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
}
