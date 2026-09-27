<?php

declare(strict_types=1);

namespace App\Modules\X164\Actions;

use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;

final class EstimateReadAction
{
    /**
     * What a customer may see of an estimate through a portal link: kind, number,
     * state in plain words, lines and totals — or null when there is no such row (wave 820).
     *
     * @return array{kind:string,number:string,state:string,lines:list<array{label:string,quantity:int,subtotal_cents:int}>,total_cents:int,secondary:?string}|null
     */
    public function forPortal(int $businessId, int $estimateId): ?array
    {
        $estimate = Estimate::where('business_id', $businessId)->find($estimateId);
        if ($estimate === null) {
            return null;
        }

        $lines = EstimateLine::where('business_id', $businessId)->where('estimate_id', $estimate->id)->orderBy('id')->get()
            ->map(fn (EstimateLine $l) => ['label' => (string) $l->service_name, 'quantity' => (int) $l->quantity, 'subtotal_cents' => (int) $l->subtotal_cents])
            ->all();

        return [
            'kind' => 'Estimate',
            'number' => (string) $estimate->estimate_number,
            'state' => match ((string) $estimate->status) {
                'accepted' => 'Accepted'.($estimate->accepted_at ? ' on '.$estimate->accepted_at->format('j M Y') : ''),
                'expired', 'expired_locked' => 'Expired',
                'sent' => 'Awaiting your reply'.($estimate->expires_at ? ' — valid until '.$estimate->expires_at->format('j M Y') : ''),
                default => 'Being prepared',
            },
            'lines' => $lines,
            'total_cents' => (int) $estimate->total_cents,
            'secondary' => (int) $estimate->deposit_amount_cents > 0 ? 'Deposit: $'.number_format(((int) $estimate->deposit_amount_cents) / 100, 2) : null,
        ];
    }
}
