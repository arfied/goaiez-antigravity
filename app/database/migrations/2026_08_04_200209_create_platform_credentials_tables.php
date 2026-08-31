<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Credentials Manager's store (doc `38` Part 1, its D-149).
 *
 * `38` Part 1: "environment variables are **bootstrap seeds only**. At runtime,
 * every vendor credential resolves from the encrypted `platform_credentials`
 * store, managed in Ops Console → Platform → **Credentials**."
 *
 * `App\Support\PlatformCredentials` has been the seam for that sentence since
 * row 2 slice B, and its own docblock names this migration as the thing it was
 * waiting for: "When CFG1 arrives, `get()` grows a lookup against
 * `platform_credentials` with config as the fallback, and **no call site
 * changes**. That is the entire point."
 *
 * NEITHER TABLE IS TENANT-OWNED, AND NEITHER TAKES RLS — the same argument
 * `platform_settings`, `plan_entitlements` and `legal_documents` make (417–420,
 * 508). These are *our* vendor keys: one Google Places key, one Turnstile
 * secret, one Infobip account. A tenant's own OAuth tokens are a different thing
 * entirely and live encrypted per connection behind `TokenService`, which is
 * tenant-owned and stays that way. Both models join the `ArchitectureTest` scope
 * allowlist with their reasoning written there, and what protects them is the
 * admin gate plus a chokepoint lint naming their only reader and writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * One vendor credential.
         *
         * ⚠️ THE VALUE IS ENCRYPTED AT THE APPLICATION LAYER, NOT BY THE
         * DATABASE. `38` Part 1: "values encrypted at the application layer
         * (same discipline as OAuth tokens)". The column is therefore a `text`
         * holding Laravel's ciphertext envelope, never the key itself — a
         * `string(255)` would silently truncate one, because the envelope is
         * substantially longer than the secret inside it and truncation produces
         * a decryption failure days later with nothing pointing back here.
         */
        Schema::create('platform_credentials', function (Blueprint $table): void {
            // The key a caller already holds is the identifier, exactly as in
            // `platform_settings`: `PlatformCredentials::get('google_places_key')`
            // names the row. Decision 179's bigint rule is about surrogate keys
            // and does not apply where the natural key is the whole address.
            $table->string('key')->primary();

            $table->text('value');

            /*
             * The last four characters, stored rather than derived.
             *
             * ⚠️ THIS IS WHAT LETS THE OPS SCREEN HAVE NO DECRYPTION PATH AT
             * ALL. `38` Part 1 asks for a "masked value (last-4 visible)", and
             * the obvious implementation decrypts every secret on every render
             * of the management screen — which puts six plaintext vendor keys in
             * the memory of a page whose entire job is to *not* show them, one
             * careless public Livewire property away from the browser. Storing
             * four characters means the screen reads this column and the
             * ciphertext is never opened.
             *
             * Nullable because a credential can exist with an unknown tail: not
             * today, but an import path that ever receives a hash rather than a
             * value would have nothing honest to write here, and `''` would
             * render as a mask of nothing while claiming to be a mask.
             */
            $table->string('last_four', 4)->nullable();

            // A string cast to App\Enums\CredentialEnvironment, never a database
            // enum (CLAUDE.md). `38` Part 1's "environment badge (live/test)":
            // the failure it exists to prevent is a vendor's *test* key sitting
            // in production looking exactly like a live one, which surfaces as
            // the vendor rejecting real work for reasons that read as our bug.
            $table->string('environment');

            // `38` Part 1: "last-rotated". Not a `created_at` — a rotation
            // replaces the value in place, and when the *current* secret started
            // being the current secret is the question anyone asks of this row.
            $table->timestamp('rotated_at');

            // An actor label, not a users foreign key, for the reason
            // `plan_entitlements.set_by` and `audit_log.actor` give: a console
            // command and a staff member both write here and only one has a user
            // id.
            $table->string('rotated_by');

            /*
             * `38` Part 1: "last-used". Telemetry, and the only column on this
             * table written by a *read*.
             *
             * Deliberately day-granular in practice (see
             * `CredentialStore::touch()`): a credential is read on every vendor
             * call, and a timestamp accurate to the second would turn a hot path
             * into a write per call for information nobody needs at that
             * precision. What it answers is "is this key still in use, or is it
             * safe to retire" — and a day is the resolution that question has.
             */
            $table->timestamp('last_used_at')->nullable();
        });

        /*
         * The rotation log.
         *
         * `38` Part 1: "Rotation and every reveal-less set is audit-logged."
         * `AuditService` cannot serve it, for the reason `registry_changes`
         * already records: `audit_log.business_id` is NOT NULL and its policy is
         * keyed on the session tenant, so a platform-scoped act has no tenant to
         * write under and fails closed. This is that table's sibling.
         *
         * ⚠️ NO VALUE COLUMN, IN EITHER DIRECTION, NOT EVEN ENCRYPTED — and that
         * absence is the design rather than an omission. A change log holding
         * every superseded secret is a second store of every key this platform
         * has ever held, one that grows forever and is never itself rotated,
         * which inverts the point of rotation: a key is rotated *because* the
         * old value became dangerous, and copying it into an append-only table
         * on the way out makes it outlive the rotation meant to retire it. The
         * four characters below are enough to answer "did the value actually
         * change" and are worth nothing to anyone who steals the table.
         *
         * APPEND-ONLY at the model layer, like `audit_log` and
         * `registry_changes`.
         */
        Schema::create('credential_changes', function (Blueprint $table): void {
            $table->id();

            // Not a foreign key to `platform_credentials.key`. Clearing a
            // credential deletes its row and the log of that clearing has to
            // survive it — a cascade would erase exactly the history recording
            // the erasure, and a restrict would make clearing impossible.
            $table->string('credential_key');

            // A string cast to App\Enums\CredentialChangeAction.
            $table->string('action');

            $table->string('last_four_before', 4)->nullable();

            $table->string('last_four_after', 4)->nullable();

            $table->string('actor');

            $table->timestamp('created_at');

            $table->index(['credential_key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_changes');
        Schema::dropIfExists('platform_credentials');
    }
};
