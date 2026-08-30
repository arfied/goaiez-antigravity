<?php

declare(strict_types=1);

namespace Tests\Modules\X119;

use App\Modules\X119\Actions\FactConfirmAction;
use App\Modules\X119\Actions\FactLookupAction;
use App\Modules\X119\Actions\FactTeachAction;
use App\Modules\X119\Actions\KnowledgeIngestSyncAction;
use App\Modules\X119\Domain\FactResolver;
use App\Modules\X119\Events\FactCreated;
use App\Modules\X119\Events\FactInvalidated;
use App\Modules\X119\Events\GroundingMissing;
use App\Modules\X121\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X119Test extends TestCase
{
    private FactResolver $resolver;

    private FactLookupAction $lookup;

    private FactConfirmAction $confirm;

    private FactTeachAction $teach;

    private KnowledgeIngestSyncAction $ingest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FactResolver;
        $this->lookup = new FactLookupAction($this->resolver);
        $this->confirm = new FactConfirmAction($this->resolver);
        $this->teach = new FactTeachAction($this->resolver);
        $this->ingest = new KnowledgeIngestSyncAction($this->resolver);
    }

    /**
     * TEST ANCHOR
     * grep -r "price" app/Modules/X-119/ shows no path from an embedding to a number — prices resolve only through the structured resolver;
     * a Fact in SAMPLE state cannot be returned to a customer channel;
     * a page unpublish and its Facts' invalidation share one commit id
     */
    public function test_anchor_price_structured_resolution_sample_state_refusal_and_page_invalidation(): void
    {
        Event::fake([FactCreated::class, FactInvalidated::class, GroundingMissing::class]);

        $biz = Business::provision(['name' => 'Fact Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Structured price resolution
        $this->teach->handle($biz->id, 'service.oil_change.price_cents', '4999', 'pricebook');
        $priceFact = $this->lookup->handle($biz->id, 'service.oil_change.price_cents', 'customer');

        $this->assertEquals('grounded', $priceFact['status']);
        $this->assertEquals('4999', $priceFact['value']);

        // 2. A Fact in SAMPLE state cannot be returned to a customer channel
        $this->teach->handle($biz->id, 'sample.demo_price', 'SAMPLE_PRICE_100', 'demo');
        $sampleRes = $this->lookup->handle($biz->id, 'sample.demo_price', 'customer');

        $this->assertEquals('refused', $sampleRes['status']);
        $this->assertEquals('SAMPLE_FACT_REFUSED', $sampleRes['code']);

        Event::assertDispatched(GroundingMissing::class, function (GroundingMissing $event) use ($biz) {
            return $event->businessId === $biz->id
                && $event->queryKey === 'sample.demo_price';
        });

        // 3. A page unpublish and its Facts' invalidation share one commit id
        $this->teach->handle($biz->id, 'page.landing.headline', 'Best Plumber in Town', 'landing_page');
        $this->teach->handle($biz->id, 'page.landing.phone', '555-0199', 'landing_page');

        $commitId = $this->resolver->invalidatePageFacts($biz->id, 'page.landing');
        $this->assertNotEmpty($commitId);

        Event::assertDispatched(FactInvalidated::class, function (FactInvalidated $event) use ($biz, $commitId) {
            return $event->businessId === $biz->id
                && $event->commitId === $commitId;
        });

        // Verify invalid facts are no longer returned
        $lookupAfter = $this->lookup->handle($biz->id, 'page.landing.headline', 'customer');
        $this->assertEquals('missing', $lookupAfter['status']);
    }

    /**
     * [G3-02] the crawl is X-151's, the grounding store is X-119's. Pinecone is corpus vocabulary — one database (§22)
     */
    public function test_g3_02_grounding_store_database(): void
    {
        $biz = Business::provision(['name' => 'G3 Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $taught = $this->teach->handle($biz->id, 'about.founded_year', '2015', 'crawler');
        $this->assertGreaterThan(0, $taught['fact_id']);
    }

    /**
     * [G5-25] the grounding law; retrieval is X-148's. Pinecone is corpus vocabulary
     */
    public function test_g5_25_grounding_law_retrieval(): void
    {
        $biz = Business::provision(['name' => 'Grounding Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->lookup->handle($biz->id, 'non_existent_key', 'customer');
        $this->assertEquals('missing', $res['status']);
    }

    /**
     * [G13-38] a volunteered detail becomes a Fact with its source
     */
    public function test_g13_38_volunteered_detail_provenance(): void
    {
        $biz = Business::provision(['name' => 'Volunteer Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->teach->handle(
            businessId: $biz->id,
            key: 'owner.preferred_name',
            value: 'Bob',
            source: 'chat_conversation'
        );

        $this->assertEquals('chat_conversation', $res['source']);
        $this->assertEquals('Bob', $res['value']);
    }
}
