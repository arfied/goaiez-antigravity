<?php

declare(strict_types=1);

namespace App\Modules\X199\Console;

use App\Models\Business;
use App\Models\User;
use App\Modules\X199\Events\InvoiceDue;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

final class MarkInvoicesDueCommand extends Command
{
    protected $signature = 'x199:mark-due';

    protected $description = 'Detect overdue unpaid invoices and dispatch InvoiceDue once per invoice';

    public function handle(): int
    {
        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey());
                }
            });

        Tenancy::forgetAll();

        if ($dispatched === 0) {
            $this->info('No invoices found to mark due.');
        } else {
            $this->info("Dispatched InvoiceDue for {$dispatched} invoice(s).");
        }

        return self::SUCCESS;
    }

    private function dispatchForOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        $dispatched = 0;

        foreach ($businesses as $business) {
            $dispatched += Tenancy::actingAs((int) $business->id, function () use ($business) {
                $dueInvoices = Invoice::where('due_date', '<', now()->toDateString())
                    ->where('status', 'issued') // not fully paid
                    ->whereNull('due_notified_at')
                    ->get();

                $localDispatched = 0;
                foreach ($dueInvoices as $invoice) {
                    Event::dispatch(new InvoiceDue(
                        businessId: (int) $business->id,
                        invoiceId: (int) $invoice->id,
                        dueDate: $invoice->due_date->toDateString()
                    ));

                    $invoice->update(['due_notified_at' => now()]);
                    $localDispatched++;
                }

                return $localDispatched;
            });
        }

        return $dispatched;
    }
}
