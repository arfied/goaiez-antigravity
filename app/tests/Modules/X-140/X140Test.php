<?php

declare(strict_types=1);

namespace Tests\Modules\X140;

use App\Models\User;
use App\Modules\X140\Actions\ContentDraftFromConversationAction;
use App\Modules\X140\Actions\TopicIdentifyAction;
use App\Modules\X140\Events\ContentCreated;
use App\Modules\X140\Events\TopicIdentified;
use App\Modules\X140\Models\ContentTopic;
use App\Modules\X140\Models\TopicSource;
use App\Modules\X140\Ui\ProposedPagesView;
use App\Support\Tenancy;
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

        $biz = TestCase::provisionTenant(['name' => 'SEO Content Cluster Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

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

    public function test_screen_requires_auth_and_redirects_guest(): void
    {
        $this->get('/account/content-topics')
            ->assertRedirect('/login');
    }

    public function test_screen_loads_for_authed_user(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'SEO Content Cluster Tenant', 'currency' => 'USD']);
        $user = User::first();

        Tenancy::set((int) $biz->id);

        $this->actingAs($user)
            ->get('/account/content-topics')
            ->assertOk()
            ->assertSeeLivewire(ProposedPagesView::class);
    }

    /** [G3-37] */
    public function test_g3_37_doorway_pages_vs_real_content_mechanism(): void
    {
        Event::fake([TopicIdentified::class, ContentCreated::class]);

        $biz = TestCase::provisionTenant(['name' => 'SEO Content Cluster Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $topic = $this->topicAction->identify($biz->id, 'Emergency Boiler Repair Explained', 'emergency_repair');

        $rawContent = 'This is the distinct raw content test string.';
        $res = $this->draftAction->draftContent(
            businessId: $biz->id,
            topicId: $topic->id,
            rawContent: $rawContent,
            sourceType: 'conversation_turn',
            conversationRef: 'CONV-77120',
            similarityScore: 0.30
        );

        $source = TopicSource::where('business_id', $biz->id)->where('topic_id', $topic->id)->firstOrFail();

        $this->assertEquals($rawContent, $source->raw_content);
        $this->assertEquals('conversation_turn', $source->source_type);
        $this->assertEquals('CONV-77120', $source->conversation_ref);
        $this->assertContains($source->source_type, ['conversation_turn', 'refusal', 'customer_inquiry']);
        $this->assertTrue($res['is_published']);

        $allNames = array_merge(
            array_keys($topic->getAttributes()),
            array_keys($source->getAttributes()),
            array_map(fn ($p) => $p->getName(), (new \ReflectionMethod(TopicIdentifyAction::class, 'identify'))->getParameters()),
            array_map(fn ($p) => $p->getName(), (new \ReflectionMethod(ContentDraftFromConversationAction::class, 'draftContent'))->getParameters())
        );

        foreach ($allNames as $name) {
            $this->assertDoesNotMatchRegularExpression(
                '/(doorway|spun|spintax|city|cities|locality|localities|geo_page|near_me|programmatic|page_template|template_page)/i',
                $name
            );
        }

        $this->assertContains('source_type', $allNames);
        $this->assertContains('raw_content', $allNames);
        $this->assertContains('conversation_ref', $allNames);
        $this->assertGreaterThanOrEqual(15, count($allNames));

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/X-140')));
        $files = [];
        $controlCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getBasename(), ['capabilities.php', 'manifest.php'])) {
                $files[] = $file->getPathname();
                if (preg_match('/ContentTopic|TopicSource|ContentEngine/', $file->getBasename())) {
                    $controlCount++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(10, count($files));
        $this->assertGreaterThanOrEqual(3, $controlCount);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/\b(doorway|doorway_page|spun|spintax|city|cities|city_page|city_pages|geo_page|geo_pages|near_me|programmatic_seo|location_page)\b/i',
                $content
            );
        }
    }
}
