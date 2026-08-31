<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The pixel's public key, and the row an anonymous browser is allowed to resolve.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 1: *"Validate `data-k` → tenant. Unknown →
 * 204 drop."* The key travels in the body of a request from a visitor on
 * somebody else's website — no session, no token — so the lookup that answers it
 * has to succeed **before any tenant exists**. Decision 318's circularity, for
 * the third time in this schema.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHY THIS IS A TABLE AND NOT `businesses.pixel_tenant_id`, WHICH IT REPLACES
 * ---------------------------------------------------------------------------
 * That column has existed since the Stage 0 schema and was written for exactly
 * this job. **Nothing in `app/` ever set it** — decision 272's shape, and only
 * `BusinessFactory` filled it, so every test that built a tenant through the
 * factory would have proven a collector working against a key no real tenant
 * has. Building the writer is what exposed why the column cannot do the job:
 *
 * `businesses` is `ENABLE` + `FORCE ROW LEVEL SECURITY` under a single
 * `tenant_isolation` policy keyed on its own id, so with `app.business_id` unset
 * the predicate is NULL and **the row is invisible to the one query whose whole
 * purpose is to say which tenant this is**. `withoutGlobalScope()` does not help;
 * that removes Eloquent's filter and RLS is underneath it.
 *
 * ⛔ **AND THE FIX `plugins` USED IS NOT AVAILABLE HERE.** Decision 400 gave
 * `plugins` a `public_read` policy and 401 records why the two public-key tables
 * answer 318 differently. A `public_read` on `businesses` would make **every
 * row** of the tenant root fetchable by an unauthenticated connection — name,
 * EIN, address, phone, `data_classification`, `messaging_mode` — because a
 * PostgreSQL policy filters rows and cannot filter columns. `TenancyTest`'s own
 * scope-drop allowlist says the line out loud: *"this entry is safe **because
 * `businesses` has no policy admitting platform staff**, and adding one would
 * turn every file on this list into an enumeration path at once, silently."*
 *
 * So the key moves to a table that holds **nothing but the mapping**: the key the
 * caller already presented, and the id it resolves to. A `public_read` here
 * discloses, to somebody who already holds a key, the integer that key stands
 * for. There is no third column for it to leak.
 *
 * ⚠️ **`public_read` IS NOT "THIS TABLE IS PUBLIC"** — `plugins`' migration says
 * it first and it is inherited rather than restated: a row is fetchable *if you
 * already hold its key*, and the key is a random UUID precisely so holding one is
 * not a capability you can guess into. What keeps that true is that nothing
 * lists this table on a public path: `PixelKeys::resolve()` takes one key and
 * returns one row, with no `where` a caller can influence, no ordering and no
 * list.
 *
 * ⛔ **THE OLD COLUMN IS DROPPED IN THE SAME MIGRATION, DELIBERATELY.** Leaving
 * it would leave a second, dead public pixel key on the tenant root — and the
 * next person to write to it would get a collector that answers `204` to every
 * request with nothing anywhere explaining why. A superseded column that still
 * looks usable is worse than the one that never had a writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_keys', function (Blueprint $table): void {
            $table->id();

            // ⚠️ NOT `tenant_id`. `DATA-MODEL.md` uses `business_id` throughout
            // and `BelongsToTenant` reads that name; `businesses.id` is this
            // schema's tenant key.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⚠️ UNIQUE ACROSS THE PLATFORM, NOT PER TENANT. It is presented
            // alone, by a stranger, before any tenant is known — a key that
            // collided across two businesses would resolve to whichever row came
            // back first, and archive one tenant's visitors under the other's
            // seven-year prefix.
            $table->uuid('key')->unique();

            // ⚠️ **ONE KEY PER BUSINESS, ENFORCED HERE RATHER THAN REMEMBERED.**
            // `PixelKeys::ensureFor()` is idempotent by lookup, and a lookup then
            // an insert is decision 350's shape — two simultaneous calls both see
            // nothing and both insert. This key is pasted into a customer's
            // website, so a second row means one live archive nobody can find and
            // one they installed.
            $table->unique('business_id');

            // No `updated_at`: a public identifier that changes is a broken
            // install, so there is no update for a timestamp to record.
            $table->timestamp('created_at')->nullable();
        });

        // ENABLE alone is not enough: PostgreSQL exempts a table's owner from its
        // policies unless the table is also FORCEd, and migrations run as the
        // owner. Without FORCE these policies would exist, look correct, and do
        // nothing.
        DB::statement('ALTER TABLE pixel_keys ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pixel_keys FORCE ROW LEVEL SECURITY');

        // `plugins`' pair, for `plugins`' reason. Postgres OR's permissive
        // policies together, so SELECT is satisfied by `public_read` and every
        // INSERT, UPDATE and DELETE is reachable only through `tenant_write`.
        DB::statement(<<<'SQL'
            CREATE POLICY public_read ON pixel_keys
                FOR SELECT USING (true)
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_write ON pixel_keys
                FOR ALL
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        // The column this table replaces. Nothing in `app/` ever wrote it; the
        // only reference outside comments was `BusinessFactory`, which moved to a
        // `PixelKey` factory in the same commit.
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('pixel_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->uuid('pixel_tenant_id')->nullable()->unique();
        });

        DB::statement('DROP POLICY IF EXISTS public_read ON pixel_keys');
        DB::statement('DROP POLICY IF EXISTS tenant_write ON pixel_keys');

        Schema::dropIfExists('pixel_keys');
    }
};
