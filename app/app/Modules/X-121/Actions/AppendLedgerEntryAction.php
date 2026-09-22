<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Models\LedgerEntry;

final class AppendLedgerEntryAction
{
    public function handle(
        int $businessId,
        string $entryType,
        int $amountCents,
        string $currency,
        int $balanceAfterCents,
        string $description
    ): LedgerEntry {
        return LedgerEntry::create([
            'business_id' => $businessId,
            'entry_type' => $entryType,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'balance_after_cents' => $balanceAfterCents,
            'description' => $description,
        ]);
    }
}
