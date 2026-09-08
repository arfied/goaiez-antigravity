<?php

namespace Tests\Modules\X175;

use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X175\Domain\FieldAssistantEngine;
use App\Modules\X175\Models\FieldSuggestion;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FieldAssistantTest extends TestCase
{
    /**
     * @group N-075
     * @group N-077
     */
    public function test_authors_no_amount_and_never_quotes_customer_directly(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Field Assistant Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $engine = new FieldAssistantEngine;

        $techPersonId = 882;
        $jobId = 9104;

        $result = $engine->ask(
            businessId: $biz->id,
            jobId: $jobId,
            techPersonId: $techPersonId,
            queryText: 'How to install the unit',
            isSamplePrice: false
        );

        $savedSuggestion = FieldSuggestion::where('business_id', $biz->id)->find($result['suggestion_id']);

        // X-175 authors no amount.
        $this->assertDoesNotMatchRegularExpression('/\d/', $savedSuggestion->response_text);

        // It never quotes a customer directly (tech-facing row is what exists).
        $this->assertEquals($techPersonId, $savedSuggestion->tech_person_id);
    }

    public function test_a_quoted_price_comes_from_the_pricebook(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Field Assistant Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Compressor Swap',
            'price_cents' => 48725,
            'is_sample' => false,
            'is_confirmed' => true,
            'tax_rate_pct' => 0.0,
        ]);

        $engine = new FieldAssistantEngine;

        $result = $engine->ask(
            businessId: $biz->id,
            jobId: 123,
            techPersonId: 456,
            queryText: 'Compressor Swap',
            isSamplePrice: false
        );

        $this->assertStringContainsString('$487.25', $result['response']);

        $savedSuggestion = FieldSuggestion::where('business_id', $biz->id)->find($result['suggestion_id']);
        $this->assertStringContainsString('$487.25', $savedSuggestion->response_text);
    }

    public function test_a_caller_cannot_supply_a_price_the_pricebook_did_not(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Field Assistant Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $engine = new FieldAssistantEngine;

        $result = $engine->ask(
            businessId: $biz->id,
            jobId: 123,
            techPersonId: 456,
            queryText: 'How much for a Compressor Swap',
            isSamplePrice: false,
            verifiedAnswer: 'Compressor Swap is $9.99 from the pricebook'
        );

        $this->assertEquals("I'd need to confirm that price", $result['response']);
        $this->assertTrue($result['is_unconfirmed_price']);

        $savedSuggestion = FieldSuggestion::where('business_id', $biz->id)->find($result['suggestion_id']);
        $this->assertStringNotContainsString('9.99', $savedSuggestion->response_text);
        $this->assertTrue((bool) $savedSuggestion->is_unconfirmed_price);
    }

    public function test_refuses_matched_unconfirmed_row_but_answers_verified_procedure_for_no_fact(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Field Assistant Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $engine = new FieldAssistantEngine;

        // 1. Positive control - confirmed row
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Brake Pad Replacement',
            'price_cents' => 15000,
            'is_sample' => false,
            'is_confirmed' => true,
            'tax_rate_pct' => 0.0,
        ]);

        $resultConfirmed = $engine->ask(
            businessId: $biz->id,
            jobId: 101,
            techPersonId: 202,
            queryText: 'Brake Pad Replacement',
            isSamplePrice: false
        );

        $this->assertArrayHasKey('status', $resultConfirmed);
        $this->assertSame('answered', $resultConfirmed['status']);
        $this->assertArrayHasKey('is_unconfirmed_price', $resultConfirmed);
        $this->assertSame(false, $resultConfirmed['is_unconfirmed_price']);
        $this->assertArrayHasKey('response', $resultConfirmed);
        $this->assertSame('Brake Pad Replacement is $150.00 from the pricebook', $resultConfirmed['response']);

        // 2. The defect - unconfirmed row
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Alternator Swap',
            'price_cents' => 35000,
            'is_sample' => false,
            'is_confirmed' => false,
            'tax_rate_pct' => 0.0,
        ]);

        $resultUnconfirmed = $engine->ask(
            businessId: $biz->id,
            jobId: 101,
            techPersonId: 202,
            queryText: 'Alternator Swap',
            isSamplePrice: false
        );

        $this->assertArrayHasKey('status', $resultUnconfirmed);
        $this->assertSame('price_refusal_flagged', $resultUnconfirmed['status']);
        $this->assertArrayHasKey('is_unconfirmed_price', $resultUnconfirmed);
        $this->assertSame(true, $resultUnconfirmed['is_unconfirmed_price']);
        $this->assertArrayHasKey('response', $resultUnconfirmed);
        $this->assertSame("I'd need to confirm that price", $resultUnconfirmed['response']);

        $this->assertArrayHasKey('suggestion_id', $resultUnconfirmed);
        $savedSuggestion = FieldSuggestion::where('business_id', $biz->id)->find($resultUnconfirmed['suggestion_id']);
        $this->assertSame(true, (bool) $savedSuggestion->is_unconfirmed_price);

        // 3. The regression guard - no matched row, verified procedure
        $verifiedAnswer = 'Torque to 30 ft-lbs.';
        $resultProcedure = $engine->ask(
            businessId: $biz->id,
            jobId: 101,
            techPersonId: 202,
            queryText: 'torque spec for the caliper bolt',
            isSamplePrice: false,
            verifiedAnswer: $verifiedAnswer
        );

        $this->assertArrayHasKey('status', $resultProcedure);
        $this->assertSame('answered', $resultProcedure['status']);
        $this->assertArrayHasKey('is_unconfirmed_price', $resultProcedure);
        $this->assertSame(false, $resultProcedure['is_unconfirmed_price']);
        $this->assertArrayHasKey('response', $resultProcedure);
        $this->assertSame($verifiedAnswer, $resultProcedure['response']);
    }
}
