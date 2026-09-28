<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Models\WebhookSubscription;
use App\Services\Fetch\PublicAddressGuard;

class WebhookSubscribeAction
{
    public function subscribe(int $businessId, string $url, string $events): WebhookSubscription
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme']) || $parts['scheme'] !== 'https') {
            throw new \InvalidArgumentException('Webhook URLs must be public https addresses.');
        }

        $ips = app(PublicAddressGuard::class)->check($url);
        if ($ips === null || $ips === []) {
            throw new \InvalidArgumentException('Webhook URLs must be public https addresses.');
        }

        $normalizedEvents = array_filter(array_map('trim', explode(',', $events)));
        if (empty($normalizedEvents)) {
            throw new \InvalidArgumentException('Choose events from: '.implode(', ', WebhookDispatchAction::EVENTS).'.');
        }

        foreach ($normalizedEvents as $event) {
            if (! in_array($event, WebhookDispatchAction::EVENTS, true)) {
                throw new \InvalidArgumentException('Choose events from: '.implode(', ', WebhookDispatchAction::EVENTS).'.');
            }
        }

        return WebhookSubscription::create([
            'business_id' => $businessId,
            'target_url' => $url,
            'event_filter' => implode(',', $normalizedEvents),
            'secret' => 'sec_'.bin2hex(random_bytes(16)),
            'is_active' => true,
        ]);
    }
}
