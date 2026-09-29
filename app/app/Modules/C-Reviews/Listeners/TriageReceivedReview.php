<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Domain\PublicThreshold;
use App\Modules\CReviews\Events\ReviewReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

final class TriageReceivedReview implements ShouldQueue
{
    public function handle(ReviewReceived $event): void
    {
        $threshold = app(PublicThreshold::class)->for($event->businessId);

        if ($event->rating < $threshold) {
            app(QaTicketAction::class)->handle($event->businessId, $event->reviewRequestId);
        }
    }
}
