<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\TenantNotResolved;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * The tenant in context, and the one place that sets it.
 *
 * Two layers depend on this class agreeing with itself:
 *
 *   application   the global scope on every tenant-owned model reads id()
 *   database      every row-level security policy reads app.business_id
 *
 * They are set together, here, so they cannot drift. Setting one without the
 * other is the failure this class exists to make impossible: scope without
 * policy silently loses the second layer, policy without scope returns zero
 * rows and looks like a bug in whatever you were building.
 *
 * Context rather than a static property, because Context propagates into queued
 * jobs automatically — a job dispatched inside a request keeps its tenant
 * without anyone remembering to pass it. Hidden, because the tenant id is fine
 * in logs but Context's visible bag ends up in more places than logs.
 *
 * Specification: docs/DECISIONS.md 131-134, CLAUDE.md §Critical rules,
 * .claude/skills/multi-tenancy.
 */
final class Tenancy
{
    /**
     * Context key. Hidden keys stay out of log entries and serialized payloads.
     */
    private const KEY = 'tenancy.business_id';

    /**
     * The PostgreSQL session variable every RLS policy reads.
     */
    private const SESSION_VARIABLE = 'app.business_id';

    /**
     * Context key for the acting user.
     */
    private const USER_KEY = 'tenancy.user_id';

    /**
     * The session variable the `owner_lookup` policy on `businesses` reads.
     *
     * Separate from the tenant, and far narrower: it exists so an authenticated
     * user can find the business they own *before* a tenant is established,
     * which is otherwise circular. Nothing else reads it, and it grants no
     * write access — see the migration that adds that policy.
     */
    private const USER_SESSION_VARIABLE = 'app.user_id';

    /**
     * Establish the tenant for everything that follows on this request or job.
     */
    public static function set(int $businessId): void
    {
        Context::addHidden(self::KEY, $businessId);

        self::applyToDatabase((string) $businessId);
    }

    /**
     * The current tenant, or null when none is resolved.
     *
     * Callers that intend to query tenant-owned data want idOrFail() instead.
     * This exists for the handful of places that legitimately branch on whether
     * a tenant is present — middleware, console commands, health checks.
     */
    public static function id(): ?int
    {
        $id = Context::getHidden(self::KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * The current tenant, or throw.
     *
     * Fail closed. Never fall back to "all tenants", never to a default, never
     * return null and let a query run wide. A loud failure in a job is
     * recoverable; a silently unscoped query is a breach.
     */
    public static function idOrFail(): int
    {
        return self::id() ?? throw new TenantNotResolved;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    /**
     * Establish the acting user, so tenant resolution can happen at all.
     *
     * Set before the tenant, not instead of it. This unlocks exactly one query —
     * "which businesses does this user own" — and is what makes the `owner_lookup`
     * policy resolvable. It is not an authorization mechanism and must never be
     * treated as one.
     */
    public static function setUser(int $userId): void
    {
        Context::addHidden(self::USER_KEY, $userId);

        self::applyToDatabase((string) $userId, self::USER_SESSION_VARIABLE);
    }

    public static function userId(): ?int
    {
        $id = Context::getHidden(self::USER_KEY);

        return is_int($id) ? $id : null;
    }

    public static function forgetUser(): void
    {
        Context::forgetHidden(self::USER_KEY);

        self::applyToDatabase('', self::USER_SESSION_VARIABLE);
    }

    /**
     * Clear the tenant, and close the database down with it.
     *
     * Resetting the session variable to an empty string matters as much as
     * clearing the context: an RLS policy comparing business_id against an
     * empty setting matches nothing, so a connection left over from a previous
     * tenant returns zero rows rather than that tenant's rows.
     */
    public static function forget(): void
    {
        Context::forgetHidden(self::KEY);

        self::applyToDatabase('');
    }

    /**
     * Clear both the tenant and the acting user.
     *
     * Use between requests and between tests. Clearing only one leaves a
     * half-established security context, which is harder to reason about than
     * either extreme.
     */
    public static function forgetAll(): void
    {
        self::forget();
        self::forgetUser();
    }

    /**
     * Run a callback as another tenant, restoring the previous one afterwards.
     *
     * For jobs, console commands, and tests — not a way around the boundary in
     * request code. The restore is in a finally block so a throwing callback
     * cannot leave the wrong tenant established.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function actingAs(int $businessId, callable $callback): mixed
    {
        $previous = self::id();

        self::set($businessId);

        try {
            return $callback();
        } finally {
            $previous === null ? self::forget() : self::set($previous);
        }
    }

    /**
     * Run a callback as another *user*, restoring the previous one afterwards.
     *
     * ⚠️ THIS ANSWERS ONE QUESTION AND MUST NEVER BE USED TO ANSWER ANOTHER:
     * "which businesses does this person own". That is the whole of what the
     * `owner_lookup` policy admits — a permissive `FOR SELECT` on `businesses`,
     * keyed on `owner_user_id`, granting no write access and reaching no other
     * table. Everything else in the schema is keyed on `app.business_id` and is
     * unaffected by this call.
     *
     * It exists because the support console cannot ask that question any other
     * way. `Tenancy::actingAs()` needs the business id, which is precisely what
     * an agent holding an email address does not have — the same circularity
     * `owner_lookup` was written for, one caller further out.
     *
     * ⚠️ IT IS NOT AN AUTHORIZATION MECHANISM, exactly as setUser() says, and
     * the temptation here is stronger because the caller is staff: "act as this
     * user" reads like a grant. It is a lookup key. The gate above it decides
     * who may call it, and an ArchitectureTest lint names the one service that
     * may.
     *
     * Restored in a finally block, like actingAs(), so a throwing callback
     * cannot leave a staff session pointed at a customer's identity.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function actingAsUser(int $userId, callable $callback): mixed
    {
        $previous = self::userId();

        self::setUser($userId);

        try {
            return $callback();
        } finally {
            $previous === null ? self::forgetUser() : self::setUser($previous);
        }
    }

    /**
     * Push the tenant into the PostgreSQL session.
     *
     * set_config's third argument is `is_local`. False means session-scoped
     * rather than transaction-scoped, which is deliberate: a request does not
     * run inside one transaction, so a transaction-scoped setting would be gone
     * by the time the first query ran.
     *
     * Session scope means the value outlives a request if a connection is ever
     * reused — under a pooler, or Octane. That is safe here only because every
     * entry point sets it unconditionally, so a stale value is always
     * overwritten before it can be read. Do not weaken that: this method must
     * stay the only writer, and it must never be made conditional on the
     * current value.
     */
    private static function applyToDatabase(string $value, string $variable = self::SESSION_VARIABLE): void
    {
        try {
            DB::connection('pgsql')->statement(
                'SELECT set_config(?, ?, false)',
                [$variable, $value],
            );
        } catch (QueryException $e) {
            // 25P02, in_failed_sql_transaction: an earlier statement in this
            // transaction failed, so PostgreSQL refuses everything until it is
            // rolled back. This happens on the way *out* of a failed operation —
            // an RLS violation aborts the transaction, then actingAs()'s restore
            // runs here and would throw a second, less interesting exception
            // that buries the first.
            //
            // Ignoring it is safe rather than merely convenient: in that state no
            // query can run at all, so the session variable cannot affect
            // anything, and the rollback restores its previous value regardless —
            // a session-level set_config is itself transactional in PostgreSQL.
            //
            // Any other failure means the tenant boundary could not be set, which
            // is never something to swallow.
            if (SqlState::of($e) !== '25P02') {
                throw $e;
            }
            DB::connection('pgsql')->rollBack();
        }
    }
}
