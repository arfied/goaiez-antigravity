<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let an authenticated user find the business they own, before a tenant exists.
 *
 * Resolution is circular without this. A request arrives, the middleware needs
 * to know which business to establish, and the only link is
 * `businesses.owner_user_id` — but `tenant_isolation` compares `id` against a
 * session variable that has not been set yet, so the row is invisible. The
 * tenant cannot be resolved because resolving it requires the tenant.
 *
 * Breaking the circle outside the database was the alternative: look the row up
 * on a connection that bypasses RLS, or denormalise the business id onto
 * `users`. Both move the boundary out of the one place that cannot be
 * forgotten, and the second duplicates state that would then drift.
 *
 * This keeps it in: a second permissive policy, so with `app.user_id` set and no
 * tenant established, a user sees exactly the businesses they own — and nothing
 * else. Permissive policies are OR'd, so it widens SELECT only, and only for
 * rows already keyed to that user.
 *
 * `FOR SELECT` deliberately. It answers "which business is yours"; it grants no
 * ability to write, and `tenant_isolation`'s WITH CHECK still governs every
 * insert and update.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE POLICY owner_lookup ON businesses
                FOR SELECT
                USING (owner_user_id = nullif(current_setting('app.user_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS owner_lookup ON businesses');
    }
};
