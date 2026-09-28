<?php

declare(strict_types=1);

namespace App\Modules\X207\Jobs;

use App\Modules\X207\Actions\PushBroadcastAction;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendPushToUserJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $businessId,
        public int $userId,
        public string $eventType,
        public string $deepLink,
    ) {}

    public function handle(PushBroadcastAction $push): void
    {
        Tenancy::actingAs(
            $this->businessId,
            fn () => $push->toUser($this->businessId, $this->userId, [
                'event_type' => $this->eventType,
                'deep_link' => $this->deepLink,
            ])
        );
    }
}
