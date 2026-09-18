<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Domain;

use App\Modules\CReviews\Models\ReviewRemovalRequest;

/**
 * This class has no production caller by design.
 * The caller would be the thing that transmits to Google, which is X-177's half
 * (gbp.post, gbp.answer, gbp.sync_hours, gbp.state) and needs a GBP API grant
 * that is not currently granted.
 * Therefore, a future reader must not read the absence of a caller as dead code
 * and must not add one to circumvent this limitation.
 */
final class RemovalFilingGate
{
    public function assertFilable(ReviewRemovalRequest $request): void
    {
        if (empty($request->google_review_id)) {
            throw new RemovalNotAddressableException('This removal request lacks a Google review ID.');
        }

        if ($request->confirmed_by_user_id === null || $request->confirmed_at === null) {
            throw new RemovalNotConfirmedException('This removal request has not been confirmed by a human.');
        }

        if ($request->status !== 'confirmed') {
            throw new RemovalNotAddressableException("This removal request is in status '{$request->status}', expected 'confirmed'.");
        }
    }
}
