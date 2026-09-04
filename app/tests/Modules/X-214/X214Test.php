<?php

declare(strict_types=1);

namespace Tests\Modules\X214;

use App\Modules\X214\Actions\SurchargeApplyAction;
use App\Modules\X214\Actions\SurchargeQuoteAction;
use App\Modules\X214\Domain\SurchargeEngine;
use App\Modules\X214\Events\SurchargeApplied;
use App\Modules\X214\Events\SurchargeDisclosed;
use App\Modules\X214\Models\SurchargePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X214Test extends TestCase
{
    private SurchargeEngine $engine;

    private SurchargeQuoteAction $quoteAction;

    private SurchargeApplyAction $applyAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SurchargeEngine;
        $this->quoteAction = new SurchargeQuoteAction($this->engine);
        $this->applyAction = new SurchargeApplyAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * debit is NEVER surcharged — BIN-asserted, unknown type treated as debit ·
     * no apply without a preceding surcharge.disclosed on the same transaction ·
     * the ceiling is the LOWER of 3% and the merchant's own effective rate; above it is REFUSED, not clamped.
     */
    public function test_anchor_debit_protection_preceding_disclosure_and_effective_rate_ceiling(): void
    {
        Event::fake([SurchargeDisclosed::class, SurchargeApplied::class]);

        $biz = TestCase::provisionTenant(['name' => 'Surcharge Compliance Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $policy = SurchargePolicy::create([
            'business_id' => $biz->id,
            'merchant_effective_rate_basis_points' => 240, // Merchant rate: 2.40% (ceiling is min(3.00%, 2.40%) = 2.40%)
            'is_enabled' => true,
        ]);

        $amountCents = 10000; // $100.00

        // 1. Debit is NEVER surcharged — BIN-asserted, unknown type treated as debit (TEST ANCHOR)
        $debitRes = $this->quoteAction->handle(
            businessId: $biz->id,
            transactionId: 'txn_debit_001',
            amountCents: $amountCents,
            cardType: 'debit'
        );
        $this->assertEquals(0, $debitRes['surcharge_cents'], 'Debit is never surcharged ($0)');
        $this->assertFalse($debitRes['is_surcharged']);

        // Unknown card type treated as debit -> $0 surcharge
        $unknownRes = $this->quoteAction->handle(
            businessId: $biz->id,
            transactionId: 'txn_unknown_002',
            amountCents: $amountCents,
            cardType: null, // Unknown card type
            bin: '400012'
        );
        $this->assertEquals(0, $unknownRes['surcharge_cents'], 'Unknown card type treated as debit ($0)');
        $this->assertFalse($unknownRes['is_surcharged']);

        // 2. Ceiling is LOWER of 3% and merchant's effective rate (240 bps). Above is REFUSED, not clamped (TEST ANCHOR)
        $excessiveQuote = $this->quoteAction->handle(
            businessId: $biz->id,
            transactionId: 'txn_excessive_003',
            amountCents: $amountCents,
            cardType: 'credit',
            requestedRateBps: 280 // 2.80% requested, but merchant ceiling is 2.40%
        );
        $this->assertEquals('refused', $excessiveQuote['status']);
        $this->assertEquals('RATE_EXCEEDS_LEGAL_CEILING', $excessiveQuote['refusal_code']);
        $this->assertEquals(240, $excessiveQuote['ceiling_bps']);

        // 3. Valid Quote with preceding disclosure turn (TEST ANCHOR)
        $validQuote = $this->quoteAction->handle(
            businessId: $biz->id,
            transactionId: 'txn_valid_004',
            amountCents: $amountCents,
            cardType: 'credit',
            requestedRateBps: 240 // Exact 2.40%
        );
        $this->assertEquals('quoted_and_disclosed', $validQuote['status']);
        $this->assertEquals(240, $validQuote['surcharge_cents']); // 2.40% of $100 = $2.40
        Event::assertDispatched(SurchargeDisclosed::class);

        // 4. No apply without a preceding surcharge.disclosed on the same transaction (TEST ANCHOR)
        $undisclosedApply = $this->applyAction->handle($biz->id, 'txn_never_quoted_999');
        $this->assertEquals('refused', $undisclosedApply['status']);
        $this->assertEquals('NO_PRECEDING_DISCLOSURE', $undisclosedApply['refusal_code']);
        $this->assertFalse($undisclosedApply['applied']);

        // Legitimate apply with preceding quote
        $legitApply = $this->applyAction->handle($biz->id, 'txn_valid_004');
        $this->assertEquals('applied', $legitApply['status']);
        $this->assertTrue($legitApply['applied']);
        $this->assertEquals(240, $legitApply['surcharge_cents']);
        Event::assertDispatched(SurchargeApplied::class);
    }

    /**
     * [N-214-01], [N-214-02]
     */
    public function test_n_214_capabilities(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
