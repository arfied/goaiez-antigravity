<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Defaults Registry's two remaining stores (doc `38` Part 2, CFG1).
 *
 * `38` Part 8 is explicit that the registry needs no table of its own:
 * "realized as seeded rows in platform_settings + plan_entitlements + a seeds
 * manifest file; no separate table needed". `platform_settings` shipped in row 2
 * slice A and already says in its own docblock that CFG1 "becomes the seeding
 * authority for the rows in this table; it does not replace the table". This
 * migration adds the per-plan half and the change log.
 *
 * NEITHER TABLE IS TENANT-OWNED, AND NEITHER TAKES RLS. These are our prices and
 * our caps — the same argument `legal_documents` makes (417–420) and for the
 * same reason: a tenant-owned entitlement matrix would mean each business
 * editing the terms it is billed under. Both models join the `ArchitectureTest`
 * scope allowlist with their reasoning written there, and what protects them is
 * the admin gate plus the fact that `DefaultsRegistry` is their only writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * The per-plan half of the registry (DATA-MODEL §Part 11).
         *
         * VERSIONED RATHER THAN UPDATED, AND THAT IS THE AUDIT. DATA-MODEL
         * specifies `PRIMARY KEY (plan, key, version)` with an `effective_at`,
         * which means an edit writes a new row and the old value is still
         * readable. `38` Part 2 requires "every change is audited with
         * before/after"; for this table the before *is* a row, which is
         * strictly better than a log entry describing one. `platform_settings`
         * cannot do that — it is keyed on `key` alone — which is why
         * `registry_changes` below exists for it and not for this.
         *
         * ⚠️ IT IS ALSO WHAT MAKES `38` D-151's GRANDFATHERING POSSIBLE. "Existing
         * subscribers keep their price unless deliberately migrated" needs the
         * price a subscriber signed up under to still exist after somebody edits
         * the current one. An UPDATE in place destroys exactly that, and the
         * damage is unrecoverable: nothing anywhere would record what the old
         * price was. Do not "simplify" this into a two-column upsert.
         */
        Schema::create('plan_entitlements', function (Blueprint $table): void {
            // DATA-MODEL writes the primary key as (plan, key, version). Kept
            // as a UNIQUE below rather than as the PK, with a surrogate id in
            // front of it: Eloquent addresses a row by a single key, and a
            // composite PK makes the model unable to find, update or relate
            // without hand-written SQL for no gain. The constraint is identical
            // either way; only what Eloquent can hold onto changes. Decision
            // 179's bigint rule applies unchanged.
            $table->id();

            // A string cast to App\Enums\Plan, never a database enum
            // (CLAUDE.md). The plan ladder churns — 99 removed it and 154–157
            // put it back — which is the churn that rule is about.
            $table->string('plan');

            // Dotted, area-first, the same convention as platform_settings:
            // `price.monthly_cents`, `cost_cap.monthly_cents`.
            $table->string('key');

            // jsonb for the same reason platform_settings uses it: a value may
            // be a number, a flag or a small structure without a second column.
            $table->jsonb('value');

            // Monotonic per (plan, key). Version 1 is the seed.
            $table->integer('version');

            // When this version starts applying. Distinct from a created_at,
            // which this table does not carry: a price can be written today and
            // take effect at the next billing cycle.
            $table->timestamp('effective_at');

            // An actor label, not a users foreign key — `audit_log.actor` and
            // `platform_settings.updated_by` make the same choice, because a
            // seeder, a console command and a staff member all write here and
            // only one of them has a user id.
            //
            // NOT IN DATA-MODEL's sketch, added deliberately. A versioned row
            // with no actor answers what changed and when, and never who — and
            // "who moved the price" is the first question anyone asks of this
            // table. Deviation recorded in decision 508.
            $table->string('set_by');

            $table->unique(['plan', 'key', 'version']);

            // Every read is "the current value of one key for one plan", which
            // is this index scanned backwards.
            $table->index(['plan', 'key', 'version']);
        });

        /*
         * The change log for `platform_settings`, which cannot version itself.
         *
         * `38` Part 2's Ops UX requires "every change is audited with
         * before/after", and `29` §2 rule 42 requires every sensitive action to
         * reach an append-only log. AuditService cannot serve either here:
         * `audit_log.business_id` is NOT NULL and its policy is keyed on the
         * session tenant, so a platform-wide settings change has no tenant to
         * write under and would fail closed on RLS. This is the platform-scoped
         * equivalent, and it is deliberately narrow — one store, one shape.
         *
         * APPEND-ONLY, enforced at the model layer like `audit_log` (§5.14).
         */
        Schema::create('registry_changes', function (Blueprint $table): void {
            $table->id();

            // The platform_settings key that moved.
            $table->string('setting_key');

            // Null when the key had no row — a seed, or an operator setting a
            // key the manifest declares and deliberately does not seed.
            // Distinguishing "was absent" from "was JSON null" is the whole
            // reason this is nullable rather than defaulting to a JSON null.
            $table->jsonb('value_before')->nullable();

            $table->jsonb('value_after')->nullable();

            $table->string('actor');

            $table->timestamp('created_at');

            $table->index(['setting_key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registry_changes');
        Schema::dropIfExists('plan_entitlements');

        // Nothing to undo on platform_settings: this migration writes no rows
        // there. `defaults:sync` does, and it is a command rather than a
        // migration precisely so that a rollback does not silently delete an
        // operator's edited values. See decision 504.
    }
};
