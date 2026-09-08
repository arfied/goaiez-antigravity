<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Models\PriceBookItem;
use Illuminate\Support\Str;

/**
 * (R245) intent resolution: simple word boundary check against confirmed pricebook items
 */
final class PriceQuoteAction
{
    public function handle(int $businessId, string $question): array
    {
        $normalizedQuestion = Str::lower($question);

        $confirmedItems = PriceBookItem::where('business_id', $businessId)
            ->where('is_confirmed', true)
            ->where('is_sample', false)
            ->orderByRaw('LENGTH(service_name) DESC')
            ->get();

        if ($match = $this->findMatch($confirmedItems, $normalizedQuestion)) {
            return [
                'amount' => $match->price_cents,
                'service_name' => $match->service_name,
            ];
        }

        $allItems = PriceBookItem::where('business_id', $businessId)
            ->orderByRaw('LENGTH(service_name) DESC')
            ->get();

        if ($match = $this->findMatch($allItems, $normalizedQuestion)) {
            if ($match->is_sample === true) {
                return [
                    'refusal_code' => 'SAMPLE_STATE_REFUSED',
                    'reason' => 'Sample prices must NEVER be returned to any customer channel',
                ];
            }

            return [
                'refusal_code' => 'UNCONFIRMED',
                'reason' => 'Unconfirmed prices must NEVER be returned to any customer channel',
            ];
        }

        return [
            'refusal_code' => 'NO_FACT',
            'reason' => 'No price quote available for the given intent',
        ];
    }

    private function findMatch(iterable $items, string $normalizedQuestion): ?PriceBookItem
    {
        foreach ($items as $item) {
            $sku = Str::lower($item->service_name);
            $skuWords = explode('-', $sku);

            $matchesAll = true;
            $hasNonEmptyWord = false;
            foreach ($skuWords as $word) {
                if ($word === '') {
                    continue;
                }
                $hasNonEmptyWord = true;
                if (preg_match('/\b'.preg_quote($word, '/').'\b/', $normalizedQuestion) !== 1) {
                    $matchesAll = false;
                    break;
                }
            }

            if (! $hasNonEmptyWord) {
                continue;
            }

            // If the SKU or all its words appear in the question
            $skuMatches = preg_match('/\b'.preg_quote($sku, '/').'\b/', $normalizedQuestion) === 1;

            if ($skuMatches || $matchesAll) {
                return $item;
            }
        }

        return null;
    }
}
