<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Actions;

use App\Modules\CBilling\Models\CreditLedgerEntry;

final class LedgerExplainAction
{
    public function handle(int $businessId, int $entryId): array
    {
        $entry = CreditLedgerEntry::where('business_id', $businessId)->findOrFail($entryId);

        return [
            'entry_id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'amount_dollars' => number_format($entry->amount_hundredths_cents / 10000, 4),
            'balance_after_dollars' => number_format($entry->balance_after_hundredths_cents / 10000, 4),
            'reference_id' => $entry->reference_id,
            'description' => $entry->description,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }
}
