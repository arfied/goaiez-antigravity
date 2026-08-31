<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The public slug directory for hosted feedback pages — row 3 slice C.
 *
 * WHY THIS TABLE EXISTS AT ALL. Row 2's `/audit/{token}` worked signed-out
 * because `public_audits` carries no tenant key (decision 177). This page is
 * about a real tenant's location, and `locations` is ENABLE + FORCE row-level
 * security keyed on `app.business_id`. A signed-out request resolves no tenant,
 * so the lookup returns zero rows — and `withoutGlobalScopes()` does not help,
 * because the application connects as a non-owner role and the database filters
 * regardless of what Eloquent asks for. So a public request has to *establish* a
 * tenant, and this is the only thing it may read in order to do so.
 *
 * IT HOLDS THE MAPPING AND NOTHING ELSE. No name, no settings, no denormalised
 * business data — the only fact readable without a tenant is that a slug maps to
 * a tenant, which the public URL already reveals. Resist adding a display column
 * here for convenience: the page reads those from `locations` after the tenant
 * is set, which is the whole point.
 *
 * TWO POLICIES RATHER THAN AN EXEMPTION. `public_audits` sits outside the tenant
 * boundary entirely; this table does not have to. `public_read` grants the
 * unauthenticated lookup; `tenant_write` means no tenant can ever create or edit
 * another tenant's page. Postgres ORs permissive policies for SELECT, and
 * INSERT/UPDATE/DELETE are reached only by `tenant_write`.
 *
 * THERE IS NO BACKFILL, AND IT IS NOT AN OVERSIGHT. Migrations run as the table
 * owner, and every tenant-owned table is FORCEd precisely so the owner is not
 * exempt — so a backfill here reading `locations` with `app.business_id` unset
 * sees zero rows. The tenant id it would need is the thing it is trying to
 * discover, and the query returns an empty set rather than an error, which is
 * the more dangerous of the two. Any backfill has to be a tenant-aware console
 * command walking users -> owner_lookup -> business, the way ResolveTenant does.
 * There are no tenants to backfill, so it is not built; FeedbackPages::
 * provisionFor() is idempotent, so the day one is needed it is a loop around a
 * method that already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_pages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // Readable on a printed sign, and not enumerable — the suffix is
            // what stops somebody walking the platform's customer list by
            // guessing business names, and it is also what makes uniqueness
            // automatic rather than handing the second Joe's Dental a worse URL
            // than the first.
            $table->string('slug')->unique();

            // Published on creation, unlike slice B's destinations. Enabling a
            // destination sends a customer to a third-party platform; a capture
            // form has no such side effect, and `24` 3.1 needs the page live
            // before the 10DLC brand submission that unlocks SMS.
            $table->boolean('is_published')->default(true);

            $table->timestamps();

            // One page per location.
            $table->unique('location_id');

            // Postgres does not index foreign keys automatically — that is a
            // MySQL habit that does not transfer. `business_id` is the predicate
            // tenant_write adds to every write whether or not the caller
            // filtered on it, and the FK is cascadeOnDelete.
            $table->index('business_id');
        });

        DB::statement('ALTER TABLE feedback_pages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE feedback_pages FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY public_read ON feedback_pages
                FOR SELECT USING (true)
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_write ON feedback_pages
                FOR ALL
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_pages');
    }
};
