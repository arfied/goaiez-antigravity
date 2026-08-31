<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BillingTerm;
use App\Exceptions\AmbiguousPlanOffer;
use App\Models\PlanOffer;
use App\Services\Billing\PlanOffers;
use App\Support\PlanPricing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Ends an offer's window — R18's "dated, announced flip" (T176 P1, decision 4345).
 *
 * ⛔ **THIS COMMAND EXISTS BECAUSE THE FAIL-OPEN PRICE HAD NO REACHABLE OFF
 * SWITCH.** `PlanOffers::close()` shipped with **no caller in `app/`**: no
 * command, no route, no screen. 4319 recorded that *"closing it is one call and
 * nothing will ever remind anybody"*, and that understated it — it was one call
 * nothing in this application could make. The only supported way to end the
 * founder window was `php artisan tinker` against production, which is a
 * hand-typed Carbon argument, no audit actor, and not written down in
 * `.claude/skills/deploying/`.
 *
 * ⚠️ **IT IS NOT IN `composer deploy`, AND THAT IS THE POINT.** `offers:sync`
 * runs on every deploy because a missing offer is a bug; a close is a dated
 * decision somebody makes once, and a deploy step that closed windows would be
 * the sync-reopens-a-window failure with its sign flipped.
 *
 * ⛔ **THAT IS NARROWED SINCE 2026-08-24 AND IS NOT OVERTURNED — READ BOTH**
 * (9268). `offers:sync` now carries a closing date **the catalogue states** onto
 * a stored row whose `closes_at` is `NULL`, so on a deployed install the deploy
 * is what ends a window the tree has dated. The sentence above is right about a
 * deploy step that closes windows **as an act**; what runs there closes only
 * where `PlanOfferCatalog` states the instant and nothing else has spoken, and
 * every write it can make narrows a window rather than widening one. ⚠️ **This
 * command is unchanged and is still the whole of the answer for the cases that
 * arm cannot reach**: ending a window the catalogue does not date, ending one
 * **sooner** than it does, moving a scheduled flip, and `--reopen`. ⚠️ **It is
 * also the only one of the two that needs no deploy**, which is what a person
 * reaches for when a price has to stop being sold this afternoon.
 *
 * ⛔ **AND UNTIL 4641 IT WAS ALSO A RE-OPEN COMMAND, WHICH IS THAT SAME FAILURE
 * REACHED THROUGH THE OFF SWITCH.** `close()` wrote `closes_at` unconditionally,
 * so running this a second time against a window that had already ended — with
 * any `--at` later than now — put the founder rate back on sale and printed
 * *"Closed 2 rows"* while doing it. `--reopen` is now required for that, and
 * **a future `--at` is reported as a scheduled flip rather than as a shut
 * window**: the old closing line said the retail schedule was being quoted from
 * that moment, which is false for every instant until the date typed.
 */
#[Signature('offers:close {key : The offer key, e.g. founder} {--at= : When the window ends — any parseable instant, default now} {--reopen : Put a window that has ALREADY ENDED back on sale — refused without it}')]
#[Description('End an offer\'s window, so the next signup is quoted the retail schedule')]
final class CloseOffer extends Command
{
    /**
     * The actor recorded against the change.
     *
     * {@see SyncDefaultsRegistry::ACTOR}'s reasoning: a label rather than a user
     * id, because nobody is signed in to a console. ⚠️ **When a screen ever
     * calls `close()` it passes `user:{id}` instead** — the parameter is
     * required precisely so that surface cannot inherit this label by accident.
     */
    public const string ACTOR = 'offers:close';

