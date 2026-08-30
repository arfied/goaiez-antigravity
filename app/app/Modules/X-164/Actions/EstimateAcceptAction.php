<?php

declare(strict_types=1);

namespace App\Modules\X164\Actions;

use App\Modules\X164\Events\DepositRequested;
use App\Modules\X164\Events\EstimateAccepted;
use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;
use App\Modules\X164\Models\EstimateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class EstimateAcceptAction
{
    /**
     * Accept estimate, freeze price_book_version, or refuse if expired and locked (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        int $estimateId,
        string $customerSignature,
        ?string $staffCountersignature = null
    ): array {
        return DB::transaction(function () use ($businessId, $estimateId, $customerSignature, $staffCountersignature) {
            $estimate = Estimate::where('business_id', $businessId)->findOrFail($estimateId);

            // Expired estimate lock check (TEST ANCHOR)
            if ($estimate->status === 'expired' || ($estimate->expires_at && $estimate->expires_at->isPast())) {
                $estimate->update(['status' => 'expired']);

                return [
                    'status' => 'expired_locked',
                    'message' => 'Estimate is expired and cannot be accepted without a refresh',
                ];
            }

            // Capture frozen snapshot of lines and version (G10-01, G17-11)
            $lines = EstimateLine::where('business_id', $businessId)->where('estimate_id', $estimate->id)->get();

            $snapshot = [
                'estimate_number' => $estimate->estimate_number,
                'price_book_version' => $estimate->price_book_version,
                'total_cents' => $estimate->total_cents,
                'lines' => $lines->toArray(),
                'signed_by' => $customerSignature,
                'countersigned_by' => $staffCountersignature ?? 'Staff Authorized',
                'frozen_at' => now()->toIso8601String(),
            ];

            EstimateVersion::create([
                'business_id' => $businessId,
                'estimate_id' => $estimate->id,
                'version_number' => $estimate->price_book_version,
                'frozen_snapshot' => $snapshot,
            ]);

            $estimate->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'signed_by_customer' => $customerSignature,
                'countersigned_by_staff' => $staffCountersignature ?? 'Staff Authorized',
                'executed_doc_url' => "https://cdn.goaiez.com/docs/{$estimate->estimate_number}.pdf",
            ]);

            Event::dispatch(new EstimateAccepted(
                businessId: $businessId,
                estimateId: $estimate->id,
                priceBookVersion: $estimate->price_book_version,
                signedBy: $customerSignature
            ));

            Event::dispatch(new DepositRequested(
                businessId: $businessId,
                estimateId: $estimate->id,
                depositAmountCents: $estimate->deposit_amount_cents
            ));

            return [
                'status' => 'accepted',
                'estimate_id' => $estimate->id,
                'price_book_version' => $estimate->price_book_version,
                'frozen_snapshot' => $snapshot,
                'executed_doc_url' => $estimate->executed_doc_url,
            ];
        });
    }
}
