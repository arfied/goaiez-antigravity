<?php

declare(strict_types=1);

namespace App\Modules\X142\Jobs;

use App\Modules\X142\Models\WebhookDelivery;
use App\Services\Webhooks\TenantWebhookClient;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

final class DeliverTenantWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public int $businessId,
        public int $deliveryId,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(TenantWebhookClient $client): void
    {
        Tenancy::actingAs(
            $this->businessId,
            function () use ($client) {
                $delivery = WebhookDelivery::with('subscription')->find($this->deliveryId);

                if ($delivery === null || $delivery->subscription === null) {
                    return;
                }

                $delivery->increment('attempts');

                $body = json_encode([
                    'event' => $delivery->event,
                    'delivery' => $delivery->delivery_ref,
                    'occurred_at' => $delivery->created_at?->toIso8601String(),
                    'data' => $delivery->payload,
                ], JSON_THROW_ON_ERROR);

                $result = $client->post(
                    $delivery->subscription->target_url,
                    $delivery->subscription->secret,
                    $delivery->event,
                    $delivery->delivery_ref,
                    $body
                );

                if ($result['outcome'] === 'delivered') {
                    $delivery->update([
                        'status' => 'delivered',
                        'delivered_at' => Carbon::now(),
                        'last_status_code' => $result['status'],
                        'last_error' => null,
                    ]);
                } elseif ($result['outcome'] === 'rejected') {
                    $delivery->update([
                        'status' => 'failed',
                        'last_status_code' => $result['status'],
                        'last_error' => $result['error'],
                    ]);
                } elseif ($result['outcome'] === 'refused') {
                    $delivery->update([
                        'status' => 'refused',
                        'last_status_code' => $result['status'],
                        'last_error' => $result['error'],
                    ]);
                } elseif ($result['outcome'] === 'retry') {
                    $delivery->update([
                        'last_status_code' => $result['status'],
                        'last_error' => $result['error'],
                    ]);

                    if ($this->attempts() >= $this->tries) {
                        $delivery->update(['status' => 'failed']);
                    } else {
                        $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
                    }
                }
            }
        );
    }
}
