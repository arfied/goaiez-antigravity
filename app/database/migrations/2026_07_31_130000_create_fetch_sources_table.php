<?php

declare(strict_types=1);

use App\Enums\FetchMethodCeiling;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per thing we might fetch, and the policy governing whether we may.
 *
 * `40` §6.3's shape, kept. This is the table the FetchGateway consults before
 * any outbound page request, and it is deliberately data rather than code: `40`
 * §6.2 requires that raising a source's method ceiling need "the counsel-note
 * checkbox on its `fetch_sources` row", which is only meaningful if the ceiling
 * lives somewhere a human signs off on rather than in a constant somebody can
 * edit in a pull request.
 *
 * WHY THIS TABLE EXISTS IN ROW 2 AT ALL. `40` Part 8 assigns the gateway to its
 * own build row "1c — DEP1", in the `30`–`40` pack's internal numbering, which
 * does not map onto `29` §11.2's 26 rows — so nothing in our plan ever scheduled
 * it (`BUILD-PLAN` §4.4). Meanwhile `40` Part 8 also makes "no HTML fetch outside
 * the gateway" a **build-failing import lint**, and row 2's NAP quick-scan is
 * exactly the outbound page fetch the gateway is meant to own. So the choice was
 * to build the gateway here or to write code that a later lint would fail.
 * `BUILD-PLAN` §2.5.2 slice D takes the first: the interface and the policy gate
 * now, the F1–F3 ladder left to the row that needs it.
 *
 * NOT TENANT-OWNED. These are platform-wide fetch policies — whether *we* may
 * fetch Yelp is not a per-tenant question, and a tenant cannot be permitted to
 * raise a ceiling that exists to keep us on the right side of someone else's
 * terms. Tenant-owned properties are handled by their source row's ceiling
 * (`40` §6.2: a tenant's own site is authorised by them and gets the full
 * ladder), not by a `business_id` here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetch_sources', function (Blueprint $table): void {
            // Natural key, same reasoning as platform_settings (decision 206):
            // adapters hold 'yelp' or 'google', nothing foreign-keys to a
            // source, and a surrogate id would be a second identifier no code
            // would use.
            $table->string('key')->primary();

            // The adapter class, where one exists. Nullable because a source can
            // be policy-registered before anything is built to read it — which
            // is exactly the state Google and Yelp are in here.
            $table->string('class')->nullable();

            // Cast to App\Enums\FetchMethodCeiling. A string column, never a
            // database enum — and this one is policy, which is the churniest
            // kind of enumerable there is.
            $table->string('method_ceiling', 24);

            // `40` §6.2: "robots.txt is respected at every tier, F2 included."
            // The column exists because the spec models it, not because it is a
            // dial — see the CHECK below.
            $table->boolean('robots_respect')->default(true);

            // Per-source rate budget, e.g. {"per_minute": 6, "per_day": 500}.
            // jsonb so a source can carry the windows it actually needs rather
            // than the union of every window any source might need.
            $table->jsonb('rate_budget')->nullable();

            // `40` §6.2 / the D-153 pattern: raising a ceiling requires a
            // recorded counsel note. This is where its reference lands, and its
            // absence is why a ceiling raise cannot be a code change.
            $table->string('counsel_note_ref')->nullable();

            // The instant kill switch of `40` §5. Disabling a source degrades it
            // honestly to "last checked {date}" rather than failing.
            $table->boolean('kill')->default(false);

            // An actor label, not a users FK — the same choice audit_log.actor
            // and platform_settings.updated_by make.
            $table->string('updated_by')->nullable();

            $table->timestamp('updated_at')->nullable();
        });

        // robots_respect is a column because `40` §6.3 models it as one, but it
        // is not a setting: §6.2 says robots is respected at every tier without
        // exception. A CHECK rather than a comment, because the difference
        // between "we model this" and "this is negotiable" is exactly the thing
        // that erodes, and the erosion would be invisible in review.
        //
        // If a lawful reason to fetch a robots-disallowed URL ever appears, this
        // constraint is where the argument has to happen — which is the point.
        DB::statement(
            'ALTER TABLE fetch_sources
                 ADD CONSTRAINT fetch_sources_robots_always_respected
                 CHECK (robots_respect = true)'
        );

        $now = now();

        // The seeds `BUILD-PLAN` §2.5.2 slice D calls for: "guided_only ceiling
        // seeded, Google seeded never-fetchable".
        //
        // Google is the one that surprises people, so it is worth stating: we
        // never fetch Google's pages. Everything the audit knows about a Google
        // listing comes from the Places API, which is a licensed interface with
        // a bill attached (slice B). Scraping Maps or Search would be a terms
        // violation that the Places API makes pointless as well as wrong.
        DB::table('fetch_sources')->insert([
            [
                'key' => 'google',
                'class' => null,
                'method_ceiling' => FetchMethodCeiling::GuidedOnly->value,
                'robots_respect' => true,
                'rate_budget' => null,
                'counsel_note_ref' => null,
                'kill' => false,
                'updated_by' => 'seed:row-2-slice-d',
                'updated_at' => $now,
            ],
            [
                // `41` and `40` §4 both put Yelp here, and CLAUDE.md goes
                // further: Yelp is never a review destination at all (112), and
                // solicitation is prohibited outright.
                'key' => 'yelp',
                'class' => null,
                'method_ceiling' => FetchMethodCeiling::GuidedOnly->value,
                'robots_respect' => true,
                'rate_budget' => null,
                'counsel_note_ref' => null,
                'kill' => false,
                'updated_by' => 'seed:row-2-slice-d',
                'updated_at' => $now,
            ],
            [
                'key' => 'facebook',
                'class' => null,
                'method_ceiling' => FetchMethodCeiling::GuidedOnly->value,
                'robots_respect' => true,
                'rate_budget' => null,
                'counsel_note_ref' => null,
                'kill' => false,
                'updated_by' => 'seed:row-2-slice-d',
                'updated_at' => $now,
            ],
            [
                // The one row 2 actually uses: the NAP quick-scan fetching the
                // business's own public website. Light fetch only — one direct
                // request, no rendering, no proxy. The full ladder that `40`
                // §6.2 permits for tenant-owned properties belongs to the row
                // that has a tenant; row 2 runs before signup and the site is
                // not yet demonstrably theirs.
                'key' => 'subject_website',
                'class' => null,
                'method_ceiling' => FetchMethodCeiling::LightFetch->value,
                'robots_respect' => true,
                'rate_budget' => json_encode(['per_minute' => 6, 'per_day' => 500]),
                'counsel_note_ref' => null,
                'kill' => false,
                'updated_by' => 'seed:row-2-slice-d',
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_sources');
    }
};
