<?php

declare(strict_types=1);

namespace App\Modules\X199\Domain;

use App\Modules\X199\Models\Invoice;

final class InvoiceNumber
{
    public static function next(int $businessId): string
    {
        $lastInvoice = Invoice::where('business_id', $businessId)
            ->where('invoice_number', '~', '^INV-[0-9]{6}$')
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, 4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return 'INV-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
