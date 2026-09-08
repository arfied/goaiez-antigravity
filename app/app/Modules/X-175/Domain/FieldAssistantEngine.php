<?php

declare(strict_types=1);

namespace App\Modules\X175\Domain;

use App\Modules\X163\Actions\PriceLookupAction;
use App\Modules\X175\Events\AssistantSuggested;
use App\Modules\X175\Events\UpsellPrompted;
use App\Modules\X175\Models\FieldSuggestion;
use Illuminate\Support\Facades\Event;

final class FieldAssistantEngine
{
    private const MATCHED_ROW_REFUSALS = ['SAMPLE_STATE_REFUSED', 'UNCONFIRMED'];

    /**
     * Answers an on-site field question.
     * 1. No output routed to customer channel (TEST ANCHOR).
     * 2. A SAMPLE price asked on site writes price.refusal_flagged and returns "I'd need to confirm that price" (TEST ANCHOR).
     */
    public function ask(
        int $businessId,
        ?int $jobId,
        ?int $techPersonId,
        string $queryText,
        bool $isSamplePrice = false,
        ?string $verifiedAnswer = null
    ): array {
        // Sample / unconfirmed price refusal (TEST ANCHOR)
        if ($isSamplePrice) {
            $responseText = "I'd need to confirm that price";

            $suggestion = FieldSuggestion::create([
                'business_id' => $businessId,
                'job_id' => $jobId,
                'tech_person_id' => $techPersonId,
                'query_text' => $queryText,
                'response_text' => $responseText,
                'is_unconfirmed_price' => true, // Refusal flagged (TEST ANCHOR)
                'is_upsell' => false,
            ]);

            Event::dispatch(new AssistantSuggested($businessId, $suggestion->id, $responseText));

            return [
                'status' => 'price_refusal_flagged',
                'suggestion_id' => $suggestion->id,
                'response' => $responseText,
                'is_unconfirmed_price' => true,
            ];
        }

        $lookup = app(PriceLookupAction::class)->handle($businessId, $queryText);

        if (isset($lookup['status']) && $lookup['status'] === 'quoted') {
            $responseText = $lookup['service_name'].' is '.$lookup['formatted_price'].' from the pricebook';
            $isUnconfirmedPrice = false;
        } elseif ($this->isPriceShaped($queryText) || (isset($lookup['refusal_code']) && in_array($lookup['refusal_code'], self::MATCHED_ROW_REFUSALS, true))) {
            $responseText = "I'd need to confirm that price";
            $isUnconfirmedPrice = true;
        } else {
            $responseText = $verifiedAnswer ?? "Verified procedure for: {$queryText}";
            $isUnconfirmedPrice = false;
        }

        $suggestion = FieldSuggestion::create([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'tech_person_id' => $techPersonId,
            'query_text' => $queryText,
            'response_text' => $responseText,
            'is_unconfirmed_price' => $isUnconfirmedPrice,
            'is_upsell' => false,
        ]);

        Event::dispatch(new AssistantSuggested($businessId, $suggestion->id, $responseText));

        return [
            'status' => $isUnconfirmedPrice ? 'price_refusal_flagged' : 'answered',
            'suggestion_id' => $suggestion->id,
            'response' => $responseText,
            'is_unconfirmed_price' => $isUnconfirmedPrice,
        ];
    }

    /**
     * Determines if a query is asking for a price. R245
     */
    private function isPriceShaped(string $queryText): bool
    {
        return preg_match('/price|cost|how much|charge|quote/i', $queryText) === 1;
    }

    /**
     * Suggests an on-site upsell opportunity.
     */
    public function suggestUpsell(
        int $businessId,
        ?int $jobId,
        ?int $techPersonId,
        string $upsellItem,
        string $rationale
    ): array {
        $responseText = "Recommend {$upsellItem}: {$rationale}";

        $suggestion = FieldSuggestion::create([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'tech_person_id' => $techPersonId,
            'query_text' => "Upsell trigger: {$upsellItem}",
            'response_text' => $responseText,
            'is_unconfirmed_price' => false,
            'is_upsell' => true,
        ]);

        Event::dispatch(new UpsellPrompted($businessId, $suggestion->id, $upsellItem));

        return [
            'status' => 'upsell_suggested',
            'suggestion_id' => $suggestion->id,
            'upsell_item' => $upsellItem,
            'response' => $responseText,
        ];
    }
}
