<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

trait LabelsDunningAction
{
    /**
     * The words an owner reads for a row in an invoice's dunning history.
     *
     * The column holds two values and both are written by this module:
     * reason_recorded when an owner records why an invoice is unpaid, and
     * escalate_to_human when the reason is one that routes to a person.
     *
     * escalate_to_human is deliberately not called "sent" or "raised": this
     * module has no transport, so the row records that the invoice was marked
     * for a person and claims nothing about anyone being told.
     *
     * @return array<string, string>
     */
    protected function dunningActionLabels(): array
    {
        return [
            'reason_recorded' => 'reason recorded',
            'escalate_to_human' => 'flagged for a human',
        ];
    }
}
