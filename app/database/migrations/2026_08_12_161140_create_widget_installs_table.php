<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a tenant's review widget has actually been seen working — 2969's gap,
 * and `41` §3.3's "mark installed" verification without a beacon (3080).
 *
 * ⚠️ **NOTHING HERE IS ABOUT A VISITOR, AND THAT IS THE WHOLE DESIGN.** The row
 * is written when the installed bundle fetches its feed, which is the only
 * moment anybody can prove an install actually works. What is kept from that
 * request is *which feed*, *which of the tenant's own allowed hosts*, and *when
 * it was first and last seen* — a fact about a **website**. ⛔ **No IP, no IP
 * hash, no user agent, no referrer, no page path, and no identifier of any kind
 * that could be assembled into one** (`29` §2, 3087). `Architecture/WidgetTest`
 * asserts this table's column list *exactly*, so the useful-looking extra column
 * fails the build rather than being noticed later.
 *
 * ⛔ **AND NO COUNTER** (3085). A hit count beside the timestamp is one column
 * and would let a screen say "1,204 views this week" — which is traffic
 * analytics on a third party's website, collected as a side effect of an install
 * check, with no consent behind it and nobody asking for it. The two timestamps
 * are overwritten in place; this is a running last-seen, never an event log.
 *
 * ## Why its own table, and not two columns on `plugins`
 *
 * ⛔ **`plugins` carries `public_read` + `tenant_write` (400, 401), so RLS does
 * not refuse a cross-tenant SELECT of it** — the price of resolving an embed key
 * for a request that arrives with no tenant. Which websites a business runs,
 * when its widget went live and when it stopped are facts about that business,
 * and they do not belong on a publicly readable row (3088).
 *
 * ## Why there is no `widget` column, though `41` §7 names one
 *
 * §7 sketches `widget ENUM(w1_reviews,w2_chat,w3_booking,w4_fab)`. A database
 * enum is forbidden here outright (`CLAUDE.md`, and a convention test enforces
 * it). The column itself is refused because exactly one widget exists, so it
 * would carry one value written by one writer — 272's shape in miniature. The
 * plugin row *is* the widget instance and `plugin_id` says which; the column
 * arrives with W2 (3089).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_installs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The feed that was fetched. Cascades because a deleted plugin's
            // sightings describe a widget that no longer exists — there is
            // nothing to report about it and nothing to keep.
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();

            // ⚠️ **ONE OF THE TENANT'S OWN `allowed_domains` ENTRIES, NORMALISED
            // BY THE SAME PARSER THAT MATCHED IT** (3093). Never a raw `Origin`
            // header: `WidgetInstalls::record()` refuses anything the allowlist
            // does not already contain, so this column can only ever hold a host
            // the tenant themselves typed into the widget screen (3090).
            $table->string('host');

            // ⚠️ **`timestamp()`, NEVER `timestampTz()`, AND THAT IS NOT A
            // STYLE CHOICE HERE.** This database's session time zone is not UTC
            // (`Asia/Singapore` on the machine this was written on). Laravel
            // hands Postgres a naive UTC string, and a `timestamptz` column
            // tags it with the *session* offset — so the row comes back with
            // the right wall clock and an instant eight hours out, and the
            // 72-hour staleness comparison silently reports a working widget as
            // stopped. Caught by the boundary test below rather than by
            // reasoning; `timestamp` round-trips exactly, which is why 29 of
            // the 30 columns in this schema use it.
            //
            // Working since. Set once and never moved, so an owner can see the
            // install has been up for months rather than only that it was up
            // five minutes ago.
            $table->timestamp('first_seen_at');

            // ⚠️ **REFRESHED AT MOST EVERY 15 MINUTES** (3086). Second-accurate
            // on a low-traffic site this column would be a one-row visit log.
            // Blunted, it answers "is this working" and cannot answer "when did
            // somebody look".
            $table->timestamp('last_seen_at');

            $table->timestamps();

            // One row per feed per host. The upsert depends on this, and without
            // it a busy site accumulates a row per request — which is the event
            // log 3085 refuses, arrived at by accident.
            $table->unique(['plugin_id', 'host']);
        });

        DB::statement('ALTER TABLE widget_installs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE widget_installs FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON widget_installs
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_installs');
    }
};
