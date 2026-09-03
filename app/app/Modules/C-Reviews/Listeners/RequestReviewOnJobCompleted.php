<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\X171\Events\JobCompleted;
use Illuminate\Support\Facades\DB;

final class RequestReviewOnJobCompleted
{
    public function __construct(private readonly ReviewRequestAction $action) {}

    public function handle(JobCompleted $event): void
    {
        $recent = DB::table('review_requests')
            ->where('business_id', $event->businessId)
            ->where('customer_id', $event->personId)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();
            
        if ($recent) {
            return;
        }

        $this->action->handle(
            $event->businessId,
            $event->personId,
            'How did the repair go?'
        );
    }
}
