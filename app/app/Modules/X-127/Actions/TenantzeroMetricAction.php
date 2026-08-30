<?php

declare(strict_types=1);

namespace App\Modules\X127\Actions;

use App\Modules\X127\Events\TenantzeroMetricPublished;
use App\Modules\X127\Models\PublishedMetric;
use Illuminate\Support\Facades\Event;

final class TenantzeroMetricAction
{
    public function handle(
        int $businessId,
        string $metricKey,
        string $publishedValue,
        string $liveQuery
    ): PublishedMetric {
        $metric = PublishedMetric::updateOrCreate(
            ['business_id' => $businessId, 'metric_key' => $metricKey],
            [
                'published_value' => $publishedValue,
                'live_query' => $liveQuery,
                'last_verified_value' => $publishedValue,
                'status' => 'published',
            ]
        );

        Event::dispatch(new TenantzeroMetricPublished($businessId, $metricKey, $publishedValue));

        return $metric;
    }
}
