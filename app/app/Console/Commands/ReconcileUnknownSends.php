<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Campaigns\UnknownSendReconciler;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The trigger for {@see UnknownSendReconciler} — 7066's lookup, on a clock.
 *
 * ⛔ **THE VENDOR'S WINDOW IS FORTY-EIGHT HOURS FROM THE ATTEMPT, SO A
 * RECONCILER WITH NO SCHEDULE IS A RECONCILER THAT NEVER RUNS IN TIME.** This
 * is `RunDueCampaigns`' own lesson one lane over: that command exists because
 * `RunCampaignJob` had no dispatcher and the suite was green throughout
 * (272's shape, with the writer missing). A service that can only be reached by
 * `tinker` would have all of this slice's machinery and none of its value.
 *
 * ## Why a sweep over every tenant rather than a job dispatched at the failure
 *
 * ⚠️ **BECAUSE THE ANSWER IS NOT AVAILABLE AT THE MOMENT OF THE FAILURE.** A
 * job queued from `markUnknown()` would ask the carrier about a message the
 * carrier has not finished writing to its own log, and its one retry ladder
 * would have to span up to two days. The window is long, the question is cheap
 * and idempotent, and asking again next hour costs one HTTP GET — so a sweep is
 * the shape, exactly as it is for the deferrals `campaigns:run-due` returns to.
 *
 * ## What it deliberately does not check
 *
 * ⛔ **NOT `sms.enabled`, NOT THE CAMPAIGN KILL SWITCH, NOT A TENANT PAUSE.**
 * Every one of those exists to stop **outbound messaging to other people**, and
 * this command sends nothing, charges nothing and creates nothing — it reads our
 * own account's log about messages that already left. `PlatformTexter`'s
 * `alertOperator()` makes the same call for the same reason and states it: the
 * moment sending is halted is the moment somebody most wants to know what
 * became of the messages already out. **The off switch is the schedule.**
 *
 * ⚠️ **AND THE `log` DRIVER MAKES IT A NO-OP WITHOUT A SWITCH.**
 * `LogTexter::outcomesFor()` answers nothing about everything, so on any
 * deployment that has never reached a carrier this walks the tenants, asks, and
 * correctly confirms none.
 */
#[Signature('campaigns:reconcile-unknown')]
#[Description('Ask the carrier what became of campaign sends whose outcome could not be established')]
final class ReconcileUnknownSends extends Command
{
    public function handle(): int
    {
        $confirmed = 0;
        $undelivered = 0;
        $unresolved = 0;

        // The enumeration follows `campaigns:run-due`, which follows
        // `reviews:reinvite` and `oauth:refresh-tokens`, and the reasoning is
        // theirs: this runs outside any tenant, `businesses` is FORCE ROW LEVEL
        // SECURITY on a policy keyed to the session tenant, so `Business::all()`
        // returns nothing and dropping a global scope does not help because the
        // policy is in the database. Reaching each business through its owner
        // grants this sweep no privilege a logged-in owner does not have.
        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$confirmed, &$undelivered, &$unresolved): void {
                foreach ($users as $user) {
                    foreach ($this->businessesOf((int) $user->getKey()) as $businessId) {
                        Tenancy::set($businessId);

                        $counts = app(UnknownSendReconciler::class)->reconcile();

                        $confirmed += $counts['confirmed'];
                        $undelivered += $counts['undelivered'];
                        $unresolved += $counts['unresolved'];
                    }
                }
            });

        // Never leave a security context established after a console command.
        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        // ⚠️ **ALL THREE COUNTS ARE PRINTED, INCLUDING THE ONE THAT DID
        // NOTHING.** `unresolved` is the number this command exists to watch:
        // a figure that never falls is a vendor that is not adopting our
        // `messageId` at all, and it is the only symptom that failure has.
        //
        // ⛔ **TWO OF THE THREE ARE COUNTS OF CONTACTS AND THE MIDDLE ONE ONLY
        // BECAME ONE AT 7501** (7505). A confirmed row has always left the sweep
        // by its status; a failed one now leaves it by `carrier_answered_at`, so
        // neither can be counted twice in any later pass — whereas the old
        // `refused` figure counted the same handful of contacts once an hour for
        // two days and read exactly like a number of people. **`unresolved` is
        // deliberately still per-pass**: it is the size of the open question,
        // and the same row appearing in it again next hour is the point.
        $this->info($confirmed + $undelivered + $unresolved === 0
            ? 'No unconfirmed campaign sends are waiting on the carrier.'
            : "Confirmed {$confirmed}, did not arrive {$undelivered}, still unresolved {$unresolved}.");

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return list<int>
     */
    private function businessesOf(int $userId): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity `ResolveTenant`
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        return array_values(
            Business::withoutGlobalScopes()
                ->where('owner_user_id', $userId)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all()
        );
    }
}
