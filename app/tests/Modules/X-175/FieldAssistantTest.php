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
}
