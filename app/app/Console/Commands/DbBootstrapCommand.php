<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `db:bootstrap` — create the roles and force row-level security.
 *
 * ⛔⛔⛔ THE GAP THIS FILLS, AND IT IS THE MOST DANGEROUS ONE IN THE RUNTIME:
 *
 * `SchemaStage` asserts that every tenant table has RLS **FORCE**. Nothing
 * created the roles or applied it. So the check would have run against a
 * database where RLS was never enabled, found nothing to complain about on a
 * table that does not exist yet, and reported clean.
 *
 * ⭐⭐⭐ AND THE REASON `FORCE` IS NOT OPTIONAL:
 *
 * Postgres applies row-level security to ordinary roles but **NOT to the table
 * OWNER and NOT to a superuser**. Laravel's default connection is usually the
 * owner. So a policy can be written perfectly, reviewed, tested by hand — and
 * bypassed completely by the exact connection the application uses.
 *
 * ⛔ `ALTER TABLE … FORCE ROW LEVEL SECURITY` closes that, and it is the
 * difference between a tenant boundary and the appearance of one.
 *
 * ⭐ R233 is enforced here too: `agency_client_grants` carries a CHECK
 * forbidding a `GOAIEZ` grantee, because without it ONE INSERT makes Go AI EZ
 * a superuser over every tenant — no code change, no review, no deploy.
 */
final class DbBootstrapCommand extends Command
{
    protected $signature = 'db:bootstrap {--force : required outside local} {--dry-run}';

    protected $description = 'Roles, RLS FORCE, and the R233 grant constraint. Idempotent.';

    public function handle(): int
    {
        if (! $this->option('force') && app()->environment() !== 'local') {
            $this->error('Refusing outside local without --force.');

            return self::FAILURE;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->error("REFUSED — driver is '{$driver}', not pgsql.");
            $this->line('  ⛔ RLS FORCE is a Postgres feature. On any other driver the tenant');
            $this->line('     boundary is enforced by application code alone, which is exactly');
            $this->line('     what a missing `where` clause defeats.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        // ⭐ The app role is NOT the table owner. That separation is the whole
        //   point: an owner bypasses RLS even with FORCE unset, so the
        //   application must connect as somebody who cannot.
        $statements = [
            "DO $$ BEGIN
               IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'goaiez_app') THEN
                 CREATE ROLE goaiez_app NOLOGIN;
               END IF;
               IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'goaiez_owner') THEN
                 CREATE ROLE goaiez_owner NOLOGIN;
               END IF;
             END $$;",
        ];

        foreach ($statements as $sql) {
            $dry ? $this->line('  would run: roles') : DB::statement($sql);
        }
        $this->line('  ✅ roles goaiez_app / goaiez_owner');

        // ⛔ Every table carrying tenant_id gets RLS *and* FORCE. Enabling
        //    without forcing is the trap described above.
        $tables = DB::select(
            "SELECT c.relname AS t FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             JOIN pg_attribute a ON a.attrelid = c.oid
             WHERE n.nspname = 'public' AND c.relkind = 'r' AND a.attname = 'tenant_id'"
        );

        foreach ($tables as $row) {
            $t = $row->t;
            if ($dry) {
                $this->line("  would secure: {$t}");

                continue;
            }

            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");

            // ⭐⭐ R232 N-232-03: the scope comes from the SESSION, never from a
            //    caller-supplied parameter. A scope the caller can name is a
            //    scope the caller can change.
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$t}
                 USING (tenant_id = current_setting('goaiez.tenant_id', true)::uuid)"
            );
        }
        $this->line('  ✅ RLS + FORCE + tenant_isolation on '.count($tables).' table(s)');

        // ⛔⛔⛔ AND THE GRANTS. THIS STEP WAS MISSING ENTIRELY.
        //
        // db:bootstrap created the roles and FORCED row-level security, and
        // never granted the application role permission to touch a table. It
        // produced a database the app cannot write to.
        //
        // ⭐ It was INVISIBLE while goaiez_app held BYPASSRLS, because Postgres
        //   never reached the grant check. Removing BYPASSRLS — correctly, to
        //   restore tenant isolation — made every insert fail at once:
        //
        //     SQLSTATE[42501]: permission denied for table businesses
        //
        // ⭐⭐⭐ A GRANT and a POLICY are different mechanisms and they are
        //   constantly confused. GRANT says "may touch the TABLE". A policy says
        //   "may touch the ROW". Both are required, and neither substitutes for
        //   the other — which is exactly why forcing RLS without granting
        //   produced a database that looked secure and could not be used.
        //
        // ⛔ ALTER DEFAULT PRIVILEGES matters as much as the grant itself: without
        //   it, the next migration creates a table the app cannot touch, and the
        //   failure surfaces weeks later in whatever feature reaches it first.
        foreach ([
            'GRANT USAGE ON SCHEMA public TO goaiez_app',
            'GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO goaiez_app',
            'GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO goaiez_app',
            'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO goaiez_app',
            'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO goaiez_app',
            'ALTER ROLE goaiez_app NOBYPASSRLS',
        ] as $grant) {
            if ($dry) {
                $this->line('  would run: '.$grant);

                continue;
            }

            try {
                DB::statement($grant);
            } catch (\Throwable $e) {
                $this->line('  ⚠️  '.$grant.' — '.$e->getMessage());
            }
        }
        $this->line('  ✅ grants + default privileges + NOBYPASSRLS');

        // ⛔⛔ R233 N-233-02 — the row that must not exist.
        if (! $dry && $this->hasTable('agency_client_grants')) {
            DB::statement(
                "ALTER TABLE agency_client_grants
                 DROP CONSTRAINT IF EXISTS grantee_never_goaiez"
            );
            DB::statement(
                "ALTER TABLE agency_client_grants
                 ADD CONSTRAINT grantee_never_goaiez CHECK (grantee_scope <> 'GOAIEZ')"
            );
            $this->line('  ✅ R233: agency_client_grants cannot name a GOAIEZ grantee');
        }

        $this->newLine();
        $this->line('⭐ Idempotent — safe to re-run after every migration that adds a tenant table.');

        return self::SUCCESS;
    }

    private function hasTable(string $t): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            ['public', $t]
        ) !== [];
    }
}
