<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local Visibility, the GSC half — row 15 slice 1, phase 1.
 *
 * Two tables: which Search Console property a location *is*, and what that
 * property measured on each day.
 *
 * ⚠️ **THE FIRST TABLE IS DECISION 1083, AND IT LANDS WITH THE CLIENT RATHER
 * THAN AFTER IT.** A `siteUrl` is opaque — one Google account routinely holds
 * properties for many businesses, and nothing in the string identifies whose it
 * is. That is decision 531's Zernio finding exactly, where the honest options
 * were *"a column with no writer or a stated gap"* and the gap was chosen because
 * the client had no caller. This client has a caller on day one, so the binding
 * ships in the same slice, tenant-owned and RLS-forced, in the migration that
 * creates it.
 *
 * ⚠️ **AND THE PROPERTY IS NEVER INFERRED FROM `locations.website_url`.** The
 * inference is one line and it is wrong in a way nobody would notice: a business
 * whose site is a page on a franchisor's domain would be silently mapped to the
 * franchisor's whole property, and every number this product then printed would
 * be somebody else's traffic attributed to them. The owner chooses, from a list
 * their own Google account was actually shown.
 *
 * THE THREE LAYERS ARE NOT BELT-AND-BRACES, and this is slice B's precedent at
 * decisions 314–316 and 359 applied again: the enum stops a bad value reaching
 * the model, the service gives a caller an error they can act on instead of a
 * SQLSTATE, and the constraint catches the repair script, the seeder and the
 * psql session that reached neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_site_properties', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // `sc-domain:example.com` or `https://example.com/`, stored exactly
            // as Google returned it. Never normalised, never lowercased, never
            // stripped of its trailing slash — `https://example.com/` and
            // `https://www.example.com/` are two different properties with two
            // different sets of numbers, and "tidying" one into the other
            // silently reports the wrong site.
            $table->string('site_url');

            // A string cast to App\Enums\GscPermissionLevel, never a database
            // enum (CLAUDE.md): a DB enum is a second source of truth that
            // drifts from the PHP one, and Postgres enum values cannot be
            // dropped or reordered once added.
            //
            // ⚠️ THIS IS PROVENANCE, NOT A GATE. It records what Google said
            // when the owner chose. Nothing reads it to decide whether a call may
            // happen — the live 403 decides that — because a stored permission
            // level goes stale the moment somebody edits their Search Console
            // users, and a gate reading a stale copy is decision 286's lossy
            // boolean rebuilt.
            $table->string('permission_level');

            // Who chose it, for the same reason a consent record names its
            // capturer: "why is this location pointed at that property" is a
            // question somebody will ask, and reconstructing it from timestamps
            // is guesswork. Nullable and nullOnDelete because a departed staff
            // member must not take the mapping with them.
            $table->foreignId('chosen_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('chosen_at');

            $table->timestamps();

            // One property per location. A location is one place of business and
            // it is one site in Search Console; a second row would make "which
            // property is this?" ambiguous, and every reader would then need a
            // tiebreak this schema deliberately does not define.
            $table->unique('location_id');
        });

        // ⚠️ A property whose permission level cannot read Performance data must
        // never be storable. `sites.list` returns `siteUnverifiedUser` entries —
        // a verification somebody started and abandoned — and they are
        // indistinguishable from readable ones in a picker. Store one and every
        // read fails forever, which renders as "no data yet": an absence
        // presented as a measurement, which is the one thing decision 1084
        // forbids outright.
        //
        // A PROHIBITION, NOT A WHITELIST, on decision 312's precedent: naming the
        // values that may appear would need a migration the day Google adds a
        // fifth — the discovery document already carries a
        // `SITE_PERMISSION_LEVEL_UNSPECIFIED` the reference page does not
        // mention. `unknown` is forbidden for the same reason it exists: it means
        // we could not read what Google said, and storing that is storing a guess.
        DB::statement(<<<'SQL'
            ALTER TABLE gsc_site_properties
                ADD CONSTRAINT gsc_site_properties_level_can_read_performance
                CHECK (permission_level NOT IN ('siteUnverifiedUser', 'unknown'))
        SQL);

        // Google's two property shapes and nothing else. A bare `example.com`
        // pasted into this column is the single most likely repair-script error,
        // it looks entirely reasonable, and every query made with it 404s — which
        // this slice classifies as `property_missing` and reports to the owner as
        // their property having gone away.
        DB::statement(<<<'SQL'
            ALTER TABLE gsc_site_properties
                ADD CONSTRAINT gsc_site_properties_site_url_is_a_property_key
                CHECK (site_url LIKE 'sc-domain:%' OR site_url LIKE 'http://%' OR site_url LIKE 'https://%')
        SQL);

        DB::statement('ALTER TABLE gsc_site_properties ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE gsc_site_properties FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON gsc_site_properties
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        Schema::create('gsc_daily_snapshots', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // ⚠️ PACIFIC TIME, NOT UTC AND NOT THE TENANT'S ZONE. Google's own
            // reference states the range is "YYYY-MM-DD format in PT time", so
            // this column is Google's day boundary, and casting it to a timestamp
            // in any other zone shifts every row by a day for half the world. A
            // `date` rather than a `timestamp` for exactly that reason: there is
            // no time of day here to be wrong about.
            $table->date('date');

            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');

            // Impression-weighted average position, Google's own figure. NOT a
            // rank — decision 1085 keeps it off the normal surface for that
            // reason as much as for `29` §2's ranking rule, and `28` §5.3.3 puts
            // it on Advanced only. Stored because nothing can reconstruct it from
            // clicks and impressions; CTR is not stored because it is exactly
            // clicks/impressions and a stored copy disagrees with the two numbers
            // printed beside it once summed.
            $table->decimal('position', 8, 4);

            // ⚠️ THE HONESTY COLUMN. False means Google told us, in the response
            // this row came from, that the day's numbers may still change
            // (`metadata.firstIncompleteDate`). Without it a sync run two days
            // early records a partial day as settled and nothing ever corrects
            // it, so a business sees a dip that never happened. Defaults false:
            // a row inserted with no stated freshness is assumed still moving,
            // which is the direction that under-claims.
            $table->boolean('is_final')->default(false);

            $table->timestamps();

            // The idempotency of the sync, expressed in the schema. A day is
            // upserted against this, so re-running a window replaces rather than
            // duplicates — and an unfinalised day genuinely has to be replaced
            // when it settles.
            $table->unique(['location_id', 'date']);

            // RLS predicates `business_id` on every query and Postgres does not
            // index foreign keys automatically — a MySQL habit that does not
            // transfer, and decisions 314–316 recorded it costing
            // `destination_clicks` an index.
            $table->index(['business_id', 'date']);
        });

        // Clicks can never exceed impressions — a click is a click on an
        // impression. A row violating it is a parsing error (the two columns
        // transposed is the obvious one), and it would print a click-through rate
        // above 100% on the owner's own page.
        DB::statement(<<<'SQL'
            ALTER TABLE gsc_daily_snapshots
                ADD CONSTRAINT gsc_daily_snapshots_clicks_within_impressions
                CHECK (clicks <= impressions)
        SQL);

        DB::statement('ALTER TABLE gsc_daily_snapshots ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE gsc_daily_snapshots FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON gsc_daily_snapshots
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_daily_snapshots');
        Schema::dropIfExists('gsc_site_properties');
    }
};
