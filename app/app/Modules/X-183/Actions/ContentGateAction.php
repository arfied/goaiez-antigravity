<?php

declare(strict_types=1);

namespace App\Modules\X183\Actions;

use App\Modules\X183\Events\ContentGated;
use App\Modules\X183\Events\ContentRejected;
use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\GateResult;
use App\Modules\X183\Models\TrustLadder;
use Illuminate\Support\Facades\Event;

final class ContentGateAction
{
    /**
     * Runs pre-publish grounding & compliance gate (G2-11, G12-02, G12-29).
     * TEST ANCHOR: No draft with gate_results.passed = false ever reaches content.created / is published.
     */
    public function evaluateGate(int $businessId, int $draftId): GateResult
    {
        $draft = ContentDraft::where('business_id', $businessId)->findOrFail($draftId);

        $rejectionReason = null;

        // 1. Check for sample pricing strings (G12-29)
        if (preg_match('/(SAMPLE PRICE|\$XX|\[PRICE\])/i', $draft->body_text)) {
            $rejectionReason = 'Draft contains placeholder sample price';
        }

        // 2. R36 Case Study Double Consent Rule (G2-11, G5-35, G6-04)
        if ($draft->is_case_study && ! $draft->has_double_consent) {
            $rejectionReason = 'R36: Case study requires verified double consent before publication';
        }

        $passed = ($rejectionReason === null);

        $gateResult = GateResult::create([
            'business_id' => $businessId,
            'draft_id' => $draft->id,
            'passed' => $passed,
            'rejection_reason' => $rejectionReason,
            'checked_at' => now(),
        ]);

        if ($passed) {
            $draft->update([
                'is_approved' => true,
                'is_published' => true,
            ]);

            $ladder = TrustLadder::firstOrCreate(
                ['business_id' => $businessId],
                ['consecutive_approved_count' => 0, 'unattended' => true]
            );
            $ladder->increment('consecutive_approved_count');

            Event::dispatch(new ContentGated($businessId, $draft->id, true));
        } else {
            // TEST ANCHOR: Rejected draft NEVER published
            $draft->update([
                'is_approved' => false,
                'is_published' => false,
            ]);

            Event::dispatch(new ContentRejected($businessId, $draft->id, $rejectionReason));
        }

        return $gateResult;
    }
}
