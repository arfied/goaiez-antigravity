<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Enums\BillingTerm;
use App\Models\Subscription;

trait ReadsAgreedMonthly
{
    /**
     * The monthly value this account's subscription row contributes (3443): the
     * agreed price plus extra locations; a yearly term is shown as a twelfth.
     * Null when the row predates the agreed-price columns — the registry figure
     * is never quoted in its place (3444).
     *
     * @return array{cents: ?int, yearly: bool, currency: ?string}
     */
    protected function agreedMonthly(?Subscription $sub): array
    {
        if ($sub === null || $sub->price_cents === null || $sub->additional_location_cents === null || $sub->additional_locations === null) {
            return ['cents' => null, 'yearly' => false, 'currency' => null];
        }

        $agreed = $sub->price_cents + $sub->additional_location_cents * $sub->additional_locations;
        $yearly = $sub->term === BillingTerm::Annual;

        return [
            'cents' => $yearly ? intdiv($agreed, 12) : $agreed,
            'yearly' => $yearly,
            'currency' => $sub->price_currency,
        ];
    }
}
