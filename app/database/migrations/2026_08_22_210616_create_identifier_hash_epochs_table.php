<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which hashing key this schema's stored identifier hashes were written under.
 *
 * ⛔ **THE FAILURE THIS TABLE EXISTS TO MAKE IMPOSSIBLE TO HAVE QUIETLY, AND IT
 * WAS MEASURED RATHER THAN REASONED ABOUT** (8080). `Identifier::hash()` is
 * `hash_hmac('sha256', $normalised, config('app.key'))`, and every suppression
 * store in this schema keeps that digest and nothing else — `opt_outs`,
 * `compliance_suppressions`, `suppression_lifts`. Rotate `APP_KEY` and a freshly
 * computed hash matches none of them. A carrier STOP written by
 * `ConsentService::suppressFromCarrier()` has **no clear copy anywhere in this
 * schema**, so the row survives, refuses nobody, and `decide()` falls through to
 * the consent record — **which exists, because that is why the person was texted
 * in the first place** — and grants. Driven on 2026-08-22: `refused opted_out`
 * before the rotation, `GRANTED` after it, with nothing raised and nothing
 * logged.
 *
 * ⚠️ **AND `previous_keys` CANNOT HELP, WHICH IS WHAT MAKES IT SILENT.**
 * `config/app.php` carries Laravel's stock `previous_keys` and it is
 * **decryption-only** — the encrypter walks it, `hash_hmac()` reads
 * `config('app.key')` directly and has no notion of an older one. So an operator
 * following Laravel's own documented graceful rotation finds every `Crypt`
 * column, OAuth token and unsubscribe link still working and concludes the
 * rotation went cleanly. **Every `Crypt` path screams; every `hash_hmac` path is
 * silent.**
 *
 * ⚠️ **A FINGERPRINT OF THE FUNCTION, NOT OF THE KEY** — `IdentifierHashEpochs`
 * hashes two fixed canaries (a NANP national number and a reserved-domain
 * address) **through `Identifier::hash()` itself** and stores a digest of the
 * pair. So this column moves when the key moves, and it also moves when
 * `Identifier::PHONE_REGION` moves — which that file records as a hazard for a
 * changing region and nothing enforced. One row here answers both.
 *
 * ⚠️ **STORING IT REVEALS NOTHING.** The value is a SHA-256 of two HMAC-SHA-256
 * tags over publicly known inputs; recovering the key from it is the known-
 * plaintext attack HMAC is defined to resist, over a 32-byte random secret. What
 * it does give is an offline check that a candidate key is the right one, which
 * is exactly what an operator restoring a backup needs.
 *
 * ⚠️ **NOT TENANT-OWNED, AND NO RLS** — the same reasoning as
 * `compliance_suppressions` and `opt_outs` beside it. There is one application
 * key for the whole install; an epoch is a fact about this deployment, not about
 * a business, and a tenant predicate would hide it from the send gate on the
 * platform paths that have no tenant resolved at all (a carrier webhook is
 * exactly one).
 *
 * ⛔ **AND "IT CARRIES NO PERSONAL DATA OF ANY KIND" IS WHAT THIS PARAGRAPH SAID
 * UNTIL 2026-08-22, IN THE SAME COMMIT THAT SHIPPED `retired_by` — CORRECTED AT
 * 8189.** `retired_by` and `adopted_by` are whatever an operator typed into
 * `--actor`, which is a person by design: the row is the only record that
 * somebody decided this. What is true, and is the whole of what the exemption
 * needs, is that **no end customer's data can reach this table** — there is no
 * identifier, no hash of one, and no tenant. The operator handle is ours, it is
 * the same class of value `credential_changes` and `registry_changes` already
 * hold outside the tenant boundary, and an RLS policy keyed on `business_id`
 * could not protect it in any case.
 *
 * ⚠️ **APPEND-ONLY IN SPIRIT AND SOFT-RETIRED IN FACT**, `compliance_suppressions`'
 * precedent: retiring an epoch is the act of saying *"every hash written under
 * that key is permanently unmatchable and we accept it"*, which is the single
 * most consequential thing an operator can do to this platform's suppression
 * registers. A hard delete would leave that decision with no trace, so a
 * retirement is a date, an actor and a reason or it is none of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifier_hash_epochs', function (Blueprint $table): void {
            $table->id();

            // The digest of the two canaries, from `IdentifierHashEpochs::fingerprint()`.
            //
            // ⛔ **NOT UNIQUE, AND THE PARTIAL INDEX BELOW IS WHY** (8184). One
            // row here is one *era* of a key, not one key: an install can
            // legitimately return to a fingerprint it has retired — restore a
            // backed-up `.env` after accepting the loss and the hashes written
            // under it are readable again — and a plain unique would force that
            // return to be spelled as an UPDATE that nulls `retired_at`,
            // destroying the only record that anybody ever decided to lose
            // every suppression on the platform.
            $table->string('fingerprint', 64);

            // When something first wrote a durable hash under this key. Not
            // `timestamps()`: there is no meaningful `updated_at` on a row whose
            // only mutation is a retirement, which carries its own date.
            $table->timestamp('first_seen_at');

            // What first wrote under it — `carrier-stop`, `compliance-register`,
            // `suppression`, `console:consent:hash-epoch`. Names the act, never
            // a person and never an identifier.
            $table->string('first_seen_by');

            // ⚠️ **SET WHEN A PERSON ASSERTED THIS ERA RATHER THAN WHEN A WRITE
            // OBSERVED IT** (8185). An epoch is normally recorded by the act
            // that stored a hash, which is evidence. These two columns cover
            // the case where there is no evidence to be had — an install that
            // upgraded onto this guard with suppression rows already in it —
            // and somebody had to say *the key in `.env` is the one that wrote
            // these*. That is a claim, not an observation, so it carries a
            // name: `first_seen_by` stays the act and the person goes here.
            $table->string('adopted_by')->nullable();
            $table->string('adopted_reason')->nullable();

            $table->timestamp('retired_at')->nullable();
            $table->string('retired_by')->nullable();
            $table->string('retired_reason')->nullable();

            // The read on every send decision: which epochs still claim to
            // describe live data.
            $table->index('retired_at');
        });

        // ⛔ **AT MOST ONE LIVE ERA PER FINGERPRINT, WHICH IS WHAT THE PLAIN
        // UNIQUE USED TO BUY AND IS ALL OF IT THAT WAS LOAD-BEARING.** Two live
        // rows for one fingerprint would make `status()` compare a set against
        // a single-element list and answer `Rotated` against this install's own
        // key. Retired rows are outside the index on purpose: they are history,
        // and history repeats here.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX identifier_hash_epochs_one_live_era
                ON identifier_hash_epochs (fingerprint)
                WHERE retired_at IS NULL
        SQL);

        // ⚠️ THE SAME SHAPE CHECK `compliance_suppressions`, `opt_outs` AND
        // `trial_claims` CARRY. A value of any other shape reached this column
        // without going through `fingerprint()`, and a fingerprint that is not a
        // digest cannot be compared with one that is — which would make this
        // whole table answer `Rotated` for ever.
        DB::statement(<<<'SQL'
            ALTER TABLE identifier_hash_epochs
                ADD CONSTRAINT identifier_hash_epochs_fingerprint_is_sha256
                CHECK (fingerprint ~ '^[0-9a-f]{64}$')
        SQL);

        // A retirement is a date, an actor and a reason, or it is none of them —
        // `compliance_suppressions_removal_is_attributed`'s rule, and it matters
        // more here: this is the row that records somebody deciding to lose
        // every stored suppression on the platform.
        DB::statement(<<<'SQL'
            ALTER TABLE identifier_hash_epochs
                ADD CONSTRAINT identifier_hash_epochs_retirement_is_attributed
                CHECK (
                    (retired_at IS NULL AND retired_by IS NULL AND retired_reason IS NULL)
                    OR (retired_at IS NOT NULL AND retired_by IS NOT NULL AND retired_reason IS NOT NULL)
                )
        SQL);

        // The same rule for the other attributed act on this row. An adoption
        // records that a **person** asserted something this application cannot
        // check, so a name without a reason is half a record.
        DB::statement(<<<'SQL'
            ALTER TABLE identifier_hash_epochs
                ADD CONSTRAINT identifier_hash_epochs_adoption_is_attributed
                CHECK (
                    (adopted_by IS NULL AND adopted_reason IS NULL)
                    OR (adopted_by IS NOT NULL AND adopted_reason IS NOT NULL)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('identifier_hash_epochs');
    }
};
