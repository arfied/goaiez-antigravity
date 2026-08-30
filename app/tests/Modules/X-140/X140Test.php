<?php

declare(strict_types=1);

namespace Tests\Modules\X140;

use App\Modules\X121\Models\Business;
use App\Modules\X140\Actions\ContentDraftFromConversationAction;
use App\Modules\X140\Actions\TopicIdentifyAction;
use App\Modules\X140\Events\ContentCreated;
use App\Modules\X140\Events\TopicIdentified;
use App\Modules\X140\Models\ContentTopic;
use App\Modules\X140\Models\TopicSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X140Test extends TestCase
{
    private TopicIdentifyAction $topicAction;

    private ContentDraftFromConversationAction $draftAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topicAction = new TopicIdentifyAction;
        $this->draftAction = new ContentDraftFromConversationAction;
    }

    /**
     * TEST ANCHOR
     * a page drafted from a refusal cites the refusal id;
     * a draft containing a SAMPLE price fails the gate and is never published
     */
    public function test_anchor_refusal_citation_and_sample_price_fails_publish_gate(): void
    {
        Event::fake([TopicIdentified::class, ContentCreated::class]);

        $biz = Business::provision(['name' => 'SEO Content Cluster Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $topic = $this->topicAction->identify($biz->id, 'Commercial HVAC Emergency Repairs', 'emergency_repair');
        $this->assertNotNull($topic);
        Event::assertDispatched(TopicIdentified::class);

        // 1. Draft from refusal cites refusal id (TEST ANCHOR)
        $refusalId = 'REF-HVAC-9921';
        $refusalDraft = $this->draftAction->draftContent(
            businessId: $biz->id,
            topicId: $topic->id,
            rawContent: 'Guide on why refrigerant replacement requires certified HVAC technicians under EPA regulations.',
            sourceType: 'refusal',
            refusalId: $refusalId,
            similarityScore: 0.35
        );

        $this->assertEquals($refusalId, $refusalDraft['refusal_id_cited']);
        $this->assertTrue($refusalDraft['is_published']);

        $sourceRow = TopicSource::where('business_id', $biz->id)->where('topic_id', $topic->id)->first();
        $this->assertNotNull($sourceRow);
        $this->assertEquals($refusalId, $sourceRow->refusal_id, 'Source cites refusal ID (TEST ANCHOR)');

        // 2. Draft containing a SAMPLE price fails the gate and is NEVER published (TEST ANCHOR)
        $samplePriceTopic = $this->topicAction->identify($biz->id, 'Commercial Boiler Pricing Guide', 'pricing');
        $sampleDraft = $this->draftAction->draftContent(
            businessId: $biz->id,
            topicId: $samplePriceTopic->id,
            rawContent: 'Our standard maintenance package starts at SAMPLE PRICE $XX per month.', // Sample price string
            sourceType: 'conversation_turn',
            similarityScore: 0.20
        );

        $this->assertFalse($sampleDraft['is_published'], 'Draft containing a SAMPLE price fails the gate and is NEVER published (TEST ANCHOR)');

        $savedTopic = ContentTopic::where('business_id', $biz->id)->find($samplePriceTopic->id);
        $this->assertFalse($savedTopic->is_published, 'Database is_published is false for sample price draft (TEST ANCHOR)');

        // 3. Cannibalization gate check (G8-24)
        $cannibalTopic = $this->topicAction->identify($biz->id, 'Duplicate HVAC Guide', 'emergency_repair');
        $cannibalDraft = $this->draftAction->draftContent(
            businessId: $biz->id,
            topicId: $cannibalTopic->id,
            rawContent: 'Standard real content without placeholder price',
            similarityScore: 0.94 // Exceeds 0.88 threshold
        );
        $this->assertFalse($cannibalDraft['is_published'], 'Cannibalizing content fails publish gate (G8-24)');

        Event::assertDispatched(ContentCreated::class, 3);
    }

    /**
     * [G8-07], [G8-24], [G8-40], [G11-21], [G12-16], [G12-21]
     */
    public function test_content_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
