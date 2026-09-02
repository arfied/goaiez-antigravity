<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Models\WebhookSubscription;

class WebhookSubscribeAction
{
    public function subscribe(int $businessId, string $url, string $events): WebhookSubscription
    {
        return WebhookSubscription::create([
            'business_id' => $businessId,
            'url' => $url,
            'events' => [$events],
        ]);
    }
}
