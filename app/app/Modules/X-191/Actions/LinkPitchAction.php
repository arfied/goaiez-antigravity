<?php

declare(strict_types=1);

namespace App\Modules\X191\Actions;

use App\Modules\X191\Models\LinkPitch;
use App\Modules\X191\Models\LinkTarget;
use App\Services\Config\DefaultsRegistry;
use InvalidArgumentException;

final class LinkPitchAction
{
    public const MONTHLY_SEND_CEILING = 50;

    /**
     * Pitches an outreach backlink target.
     * 1. A target flagged PBN never receives a pitch (TEST ANCHOR).
     * 2. The monthly send count never exceeds target ceiling (TEST ANCHOR).
     * 3. A pitch template with no page-specific fact fails qualify gate (TEST ANCHOR & G11-34).
     * 4. ONE follow-up only, per the header (G8-20, G12-07, G17-06).
     */
    private function monthlySendCeiling(): int
    {
        return $this->registry->int('links.pitch.monthly_send_ceiling');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function sendPitch(
        int $businessId,
        int $targetId,
        string $pitchBody,
        ?string $pageSpecificFact = null,
        ?string $sentMonth = null
    ): LinkPitch {
        $target = LinkTarget::where('business_id', $businessId)->findOrFail($targetId);

        // TEST ANCHOR 1: A target flagged PBN never receives a pitch
        if ($target->is_pbn) {
            throw new InvalidArgumentException('Pitch rejected: target flagged as PBN/toxic network (TEST ANCHOR)');
        }

        // TEST ANCHOR 3: Pitch template with no page-specific fact fails qualify gate (G11-34)
        if (empty($pageSpecificFact)) {
            throw new InvalidArgumentException('Pitch rejected: pitch template must contain a page-specific verified fact (TEST ANCHOR & G11-34)');
        }

        $month = $sentMonth ?? now()->format('Y-m');

        // TEST ANCHOR 2: Monthly send count never exceeds ceiling
        $monthlyCount = LinkPitch::where('business_id', $businessId)
            ->where('sent_month', $month)
            ->where('is_sent', true)
            ->count();

        if ($monthlyCount >= $this->monthlySendCeiling()) {
            throw new InvalidArgumentException('Pitch rejected: monthly send ceiling reached (TEST ANCHOR)');
        }

        return LinkPitch::create([
            'business_id' => $businessId,
            'target_id' => $target->id,
            'pitch_body' => $pitchBody,
            'page_specific_fact' => $pageSpecificFact,
            'follow_up_count' => 0,
            'is_sent' => true,
            'sent_month' => $month,
        ]);
    }

    /**
     * Follows up on a pitch — ONE follow-up only (G8-20, G12-07, G17-06).
     */
    public function sendFollowUp(int $businessId, int $pitchId): LinkPitch
    {
        $pitch = LinkPitch::where('business_id', $businessId)->findOrFail($pitchId);

        // G12-07: ONE follow-up only — not three
        if ($pitch->follow_up_count >= 1) {
            throw new InvalidArgumentException('Follow-up rejected: strictly one follow-up allowed (G12-07, G17-06)');
        }

        $pitch->increment('follow_up_count');

        return $pitch;
    }
}
