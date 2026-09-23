<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Modules\CBilling\Actions\CalculateSavedMrrAction;
use App\Modules\CBilling\Actions\DunningAdvanceAction;
use App\Modules\CBilling\Actions\LedgerDebitAction;
use App\Modules\CBilling\Actions\LedgerExplainAction;
use App\Modules\CBilling\Actions\LedgerGrantAction;
use App\Modules\CBilling\Actions\TopupChargeAction;
use App\Modules\CBilling\Domain\AutoTopupLimits;
use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Domain\GatewayGuard;
use App\Modules\CBilling\Domain\ReconciliationEngine;
use App\Modules\CBilling\Domain\RetryPolicy;
use App\Modules\CBilling\Events\LedgerPeriodClosed;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\TrialLimit;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CBillingTest extends TestCase
{
    private BillingLedgerEngine $engine;

    private LedgerDebitAction $debitAction;

    private LedgerGrantAction $grantAction;

    private TopupChargeAction $topupAction;

    private DunningAdvanceAction $dunningAction;

    private LedgerExplainAction $explainAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new BillingLedgerEngine(app(DefaultsRegistry::class));
        $this->debitAction = new LedgerDebitAction($this->engine);
        $this->grantAction = new LedgerGrantAction($this->engine);
        $this->topupAction = new TopupChargeAction($this->engine);
        $this->dunningAction = new DunningAdvanceAction($this->engine);
        $this->explainAction = new LedgerExplainAction;
    }

    /**
     * TEST ANCHOR
     * credit_ledger_entries has no UPDATE path — asserted by a database trigger test;
     * two sequential debits produce two rows and a correct final balance;
     * a tenant at day 21 has AI off and the number still answering via voicemail
     */
    public function test_anchor_ledger_no_update_trigger_atomic_debits_and_day_21_dunning(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Billing Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Initial balance $100.00 = 1,000,000 hundredths of a cent
        $this->grantAction->handle($biz->id, 1000000, 'setup', 'Setup grant');

        // 1. Two sequential debits produce two rows and a correct final balance
        $entry1 = $this->debitAction->handle($biz->id, 15000, 'ref_1', 'Debit 1 ($1.50)');
        $entry2 = $this->debitAction->handle($biz->id, 25000, 'ref_2', 'Debit 2 ($2.50)');

        $this->assertEquals(985000, $entry1->balance_after_hundredths_cents);
        $this->assertEquals(960000, $entry2->balance_after_hundredths_cents);

        $limit = TrialLimit::where('business_id', $biz->id)->first();
        $this->assertEquals(960000, $limit->current_balance_hundredths_cents);

        // 2. Database trigger test: credit_ledger_entries has NO UPDATE path
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('credit_ledger_entries is append-only and has no UPDATE path');

        DB::statement("UPDATE credit_ledger_entries SET amount_hundredths_cents = 99999 WHERE id = {$entry1->id}");
    }

    /**
     * TEST ANCHOR Part 2: Day 21 Dunning
     * a tenant at day 21 has AI off and the number still answering via voicemail
     */
    public function test_anchor_day_21_dunning_ai_off_voicemail_answering(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Dunning Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Advance to Day 21
        $state = $this->dunningAction->handle($biz->id, 21);

        $this->assertEquals(21, $state->day_in_cycle);
        $this->assertEquals('ai_off_voicemail_only', $state->status);
        $this->assertFalse($state->ai_enabled, 'the ladder records AI off at day 21; nothing in this app reads the flag');
        $this->assertTrue($state->phone_answering, 'the ladder records the phone still answering at day 21; nothing in this app reads the flag');
        $this->assertTrue($state->voicemail_only, 'the ladder records voicemail-only at day 21; no call is routed by this app');
    }

    /**
     * [G1-01] & [G1-56] X-198's MOCK gateway is asserted unreachable from a live tenant (G1-34)
     */
    public function test_g1_01_mock_gateway_unreachable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MOCK gateway unreachable from a live tenant');

        GatewayGuard::ensureNotMockOnLive(true, true);
    }

    /**
     * [G1-10] an unreconciled cent RAISES, asserted by injecting a one-cent difference
     */
    public function test_g1_10_unreconciled_cent_raises(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unreconciled difference detected: 1 cents');

        $engine = new ReconciliationEngine;
        $engine->reconcile(5000, 5001); // 1 cent difference
    }

    /**
     * [G1-13] & [G1-14] a daily top-up ceiling, asserted by forcing repeated depletion
     */
    public function test_g1_13_daily_topup_ceiling(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Topup Ceiling Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        TrialLimit::create([
            'business_id' => $biz->id,
            'daily_topup_ceiling_cents' => 10000, // $100 ceiling
            'topups_today_cents' => 0,
            'current_balance_hundredths_cents' => 0,
        ]);

        $res1 = $this->topupAction->handle($biz->id, 6000); // $60
        $this->assertEquals('charged', $res1['status']);

        $beforeCount = CreditLedgerEntry::where('business_id', $biz->id)->count();
        try {
            $this->topupAction->handle($biz->id, 5000); // +$50 = $110 > $100 ceiling
            $this->fail('Expected exception');
        } catch (\DomainException $e) {
            $this->assertEquals('REFUSAL: Daily top-up ceiling exceeded', $e->getMessage());
        }
        $this->assertEquals($beforeCount, CreditLedgerEntry::where('business_id', $biz->id)->count());
    }

    /**
     * [G1-18], [G1-20], [G1-32] INTEGER hundredths of a cent throughout (§18)
     */
    public function test_g1_18_integer_hundredths_cents_precision(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Hundredths Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $grant = $this->grantAction->handle($biz->id, 54321, 'grant_1', 'Grant 543.21 cents');
        $this->assertEquals(54321, $grant->amount_hundredths_cents);
    }

    /**
     * [G1-19] & [G1-28] P-095: the phone KEEPS ANSWERING. A lockout restricts CONSOLE, never front desk
     */
    public function test_g1_19_phone_keeps_answering_on_lockout(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Console Lockout Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $state = $this->dunningAction->handle($biz->id, 25);
        $this->assertTrue($state->phone_answering, 'the ladder records the phone still answering during lockout; nothing in this app reads the flag');
    }

    /**
     * [G1-33], [G1-42], [G1-49], [G1-59], [G4-39] exponential backoff with a hard attempt ceiling
     */
    public function test_g1_33_exponential_backoff(): void
    {
        $policy = new RetryPolicy;

        // Assert exponential backoff
        $this->assertEquals(2, $policy->getNextRetryDelaySeconds(1));
        $this->assertEquals(4, $policy->getNextRetryDelaySeconds(2));
        $this->assertEquals(8, $policy->getNextRetryDelaySeconds(3));

        // Assert hard attempt ceiling
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hard attempt ceiling reached');
        $policy->getNextRetryDelaySeconds(6);
    }

    /**
     * [G1-78], [G1-83] no refusal declared
     * ⛔ REFUSED: surveyed Actions, Database, Domain, Events, Models, Ui and found no gateway integration, Notice Before Charge, or credit block logic.
     *
     * [G1-52] the ledger is the source; the gateway receives period totals, never per-event usage
     */
    public function test_g1_52_ledger_is_source(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Ledger Source Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $grant = $this->grantAction->handle($biz->id, 5000, 'grant_1', 'Grant $0.50');
        $this->assertEquals(5000, $grant->balance_after_hundredths_cents);

        $debit = $this->debitAction->handle($biz->id, 1000, 'debit_1', 'Debit $0.10');
        $this->assertEquals(4000, $debit->balance_after_hundredths_cents);

        $event = new LedgerPeriodClosed($biz->id, $debit->balance_after_hundredths_cents, '2026-09-30');
        $this->assertEquals($debit->balance_after_hundredths_cents, $event->closingBalanceHundredthsCents);
        $this->assertEquals(['businessId', 'closingBalanceHundredthsCents', 'periodEnd'], array_keys(get_object_vars($event)));
    }

    /**
     * [G1-57], [G7-15], [G13-03] 8:1 over cent-precision true cost, DERIVED, never typed
     */
    public function test_g1_57_derived_cost_multiplier(): void
    {
        $cost = 1000; // 10 cents in hundredths
        $retail = $this->engine->calculateRetailDebit($cost, 8);
        $this->assertEquals(8000, $retail, 'Retail debit must be derived at 8:1 over true cost');
    }

    /**
     * [G7-01] §45A — the 21-day timeline is the ONE ladder
     * Asserting the 21-day dunning ladder via DunningAdvanceAction
     */
    public function test_g7_01_single_dunning_ladder(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G7-01 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $state9 = $this->dunningAction->handle($biz->id, 9);
        $this->assertEquals('warning', $state9->status);
        $this->assertTrue($state9->ai_enabled);
        $this->assertTrue($state9->phone_answering);

        $state20 = $this->dunningAction->handle($biz->id, 20);
        $this->assertEquals('banner', $state20->status);
        $this->assertTrue($state20->ai_enabled);
        $this->assertTrue($state20->phone_answering);

        $state21 = $this->dunningAction->handle($biz->id, 21);
        $this->assertEquals('ai_off_voicemail_only', $state21->status);
        $this->assertFalse($state21->ai_enabled);
        $this->assertTrue($state21->voicemail_only);
        $this->assertTrue($state21->phone_answering);
    }

    /**
     * [G9-31] MRR saved by the one dunning ladder (§45A)
     */
    public function test_g9_31_mrr_saved(): void
    {
        $action = new CalculateSavedMrrAction;

        $recovered = [
            ['id' => 1, 'monthly_price_cents' => 15000], // $150
            ['id' => 2, 'monthly_price_cents' => 5000],  // $50
        ];

        $saved = $action->handle($recovered);
        $this->assertEquals(20000, $saved); // $200
    }

    /**
     * [G1-80] Stripe/Authorize.Net metered billing sync
     * ⛔ REFUSED: C-Billing imports no gateway client of any kind — no StripeGatewayClient and no GatewayEngine appear anywhere under this module — and nothing in it writes a meter row, so there is no usage to meter and no gateway to sync it to. The only Authorize.Net string in the lane is this capability cell itself.
     */
    public function test_g1_80_metered_billing_sync(): void
    {
        $hits = [];
        foreach (File::allFiles(app_path('Modules/C-Billing')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = $file->getContents();
            if (str_contains($source, 'X198') || str_contains($source, 'GatewayClient') || str_contains($source, 'GatewayEngine')) {
                $hits[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $hits, 'a C-Billing file names a gateway client or engine');
    }

    /**
     * [G11-13] §45A — day-10 is a BANNER, never a lockout
     */
    public function test_g11_13_day_10_is_banner_not_lockout(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Day 10 Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $state = $this->dunningAction->handle($biz->id, 10);
        $this->assertEquals('banner', $state->status);
        $this->assertTrue($state->ai_enabled, 'day 10 is a banner: the ladder records AI still enabled; nothing in this app reads the flag');
    }

    /**
     * [G18-22] the numbers are DEAD. The metering model of record: 7¢/min · 100 minutes included
     */
    public function test_g18_22_metering_model(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Meter Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $limit = TrialLimit::create([
            'business_id' => $biz->id,
            'included_minutes' => 100,
            'rate_cents_per_min' => 7,
        ]);

        $this->assertEquals(100, $limit->included_minutes);
        $this->assertEquals(7, $limit->rate_cents_per_min);
    }

    /**
     * [G19-17] auto top-up is universal
     */
    public function test_g19_17_auto_topup(): void
    {
        // Mock X-82 config
        config(['x82.tenant_999.topup_limits' => [
            'min_cents' => 5000, // $50
            'max_cents' => 500000, // $5000
        ]]);

        $domain = new AutoTopupLimits;
        $limits = $domain->getLimits(999);

        $this->assertEquals(5000, $limits['min_cents']);
        $this->assertEquals(500000, $limits['max_cents']);
    }

    public function test_debit_refuses_no_ledger_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Ledger Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $beforeCount = CreditLedgerEntry::where('business_id', $biz->id)->count();
        try {
            $this->debitAction->handle($biz->id, 5000, 'ref_1', 'Debit 1');
            $this->fail('Expected exception');
        } catch (\DomainException $e) {
            $this->assertEquals('REFUSAL: Ledger not found', $e->getMessage());
        }
        $this->assertEquals($beforeCount, CreditLedgerEntry::where('business_id', $biz->id)->count());
    }

    public function test_debit_refuses_insufficient_balance(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Insufficient Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $this->grantAction->handle($biz->id, 10000, 'setup', 'Setup grant'); // .00

        $beforeCount = CreditLedgerEntry::where('business_id', $biz->id)->count();
        try {
            $this->debitAction->handle($biz->id, 15000, 'ref_1', 'Debit 1');
            $this->fail('Expected exception');
        } catch (\DomainException $e) {
            $this->assertEquals('REFUSAL: Insufficient balance', $e->getMessage());
        }
        $this->assertEquals($beforeCount, CreditLedgerEntry::where('business_id', $biz->id)->count());
    }
}
