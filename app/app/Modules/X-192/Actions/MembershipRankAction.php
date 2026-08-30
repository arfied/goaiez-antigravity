<?php

declare(strict_types=1);

namespace App\Modules\X192\Actions;

use App\Modules\X192\Events\MembershipRecommended;
use App\Modules\X192\Models\DirectoryMembership;
use Illuminate\Support\Facades\Event;

final class MembershipRankAction
{
    /**
     * Ranks directory memberships (G8-08, G8-28).
     * TEST ANCHOR: A directory with noindex never appears above an indexed one in the ranking
     * and carries a "Google can't see this" note.
     */
    public function rankDirectory(
        int $businessId,
        string $directoryName,
        string $directoryUrl,
        bool $isNoindex = false,
        int $baseScore = 80
    ): DirectoryMembership {
        if ($isNoindex) {
            // TEST ANCHOR: noindex penalized so it never ranks above indexed directories
            $finalScore = min($baseScore, 10);
            $note = "Google can't see this (noindexed directory: G8-08)";
        } else {
            $finalScore = max($baseScore, 50);
            $note = 'indexed directory with active search visibility';
        }

        $membership = DirectoryMembership::create([
            'business_id' => $businessId,
            'directory_name' => $directoryName,
            'directory_url' => $directoryUrl,
            'is_noindex' => $isNoindex,
            'rank_score' => $finalScore,
            'recommendation_note' => $note,
            'is_purchased' => false,
            'approved_by_action_id' => null,
        ]);

        Event::dispatch(new MembershipRecommended($businessId, $membership->id, $finalScore));

        return $membership;
    }
}
