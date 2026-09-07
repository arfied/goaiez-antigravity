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

        $items = PriceBookItem::where('business_id', $businessId)
            ->where('is_confirmed', true)
            ->where('is_sample', false)
            ->orderByRaw('LENGTH(service_name) DESC')
            ->get();

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
                return [
                    'amount' => $item->price_cents,
                ];
            }
        }

        return [
            'refusal_code' => 'NO_FACT',
            'reason' => 'No price quote available for the given intent',
        ];
    }
}
