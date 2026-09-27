<?php

declare(strict_types=1);

namespace App\Modules\X207\Listeners;

use App\Modules\CSms\Events\MessageReceived;
use App\Modules\X207\Jobs\SendPushToUserJob;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

final class PushOnInboundText
{
    public function handle(MessageReceived $event): void
    {
        $ownerUserId = Tenancy::actingAs(
            $event->businessId,
            fn () => DB::table('businesses')->where('id', $event->businessId)->value('owner_user_id')
        );

        if ($ownerUserId !== null) {
            SendPushToUserJob::dispatch(
                $event->businessId,
                (int) $ownerUserId,
                'inbound_message',
                '/account/inbox'
            );
        }
    }
}
