<?php

declare(strict_types=1);

namespace App\Modules\X164\Actions;

use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EstimateDraftAction
{
    public function handle(
        int $businessId,
        ?int $customerId,
        array $lines,
        int $priceBookVersion = 1,
        int $validDays = 7
    ): Estimate {
        return DB::transaction(function () use ($businessId, $customerId, $lines, $priceBookVersion, $validDays) {
            $totalCents = 0;
            foreach ($lines as $line) {
                $subtotal = ($line['quantity'] ?? 1) * ($line['unit_price_cents'] ?? 0);
                $totalCents += $subtotal;
            }

            $estimate = Estimate::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'estimate_number' => 'EST-'.strtoupper(Str::random(6)),
                'price_book_version' => $priceBookVersion,
                'status' => 'draft',
                'total_cents' => $totalCents,
                'deposit_amount_cents' => (int) ($totalCents * 0.20), // 20% deposit
                'expires_at' => now()->addDays($validDays),
            ]);

            foreach ($lines as $line) {
                $qty = $line['quantity'] ?? 1;
                $unitPrice = $line['unit_price_cents'] ?? 0;
                EstimateLine::create([
                    'business_id' => $businessId,
                    'estimate_id' => $estimate->id,
                    'service_name' => $line['service_name'],
                    'quantity' => $qty,
                    'unit_price_cents' => $unitPrice,
                    'subtotal_cents' => $qty * $unitPrice,
                ]);
            }

            return $estimate;
        });
    }
}
