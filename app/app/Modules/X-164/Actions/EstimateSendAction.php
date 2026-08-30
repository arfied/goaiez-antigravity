<?php

declare(strict_types=1);

namespace App\Modules\X164\Actions;

use App\Modules\X164\Events\EstimateSent;
use App\Modules\X164\Models\Estimate;
use Illuminate\Support\Facades\Event;

final class EstimateSendAction
{
    public function handle(int $businessId, int $estimateId): Estimate
    {
        $estimate = Estimate::where('business_id', $businessId)->findOrFail($estimateId);
        $estimate->update(['status' => 'sent']);

        Event::dispatch(new EstimateSent(
            businessId: $businessId,
            estimateId: $estimate->id,
            estimateNumber: $estimate->estimate_number,
            totalCents: $estimate->total_cents
        ));

        return $estimate;
    }
}
