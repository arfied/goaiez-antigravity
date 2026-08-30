<?php

declare(strict_types=1);

namespace App\Modules\X164\Actions;

use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;
use Illuminate\Support\Facades\DB;

final class EstimateRefreshAction
{
    /**
     * Refresh expired/locked estimate to latest pricebook version and unlock it (TEST ANCHOR).
     */
    public function handle(int $businessId, int $estimateId, int $newPriceBookVersion, array $updatedLinePrices): Estimate
    {
        return DB::transaction(function () use ($businessId, $estimateId, $newPriceBookVersion, $updatedLinePrices) {
            $estimate = Estimate::where('business_id', $businessId)->findOrFail($estimateId);

            $totalCents = 0;
            foreach ($updatedLinePrices as $item) {
                EstimateLine::where('business_id', $businessId)
                    ->where('estimate_id', $estimate->id)
                    ->where('service_name', $item['service_name'])
                    ->update([
                        'unit_price_cents' => $item['unit_price_cents'],
                        'subtotal_cents' => $item['unit_price_cents'] * ($item['quantity'] ?? 1),
                    ]);

                $totalCents += $item['unit_price_cents'] * ($item['quantity'] ?? 1);
            }

            $estimate->update([
                'price_book_version' => $newPriceBookVersion,
                'total_cents' => $totalCents,
                'deposit_amount_cents' => (int) ($totalCents * 0.20),
                'status' => 'sent',
                'expires_at' => now()->addDays(7),
            ]);

            return $estimate;
        });
    }
}
