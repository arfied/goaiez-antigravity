<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X140\Models\ContentTopic;
use App\Modules\X140\Models\TopicSource;

class X140Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-140';
    }

    public function fill(Business $business): int
    {
        if (ContentTopic::where('business_id', $business->id)->where('topic_title', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $topic1 = ContentTopic::create([
            'business_id' => $business->id,
            'topic_title' => self::MARKER.'How much does a drain cleaning cost?',
            'slug' => 'demo-drain-cleaning-cost',
            'cluster_key' => 'pricing',
            'similarity_rate' => 0.820,
            'is_published' => true,
        ]);

        TopicSource::create([
            'business_id' => $business->id,
            'topic_id' => $topic1->id,
            'source_type' => 'customer_inquiry',
            'raw_content' => self::MARKER.'Customer asked on the phone what a drain cleaning runs',
        ]);

        $topic2 = ContentTopic::create([
            'business_id' => $business->id,
            'topic_title' => self::MARKER.'Do you service tankless water heaters?',
            'slug' => 'demo-tankless-water-heaters',
            'cluster_key' => 'services',
            'similarity_rate' => 0.640,
            'is_published' => false,
        ]);

        TopicSource::create([
            'business_id' => $business->id,
            'topic_id' => $topic2->id,
            'source_type' => 'conversation_turn',
            'conversation_ref' => self::MARKER.'chat-4623',
            'raw_content' => self::MARKER.'Do you install or service tankless units?',
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $sourcesCount = TopicSource::where('business_id', $business->id)
            ->where('raw_content', 'like', self::MARKER.'%')
            ->delete();

        $topicsCount = ContentTopic::where('business_id', $business->id)
            ->where('topic_title', 'like', self::MARKER.'%')
            ->delete();

        return $sourcesCount + $topicsCount;
    }
}
