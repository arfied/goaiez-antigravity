<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Models\ReviewRemovalRequest;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ConfirmRemovalRequestAction
{
    public function execute(ReviewRemovalRequest $request, ?int $userId): void
    {
        if ($userId === null) {
            throw new InvalidArgumentException('A user ID is required to confirm a removal request.');
        }

        $request->update([
            'confirmed_by_user_id' => $userId,
            'confirmed_at' => Carbon::now(),
            'status' => 'confirmed',
        ]);
    }
}
