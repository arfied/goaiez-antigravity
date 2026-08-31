<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide settings — one key, one JSON value, no tenant.
 *
 * DATA-MODEL §5.12 sketches it as `(key PK, value JSONB, description,
 * updated_by, updated_at)`, and that shape is kept exactly. It exists now
 * because row 2 needs somewhere to put the free audit's daily global budget:
 * `29` §6.2 says "daily global budget in `platform_settings`", and decision 193
 * seeds it at 250 audits/day.
 *
 * A NATURAL KEY, NOT A SURROGATE ONE. Decision 179 makes primary keys bigint
 * rather than UUID, and this table is not an exception to it so much as a
 * different question: 179 is about surrogate identifiers, and its rationale is
 * that every RLS policy casts `::bigint`. There is no policy here, nothing
 * points a foreign key at a setting, and the key a caller actually holds is the
 * name of the setting. A bigint would add a second identifier that no code
 * would ever use.
 *
 * NOT TENANT-OWNED. These are our numbers, not a tenant's. Adding a nullable
 * `business_id` here would produce a second per-tenant configuration store that
 * no global scope covers, which is the leak shape `CLAUDE.md` warns about
 * rather than a convenience. THAT HALF IS UNCHANGED and is the load-bearing
 * half of the argument.
 *
 * ⛔ **THE OTHER HALF WAS FALSE FROM THE DAY IT WAS WRITTEN AND STOOD FOR
 * THREE WEEKS — CORRECTED 2026-08-23 (8410).** It read: *"A per-business
 * override belongs in `feature_flags` (`flag_key, business_id NULL`) or in the
 * tenant's own settings tables, both of which already exist for that purpose."*
 * **`feature_flags` has never existed.** It is declared at `DATA-MODEL` §5.12,
 * recorded as spec-only at §7.1, and is in no migration — and the capability
 * does not exist under another name either: every read on `DefaultsRegistry` is
 * keyed by `key` alone or by `(Plan, key)`, and **not one method on it takes a
 * business**. So the sentence did not merely name the wrong table; it offered a
 * place to put something when there is no place at all.
 *
 * ⚠️ **THE CLAUSE THAT DID THE DAMAGE IS `already exist`.** It asserts rather
 * than cites, which is 314–316 applied to a table rather than to a protection:
 * the sentence explaining where the thing lives is what makes a reader stop
 * looking for it. Kept and dated rather than deleted (4368) — the wording is
 * the evidence.
 *
 * WHAT THIS APPLICATION DOES INSTEAD, so the correction leaves a reader
 * somewhere to go. A per-business setting here has always been a **typed column
 * on a per-business table** — `autopilot_settings`, `support_settings`,
 * `review_destinations.invite_threshold` — each tenant-owned, each under
 * `ENABLE`+`FORCE` row-level security, each with its own CHECK constraints.
 * ⛔ **That is not a generic override and must not be read as one**: it costs a
 * migration per setting on purpose, because `CLAUDE.md`'s standing rule is
 * *never add a tenant-facing toggle* and its first tiebreaker is *less support
 * surface*. A key/value override of every registry key is the opposite of both,
 * and building one is a slice with an argument, not a lookup.
 *
 * ⚠️ **The absence is pinned rather than asserted.**
 * `tests/Feature/PlatformSettingTest.php` fails the build if this table ever
 * grows a tenant column or if `DefaultsRegistry` grows a per-business read, and
 * `tests/Feature/Schema/UnbuiltTableReferencesTest.php` fails it if a comment in
 * `app/`, `database/migrations/` or `tests/` names one of the **multi-word**
 * tables `DATA-MODEL` §7 records as unbuilt without a registered reason.
 * ⚠️ **Multi-word is the lint's own stated bound, not an oversight**: it cannot
 * tell `services` or `segments` the §7 table from the ordinary English noun,
 * and it says so rather than pretending to a coverage it has not got. This
 * paragraph is written after those mechanisms exist and describes only what
 * they do — which is the rule the old sentence broke.
 *
 * WHAT THIS IS NOT. It is not the Defaults Registry of doc `38` (CFG1), which
 * is unscheduled (BUILD-PLAN §4.4) and which owns seeded caps, thresholds and
 * prices across every document. It is not the credential store either — `38`
 * D-149 puts vendor credentials in an encrypted `platform_credentials` table,
 * and slice B reads the Places key through an accessor for exactly that reason.
 * This table holds operational numbers we set and change ourselves. When CFG1
 * lands, the registry becomes the seeding authority for the rows in here; it
 * does not replace the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            // Dotted keys, e.g. `public_audit.daily_budget`. The namespace is a
            // convention rather than a constraint, because a settings table
            // that enforces its own taxonomy becomes a migration every time a
            // new area needs a number.
            $table->string('key')->primary();

            // jsonb rather than a string, so a setting can be a number, a flag,
            // or a small structure without a second column or a parsing
            // convention at every call site.
            $table->jsonb('value');

            // What this number means and who changes it, for whoever finds the
            // row in three months. Nullable, because the seed writes one and an
            // operator editing a value should not have to.
            $table->text('description')->nullable();

            // An actor label, not a users foreign key — the same choice
            // `audit_log.actor` makes, and for the same reason: a seed, a
            // console command and a staff member all write here, and only one
            // of those has a user id.
            $table->string('updated_by')->nullable();

            // DATA-MODEL §5.12 lists `updated_at` and no `created_at`, and that
            // is right for this table: a setting's interesting timestamp is
            // when its value last moved, not when the key was first defined.
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
