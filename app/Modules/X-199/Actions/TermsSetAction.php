<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Domain\InvalidTermsException;
use App\Modules\X199\Models\CreditTerm;

final class TermsSetAction
{
    /** The closed set. `net_60` is in the plan ("terms net 15/30/60") and was missing. */
    public const TYPES = ['due_on_receipt', 'net_15', 'net_30', 'net_60'];

    public function handle(
        int $businessId,
        int $customerId,
        string $termsType = 'net_30',
        int $creditLimitCents = 500000,
        ?string $cardOnFileToken = null
    ): CreditTerm {
        if (! in_array($termsType, self::TYPES, true)) {
            throw new InvalidTermsException($termsType.' is not a terms type. Pick due on receipt, net 15, net 30 or net 60.');
        }

        if ($creditLimitCents < 0) {
            throw new InvalidTermsException('A credit limit is never negative.');
        }

        $values = [
            'terms_type' => $termsType,
            'credit_limit_cents' => $creditLimitCents,
        ];

        // A null token means "keep the card on file", never "blank it": the overflow
        // rule (§46A) charges that card, and a terms change must not disarm it.
        if ($cardOnFileToken !== null) {
            $values['card_on_file_token'] = $cardOnFileToken;
        }

        return CreditTerm::updateOrCreate(
            ['business_id' => $businessId, 'customer_id' => $customerId],
            $values
        );
    }
}
