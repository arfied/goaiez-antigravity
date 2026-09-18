<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\PaymentLink;

final class PaymentReadAction
{
    public function exists(int $businessId, int $paymentId): bool
    {
        Payment::where('business_id', $businessId)->findOrFail($paymentId);

        return true;
    }

    public function declined(int $businessId, bool $showAll = true, array $excludeIds = []): array
    {
        $query = Payment::where('business_id', $businessId)
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! $showAll) {
            $query->where('created_at', '>=', now()->startOfWeek());

            if (! empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }
        }

        return $query->get()->toArray();
    }

    public function recovered(int $businessId, string $paymentToken, string $createdAt): array|bool
    {
        $recovered = Payment::where('business_id', $businessId)
            ->where('status', 'captured')
            ->where('payment_token', $paymentToken)
            ->where('created_at', '>', $createdAt)
            ->orderBy('created_at')
            ->first();

        return $recovered ? $recovered->toArray() : false;
    }

    public function links(int $businessId, array $paymentIds): array
    {
        return PaymentLink::where('business_id', $businessId)
            ->whereIn('payment_id', $paymentIds)
            ->get()
            ->keyBy('payment_id')
            ->toArray();
    }

    public function countForBusiness(int $businessId): int
    {
        return Payment::where('business_id', $businessId)->count();
    }
}
