<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Jobs\DeliverTenantWebhookJob;
use App\Modules\X142\Models\WebhookDelivery;
use App\Modules\X142\Models\WebhookSubscription;

final class WebhookDispatchAction
{
    public const array EVENTS = ['contact.created', 'message.received', 'lead.assigned', 'estimate.sent', 'estimate.accepted'];

    public function dispatch(int $businessId, string $event, array $payload): int
    {
        $subscriptions = WebhookSubscription::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        $count = 0;

        foreach ($subscriptions as $subscription) {
            $filters = array_map('trim', explode(',', $subscription->event_filter));

            if (in_array($event, $filters, true)) {
                $delivery = WebhookDelivery::create([
                    'business_id' => $businessId,
                    'subscription_id' => $subscription->id,
                    'event' => $event,
                    'delivery_ref' => 'whd_'.bin2hex(random_bytes(12)),
                    'payload' => $payload,
                    'status' => 'pending',
                ]);

                DeliverTenantWebhookJob::dispatch($businessId, $delivery->id);
                $count++;
            }
        }

        return $count;
    }
}
