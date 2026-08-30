<?php

declare(strict_types=1);

namespace App\Modules\X172\Actions;

use App\Modules\X172\Events\PortalViewed;
use App\Modules\X172\Models\PortalLink;
use App\Modules\X172\Models\PortalView;
use Illuminate\Support\Facades\Event;

final class PortalViewAction
{
    public function handle(string $token, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $link = PortalLink::where('token', $token)->first();

        if ($link === null || ! $link->is_active) {
            return [
                'status' => 'invalid_link',
                'message' => 'Portal link is invalid or expired',
            ];
        }

        if ($link->expires_at->isPast()) {
            return [
                'status' => 'expired_link',
                'message' => 'Portal link has expired. A fresh token can be re-issued without credential requirements.',
            ];
        }

        $view = PortalView::create([
            'business_id' => $link->business_id,
            'portal_link_id' => $link->id,
            'customer_id' => $link->customer_id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'opened_at' => now(),
        ]);

        Event::dispatch(new PortalViewed(
            businessId: $link->business_id,
            portalLinkId: $link->id,
            resourceType: $link->resource_type,
            resourceId: (int) $link->resource_id,
            openedAt: $view->opened_at->toIso8601String()
        ));

        return [
            'status' => 'opened',
            'portal_link' => $link,
            'opened_at' => $view->opened_at->toIso8601String(),
        ];
    }
}
