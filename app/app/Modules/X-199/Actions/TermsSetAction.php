<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Models\CreditTerm;

final class TermsSetAction
{
    public function handle(
        int $businessId,
        int $customerId,
        string $termsType = 'net_30',
        int $creditLimitCents = 500000,
        ?string $cardOnFileToken = null
    ): CreditTerm {
        return CreditTerm::updateOrCreate(
            ['business_id' => $businessId, 'customer_id' => $customerId],
            [
                'terms_type' => $termsType,
                'credit_limit_cents' => $creditLimitCents,
                'card_on_file_token' => $cardOnFileToken,
            ]
        );
    }
}
