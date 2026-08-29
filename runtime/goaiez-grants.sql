-- goaiez-grants.sql
--
-- ⭐⭐⭐ RUN THIS AGAINST EVERY DATABASE, INCLUDING THE TEST ONE.
--
--   psql -U postgres -d goaiez             -f goaiez-grants.sql
--   psql -U postgres -d goaiez_clean_test_2 -f goaiez-grants.sql
--
-- WHY IT IS A FILE AND NOT A COMMAND:
-- an agent spent twenty minutes on PowerShell quoting trying to issue four
-- lines of SQL. A file has no quoting to get wrong.
--
-- ⛔⛔ WHAT WENT WRONG:
--
-- `ALTER ROLE goaiez_app NOBYPASSRLS` was correct — BYPASSRLS had switched off
-- tenant isolation for the whole application. But it had ALSO been masking a
-- second problem: the role has no INSERT/SELECT grants on these tables. With
-- BYPASSRLS set, Postgres never reached the grant check.
--
-- ⭐ Remove the bypass and the missing grants surface at once:
--      SQLSTATE[42501]: permission denied for table businesses
--
-- ⭐⭐⭐ `permission denied` IS NOT AN RLS ERROR. RLS returns zero rows, or
--   "new row violates row-level security policy". A permission error is a
--   GRANT problem, and the two get confused constantly.

-- ① The application role may read and write every table, and RLS then decides
--    WHICH ROWS. Grants say "may touch the table"; policies say "may touch the
--    row". Both are needed, and they are not substitutes.
GRANT USAGE ON SCHEMA public TO goaiez_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO goaiez_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO goaiez_app;

-- ② And for every table created AFTER this runs. Without this line a new
--    migration silently produces a table the application cannot touch, and the
--    failure appears weeks later in whatever feature used it first.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO goaiez_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO goaiez_app;

-- ③ ⛔⛔⛔ AND NO BYPASSRLS. EVER.
--    Granting it to the application role means one tenant can read every other
--    tenant's businesses, conversations and messages — and `doctor --stage=schema`
--    will now report any role that holds it.
ALTER ROLE goaiez_app NOBYPASSRLS;
ALTER ROLE goaiez_owner NOBYPASSRLS;

-- ④ Confirm. If either query returns a row, something is still wrong.
SELECT rolname AS roles_that_can_bypass_rls
  FROM pg_roles
 WHERE rolbypassrls AND NOT rolsuper;

SELECT tablename AS tables_the_app_cannot_write
  FROM pg_tables
 WHERE schemaname = 'public'
   AND NOT has_table_privilege('goaiez_app', schemaname || '.' || tablename, 'INSERT');
