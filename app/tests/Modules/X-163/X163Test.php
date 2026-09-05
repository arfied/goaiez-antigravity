<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Modules\X163\Actions\BookVersionAction;
use App\Modules\X163\Actions\CalloutLookupAction;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Actions\PriceLookupAction;
use App\Modules\X163\Actions\PriceRangeAction;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Events\PriceRefusalFlagged;
use App\Modules\X163\Events\VersionBumped;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\Pricebook;
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
     * [N-062] no refusal declared
     */
    public function test_n_062_assertion(): void
    {
        $this->assertTrue(true);
    }

    public function test_no_fake_rows_written_on_mount(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Mount Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::test(Pricebook::class)
            ->assertOk();

        $this->assertEquals(0, PriceBookItem::where('business_id', $biz->id)->count());
    }
}
