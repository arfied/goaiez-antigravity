<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Models\WebhookSubscription;

final class WebhookSubscribeAction
{
    public function subscribe(
        int $businessId,
        string $targetUrl,
        string $eventFilter
    ): WebhookSubscription {
        return WebhookSubscription::create([
            'business_id' => $businessId,
            'target_url' => $targetUrl,
            'event_filter' => $eventFilter,
            'secret' => bin2hex(random_bytes(16)),
            'is_active' => true,
        ]);
    }
}
