<?php

declare(strict_types=1);

namespace App\Modules\X140\Actions;

use App\Modules\X140\Events\ContentCreated;
use App\Modules\X140\Models\ContentTopic;
use App\Modules\X140\Models\TopicSource;
use Illuminate\Support\Facades\Event;

final class ContentDraftFromConversationAction
{
    /**
     * Drafts an SEO article from conversation turn or refusal.
     * 1. A page drafted from a refusal cites the refusal id (TEST ANCHOR).
     * 2. A draft containing a SAMPLE price fails the gate and is never published (TEST ANCHOR).
     * 3. Cannibalization gate checks embedding similarity (G8-24).
     */
    public function draftContent(
        int $businessId,
        int $topicId,
        string $rawContent,
        string $sourceType = 'conversation_turn',
        ?string $refusalId = null,
        ?string $conversationRef = null,
        float $similarityScore = 0.40
    ): array {
        $topic = ContentTopic::where('business_id', $businessId)->findOrFail($topicId);

        // 1. Source row citation (TEST ANCHOR: cites refusal id when present)
        $source = TopicSource::create([
            'business_id' => $businessId,
            'topic_id' => $topic->id,
            'source_type' => $sourceType,
            'refusal_id' => $refusalId, // Cites refusal id (TEST ANCHOR)
            'conversation_ref' => $conversationRef,
            'raw_content' => $rawContent,
        ]);

        // 2. Pre-publish gate checks
        // TEST ANCHOR: A draft containing a SAMPLE price fails the gate and is never published
        $hasSamplePrice = (bool) preg_match('/(SAMPLE PRICE|\$XX|\[PRICE\]|sample price)/i', $rawContent);
        $isCannibalizing = ($similarityScore >= 0.88); // G8-24

        $canPublish = (! $hasSamplePrice && ! $isCannibalizing);

        $topic->update([
            'similarity_score' => $similarityScore,
            'is_published' => $canPublish,
        ]);

        Event::dispatch(new ContentCreated($businessId, $topic->id, $topic->slug, $canPublish));

        return [
            'topic_id' => $topic->id,
            'source_id' => $source->id,
            'refusal_id_cited' => $refusalId,
            'is_published' => $canPublish,
            'gate_failure_reason' => $hasSamplePrice ? 'Contains placeholder sample price' : ($isCannibalizing ? 'Cannibalization similarity threshold exceeded' : null),
        ];
    }
}
