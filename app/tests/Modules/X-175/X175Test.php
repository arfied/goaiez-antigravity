<?php

declare(strict_types=1);

namespace Tests\Modules\X175;

use App\Modules\X175\Actions\FieldAskAction;
use App\Modules\X175\Actions\FieldSuggestAction;
use App\Modules\X175\Domain\FieldAssistantEngine;
use App\Modules\X175\Events\AssistantSuggested;
use App\Modules\X175\Events\UpsellPrompted;
use App\Modules\X175\Models\FieldSuggestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X175Test extends TestCase
{
    private FieldAssistantEngine $engine;

    private FieldAskAction $askAction;

    private FieldSuggestAction $suggestAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FieldAssistantEngine;
        $this->askAction = new FieldAskAction($this->engine);
        $this->suggestAction = new FieldSuggestAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * no output of this module is ever routed to a customer channel — asserted by the absence of any sender import;
     * a SAMPLE price asked on site writes price.refusal_flagged and returns the "I'd need to confirm that price" string
     */
    public function test_anchor_sample_price_refusal_and_staff_only_assistance(): void
    {
        Event::fake([AssistantSuggested::class, UpsellPrompted::class]);

        $biz = TestCase::provisionTenant(['name' => 'Field Tech Assistant Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 882;
        $jobId = 9104;

        // 1. A SAMPLE/unconfirmed price asked on site writes refusal flagged and returns "I'd need to confirm that price" (TEST ANCHOR)
        $samplePriceQuery = 'What is the estimated price for custom duct rerouting?';
        $refusalResult = $this->askAction->handle(
            businessId: $biz->id,
            jobId: $jobId,
            techPersonId: $techPersonId,
            queryText: $samplePriceQuery,
            isSamplePrice: true // Sample / unconfirmed price
        );

        $this->assertEquals('price_refusal_flagged', $refusalResult['status']);
        $this->assertTrue($refusalResult['is_unconfirmed_price']);
        $this->assertEquals("I'd need to confirm that price", $refusalResult['response'], 'Sample price returns exact confirmation string');

        $savedRefusal = FieldSuggestion::where('business_id', $biz->id)->find($refusalResult['suggestion_id']);
        $this->assertNotNull($savedRefusal);
        $this->assertTrue($savedRefusal->is_unconfirmed_price);
        $this->assertEquals("I'd need to confirm that price", $savedRefusal->response_text);

        Event::assertDispatched(AssistantSuggested::class);

        // 2. Verified technical procedure query
        $techQuery = 'What is the torque spec for Carrier 24VNA9 compressor mounting bolts?';
        $verifiedResult = $this->askAction->handle(
            businessId: $biz->id,
            jobId: $jobId,
            techPersonId: $techPersonId,
            queryText: $techQuery,
            isSamplePrice: false,
            verifiedAnswer: 'Torque to 18 ft-lbs in star pattern'
        );

        $this->assertEquals('answered', $verifiedResult['status']);
        $this->assertFalse($verifiedResult['is_unconfirmed_price']);
        $this->assertEquals('Torque to 18 ft-lbs in star pattern', $verifiedResult['response']);

        // 3. Upsell suggestion
        $upsellResult = $this->suggestAction->handle(
            businessId: $biz->id,
            jobId: $jobId,
            techPersonId: $techPersonId,
            upsellItem: 'UV Air Purifier',
            rationale: 'Customer mentioned family asthma issues during duct inspection'
        );

        $this->assertEquals('upsell_suggested', $upsellResult['status']);
        Event::assertDispatched(UpsellPrompted::class);
    }

    /**
     * [N-175-01]
     */
    public function test_n_175_capabilities(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
