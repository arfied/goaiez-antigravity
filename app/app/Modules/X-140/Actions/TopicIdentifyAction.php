<?php

declare(strict_types=1);

namespace App\Modules\X140\Actions;

use App\Modules\X140\Events\TopicIdentified;
use App\Modules\X140\Models\ContentTopic;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class TopicIdentifyAction
{
    public function identify(int $businessId, string $topicTitle, string $clusterKey = 'emergency_repair'): ContentTopic
    {
        $topic = ContentTopic::create([
            'business_id' => $businessId,
            'topic_title' => $topicTitle,
            'slug' => Str::slug($topicTitle),
            'cluster_key' => $clusterKey,
            'similarity_rate' => 0.000,
            'is_published' => false,
        ]);

        Event::dispatch(new TopicIdentified($businessId, $topic->id, $topicTitle, $clusterKey));

        return $topic;
    }
}
