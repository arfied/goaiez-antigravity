<?php

declare(strict_types=1);

namespace App\Modules\X110\Actions;

use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X110\Models\PixelEvent;

final class PixelEventsAction
{
    public function __construct(private readonly PixelEngine $engine) {}

    public function handle(int $businessId, int $sessionId, string $eventName, array $payload): PixelEvent
    {
        if ($eventName === 'form.abandoned') {
            return $this->engine->recordFormAbandonment(
                businessId: $businessId,
                sessionId: $sessionId,
                formId: $payload['form_id'] ?? 'default_form',
                abandonedFieldName: $payload['abandoned_field'] ?? 'unknown_field',
                fieldIndex: $payload['field_index'] ?? 1
            );
        }

        if ($eventName === 'rage_click') {
            return $this->engine->recordRageClick(
                businessId: $businessId,
                sessionId: $sessionId,
                elementSelector: $payload['element'] ?? 'button#submit',
                clicksCount: $payload['clicks'] ?? 5
            );
        }

        return PixelEvent::create([
            'business_id' => $businessId,
            'session_id' => $sessionId,
            'event_name' => $eventName,
            'payload' => $payload,
        ]);
    }

    public function abandonedFormsSince(int $businessId, \DateTimeInterface $since): array
    {
        return PixelEvent::where('business_id', $businessId)
            ->where('event_name', 'form.abandoned')
            ->where('created_at', '>=', $since)
            ->get()
            ->map(fn (PixelEvent $event) => [
                'abandoned_field' => $event->payload['abandoned_field'] ?? null,
            ])
            ->toArray();
    }
}
