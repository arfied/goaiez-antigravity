<?php

declare(strict_types=1);

namespace App\Modules\X208\Actions;

use App\Modules\X208\Events\ApprovalRequested;
use App\Modules\X208\Models\MailPiece;
use Illuminate\Support\Facades\Event;

final class MailProposeAction
{
    public function handle(int $businessId, int $pieceId): array
    {
        $piece = MailPiece::where('business_id', $businessId)->findOrFail($pieceId);
        $piece->update(['status' => 'proposed']);

        Event::dispatch(new ApprovalRequested($businessId, $piece->id, $piece->cost_cents));

        return [
            'status' => 'proposed',
            'piece_id' => $piece->id,
        ];
    }
}
