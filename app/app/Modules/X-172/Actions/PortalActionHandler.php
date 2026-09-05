<?php

declare(strict_types=1);

namespace App\Modules\X172\Actions;

use App\Modules\X172\Events\PortalAction;
use App\Modules\X172\Models\PortalLink;
use App\Modules\X172\Models\PortalView;
use Illuminate\Support\Facades\Event;

final class PortalActionHandler
{
    public function handle(string $token, string $actionType, array $payload): array
    {
        if ($actionType === 'redline_accepted') {
            throw new \InvalidArgumentException('REFUSAL (G10-24): a redline is SURFACED with a diff, never accepted');
        }

        $link = PortalLink::where('token', $token)->where('is_active', true)->firstOrFail();

        $view = PortalView::create([
            'business_id' => $link->business_id,
            'portal_link_id' => $link->id,
            'customer_id' => $link->customer_id,
            'opened_at' => now(),
            'action_taken' => $actionType,
            'action_payload' => json_encode($payload),
        ]);

        Event::dispatch(new PortalAction(
            businessId: $link->business_id,
            portalLinkId: $link->id,
            actionTaken: $actionType,
            payload: $payload
        ));

        return [
            'status' => 'action_recorded',
            'action_taken' => $actionType,
            'portal_link_id' => $link->id,
        ];
    }
}
