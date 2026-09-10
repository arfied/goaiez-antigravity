<?php

declare(strict_types=1);

namespace App\Modules\X117\Ui;

trait LabelsOrderStatus
{
    /**
     * The words a business owner reads for an order's status.
     *
     * The column holds two values in this checkout: an order is written
     * pending_payment when it is placed, and cancelled when the owner cancels
     * it. paid is written by nothing here, because no listener promotes an
     * order until a card-entry surface exists; it is mapped for the day that
     * lands rather than left to fall through.
     *
     * sold_out is deliberately absent. It is a key on the envelope the engine
     * returns and never reaches the row, so a map carrying it would name a
     * value the column cannot hold.
     *
     * @return array<string, string>
     */
    protected function orderStatusLabels(): array
    {
        return [
            'pending_payment' => 'placed, not paid',
            'cancelled' => 'cancelled',
            'paid' => 'paid',
        ];
    }

    /**
     * The signal the pill carries beside that word. An order awaiting payment
     * is the one an owner can still act on; a cancelled order is closed and
     * asks for nothing, so it carries no signal rather than the same one.
     *
     * @return array<string, string>
     */
    protected function orderStatusPillStates(): array
    {
        return [
            'pending_payment' => 'attention',
            'cancelled' => 'unknown',
            'paid' => 'ok',
        ];
    }
}
