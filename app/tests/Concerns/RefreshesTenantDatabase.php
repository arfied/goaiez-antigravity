<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RefreshDatabase, adjusted for the two-role database.
 *
 * Laravel's own RefreshDatabase runs `migrate:fresh` on the *default* connection.
 * Here the default connection is the runtime role — a deliberately non-owner role
 * that cannot create tables, because PostgreSQL does not apply row-level security
 * to a table's owner unless the table is also FORCEd. So stock RefreshDatabase
 * cannot work in this project: it would fail on the first CREATE TABLE.
 *
 * The split this trait maintains:
 *
 *   schema   created by the owner role   (pgsql_migrate) — DDL is allowed
 *   tests    executed as the runtime role (pgsql)        — RLS applies
 *
 * That second line is the point. If tests ran as the owner they would pass while
 * proving nothing about isolation, because RLS would not be constraining them.
 * The schema still has to come from somewhere, hence the first line.
 *
 * Everything else is inherited: the migration runs once per process (guarded by
 * RefreshDatabaseState), each test is wrapped in a transaction on the runtime
 * connection, and the rollback is registered via beforeApplicationDestroyed.
 *
 * Specification: docs/DECISIONS.md 131-134, docs/BUILD-PLAN.md §2.2,
 * CLAUDE.md "Engineering". Provisioning: database/db-bootstrap.sql.
 * Verified by tests/Feature/TestDatabaseLifecycleTest.php.
 */
trait RefreshesTenantDatabase
{
    use RefreshDatabase {
        refreshDatabase as private refreshDatabaseUsingLaravel;
    }

    /**
     * The name every PostgreSQL connection is pointed at before anything connects.
     *
     * Laravel calls this from `setUpTraits()`, once per test, before the migration
     * and before `beginDatabaseTransaction()` — which is the only window where the
     * connection can still be repointed without disturbing a live transaction.
     */
    public function refreshDatabase(): void
    {
        self::useTestDatabase();

        $this->refreshDatabaseUsingLaravel();
    }

    /**
     * The test database that belongs to a configured one: `goaiez` → `goaiez_test`.
     *
     * ⚠️ **THIS DERIVES RATHER THAN REFUSING, AND THE DIFFERENCE IS WHERE THE
     * DEVELOPER'S DATABASE IS.** `phpunit.xml` used to pin `DB_DATABASE=goaiez_test`
     * for everyone; unpinning it (decision 442) fixed four worktrees sharing one
     * tracked config and, on a primary checkout, moved the name to `.env` — **the
     * same file the dev server, `artisan`, and `tinker` read.** So the advice the
     * refusal gave, *"set DB_DATABASE in .env"*, is advice to point the running
     * application at the database the suite drops on every run. Correct in a
     * worktree, where `.env` is disposable; destructive in the checkout somebody
     * develops in.
     *
     * Deriving needs neither. A worktree's `goaiez_wt4` and CI's `goaiez_test`
     * already satisfy the predicate and are returned untouched; a primary
     * checkout's `goaiez` yields `goaiez_test` without `.env` mentioning tests at
     * all, and the dev server keeps its own database.
     */
    public static function derivedTestDatabaseName(string $name): string
    {
        return self::isTestDatabaseName($name) ? $name : $name.'_test';
    }

    /**
     * Point both roles' connections at the test database.
     *
     * Both must move together. `migrate:fresh` runs on `pgsql_migrate` and every
     * test body runs on `pgsql`, so repointing one alone would build the schema in
     * one database and assert against another — and that failure would surface as
     * a missing table rather than as anything naming a database.
     *
     * ⚠️ **NOTHING IS PURGED HERE, AND THE FIRST VERSION OF THIS DID PURGE.**
     * `DB::purge()` looks like the way to make a config rewrite take effect on an
     * already-resolved connection, and `tests/Pest.php` already records why it is
     * not: the suite holds its per-test transaction on `pgsql`, so purging
     * disconnects and rolls that back underneath whatever is running. Measured
     * rather than reasoned — with the purge, eight tests in `PlaceResolverTest`
     * failed on a missing table **against the correct database**; without it, all
     * 2,141 pass. The rewrite needs no help: connections resolve lazily and this
     * runs before the first query of every test.
     *
     * What replaces the purge is proof rather than a bet. `TestDatabaseLifecycleTest`
     * asks the live connection for `current_database()`, not `config()` — so if
     * anything ever does connect ahead of this, the assertion reddens instead of
     * the suite quietly running somewhere else.
     */
    protected static function useTestDatabase(): void
    {
        foreach (['pgsql', 'pgsql_migrate'] as $connection) {
            $configured = (string) config("database.connections.{$connection}.database");

            self::refuseUnderivableDatabase($connection, $configured);

            $derived = self::derivedTestDatabaseName($configured);

            if ($derived === $configured) {
                continue;
            }

            config(["database.connections.{$connection}.database" => $derived]);

        }
    }

