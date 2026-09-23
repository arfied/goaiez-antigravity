<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\X171\Events\JobCompleted;
use App\Services\Config\DefaultsRegistry;

final class AskForReviewOnJobCompleted
{
    public function handle(JobCompleted $event): void
    {
        // P-113: a job replayed late (X-171 offline sync) carries its real completion date; a fresh job is day 0 and is never triaged.
        $action = new ReviewRequestAction(app(DefaultsRegistry::class));
        $action->handle(
            $event->businessId,
            $event->personId,
            'How did the repair go? Please leave us a review!',
            'google',
            csatScore: null,
            jobAgeDays: $event->occurredAt === null ? 0 : (int) $event->occurredAt->diffInDays(now())
        );
    }
}
