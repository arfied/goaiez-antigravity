<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which Google Business account a location's reviews are read through, and
 * through whom.
 *
 * ⚠️ **THIS IS THE TABLE DECISION 531 SAID HAD TO EXIST BEFORE `GbpClient` GOT A
 * CALLER.** `ZernioGbpClient` shipped flag-dark on 2026-08-04 with the gap
 * written into its own docblock: `$accountRef` is opaque, and **nothing in the
 * client can tell whether an account id belongs to the tenant it was called
 * for**. The honest options were a column with no writer or a stated gap, and
 * this codebase has hit decision 272's shape fourteen times — so the gap was
 * stated and the store deferred to whatever gave the client a caller. This is
 * that change, and the store, its writer and its reader land together.
 *
 * ## Why per location rather than per business
 *
 * A Google Business *account* can own many locations, and `29` §2 rule 40 scopes
 * automation to a location rather than a business. Zernio models the same split:
 * its connect flow ends in a **location** selection and mints one of its own
 * account records per selected location, so one tenant with three shopfronts
 * holds three account refs. A per-business row could hold only one of them.
 *
 * ## Why `oauth_connections` is not this table
 *
 * That table holds **tokens we are responsible for**, encrypted through
 * `TokenService`, for grants made to *our* OAuth client — Search Console's is
 * the live example. Here the grant is made to **Zernio's** OAuth application and
 * the tokens are theirs; we hold one platform API key and an opaque reference.
 * Storing a token-less row in a table whose reason for existing is token custody
 * would make every reader of it check which kind it had.
 *
 * ## What is deliberately not a column yet
 *
 * **The sync cursor and `last_synced_at`.** Reading reviews is row 3 slice I and
 * this is slice H. A cursor column here would be written by nothing —
 * `display_on_website` sat exactly like that for two slices (403), `users.role`
 * for thirteen (740) — and a nullable column with no writer reads as a feature
 * that is not working rather than one that is not built. It arrives with the
 * job that advances it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::create removed for gbp_connections to fix duplicates

        DB::statement('ALTER TABLE gbp_connections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE gbp_connections FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON gbp_connections
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        // Decision 359's ruling, and 306's three-layer thesis: the enum stops a
        // bad value reaching the model, the service gives a caller an error they
        // can act on, and the CHECK catches the repair script that reached
        // neither. Written out rather than derived from the enum — a constraint
        // that reads its own values from the code it constrains cannot catch the
        // code changing.
        DB::statement(<<<'SQL'
            ALTER TABLE gbp_connections
                ADD CONSTRAINT gbp_connections_provider_is_known
                CHECK (provider IN ('zernio', 'direct'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE gbp_connections
                ADD CONSTRAINT gbp_connections_status_is_known
                CHECK (status IN ('pending', 'connected', 'disconnected'))
        SQL);

        // ⚠️ A connected row without an account ref is the one state that would
        // be actively wrong rather than merely empty: `reviews()` would be
        // called with null, and slice I's sync would report a location as
        // connected and read nothing from it forever. The screen would show a
        // working connection the whole time.
        DB::statement(<<<'SQL'
            ALTER TABLE gbp_connections
                ADD CONSTRAINT gbp_connections_connected_rows_carry_an_account
                CHECK (status <> 'connected' OR account_ref IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_connections');
    }
};
