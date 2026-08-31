<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\PlanOffers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Loads `App\Support\PlanOfferCatalog` into `plan_offers` (T176 P1).
 *
 * A command rather than a migration, on {@see SyncDefaultsRegistry}'s three
 * reasons unchanged — a migration runs once, a rollback would delete rows it did
 * not write, and the seed has to be re-runnable against a live database — plus a
 * fourth that belongs to this table alone: **an offer's window is edited after it
 * is seeded**. R18 closes the founder window at an announced event, and that
 * close is an `UPDATE` on a seeded row. A migration that inserted these would put
 * the seed and the operator's edit in the same place with no way to tell them
 * apart.
 *
 * ⚠️ **IT WRITES ONLY WHAT IS MISSING, PLUS ONE THING.** See `PlanOffers::sync()`
 * for why that matters more here than it does for the registry: refreshing a
 * seeded row would reopen a window somebody had closed, on the next deploy,
 * silently.
 *
 * ⛔ **THE ONE THING IS A CLOSING DATE THE CATALOGUE STATES ON A ROW THAT HAS
 * NONE, AND THIS COMMAND IS THEREFORE HOW A WINDOW ENDS ON A DEPLOYED INSTALL
 * (9268).** It is reported on its own line — *"Closed:"* rather than *"Wrote:"* —
 * because the two are different acts and an operator reading a deploy log is the
 * only person who will ever see either. ⚠️ **`offers:close` is unchanged and is
 * still the way to end a window the catalogue does not state**, or to end one
 * sooner than it does.
 */
#[Signature('offers:sync {--dry-run : Report what would be written without writing it}')]
#[Description('Seed any plan offers that are missing, and end a window the catalogue has dated')]
final class SyncPlanOffers extends Command
{
    public function handle(PlanOffers $offers): int
    {
        $dryRun = (bool) $this->option('dry-run');

        ['written' => $written, 'closed' => $closed] = $offers->sync($dryRun);

        foreach ($written as $offer) {
            $this->line(($dryRun ? 'Would write' : 'Wrote').": {$offer}");
        }

        foreach ($closed as $offer => $endsAt) {
            // ⛔ A SEPARATE VERB, BECAUSE IT IS A SEPARATE ACT AND THE MORE
            // CONSEQUENTIAL OF THE TWO. "Wrote" describes an offer arriving;
            // this line describes one going off sale, and folding it into the
            // list above would hide a price change inside a seed report.
            //
            // ⚠️ THE INSTANT IS THE ROW'S OWN AND NOT A CONSTANT READ BACK HERE.
            // A key other than `founder` may state a different date, and a line
            // naming the wrong one is worse than a line naming none.
            $this->line(($dryRun ? 'Would close' : 'Closed').": {$offer} at {$endsAt}");
        }

        if ($written === [] && $closed === []) {
            $this->info('Every offer in the catalogue already exists on the terms it states. Nothing written.');
        }

        return self::SUCCESS;
    }
}