    /**
     * The two states a name cannot be derived from, each refused rather than guessed.
     *
     * An empty name would derive to `_test`, which is a database nobody has and a
     * message nobody can act on. And a connection carrying a `url` is the sharper
     * one: PostgreSQL's connector prefers the DSN over the individual keys, so the
     * rewrite above would apply, look correct in `config()`, and the suite would
     * connect somewhere else entirely — **the one failure this whole mechanism
     * exists to prevent, wearing the mechanism's own green tick.** `phpunit.xml.dist`
     * sets `DB_URL` empty for this reason; this is what happens if that changes.
     */
    protected static function refuseUnderivableDatabase(string $connection, string $database): void
    {
        if ($database === '') {
            throw new RuntimeException(
                "Connection '{$connection}' names no database, so no test database can be "
                .'derived from it. Set DB_DATABASE to the database this checkout develops '
                .'against — the suite appends `_test` to it and never touches the original.',
            );
        }

        if ((string) config("database.connections.{$connection}.url") !== '') {
            throw new RuntimeException(
                "Connection '{$connection}' is configured with a DB_URL, which PostgreSQL's "
                .'connector prefers over the database name — so the suite would silently run '
                .'against whatever that URL names, test database or not. Unset DB_URL for '
                .'test runs; phpunit.xml.dist already sets it empty.',
            );
        }
    }

    /**
     * Options passed to `migrate:fresh`.
     *
     * Mirrors CanConfigureMigrationCommands::migrateFreshUsing() with two
     * changes, both load-bearing.
     *
     * @return array<string, bool|string>
     */
    protected function migrateFreshUsing(): array
    {
        self::refuseNonTestDatabase();

        $seeder = $this->seeder();

        return array_merge(
            [
                // Migrate as the schema owner. Without this the runtime role
                // attempts the DDL and the whole suite fails at the first table.
                '--database' => 'pgsql_migrate',

                '--drop-views' => $this->shouldDropViews(),

                // Never true, and not configurable per test case on purpose.
                // `db:wipe --drop-types` drops user-defined types, which includes
                // the `vector` type that CREATE EXTENSION installed — so enabling
                // it would take pgvector down with it, and the failure would
                // surface as a confusing error in an unrelated migration rather
                // than as anything pointing back here. pgvector is provisioned
                // once by db-bootstrap.sql, outside the migration lifecycle,
                // because CREATE EXTENSION needs a superuser and the owner role
                // deliberately is not one.
                '--drop-types' => false,
            ],
            $seeder ? ['--seeder' => $seeder] : ['--seed' => $this->shouldSeed()],
        );
    }

    /**
     * Is this a database the suite is allowed to destroy?
     *
     * The convention on this machine, and the only two shapes that exist: CI's
     * `goaiez_test`, and a worktree's `goaiez_wtN` provisioned by
     * `new-worktree.sh`. Anything else is refused.
     *
     * ⚠️ **A NEW NAMING CONVENTION MEANS EXTENDING THIS, AND THE FAILURE IS
     * LOUD.** That is the trade being made: an over-strict guard stops a run
     * with a message naming the database, and an over-lax one drops somebody's
     * data. The first is a minute, the second has no undo.
     */
    public static function isTestDatabaseName(string $name): bool
    {
        return (bool) preg_match('/(?:^|_)(?:test|wt\d+)$/', $name);
    }

    /**
     * Refuse to run `migrate:fresh` against a database that is not a test database.
     *
     * ⚠️ **`useTestDatabase()` IS THE PROTECTION NOW, AND THIS IS THE TRIPWIRE
     * BEHIND IT.** Deriving the name means the suite never connects to the
     * developer's own database in the first place, so on the ordinary path this
     * throw is unreachable — every name reaching it has already been rewritten.
     * That is stated rather than left to be discovered, because a guard described
     * as the thing keeping you safe, while something upstream is what actually
     * does it, is how the upstream half gets deleted as redundant (decision 398).
     *
     * It stays because it sits on the one call that is destructive. `migrate:fresh`
     * drops every table before it migrates, and it runs once per process, before
     * any test body — so if a later change reorders the derivation, adds a third
     * connection, or overrides `refreshDatabase()` without it, this is what stops
     * the drop. Checking in `TestCase::setUp()` would be too late: `parent::setUp()`
     * is what triggers the refresh.
     *
     * The production checkout cannot reach this at all — it is a `--no-dev`
     * install with no Pest in it (decision 440) — so this guards a developer's
     * own machine, which is where the mistake is actually available.
     */
    protected static function refuseNonTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (self::isTestDatabaseName($database)) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run migrate:fresh against '{$database}': it is not a test database, "
            .'and the suite would drop every table in it. Expected a name ending in `_test` or '
            .'`_wtN`. Reaching this means useTestDatabase() did not run or did not cover this '
            .'connection — the name should have been derived before anything connected.',
        );
    }

    // connectionsToTransact() is deliberately not overridden. Its default is
    // [config('database.default')] — the runtime role — which is exactly right:
    // the transaction, and therefore every query the test makes, belongs to the
    // role that RLS constrains.

    /**
     * Start every test with no tenant established.
     *
     * Runs after the per-test transaction has begun. Laravel gives each test a
     * fresh application, so `Context` starts empty — but `app.business_id` lives
     * in the PostgreSQL session, which is the same connection across the whole
     * run and knows nothing about PHPUnit.
     *
     * PostgreSQL does roll a session-level `set_config` back with its
     * transaction, so in practice the previous test's tenant is already gone by
     * the time this runs. That is a guarantee worth not depending on silently:
     * a test that began asserting isolation while inheriting a tenant would pass
     * for the wrong reason, and nothing else would catch it.
     */
    protected function afterRefreshingDatabase(): void
    {
        Tenancy::forget();
        DB::select("SELECT nextval('businesses_id_seq') FROM generate_series(1, 10000)");
    }
}
