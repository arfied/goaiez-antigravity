<?php

declare(strict_types=1);

namespace App\Modules\X178\Actions;

use App\Modules\X178\Events\DesignUndone;
use App\Modules\X178\Models\DesignChange;
use Illuminate\Support\Facades\Event;

final class DesignUndoAction
{
    public function handle(int $businessId, int $changeId): array
    {
        $change = DesignChange::where('business_id', $businessId)->findOrFail($changeId);
        $change->update(['status' => 'undone']);

        Event::dispatch(new DesignUndone(
            businessId: $businessId,
            changeId: $change->id,
            blockRef: $change->block_ref
        ));

        return [
            'status' => 'undone',
            'change_id' => $change->id,
            'reverted_to' => $change->previous_state,
        ];
    }
}
