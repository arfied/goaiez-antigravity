<?php

declare(strict_types=1);

namespace App\Modules\X109\Actions;

use App\Modules\X109\Events\ChallengeEncountered;
use App\Modules\X109\Events\FormSubmitted;
use App\Modules\X109\Events\QuotaExhausted;
use App\Modules\X109\Models\CaptchaQuota;
use Illuminate\Support\Facades\Event;

final class FormSubmitAction
{
    /**
     * Submits prospect contact form.
     * 1. The same prospect's form is never submitted twice in a campaign (TEST ANCHOR).
     * 2. Quota at zero produces a queue row and no third-party charge (TEST ANCHOR).
     */
    public function submitForm(
        int $businessId,
        string $campaignId,
        string $prospectIdentifier,
        array $formData = [],
        bool $challengeEncountered = false
    ): array {
        // 1. Duplicate check within campaign (TEST ANCHOR)
        $existing = CaptchaQuota::where('business_id', $businessId)
            ->where('campaign_id', $campaignId)
            ->where('prospect_identifier', $prospectIdentifier)
            ->where('status', 'submitted')
            ->first();

        if ($existing) {
            return [
                'status' => 'skipped_duplicate',
                'message' => 'Prospect form already submitted in this campaign',
            ];
        }

        $parkedRow = CaptchaQuota::where('business_id', $businessId)
            ->where('campaign_id', $campaignId)
            ->where('prospect_identifier', $prospectIdentifier)
            ->where('status', 'queued_manual')
            ->first();

        // Get tenant quota balance
        $quotaRecord = CaptchaQuota::where('business_id', $businessId)
            ->whereNull('prospect_identifier')
            ->latest('id')
            ->first();

        $availableQuota = $quotaRecord ? $quotaRecord->available_quota : 0;

        // 2. Quota at zero -> produce queue row and NO third-party charge (TEST ANCHOR)
        if ($availableQuota <= 0) {
            if ($parkedRow) {
                $parkedRow->touch();
                $queueRow = $parkedRow;
            } else {
                $queueRow = CaptchaQuota::create([
                    'business_id' => $businessId,
                    'campaign_id' => $campaignId,
                    'prospect_identifier' => $prospectIdentifier,
                    'status' => 'queued_manual',
                    'available_quota' => 0,
                    'used_quota' => 0,
                ]);
            }

            Event::dispatch(new QuotaExhausted($businessId, $campaignId));

            return [
                'status' => 'queued_manual',
                'message' => 'Zero quota: queued to manual review without third-party charges',
                'row_id' => $queueRow->id,
            ];
        }

        // 3. Quota available -> execute submission
        if ($challengeEncountered) {
            Event::dispatch(new ChallengeEncountered($businessId, $prospectIdentifier));
        }

        $quotaRecord->decrement('available_quota');
        $quotaRecord->increment('used_quota');

        $submission = CaptchaQuota::create([
            'business_id' => $businessId,
            'campaign_id' => $campaignId,
            'prospect_identifier' => $prospectIdentifier,
            'status' => 'submitted',
            'available_quota' => $quotaRecord->available_quota,
            'used_quota' => 1,
        ]);

        if ($parkedRow) {
            $parkedRow->delete();
        }

        Event::dispatch(new FormSubmitted($businessId, $campaignId, $prospectIdentifier));

        return [
            'status' => 'submitted',
            'submission_id' => $submission->id,
        ];
    }
}
