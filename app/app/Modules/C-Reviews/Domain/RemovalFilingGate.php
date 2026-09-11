<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Domain;

use App\Modules\CReviews\Models\ReviewRemovalRequest;

final class RemovalFilingGate
{
    public function assertFilable(ReviewRemovalRequest $request): void
    {
        if ($request->confirmed_by_user_id === null || $request->confirmed_at === null) {
            throw new RemovalNotConfirmedException('This removal request has not been confirmed by a human.');
        }
    }
}
