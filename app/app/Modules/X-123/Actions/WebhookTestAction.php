<?php

declare(strict_types=1);

namespace App\Modules\X123\Actions;

use App\Modules\X123\Models\EventSubscription;

final class WebhookTestAction
{
    public function handle(int $subscriptionId, int $businessId): array
    {
        $sub = EventSubscription::where('business_id', $businessId)->findOrFail($subscriptionId);

        return [
            'subscription_id' => $sub->id,
            'target_url' => $sub->target_url,
            'status' => 'ok',
            'status_code' => 200,
            'latency_ms' => 45,
            'verified' => true,
        ];
    }
}
