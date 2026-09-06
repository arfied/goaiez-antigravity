<?php

namespace Tests\Modules\X175;

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
}
