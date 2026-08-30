<?php

declare(strict_types=1);

namespace App\Modules\X140\Events;

final class TopicIdentified
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $topicId,
        public readonly string $topicTitle,
        public readonly string $clusterKey
    ) {}
}
