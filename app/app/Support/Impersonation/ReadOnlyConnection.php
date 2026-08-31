<?php

declare(strict_types=1);

namespace App\Support\Impersonation;

use App\Services\Impersonation\Impersonation;
use App\Support\SqlState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The database's own refusal to let a view-only support session write.
 *
 * ## Why this exists when the middleware already refuses non-GET requests
 *
 * Because **a GET writes in this codebase, by design**. Decision 387 made
 * `GET /f/{slug}/to/{destination}` record a `destination_clicks` row before
 * redirecting, because a JavaScript beacon records nothing with scripting off
 * and *the hole is silent*. So `28` §9.4's "GET-only, middleware-enforced" and
 * the thing it is trying to guarantee — that a diagnostic session changes
 * nothing — came apart the moment that route shipped, eleven weeks before
 * anybody wrote this class. A support agent reproducing a customer's complaint
 * by walking their feedback page would have written click rows into the
 * tenant's own data, and those rows are the numbers the product reports on.
 *
 * This is the second layer, in the same relationship to the method check that
 * RLS has to the global scope: it catches the day the first one is wrong,
 * and it does not excuse the first one being there.
 *
 * ## Mechanics, and the trap
 *
 * `SET default_transaction_read_only = on` makes every subsequent transaction
 * on this connection refuse INSERT, UPDATE, DELETE and DDL with SQLSTATE 25006.
 * It is **session-scoped**, exactly like `app.business_id` — which means it
 * outlives the request whenever a connection is reused, under a pooler or
 * Octane.
 *
 * ⚠️ `Tenancy` states the invariant this borrows: *every entry point sets it
 * unconditionally, so a stale value is always overwritten before it can be
 * read.* Here the stakes are inverted and worse. A stale tenant returns the
 * wrong rows; a stale read-only flag makes an ordinary request fail to write,
 * on a page nobody has touched, with an error naming a transaction property no
 * feature in this application sets. **`clear()` therefore runs unconditionally
 * at the top of the middleware and again in a `finally`**, and the second is
 * not redundant with the first: the first protects this request from the last
 * one, and the second protects the next request from this one throwing.
 *
 * ## ⚠️ Why this is `default_transaction_read_only` and not `transaction_read_only`
 *
 * Both were measured against this database rather than assumed, and they differ
 * in exactly the way that decides it:
 *
 *   `default_transaction_read_only`  applies to every transaction started
 *                                    *after* it is set, and not to one already
 *                                    open
 *   `transaction_read_only`          applies to the transaction already open —
 *                                    and **cannot be turned back off once a
 *                                    query has run** (`SQLSTATE 25001`,
 *                                    *"transaction read-write mode must be set
 *                                    before any query"*)
 *
 * The second is therefore unusable here: `clear()` would fail at the end of
 * every request that had an open transaction, and a failed `SET` aborts that
 * transaction, so the cleanup would destroy the request it was tidying up
 * after. The first is correct for a web request, which has no ambient
 * transaction — each statement gets its own, started after the flag is set, and
 * any `DB::transaction()` opened during the request is likewise started after.
 *
 * ## ⚠️ What no test in this repository can prove, and decision 352's wall
 *
 * `RefreshesTenantDatabase` wraps every test in a transaction that is open
 * *before* the request begins. `default_transaction_read_only` does not reach
 * it. So **a test cannot demonstrate that this layer refuses a write** — it can
 * only demonstrate that the flag is set, which is what
 * `ImpersonationTest` pins by reading the setting from inside a live request.
 *
 * That is the same wall decision 352 hit with `->afterCommit()`, and it is
 * stated for the same reason: a test *named* for enforcement while proving only
 * wiring is worse than no test, because the next person reads the name. The
 * enforcement itself is PostgreSQL's documented behaviour rather than ours, and
 * an earlier version of that test appeared to prove it while actually tripping
 * over an RLS policy on the table it was inserting into.
 */
final class ReadOnlyConnection
{
    /**
     * Refuse every write on this connection until it is cleared.
     */
    public static function engage(): void
    {
        self::set('on');
    }

    /**
     * Return the connection to normal.
     */
    public static function clear(): void
    {
        self::set('off');
    }

    /**
     * Run one write that is **ours about the session**, not the tenant's data,
     * with the read-only boundary lifted for exactly its duration (1996).
     *
     * ## ⚠️ THIS IS A HOLE IN A SECURITY LAYER AND IT IS DELIBERATE
     *
     * `Impersonating` already made this decision and achieved it by ordering
     * rather than by a method: *"Bookkeeping before the read-only flag, not
     * after: counting the page view is itself a write, and it is ours rather
     * than the tenant's. `28` §9.4 requires view-only page views be logged, so
     * a design where the read-only layer silently swallowed its own audit trail
     * would defeat the requirement it protects."* That sentence is the whole
     * argument for this method; the middleware can satisfy it by writing first
     * because it runs first, and {@see Impersonation::refuse()} cannot, because
     * it fires from inside a service call made long after the flag is on.
     *
     * ⚠️ **WITHOUT IT THE MOST SENSITIVE REFUSAL IS THE ONE THAT GOES
     * UNRECORDED.** An agent attempting a blocklisted capability in a
     * **view-only** session is the likeliest instance of the thing `28` §9.4
     * wants logged, and it is precisely the session whose connection cannot
     * write — so the naive audit line turns an intended 403 into a 500 with
     * SQLSTATE 25006, and nothing is recorded either way.
     *
     * ⚠️ **AND NO TEST IN THIS REPOSITORY CAN CATCH THAT.** The class docblock
     * above says why: `RefreshesTenantDatabase` opens its transaction *before*
     * the request, so `default_transaction_read_only` never reaches it and the
     * naive version passes green while failing in production. Decision 352's
     * wall, met by a defect rather than by a test — which is exactly why this
     * is a named method with this docblock instead of two lines somewhere.
     *
     * ⚠️ **HELD TO ONE CALLER BY A LINT** in
     * `tests/Feature/Architecture/StaffTest.php`. A second caller is how this
     * becomes "the way to write during a view-only session", and the layer is
     * then decoration. If you are reaching for it to write *tenant* data, the
     * answer is no — start an act-as session, which is attributed, ticketed and
     * notified.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $write
     * @return TReturn
     */
    public static function forOurOwnRecord(callable $write): mixed
    {
        $engaged = self::isEngaged();

        if (! $engaged) {
            return $write();
        }

        self::clear();

        try {
            return $write();
        } finally {
            // ⚠️ `finally`, for the reason the middleware gives about its own
            // second `clear()`: a throw on the way out must not hand the rest
            // of this request a writable connection. The caller here throws by
            // design — it is a refusal — so this path is the ordinary one.
            self::engage();
        }
    }

    /**
     * Whether the flag is currently set on this connection.
     *
     * Exists for the tests, and says so. It is the only claim about this layer
     * that this repository's harness can actually make — see the class docblock
     * — so it is a real method rather than a test-local `SHOW`, both because
     * the setting name should appear in one place and because a test reaching
     * for `SHOW default_transaction_read_only` by hand is one typo away from
     * asserting on a setting that does not exist and always reading `off`.
     */
    public static function isEngaged(): bool
    {
        $row = DB::connection('pgsql')->selectOne('SHOW default_transaction_read_only');

        return is_object($row) && (($row->default_transaction_read_only ?? 'off') === 'on');
    }

    private static function set(string $value): void
    {
        try {
            DB::connection('pgsql')->statement(
                'SET default_transaction_read_only = '.($value === 'on' ? 'on' : 'off')
            );
        } catch (QueryException $e) {
            // 25P02 only, and for the reason Tenancy::applyToDatabase() gives:
            // an earlier failed statement leaves the transaction refusing
            // everything, so this would throw a second, less interesting
            // exception on the way out of the first. In that state no query can
            // run at all, so the flag cannot affect anything either way.
            //
            // Any other failure means the write boundary could not be set,
            // which is never something to swallow — a silently-failed engage()
            // is a view-only session with write access.
            if (SqlState::of($e) !== '25P02') {
                throw $e;
            }
        }
    }
}
