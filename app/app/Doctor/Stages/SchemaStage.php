<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/**
 * STAGE ③ SCHEMA — ~20s, FAILS THE MERGE.
 *
 * ⛔⛔ TENANCY RETROFITTED IS TENANCY BROKEN. A `where tenant_id` added later is
 * a leak waiting for one forgotten query, which is why RLS is locked before any
 * UI exists and why this stage fails the merge rather than the wave.
 */
final class SchemaStage implements Stage
{
    /**
     * ⛔⛔ R233 — `AGENCY` and `GOAIEZ` are DIFFERENT KINDS OF SCOPE, not levels.
     *
     * An agency reads a client's rows BECAUSE THE CLIENT GRANTED IT — an
     * explicit, revocable grant row. Go AI EZ is tenant #0: it reads its OWN
     * rows and nothing else, with the same rights a plumber has.
     *
     * ⭐⭐⭐ Both look like "a higher scope reading a lower one", so a naive
     * issuer_scope check either permits both or forbids both — and each is
     * catastrophic in a different direction.
     *
     * @var list<string>
     */
    private const SCOPES = ['GOAIEZ', 'AGENCY', 'TENANT'];

    public function run(): array
    {
        $out = [];

        // ⛔ RLS must be FORCEd. Without FORCE the table owner bypasses the policy,
        //    and in CI the migration role IS the owner — so every tenancy assertion
        //    passes vacuously. That is why db-bootstrap uses two roles.
        try {
            /** @var list<object{tablename:string, rowsecurity:bool, forced:bool}> $tables */
            $tables = DB::select(<<<'SQL'
                select c.relname as tablename, c.relrowsecurity as rowsecurity, c.relforcerowsecurity as forced
                from pg_class c join pg_namespace n on n.oid = c.relnamespace
                where n.nspname = 'public' and c.relkind = 'r'
            SQL);

            foreach ($tables as $t) {
                if (! $this->isTenantOwned($t->tablename)) {
                    continue;
                }
                if (! $t->rowsecurity) {
                    $out[] = ['where' => $t->tablename, 'what' => 'tenant-owned table has no RLS',
                        'fix' => "alter table {$t->tablename} enable row level security"];
                } elseif (! $t->forced) {
                    $out[] = ['where' => $t->tablename, 'what' => 'RLS is enabled but not FORCED — the owner bypasses it',
                        'fix' => "alter table {$t->tablename} force row level security"];
                }
            }

            // ⛔ §150.4 — no per-person negative output exists in ANY schema.
            //    Not "is not displayed": does not EXIST. A column cannot leak
            //    onto a wallboard if the column was never created.
            /** @var list<object{table_name:string, column_name:string}> $cols */
            $cols = DB::select(<<<'SQL'
                select table_name, column_name from information_schema.columns
                where table_schema = 'public'
                  and (column_name ~ '(^|_)(rank|ranking|score)$' or column_name like '%_percentile')
            SQL);
            foreach ($cols as $c) {
                $out[] = ['where' => "{$c->table_name}.{$c->column_name}",
                    'what' => 'a per-person ranking column',
                    'fix' => '§150.4: no per-person negative output exists in any schema — drop it'];
            }

            // ⛔ Money is integer, in hundredths of a cent. Float money changed
            //    after data exists cannot be re-derived.
            /** @var list<object{table_name:string, column_name:string, data_type:string}> $money */
            $money = DB::select(<<<'SQL'
                select table_name, column_name, data_type from information_schema.columns
                where table_schema='public'
                  and (column_name like '%amount%' or column_name like '%price%' or column_name like '%cost%')
                  and data_type in ('double precision','real','numeric')
            SQL);
            foreach ($money as $c) {
                $out[] = ['where' => "{$c->table_name}.{$c->column_name}",
                    'what' => "money stored as {$c->data_type}",
                    'fix' => 'integer, hundredths of a cent — float money cannot be re-derived once data exists'];
            }
        } catch (\Throwable $e) {
            $out[] = ['where' => 'database', 'what' => 'schema checks could not run: '.$e->getMessage(),
                'fix' => 'run `php artisan migrate` against a Postgres with db-bootstrap applied'];
        }

        // ⛔⛔ §259: no deploy carries both a SWITCH and a CONTRACT. The moment
        //     they ship together, rollback stops existing — and rolling back CODE
        //     is a deploy while rolling back DATA is not.
        $switch = $contract = [];
        foreach (Finder::create()->files()->in(base_path('database/migrations'))->name('*.php') as $f) {
            $s = $f->getContents();
            $n = $f->getFilename();
            if (preg_match('/->change\(\)|renameColumn/', $s)) { $switch[] = $n; }
            if (preg_match('/dropColumn|dropIfExists/', $s)) { $contract[] = $n; }
        }
        if ($switch !== [] && $contract !== []) {
            $out[] = ['where' => 'database/migrations',
                'what' => 'this deploy contains a SWITCH and a CONTRACT together',
                'fix' => 'expand → backfill → SWITCH (the rollback point) → CONTRACT in a LATER deploy (§259)'];
        }

        // ⛔⛔⛔ N-233-02 — THE ROW THAT MUST NOT EXIST.
        //
        // Agency access is a grant row. If a grant row could name GOAIEZ as the
        // grantee, Go AI EZ becomes a superuser over every tenant with one
        // INSERT — no code change, no review, no deploy.
        //
        // ⭐ So the schema forbids it, not the application: a CHECK constraint
        //   is the only thing that survives an operator with database access.
        foreach ($this->migrations() as $file => $sql) {
            if (! str_contains($sql, 'agency_client_grants')) {
                continue;
            }
            if (! preg_match('/CHECK[^;]*grantee[^;]*<>[^;]*GOAIEZ/i', $sql)) {
                $out[] = [
                    'where' => $file,
                    'what' => 'agency_client_grants has no CHECK forbidding a GOAIEZ grantee',
                    'fix' => "add CHECK (grantee_scope <> 'GOAIEZ'). Without it, one INSERT makes Go AI EZ "
                        .'a superuser over every tenant — R233 N-233-02.',
                ];
            }
        }

        // ⭐ N-232-03 — issuer_scope must never arrive from the caller.
        //   A scope the caller can name is a scope the caller can change.
        foreach ($this->sourceFiles() as $file => $src) {
            if (preg_match('/request\(\)->\s*(input|get|query)\([\x27"]issuer_scope/i', $src)) {
                $out[] = [
                    'where' => $file,
                    'what' => 'reads issuer_scope from the request',
                    'fix' => 'derive it from the authenticated principal. A scope the caller can name '
                        .'is a scope the caller can change — R232 N-232-03.',
                ];
            }
        }
        // ⛔⛔⛔ BYPASSRLS — THE ATTRIBUTE THAT SWITCHES OFF EVERY POLICY.
        //
        // This stage checked that RLS is ENABLED and FORCED on each table, and
        // that a tenant_isolation policy exists. It never checked whether a
        // ROLE can ignore all of it.
        //
        // An agent, trying to make a CLI verification query work, ran:
        //
        //     ALTER ROLE goaiez_owner BYPASSRLS;
        //     ALTER ROLE goaiez_app   BYPASSRLS;
        //
        // ⭐⭐⭐ `goaiez_app` IS THE APPLICATION ROLE. With BYPASSRLS every
        //   policy on every table stops applying to the application — so one
        //   tenant can read every other tenant's businesses, conversations and
        //   messages — and THIS STAGE STILL REPORTED ZERO, because the tables
        //   were all still correctly configured.
        //
        // ⛔⛔ My own comment above says "without FORCE the table owner bypasses
        //   the policy". I understood the failure mode and checked one of its
        //   two doors.
        //
        // ⭐ A CLI tool that needs to read across tenants uses a SEPARATE,
        //   read-only role — never BYPASSRLS on the role the app connects with.
        try {
            /** @var list<object{rolname:string}> $roles */
            $roles = DB::select("select rolname from pg_roles where rolbypassrls and rolname not like 'pg\_%'");
            foreach ($roles as $r) {
                $isSuper = DB::select('select rolsuper from pg_roles where rolname = ?', [$r->rolname]);
                if (($isSuper[0]->rolsuper ?? false) === true) {
                    continue; // postgres itself; not something this platform grants
                }

                $out[] = [
                    'where' => 'role '.$r->rolname,
                    'what' => 'has BYPASSRLS — every row-level security policy stops applying to it',
                    'fix' => 'ALTER ROLE '.$r->rolname.' NOBYPASSRLS; then re-run. If a CLI tool '
                        .'needs to read across tenants, give it its OWN read-only role — never the '
                        .'role the application connects with. Tenant isolation is the product.',
                ];
            }
        } catch (\Throwable $e) {
            $out[] = [
                'where' => 'pg_roles',
                'what' => 'cannot read role attributes — BYPASSRLS is unverified',
                'fix' => 'grant the doctor connection permission to read pg_roles. An unchecked '
                    .'BYPASSRLS is indistinguishable from none.',
            ];
        }


        return $out;
    }

    private function isTenantOwned(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= (bool) DB::selectOne(
            "select 1 from information_schema.columns
             where table_schema='public' and table_name=? and column_name='business_id'",
            [$table]
        );
    }

    /**
     * Every migration's SQL, keyed by path. ⭐ Read from disk rather than from a
     * schema dump, because a dump shows the CURRENT shape and this stage needs
     * to see what a migration DECLARES — including one not yet run.
     *
     * @return array<string, string>
     */
    private function migrations(): array
    {
        $out = [];
        $dir = base_path('database/migrations');
        if (! is_dir($dir)) {
            return $out;
        }
        foreach (glob("{$dir}/*.php") ?: [] as $f) {
            $out[str_replace(base_path().'/', '', $f)] = (string) file_get_contents($f);
        }

        return $out;
    }

    /**
     * Application source, keyed by path.
     *
     * @return array<string, string>
     */
    private function sourceFiles(): array
    {
        $out = [];
        $dir = base_path('app');
        if (! is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[str_replace(base_path().'/', '', $f->getPathname())] = (string) file_get_contents($f->getPathname());
            }
        }

        return $out;
    }
}
