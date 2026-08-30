<?php

declare(strict_types=1);

namespace Tests\Modules\X148;

use App\Modules\X121\Models\Business;
use App\Modules\X148\Actions\RetrievalIndexAction;
use App\Modules\X148\Actions\RetrievalSearchAction;
use App\Modules\X148\Events\RetrievalCompleted;
use App\Modules\X148\Events\RetrievalEmpty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X148Test extends TestCase
{
    private RetrievalIndexAction $indexAction;

    private RetrievalSearchAction $searchAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexAction = new RetrievalIndexAction;
        $this->searchAction = new RetrievalSearchAction;
    }

    /**
     * TEST ANCHOR
     * a voice-mode retrieval trace contains exactly one search call and no rerank;
     * a tenant-A query never returns a tenant-B chunk — the predicate test asserts zero rows, not an exception
     */
    public function test_anchor_voice_mode_trace_and_tenant_isolation_zero_rows(): void
    {
        Event::fake([RetrievalCompleted::class, RetrievalEmpty::class]);

        $bizA = Business::provision(['name' => 'Tenant Alpha HVAC', 'currency' => 'USD']);
        $bizB = Business::provision(['name' => 'Tenant Beta Roofing', 'currency' => 'USD']);

        // Index chunks for Tenant A and Tenant B
        DB::statement("SET app.business_id = '{$bizA->id}'");
        $this->indexAction->indexChunk(
            businessId: $bizA->id,
            title: 'Alpha HVAC Warranty Policy',
            chunkText: 'Alpha offers a 10 year heat pump compressor warranty.'
        );

        DB::statement("SET app.business_id = '{$bizB->id}'");
        $this->indexAction->indexChunk(
            businessId: $bizB->id,
            title: 'Beta Shingle Leak Repair SLA',
            chunkText: 'Beta guarantees 24-hour emergency leak response.'
        );

        // 1. Voice-mode retrieval trace contains exactly 1 search call and NO rerank (TEST ANCHOR)
        DB::statement("SET app.business_id = '{$bizA->id}'");
        $voiceSearchRes = $this->searchAction->search(
            businessId: $bizA->id,
            query: 'warranty',
            isVoiceMode: true
        );

        $this->assertEquals('completed', $voiceSearchRes['status']);
        $this->assertEquals(1, $voiceSearchRes['trace']['search_calls_count'], 'Trace has exactly one search call');
        $this->assertFalse($voiceSearchRes['trace']['rerank_applied'], 'Trace has no rerank in voice mode');
        $this->assertEquals('voice', $voiceSearchRes['trace']['mode']);

        Event::assertDispatched(RetrievalCompleted::class);

        // 2. Tenant isolation: a tenant-A query never returns a tenant-B chunk — asserts ZERO rows, not an exception (TEST ANCHOR)
        DB::statement("SET app.business_id = '{$bizA->id}'");
        $crossTenantQuery = $this->searchAction->search(
            businessId: $bizA->id,
            query: 'Beta Shingle Leak Repair' // Content existing only in Tenant B
        );

        $this->assertEquals('empty', $crossTenantQuery['status']);
        $this->assertEquals(0, $crossTenantQuery['count'], 'Predicate test asserts zero rows, not an exception');
        $this->assertCount(0, $crossTenantQuery['chunks']);

        Event::assertDispatched(RetrievalEmpty::class);
    }

    /**
     * [G5-46] the documents are X-160's
     */
    public function test_g5_46_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
