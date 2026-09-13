<?php

declare(strict_types=1);

namespace App\Modules\X199\Domain;

use App\Modules\X199\Models\Invoice;
use Illuminate\Support\Facades\DB;

final class InvoiceNumber
{
    public static function next(int $businessId): string
    {
        if (DB::transactionLevel() === 0) {
            throw new InvoiceNumberOutsideTransactionException(
                'An invoice number may only be allocated inside a transaction; the per-business lock is released at commit.'
            );
        }

        DB::selectOne('select pg_advisory_xact_lock(?, ?)', [199, $businessId]);

        $lastInvoice = Invoice::where('business_id', $businessId)
            ->where('invoice_number', '~', '^INV-[0-9]{6,}$')
            // Length first: past six digits a plain string sort puts INV-999999 above INV-1000000.
            ->orderByRaw('length(invoice_number) desc')
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
