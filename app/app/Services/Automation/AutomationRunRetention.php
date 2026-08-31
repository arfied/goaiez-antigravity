<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Console\Commands\PruneAutomationRuns;
use App\Enums\AutomationRunStatus;
use App\Jobs\Reviews\PostReplyJob;
use App\Models\AutomationRun;
use App\Services\Config\DefaultsRegistry;
use App\Services\Tenant\TenantDeletion;
use App\Services\Visibility\VisibilitySyncHistory;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * Deletes finished `automation_runs` rows past the period an operator has
 * stated — decisions 10184, 10185, 10260, closing what wave 34 refused to
 * build.
 *
 * ## ⛔ Why wave 34 refused this, and why the refusal was the correct order
 *
 * `automation_runs` is the only exhaustive record of what every one of this
 * platform's 142 automations did — ~219 rows a day for a single connected
 * location, from the clock alone, and about 97 a day for an account that has
 * connected nothing (decision 10187). ⛔ **Three readers key on the ABSENCE of a
 * row, not merely on its content**: {@see VisibilitySyncHistory::searchAbsence()},
 * `competitorAbsence()` and `reviewAbsence()` all answer *"we have never asked
 * Google about this"* when {@see AutomationRun::query()} finds nothing for a
 * location. A pruner that deleted purely on age would turn that sentence false
 * for every location older than the horizon, on Home and on the Google reviews
 * screen, with the remedy pointed at the tenant rather than at the sweep that
 * broke it. **A survivor rule with no pruner to survive is a control with no
 * writer** (10185) — so the two ship together, in this class, and neither ships
 * alone.
 *
 * ## The survivor rule — 9934's shape, applied here
 *
 * ⛔ **THE NEWEST TERMINAL RUN OF EVERY (`location_id`, `automation_key`) PAIR
 * IS KEPT FOR EVER, REGARDLESS OF AGE.** {@see self::survivorIds()} is the same
 * shape as `App\Services\Ops\PlatformHealth::latestBeatIds()` — *a counter may be
 * pruned, a state may not* — applied to the table wave 32 lane C's own pattern
 * was written for. `VisibilitySyncHistory::lastRun()` already asks for exactly
 * this row (`ORDER BY started_at DESC NULLS LAST, id DESC LIMIT 1`), so nothing
 * in that class needed to change: what had to move is the guarantee that the row
 * it asks for is always there.
 *
 * ⚠️ **`Running` IS NEVER A CANDIDATE FOR DELETION, SURVIVOR OR NOT.** A running
 * row is an open claim — `AutopilotJob::claimRun()`'s unique
 * `(business_id, automation_key, idempotency_key)` index is what stops a second
 * dispatch running the same work twice — and deleting it would let a duplicate
 * dispatch through the very index built to refuse one. It is excluded from both
 * the delete's predicate and the survivor computation, because a `Running` row
 * with no terminal sibling for its group already survives the delete's own
 * `finished_at IS NOT NULL` shape (a running row's `finished_at` is null) and
 * asking it to also win `DISTINCT ON` would let one worker's still-open attempt
 * mask another location's finished one in the same group — impossible under
 * today's schema (`location_id` participates in the group), but not a
 * distinction this class should have to reason about at 3am.
 *
 * ## Grouped for `reply_id` too, not `location_id` and `automation_key` alone
 *
 * ⛔ **THE THREE READERS `VisibilitySyncHistory` PROTECTS ARE LOCATION-LEVEL
 * AUTOMATIONS — ONE RUN PER LOCATION PER SWEEP — AND `reviews.post_reply` IS
 * NOT.** `Jobs\Reviews\PostReplyJob` dispatches once per **reply**, many replies
 * share one location, and `Services\Reviews\ReplyPublicationStatus` (10188)
 * reads the latest post_reply run **for one reply**. A survivor rule keyed on
 * `(location_id, automation_key)` alone would keep only the single most recent
 * post_reply attempt at a location — whichever reply that happened to be — and
 * silently drop the record of every other reply's own last attempt the moment a
 * period is set. `reply_id` is a generated column (`input->>'reply_id'`,
 * COALESCE'd with `output->>'reply_id'` — the two places
 * {@see PostReplyJob::input()} and
 * {@see PostReplyJob::unpublished()} write it), so it is
 * `NULL` for every automation that is not `reviews.post_reply` — grouping by it
 * changes nothing for the three location-level automations (`NULL` groups with
 * `NULL` under `DISTINCT ON`, exactly as if the column were absent) and gives
 * `reviews.post_reply` one survivor **per reply** instead of one per location.
 *
 * ## The door this class cannot guard — deleting a `Location`
 *
 * ⛔ **`automation_runs.location_id` IS `cascadeOnDelete()` TOO** (the creating
 * migration, `:30`), and a foreign key deletes with no PHP call site — this
 * class's survivor rule is never consulted. Deleting one `Location` destroys
 * every survivor row above for it in the same statement. `business_id`'s
 * identical cascade is correct — {@see TenantDeletion}
 * is an erasure and should take everything with it — and `location_id`'s is
 * untested rather than wrong, because **no file in `app/` deletes a Location
 * today** (wave 37 lane E, decision 10500). {@see
 * \Tests\Feature\Architecture\RetentionTest} carries a census that fails the
 * build the day one is written with no argument beside it for why the
 * cascade is safe for that caller.
 *
 * ## The tenant boundary
 *
 * ⛔ **FAIL-CLOSED ON THE TENANT IN CONTEXT, WITH NO BUSINESS ID PARAMETER** —
 * `StorageRetention`'s argument applies unchanged. `AutomationRun::query()`
 * carries `BelongsToTenant`'s global scope, which calls
 * {@see Tenancy::idOrFail()} and throws rather than running unscoped, so a
 * caller with no tenant established cannot reach a query at all — never mind a
 * delete. `automation_runs` is `ENABLE`+`FORCE` row-level secured on
 * `app.business_id` beneath that, so a raw statement issued with no tenant set
 * would in any case match zero rows and report success — the silent failure
 * shape this codebase keeps finding, and the reason {@see PruneAutomationRuns}
 * walks owners rather than issuing one platform-wide delete.
 */
final class AutomationRunRetention
{
    /**
     * The registry key holding how many days a finished run is kept.
     */
    public const string RETENTION_KEY = 'automation.retention_days';

    /**
     * Rows per batch. `PlatformHealth::CHUNK`'s figure, for the same reason —
     * a limited DELETE on an indexed predicate that matches nothing on an
     * ordinary night.
     */
    private const int CHUNK = 500;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * How many days a finished run is kept, or null when nobody has said.
     *
     * ⛔ **NULL IS THE ANSWER AND IT MEANS "DELETE NOTHING"** —
     * `StorageRetention::periodFor()`'s rule, applied here for the same reason:
     * the conservative direction on a deletion schedule is to delete less, and
     * reading an unset period as zero would delete every finished automation run
     * on this platform's first scheduled sweep after this class ships.
     *
     * ⚠️ **`intOr($key, 0)` RATHER THAN `int()`**, because there is no seed to
     * fail closed to on a figure the owner has not stated — `MailQuota::ceiling()`'s
     * split, applied here identically.
     */
    public function retentionDays(): ?int
    {
        $stated = $this->defaults->intOr(self::RETENTION_KEY, 0);

        return $stated > 0 ? $stated : null;
    }

    /**
     * The id of the newest terminal run of every (`location_id`,
     * `automation_key`, `reply_id`) group, for the tenant in context.
     *
     * ⚠️ **THE SAME ORDERING `VisibilitySyncHistory::lastRun()` USES**,
     * `started_at DESC NULLS LAST, id DESC` — because a survivor that is not
     * the row that method would itself pick is a survivor rule protecting the
     * wrong row. The grouping columns (`location_id`, `automation_key`,
     * `reply_id`) are ascending, which is `ConventionsTest`'s own
     * `no_descending_order_relies_on_Postgres_putting_NULLs_first` — spelled
     * `DESC` with no `NULLS LAST` beside it fails the build, and direction is
     * irrelevant to `DISTINCT ON`'s own grouping, only to which row within a
     * group wins, which the three columns after it decide.
     *
     * @return list<int>
     */
    public function survivorIds(): array
    {
        $ids = [];

        foreach (
            AutomationRun::query()
                ->selectRaw('distinct on (location_id, automation_key, reply_id) id')
                ->where('status', '!=', AutomationRunStatus::Running)
                ->orderBy('location_id')
                ->orderBy('automation_key')
                ->orderBy('reply_id')
                ->orderByRaw('started_at desc nulls last')
                ->orderByDesc('id')
                ->pluck('id') as $id
        ) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * Delete finished runs past the stated period, keeping every survivor.
     *
     * ⚠️ **A FAILED DELETE IS NOT A FAILED RUN** — `SendingHealth::prune()`'s
     * rule (7630): the caller is an owner walk, and one tenant's lock
     * contention must not cost every account after it in the walk its sweep.
     * {@see PruneAutomationRuns} is what contains a throw from this method; this
     * method stays a plain throw-on-failure so a caller that is not walking
     * owners — a test, a future one-off command — sees the real exception
     * rather than a swallowed zero.
     *
     * ⚠️ **THE TENANT IS CHECKED EVEN ON THE PATH THAT OPENS NO QUERY** —
     * `StorageRetention::prune()`'s precedent, applied for the identical
     * reason: this throws rather than silently succeeding for a caller that
     * reached this method with no tenant established, whatever the stated
     * period is. Silently returning `0` for that caller would be
     * indistinguishable from an ordinary night with nothing to prune.
     */
    public function prune(?CarbonImmutable $now = null): int
    {
        // Fail-closed. No argument, and this throws rather than sweeping
        // whatever the process happened to be looking at.
        Tenancy::idOrFail();

        $days = $this->retentionDays();

        if ($days === null) {
            // ⛔ THE LINE THE WHOLE CLASS IS ABOUT. No query opens, so there is
            // no path from an unset period to a delete.
            return 0;
        }

        $cutoff = ($now ?? CarbonImmutable::now())->subDays($days);
        $survivors = $this->survivorIds();

        $deleted = 0;

        do {
            // Through the model, so the tenant scope and row-level security
            // both sit under it. Postgres compiles a limited DELETE to
            // `delete from … where ctid in (select … limit N)` —
            // `PostgresGrammar::compileDeleteWithJoinsOrLimit()`, verified
            // rather than remembered — so `$survivors` is computed once, above
            // the loop, and passed as a fixed list: a correlated subquery here
            // would resolve `whereNotIn` against the inner, unaliased copy of
            // this same table and could not tell a survivor from anything else.
            $batch = AutomationRun::query()
                ->where('status', '!=', AutomationRunStatus::Running)
                ->whereNotNull('finished_at')
                ->where('finished_at', '<', $cutoff)
                ->whereNotIn('id', $survivors)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $batch;
        } while ($batch === self::CHUNK);

        return $deleted;
    }
}
