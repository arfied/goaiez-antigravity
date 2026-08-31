<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `phi` schema — `29` §2 rule 24's separate-schema half, and the boundary
 * behind §12.1's gate line *"standard role cannot read `phi.*`"*.
 *
 * Rule 24 is three things: a separate schema, a separate role, and a separate
 * KMS key. This migration builds the first and only the first, and the two
 * `ArchitectureTest` tripwires that ship with it say who owes the other two.
 *
 * ⚠️ IT CREATES NO TABLES, ON PURPOSE. The gate line is a *privilege* claim,
 * not a data claim: it is satisfied by the runtime role being unable to reach
 * anything that lands here, whenever something does. Inventing a `phi.*` table
 * now would invent columns nobody has specified, and `29` §12.1's own HIPAA
 * gate ("values submitted pre-BAA are dropped server-side") is enforced at the
 * collector — which does not exist. `tests/Feature/Architecture/PhiTest.php` pins
 * the emptiness and enumerates everything the first table has to arrive with.
 *
 * ⚠️ AND IT CREATES NO ROLE. An earlier plan for this work put a third role,
 * `goaiez_phi`, into `database/db-bootstrap.sql`. It is deliberately not here:
 *
 *   - `db-bootstrap.sql` needs a PostgreSQL superuser (CREATE ROLE, CREATE
 *     DATABASE, CREATE EXTENSION), and no superuser credential is available in
 *     the development environment. Anything added there could not be applied to
 *     an existing database, so the suite would go red locally on a file nobody
 *     can run — and the pre-push gate would block every push after it.
 *   - `goaiez_owner` can CREATE SCHEMA, so the schema is an ordinary migration
 *     and needs no superuser at all.
 *   - A role with nothing reading it is decision 272's shape, which this
 *     codebase has now hit ten times: an object created by a schema slice, with
 *     tests proving it is *isolated* rather than *reachable*, and no writer.
 *     The role ships with the first `phi.*` table, when there is something for
 *     it to own.
 *
 * `IF NOT EXISTS` IS LOAD-BEARING. `db:wipe` drops the tables it can see, and
 * `config/database.php` pins `search_path` to `public` on both connections — so
 * nothing here is visible to it and this schema survives `migrate:fresh`. The
 * second `migrate:fresh` re-runs this migration against a schema that is still
 * there; without the guard it would fail, and the failure would look like a
 * broken migration rather than a working one.
 *
 * Specification: `29` §2 rule 24, `29` §12.1, CLAUDE.md "Privacy & data".
 * Provisioning context: `database/db-bootstrap.sql`.
 * Enforced by `tests/Feature/PhiSchemaIsolationTest.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // AUTHORIZATION names the owner explicitly rather than leaning on
        // current_user, matching db-bootstrap.sql, which hardcodes both role
        // names and is the file this one has to agree with.
        //
        // ⚠️ THE LITERAL HERE AND `database.connections.pgsql_migrate.username`
        // MUST NAME THE SAME ROLE, and nothing enforces it — PhiSchemaIsolation
        // Test reads the owner from config deliberately (a hardcoded assertion
        // would keep passing against a database provisioned with other roles),
        // so a deployment whose migrate role is not `goaiez_owner` fails this
        // statement outright, or worse, creates the schema owned by a role the
        // test then reports as wrong. Change one and change both.
        DB::statement('CREATE SCHEMA IF NOT EXISTS phi AUTHORIZATION goaiez_owner');

        // PostgreSQL 15+ grants PUBLIC nothing on a newly created schema, so
        // this is a restatement rather than a change — the same reasoning as
        // db-bootstrap.sql §4's REVOKE on `public`: a database restored from an
        // older dump, or a server with an altered template1, must not quietly
        // hand USAGE to every role.
        DB::statement('REVOKE ALL ON SCHEMA phi FROM PUBLIC');

        // ⚠️ goaiez_app IS GRANTED NOTHING HERE, AND THAT IS THE FEATURE.
        //
        // This is the whole of `29` §12.1's "standard role cannot read `phi.*`".
        // The runtime role has no USAGE on this schema, so every query it makes
        // against a phi object is refused with SQLSTATE 42501 before the object
        // is even resolved — which is why the refusal holds for tables that do
        // not exist yet as well as ones that do.
        //
        // It stays true by default for anything created later, because
        // db-bootstrap.sql's ALTER DEFAULT PRIVILEGES and its catch-up GRANTs
        // are both scoped `IN SCHEMA public`. A new phi table is therefore
        // unreachable the moment it is created, with nobody having to remember.
        //
        // If someone "fixes" this by adding `GRANT USAGE ON SCHEMA phi TO
        // goaiez_app`, the gate line is gone: the application would then reach
        // PHI on the same connection, under the same role, as every other
        // tenant's ordinary data — with no separate credential to revoke, no
        // separate audit trail, and rule 24's separate-role requirement
        // satisfied by nothing. PhiSchemaIsolationTest goes red on exactly that
        // grant; it is proven by mutation, so the red is real.
    }

    public function down(): void
    {
        // RESTRICT, NEVER CASCADE. A rollback must not be able to destroy PHI.
        // CASCADE would drop every table in the schema along with it, silently,
        // as a side effect of an unrelated migration being rolled back one step
        // too far. RESTRICT fails loudly the moment the schema has contents,
        // which is the correct outcome: whoever wants the schema gone has to
        // deal with what is in it deliberately.
        DB::statement('DROP SCHEMA IF EXISTS phi RESTRICT');
    }
};
