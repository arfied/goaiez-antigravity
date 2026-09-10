<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

trait SignalsLedgerEntry
{
    /**
     * The signal a ledger row carries. The engine stores an unsigned magnitude
     * and records the direction in entry_type, so the type is what says whether
     * money left the account; the amount column never carries a sign and a
     * ternary on it can only ever return one answer.
     *
     * Unmapped falls to 'unknown', which is what the pill exists to render for a
     * value we have not measured.
     *
     * @return array<string, string>
     */
    protected function ledgerEntryPillStates(): array
    {
        return [
            'debit' => 'attention',
            'grant' => 'ok',
            'topup' => 'ok',
        ];
    }
}
