<?php

declare(strict_types=1);

namespace App\Modules\X211\Console;

use App\Models\Business;
use App\Models\User;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Models\ArDunningAction;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

final class DetectOverdueReceivablesCommand extends Command
{
    protected $signature = 'x211:detect-overdue';

    protected $description = 'Detect overdue receivables and dispatch ArOverdue';

    public function handle(): int
    {
        $dispatched = 0;
        $parked = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched, &$parked): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey(), $parked);
                }
            });

        Tenancy::forgetAll();

        if ($dispatched === 0 && $parked === 0) {
            $this->info('No overdue invoices found to chase.');
        } elseif ($dispatched === 0) {
            $this->info("{$parked} overdue invoice(s) found; every one is already with a human, so none was dispatched.");
        } else {
            $this->info("Dispatched ArOverdue for {$dispatched} invoice(s).");
        }

        return self::SUCCESS;
    }

    private function dispatchForOwner(int $userId, int &$parked): int
    {
        Tenancy::setUser($userId);

        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        $dispatched = 0;

        foreach ($businesses as $business) {
            $dispatched += Tenancy::actingAs((int) $business->id, function () use ($business, &$parked) {
                $overdueInvoices = app(InvoiceReader::class)->overdueIssued((int) $business->id);

                $localDispatched = 0;
                foreach ($overdueInvoices as $invoice) {
                    $alreadyChased = ArDunningAction::where('business_id', (int) $business->id)
                        ->where('invoice_id', $invoice->id)
                        ->where('action', 'escalate_to_human')
                        ->exists();

                    if (! $alreadyChased) {
                        $daysOverdue = (int) $invoice->due_date->startOfDay()->diffInDays(now()->startOfDay());
                        Event::dispatch(new ArOverdue((int) $business->id, (int) $invoice->id, (int) $daysOverdue));
                        $localDispatched++;
                    }
                }

                $parked += $overdueInvoices->count() - $localDispatched;

                return $localDispatched;
            });
        }

        return $dispatched;
    }
}
