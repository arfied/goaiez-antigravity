<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Models\ReviewRequest;

final class ReviewRequestReadAction
{
    public function handle(int $businessId, int $id): ?ReviewRequest
    {
        return ReviewRequest::where('business_id', $businessId)
            ->where('id', $id)
            ->first();
    }
}
