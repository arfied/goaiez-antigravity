<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Actions\AgentAnswerAction;
use App\Modules\CAgent\Events\AgentRefused;
use App\Modules\X163\Actions\BookVersionAction;
use App\Modules\X163\Actions\CalloutLookupAction;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Actions\PriceLookupAction;
use App\Modules\X163\Actions\PriceQuoteAction;
use App\Modules\X163\Actions\PriceRangeAction;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Events\PriceRefusalFlagged;
use App\Modules\X163\Events\VersionBumped;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\Pricebook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class X163Test extends TestCase
{
    private PricebookEngine $engine;

    private PriceLookupAction $lookup;

    private PriceConfirmAction $confirm;

    private PriceRangeAction $range;

    private CalloutLookupAction $callout;

    private BookVersionAction $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PricebookEngine;
        $this->lookup = new PriceLookupAction($this->engine);
        $this->confirm = new PriceConfirmAction;
        $this->range = new PriceRangeAction;
        $this->callout = new CalloutLookupAction($this->engine);
        $this->version = new BookVersionAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * a SAMPLE price is never returned to any customer channel;
     * a version bump on the London book leaves the Leeds book's version unchanged;
     * the agent's answer to "how much to come out?" cites callout_fees and deducted_if_proceeding verbatim
     */
    public function test_anchor_sample_refusal_location_version_and_callout_fees(): void
    {
        Event::fake([VersionBumped::class, PriceRefusalFlagged::class]);

        $biz = TestCase::provisionTenant(['name' => 'Pricebook Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A SAMPLE price is never returned to any customer channel
        $sampleItem = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Sample Duct Cleaning',
            'price_cents' => 19900,
            'is_sample' => true,
            'is_confirmed' => false,
        ]);

        $customerLookup = $this->lookup->handle($biz->id, 'Sample Duct Cleaning', 'customer');
        $this->assertEquals('refused', $customerLookup['status']);
        $this->assertEquals('SAMPLE_STATE_REFUSED', $customerLookup['refusal_code']);
        Event::assertDispatched(PriceRefusalFlagged::class);

        // Internal lookup can see it
        $internalLookup = $this->lookup->handle($biz->id, 'Sample Duct Cleaning', 'admin');
        $this->assertEquals('quoted', $internalLookup['status']);

        // 2. A version bump on the London book leaves the Leeds book's version unchanged
        $londonBook = LocationBook::create([
            'business_id' => $biz->id,
            'location_name' => 'London',
            'version' => 1,
        ]);

        $leedsBook = LocationBook::create([
            'business_id' => $biz->id,
            'location_name' => 'Leeds',
            'version' => 1,
        ]);

        $bumpRes = $this->version->handle($biz->id, 'London');
        $this->assertEquals(2, $bumpRes['version']);

        $londonFresh = LocationBook::where('business_id', $biz->id)->find($londonBook->id);
        $leedsFresh = LocationBook::where('business_id', $biz->id)->find($leedsBook->id);

        $this->assertEquals(2, $londonFresh->version, 'London book version must be bumped to 2');
        $this->assertEquals(1, $leedsFresh->version, 'Leeds book version must remain unchanged at 1');

        // 3. The agent's answer to "how much to come out?" cites callout_fees and deducted_if_proceeding verbatim
        $calloutFee = CalloutFee::create([
            'business_id' => $biz->id,
            'callout_fee_cents' => 8500, // $85.00
            'deducted_if_proceeding' => true,
            'explanation_text' => 'Our callout fee is $85.00, which is fully deducted from the final invoice if you proceed with the repair.',
        ]);

        $calloutRes = $this->callout->handle($biz->id);
        $this->assertEquals('$85.00', $calloutRes['formatted_fee']);
        $this->assertTrue($calloutRes['deducted_if_proceeding']);
        $this->assertStringContainsString('$85.00', $calloutRes['quote_response']);
        $this->assertStringContainsString('deducted', $calloutRes['quote_response']);
    }

    /**
     * [G17-18] a rate per pricebook (§145.4 — no nexus, no jurisdiction math)
     */
    public function test_g17_18_rate_per_pricebook(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Tax Rate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Standard Diagnostic',
            'price_cents' => 9900,
            'is_sample' => false,
            'tax_rate_pct' => 8.25,
        ]);

        $res = $this->lookup->handle($biz->id, 'Standard Diagnostic');
        $this->assertEquals(8.25, $res['tax_rate_pct']);
    }

    /**
     * [N-062] a sample or unconfirmed line yields no number
     */
    public function test_n_062_assertion(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Quote Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'drain-sample',
            'price_cents' => 1850000,
            'is_sample' => true,
            'is_confirmed' => true,
            'tax_rate_pct' => 0,
        ]);

        $action = new PriceQuoteAction;
        $res = $action->handle($biz->id, 'How much to unblock a drain sample?');

        $this->assertEquals('NO_FACT', $res['refusal_code']);
        $this->assertArrayNotHasKey('amount', $res);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'drain-unconfirmed',
            'price_cents' => 1850000,
            'is_sample' => false,
            'is_confirmed' => false,
            'tax_rate_pct' => 0,
        ]);

        $res2 = $action->handle($biz->id, 'How much to unblock a drain unconfirmed?');

        $this->assertEquals('NO_FACT', $res2['refusal_code']);
        $this->assertArrayNotHasKey('amount', $res2);
    }

    public function test_price_quote_resolves_intent_from_real_data(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Quote Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Grounding data
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'drain-unblock',
            'price_cents' => 1850000,
            'is_sample' => false,
            'is_confirmed' => true,
            'tax_rate_pct' => 0,
        ]);

        $action = new PriceQuoteAction;

        // Match exact or spaced intent
        $res = $action->handle($biz->id, 'How much to unblock a drain?');
        $this->assertArrayHasKey('amount', $res);
        $this->assertEquals(1850000, $res['amount']);

        // Missing intent returns NO_FACT
        $res2 = $action->handle($biz->id, 'How much for a new roof?');
        $this->assertArrayHasKey('refusal_code', $res2);
        $this->assertEquals('NO_FACT', $res2['refusal_code']);
    }

    public function test_price_confirm_writes_fact_and_agent_answers(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Seam Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'drain unblock',
            'price_cents' => 12500,
            'is_sample' => true,
            'is_confirmed' => false,
            'tax_rate_pct' => 0,
        ]);

        $this->confirm->handle($biz->id, $item->id);

        $fact = DB::table('facts')
            ->where('business_id', $biz->id)
            ->where('key', 'price.drain-unblock')
            ->first();

        $this->assertNotNull($fact, 'Fact should be written by X-119 listener');
        $this->assertTrue((bool) $fact->is_valid);
        $this->assertEquals('12500', $fact->value);

        $agent = app(AgentAnswerAction::class);
        $res = $agent->handle($biz->id, 'How much for a drain unblock?');

        $this->assertEquals(12500, $res['amount']);

        $resNegative = $agent->handle($biz->id, 'How much for a roof repair?');
        $this->assertEquals('NO_FACT', $resNegative['refusal_code']);
    }

    public function test_no_fake_rows_written_on_mount(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Mount Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        Livewire::test(Pricebook::class)
            ->assertOk();

        $this->assertEquals(0, PriceBookItem::where('business_id', $biz->id)->count());
    }

    public function test_pricebook_list_filters_samples(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sample Filter Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Real Service',
            'price_cents' => 10000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Sample Service',
            'price_cents' => 20000,
            'is_sample' => true,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $list = app(\App\Services\Assistant\PriceBook::class)->list();
        $this->assertCount(1, $list->entries);

        $entries = array_values($list->entries);
        $this->assertEquals('Real Service', $entries[0]->label);
    }

    public function test_an_unconfirmed_price_is_never_quoted(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Unconfirmed Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Unconfirmed Drain Unblock',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $res = $this->lookup->handle($biz->id, 'Unconfirmed Drain Unblock', 'customer');
        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('UNCONFIRMED', $res['refusal_code']);
        $this->assertStringNotContainsString('$150.00', json_encode($res));
    }

    public function test_a_confirmed_price_is_still_quoted(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Confirmed Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirmed Drain Unblock',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        $res = $this->lookup->handle($biz->id, 'Confirmed Drain Unblock', 'customer');
        $this->assertEquals('quoted', $res['status']);
        $this->assertEquals('$150.00', $res['formatted_price']);
    }

    public function test_an_agent_price_refusal_becomes_a_gap_the_owner_can_see(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Gap Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a drain unblock',
            'drain unblock please'
        );

        Event::dispatch($event);

        $items = PriceBookItem::where('business_id', $biz->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(0, $items[0]->price_cents);
        $this->assertFalse($items[0]->is_confirmed);
        $this->assertEquals(1, $items[0]->refusal_count);
        $this->assertNotNull($items[0]->refusal_flagged_at);

        // Dispatch second time
        Event::dispatch($event);
        $items = PriceBookItem::where('business_id', $biz->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(2, $items[0]->refusal_count);
    }

    public function test_an_agent_refusal_that_is_not_about_price_writes_nothing(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Gap Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the warranty duration',
            'warranty duration'
        );

        Event::dispatch($event);
        $this->assertEquals(0, PriceBookItem::where('business_id', $biz->id)->count());

        $event2 = new AgentRefused(
            $biz->id,
            'QUIET_HOURS',
            'It is too late to quote pricebook',
            'late quote'
        );

        Event::dispatch($event2);
        $this->assertEquals(0, PriceBookItem::where('business_id', $biz->id)->count());
    }

    public function test_a_gap_row_is_never_quoted(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Gap Quote Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a gap row',
            'gap row'
        );
        Event::dispatch($event);

        $res = $this->lookup->handle($biz->id, 'gap row', 'customer');
        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('UNCONFIRMED', $res['refusal_code']);
    }

    public function test_a_confirmed_price_action_refuses_a_zero_amount(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Zero Confirm Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $zeroItem = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Zero Price Service',
            'price_cents' => 0,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $action = app(PriceConfirmAction::class);
        $result = $action->handle($biz->id, $zeroItem->id);

        $this->assertEquals('FILL_ME', $result['refusal_code'] ?? null);

        $zeroItem->refresh();
        $this->assertFalse($zeroItem->is_confirmed);

        $goodItem = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Good Price Service',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $resultGood = $action->handle($biz->id, $goodItem->id);
        $this->assertArrayNotHasKey('refusal_code', $resultGood);

        $goodItem->refresh();
        $this->assertTrue($goodItem->is_confirmed);
    }

    public function test_a_price_from_another_business_is_never_confirmed(): void
    {
        $a = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        $b = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);

        $bItem = PriceBookItem::create([
            'business_id' => $b->id,
            'service_name' => 'B Service',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        \DB::statement("SET app.business_id = '{$a->id}'");

        $this->expectException(ModelNotFoundException::class);
        app(PriceConfirmAction::class)->handle($a->id, $bItem->id);
    }

    public function test_the_other_businesses_price_is_still_unconfirmed(): void
    {
        $a = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        $b = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);

        $bItem = PriceBookItem::create([
            'business_id' => $b->id,
            'service_name' => 'B Service',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        \DB::statement("SET app.business_id = '{$a->id}'");

        try {
            app(PriceConfirmAction::class)->handle($a->id, $bItem->id);
        } catch (ModelNotFoundException $e) {
            // caught
        }

        \DB::statement("SET app.business_id = '{$b->id}'");
        $bItem->refresh();
        $this->assertFalse($bItem->is_confirmed);
    }

    public function test_price_range_returns_min_max_for_confirmed_non_sample_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Range Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'range-service',
            'price_cents' => 10000,
            'price_min_cents' => 4500,
            'price_max_cents' => 16500,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        $res = $this->range->handle($biz->id, 'range-service');
        $this->assertEquals(4500, $res['min_cents']);
        $this->assertEquals(16500, $res['max_cents']);
        $this->assertEquals('range-service', $res['service_name']);
    }

    public function test_price_range_refuses_unknown_service_with_no_fact(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Unknown Range Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->range->handle($biz->id, 'unknown-service');
        $this->assertEquals('NO_FACT', $res['refusal_code']);
        $this->assertArrayNotHasKey('min_cents', $res);
    }

    public function test_price_range_refuses_unconfirmed_gap_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Unconfirmed Range Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'gap-service',
            'price_cents' => 0,
            'price_min_cents' => 5000,
            'price_max_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $res = $this->range->handle($biz->id, 'gap-service');
        $this->assertEquals('NO_FACT', $res['refusal_code']);
        $this->assertArrayNotHasKey('min_cents', $res);
    }

    public function test_price_range_refuses_sample_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sample Range Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'sample-service',
            'price_cents' => 10000,
            'price_min_cents' => 5000,
            'price_max_cents' => 15000,
            'is_sample' => true,
            'is_confirmed' => true,
        ]);

        $res = $this->range->handle($biz->id, 'sample-service');
        $this->assertEquals('NO_FACT', $res['refusal_code']);
        $this->assertArrayNotHasKey('min_cents', $res);
    }

    public function test_price_quote_returns_most_specific_match(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Specific Biz 1', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'oil change',
            'price_cents' => 3951,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        $row2 = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'oil change synthetic',
            'price_cents' => 6951,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        $action = new PriceQuoteAction;
        $res = $action->handle($biz->id, 'I need an oil change synthetic please');

        $this->assertEquals($row2->price_cents, $res['amount']);
    }

    public function test_price_quote_returns_general_match_if_only_general_asked(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Specific Biz 2', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $row1 = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'oil change',
            'price_cents' => 3951,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'oil change synthetic',
            'price_cents' => 6951,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        $action = new PriceQuoteAction;
        $res = $action->handle($biz->id, 'I need an oil change please');

        $this->assertEquals($row1->price_cents, $res['amount']);
    }

    public function test_price_quote_hyphenated_shape_regression(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Regression Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'drain-unblock',
            'price_cents' => 18551,
            'is_sample' => false,
            'is_confirmed' => true,
        ]);

        $action = new PriceQuoteAction;
        $res = $action->handle($biz->id, 'How much to unblock a drain?');

        $this->assertEquals($row->price_cents, $res['amount']);
    }

    public function test_callout_refuses_when_no_fee_is_set(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Callout Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->callout->handle($biz->id);

        $this->assertEquals('NO_FACT', $res['refusal_code']);
        $this->assertArrayNotHasKey('formatted_fee', $res);
        $this->assertArrayNotHasKey('quote_response', $res);
    }

    public function test_callout_lookup_writes_no_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Write Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $this->callout->handle($biz->id);

        $this->assertEquals(0, CalloutFee::where('business_id', $biz->id)->count());
    }

    public function test_callout_quotes_the_fee_the_owner_set(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Set Callout Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $fee = CalloutFee::create([
            'business_id' => $biz->id,
            'callout_fee_cents' => 6350,
            'deducted_if_proceeding' => true,
        ]);

        $res = $this->callout->handle($biz->id);

        $expected = '$'.number_format($fee->callout_fee_cents / 100, 2);
        $this->assertEquals($expected, $res['formatted_fee']);
    }
}
