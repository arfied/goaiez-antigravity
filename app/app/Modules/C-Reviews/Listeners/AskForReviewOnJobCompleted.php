<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\X171\Events\JobCompleted;

final class AskForReviewOnJobCompleted
{
    public function handle(JobCompleted $event): void
    {
        $action = new ReviewRequestAction;
        $action->handle(
            $event->businessId,
            $event->personId,
            'How did the repair go? Please leave us a review!',
            'google'
        );
    }
}
