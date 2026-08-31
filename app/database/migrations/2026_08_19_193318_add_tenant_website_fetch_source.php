<?php

declare(strict_types=1);

use App\Enums\FetchMethodCeiling;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The tenant's own confirmed website, as a fetch source of its own.
 *
 * `BUILD-PLAN` §2.11.3 slice B probes a confirmed site for WordPress and for a
 * Cloudflare proxy. Every outbound page fetch goes through the gateway (`40`
 * Part 6, held by the outbound-file lint in `OutboundTest`), and the gateway
 * refuses an unregistered source outright — so the policy row is part of the
 * slice rather than a follow-up.
 *
 * ⚠️ **NOT `subject_website`, AND THE ROW 2 SEED SAYS WHY IN ITS OWN COMMENT**:
 * *"row 2 runs before signup and the site is not yet demonstrably theirs"*. This
 * source is the other side of that sentence — a site a signed-in owner has just
 * confirmed is theirs. Two rows rather than one buys two things: the budgets do
 * not share a bucket, so a sweep of tenant sites cannot starve the pre-signup
 * audit that sells the product (or the reverse), and the kill switch can stop
 * one without stopping the other.
 *
 * ⛔ **THE CEILING IS STILL `light_fetch`, NOT `full_ladder`.** `40` §6.2 permits
 * the fuller ladder for tenant-owned properties and **F1–F3 are deliberately
 * unbuilt** (215–219) — a ceiling naming tiers that do not exist would be a
 * permission with nothing behind it, and `FetchTier::assertImplemented()` raises
 * rather than silently serving F0 anyway. The day F1 lands is the day to argue
 * for raising this row, with the counsel-note field this table already carries.
 *
 * Robots is respected, which is not a choice: `fetch_sources_robots_always_respected`
 * is a CHECK on the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('fetch_sources')->insert([
            'key' => 'tenant_website',
            'class' => null,
            'method_ceiling' => FetchMethodCeiling::LightFetch->value,
            'robots_respect' => true,
            // Tighter per-minute than `subject_website`'s six, looser per day.
            // A probe runs once per confirmation rather than once per keystroke,
            // so the shape of the traffic is a slow trickle across many hosts
            // rather than a burst at one.
            //
            // ⛔ **THIS ENDED "— AND THE PER-MINUTE FIGURE IS THE ONE THAT
            // PROTECTS A SINGLE HOST FROM US", AND IT PROTECTS NO SINGLE HOST —
            // CORRECTED 2026-08-25 (9740–9759).**
            // `DirectFetchGateway::overRateBudget()` filters on `source_key` and
            // nothing else: no host, no tenant, no location. So this is a
            // **platform-wide throughput ceiling across every origin the source
            // covers**, shared today by `SiteProbe` (two of the four in one
            // `probe()` call), `AuthorByline` and `SitemapAnnouncement`.
            // ⚠️ **`RobotsPolicy::UNAVAILABLE_CACHE_SECONDS` has stated the true
            // property correctly the whole time** — *"that budget is per
            // *source*, shared across every origin it covers"* — so the two
            // artefacts disagreed and the wrong one was the file a reader opens
            // to find out what the figure means.
            // ⚠️ **The figures and the sentence above them are untouched.** A
            // per-host budget is a policy question with a live 500-a-day ceiling
            // on the other source behind it, and it is nobody's to decide in a
            // lane; `OutboundSiteBudget` is the shape it would take. What is
            // corrected here is only the claim.
            'rate_budget' => json_encode(['per_minute' => 4, 'per_day' => 2000]),
            'counsel_note_ref' => null,
            'kill' => false,
            'updated_by' => 'seed:row-9-slice-b',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('fetch_sources')->where('key', 'tenant_website')->delete();
    }
};
