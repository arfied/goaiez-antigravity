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
            foreach ($skuWords as $word) {
                if (! str_contains($normalizedQuestion, $word)) {
                    $matchesAll = false;
                    break;
                }
            }

            // If the SKU or all its words appear in the question
            if (str_contains($normalizedQuestion, $sku) || $matchesAll) {
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