    public function handle(PlanOffers $offers): int
    {
        $key = (string) $this->argument('key');

        $at = $this->closingInstant();

        if (! $at instanceof Carbon) {
            return self::FAILURE;
        }

        $this->preview($offers, $key);

        $reopen = (bool) $this->option('reopen');

        try {
            $closed = $offers->close($key, $at, self::ACTOR, $reopen);
        } catch (RuntimeException $refusal) {
            // The window-ordering refusal and the already-ended one, both of
            // which name the instants involved. Printed rather than thrown: an
            // operator typing a date is the one reader who can act on it, and a
            // stack trace is not the way to tell them they typed yesterday.
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }

        if ($closed === 0) {
            // ⚠️ A TYPO'D KEY CLOSES NOTHING AND MUST NOT REPORT SUCCESS. The
            // whole point of this command is that somebody believes the window
            // is shut afterwards; exiting 0 on "no such offer" is how they would
            // go on believing it while every signup is still sold at the founder
            // rate.
            $this->error("No offer is stored under the key `{$key}`, so nothing was closed.");

            return self::FAILURE;
        }

        if ($reopen) {
            // ⛔ SAID PLAINLY, BECAUSE IT IS THE OPPOSITE OF WHAT THIS COMMAND IS
            // NAMED FOR. Somebody who reaches for `--reopen` to silence a refusal
            // has just put a price back on sale, and the line they read
            // afterwards is the last chance to notice.
            $this->warn("`{$key}` had already ended and has been re-opened until {$at->toIso8601String()}.");
        }

        $this->info("Closed {$closed} ".str('row')->plural($closed)." of `{$key}` at {$at->toIso8601String()}.");

        if ($at->isFuture()) {
            // ⚠️ THE HONEST SENTENCE FOR A DATED FLIP. The line below claims the
            // retail schedule is being quoted from now on, and for a future date
            // that is false for every instant until it — R18's announced flip is
            // exactly this case, so the message it produces must not read as
            // "done".
            $this->warn(
                "The window is STILL LIVE until then: `{$key}` goes on being quoted until "
                .$at->toIso8601String().'.'
            );

            return self::SUCCESS;
        }

        $this->line('The next quote on those terms is the retail schedule. Rows already sold keep their agreed price (3443).');

        return self::SUCCESS;
    }

    /**
     * What the operator is about to stop quoting, in their own units.
     *
     * ⚠️ **THE ONE CALLER OF `PlanOffers::currentFor()` IN `app/`** — it had
     * none, which is the defect this branch found and deleted one file over at
     * 4329. It earns its place here rather than being called to justify itself:
     * a person ending a price window should see the prices they are ending
     * before the row moves, and afterwards the offer is no longer quotable.
     */
    private function preview(PlanOffers $offers, string $key): void
    {
        foreach (BillingTerm::cases() as $term) {
            try {
                $live = $offers->currentFor($term);
            } catch (AmbiguousPlanOffer $collision) {
                // Two offers live on one term is exactly the state this command
                // is the way out of, so it warns and closes rather than
                // refusing — refusing here would leave the operator with the
                // broken price table and no tool.
                $this->warn($collision->getMessage());

                continue;
            }

            if (! $live instanceof PlanOffer || $live->key !== $key) {
                continue;
            }

            $this->line(
                "  {$term->value}: ".PlanPricing::format($live->price())
                .' base, '.PlanPricing::format($live->additionalLocationPrice()).' per extra location'
            );
        }
    }

    /**
     * The instant to close at, or null when the option cannot be read.
     *
     * ⚠️ **AN UNPARSEABLE `--at` IS REFUSED RATHER THAN TREATED AS NOW.**
     * `Carbon::parse()` throws on nonsense, and catching it into "now" would
     * turn a mistyped announced date into an immediate flip — the price change
     * happening today instead of on the day that was announced.
     */
    private function closingInstant(): ?Carbon
    {
        $option = $this->option('at');

        if (! is_string($option) || $option === '') {
            return Carbon::now();
        }

        try {
            return Carbon::parse($option);
        } catch (Throwable) {
            $this->error("`{$option}` is not an instant this command can read. Use an ISO 8601 timestamp.");

            return null;
        }
    }
}
