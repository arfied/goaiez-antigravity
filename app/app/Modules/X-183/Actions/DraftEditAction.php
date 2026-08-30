<?php

declare(strict_types=1);

namespace App\Modules\X183\Actions;

use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\TrustLadder;

final class DraftEditAction
{
    /**
     * Edits an approved post: resets trust_ladder.count to zero (TEST ANCHOR).
     */
    public function editDraft(int $businessId, int $draftId, string $newBodyText): ContentDraft
    {
        $draft = ContentDraft::where('business_id', $businessId)->findOrFail($draftId);

        $draft->update([
            'body_text' => $newBodyText,
            'is_approved' => false, // Requires re-gating
            'is_published' => false,
        ]);

        // TEST ANCHOR: An edit to an approved post resets trust_ladder.count to zero
        TrustLadder::where('business_id', $businessId)->update([
            'consecutive_approved_count' => 0,
        ]);

        return $draft;
    }
}
