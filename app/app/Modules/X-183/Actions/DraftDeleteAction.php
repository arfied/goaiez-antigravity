<?php

declare(strict_types=1);

namespace App\Modules\X183\Actions;

use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\TrustLadder;

final class DraftDeleteAction
{
    /**
     * Deletes a draft: sets unattended = false (TEST ANCHOR).
     */
    public function deleteDraft(int $businessId, int $draftId): void
    {
        $draft = ContentDraft::where('business_id', $businessId)->findOrFail($draftId);
        $draft->delete();

        // TEST ANCHOR: A delete sets unattended = false
        TrustLadder::where('business_id', $businessId)->update([
            'unattended' => false,
        ]);
    }
}
