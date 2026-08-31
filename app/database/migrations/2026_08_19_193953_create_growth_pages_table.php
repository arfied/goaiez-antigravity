<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A page this platform proposes to put on a tenant's own website —
 * `DATA-MODEL.md` §5.11, with the corrections of decisions 5560–5566.
 *
 * ⛔ **THE TABLE `content_quality_checks.page_id` HAS POINTED AT SINCE STAGE 0.**
 * That migration says the foreign key *"lands with that table"* — this is that
 * table, and the sibling migration beside this one lands the key.
 *
 * ⛔ **ONLY `App\Services\Content\GrowthPages` MAY WRITE HERE**, held by a
 * chokepoint lint in `tests/Feature/Architecture/ContentTest.php` on
 * `SiteChange`'s and `VoiceUsageEvent`'s precedent (625, 4904, 5071). What the
 * chokepoint protects is narrower than rule 32 and worth naming: **`status` and
 * `hold_until` together decide whether a page publishes**, and a second writer
 * is a second place a page the gate refused can acquire a release time.
 *
 * ## What is deliberately NOT here, and why that is not an omission
 *
 * ⚠️ `DATA-MODEL.md`'s spec additionally lists `schema_json`, `author_user_id`
 * and `published_at`. **They are not created here because nothing in this slice
 * writes them** — `CLAUDE.md`'s most-repeated failure is a column with no
 * writer (272, sixteen instances and counting), and slice A set the rule for
 * this chain three days ago: each column arrives in the migration that gives it
 * a writer (5525). `published_at` lands with slice D, which publishes;
 * `schema_json` with the schema engine that generates markup; `author_user_id`
 * with whatever writes the real byline `29` §9.1 asks for, which today has no
 * source — the pages are written by a generator that is Stage 5's.
 *
 * ⚠️ **`status` IS THE OPPOSITE CASE AND IS CREATED NOW.** Slice C writes two of
 * its three values — `draft` at intake, `held` when the gate refuses — so the
 * column has a writer from the first day. `published` is an enum *case*, not a
 * column, which is `SiteChangeVerdict`'s anticipated-vocabulary pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Location-scoped, `CLAUDE.md`'s rule and `DATA-MODEL.md`'s own
            // spec: the site a page goes on is a *location's*
            // (`locations.website_url`), and doc `16` §15.3's volume caps are
            // written "per month per location".
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // 'post' | 'service' | 'area' | 'landing' | 'careers', cast to
            // App\Enums\GrowthPageType — doc `33`'s content brief plus `16` §8's
            // careers page. ⚠️ **A STRING, NEVER A DATABASE ENUM** (`CLAUDE.md`),
            // and there is a convention test that fails the build on one.
            $table->string('type', 16);

            // The path this page will live at on the tenant's own site. Not a
            // full URL: the host is the location's, and storing it twice would
            // let the two disagree after a domain change.
            $table->string('slug', 191);

            $table->string('title', 255);

            // 150–160 characters is doc `33`'s on-page rule; 320 is the column
            // so that an over-long draft can be *stored and refused* rather than
            // truncated into compliance on its way in.
            $table->string('meta_description', 320)->nullable();

            $table->text('content');

            $table->string('target_keyword', 191)->nullable();

            // ⚠️ **A SUMMARY FOR A PERSON, NEVER A THRESHOLD.** It is the share
            // of the gate's checks this page passed, and no decision anywhere is
            // made from it — the gate requires **every** check to pass, so a
            // page scoring 80 is refused exactly as firmly as one scoring 20.
            // A weighted composite would have meant inventing weights, which is
            // a product judgement nobody has made (5568).
            $table->smallInteger('quality_score')->nullable();

            // 'draft' | 'held' | 'published', cast to App\Enums\GrowthPageStatus.
            $table->string('status', 16)->default('draft');

            // ⚠️ **NULL ON A HELD PAGE MEANS "WAITS FOR A PERSON" AND IS NOT A
            // MISSING VALUE** (5565). The gate's hold never lapses; slice D's
            // AUTO-WITH-HOLD does, and `hold_until` is the only thing that tells
            // the two apart. `Reply::$hold_until` is the shape this copies.
            $table->timestamp('hold_until')->nullable();

            $table->timestamps();

            // Composite with business_id, always — a bare unique slug would let
            // one tenant's page block another's (multi-tenancy skill). Scoped to
            // the location as well, because two locations of one business have
            // two sites and may legitimately both have `/emergency-callout`.
            $table->unique(['business_id', 'location_id', 'slug']);

            $table->index(['business_id', 'status']);
        });

        // ⛔ **A RELEASE TIME ON A PAGE THAT IS NOT HELD IS A CONTRADICTION, AND
        // THE DATABASE SAYS SO.** Without this, a page could be returned to
        // `draft` with a stale `hold_until` still on it — and slice D's release
        // sweep, which looks for lapsed holds, would publish it.
        DB::statement(<<<'SQL'
            ALTER TABLE growth_pages
                ADD CONSTRAINT growth_pages_hold_until_needs_a_hold CHECK (
                    hold_until IS NULL OR status = 'held'
                )
        SQL);

        DB::statement('ALTER TABLE growth_pages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE growth_pages FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON growth_pages
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_pages');
    }
};
