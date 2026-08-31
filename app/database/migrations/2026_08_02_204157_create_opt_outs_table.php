<?php

declare(strict_types=1);

use App\Enums\OptOutScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform-scoped opt-out register — `DATA-MODEL` §5.6, decision 294's fix.
 *
 * ⚠️ WHAT IS ACTUALLY BROKEN WITHOUT IT. `suppression_list` is tenant-owned, so
 * COMP-01's "honoured instantly and globally" reaches every channel and campaign
 * of one business. **That is the right boundary on Lane B and the wrong one on
 * Lane A.** Lane A is a single verified toll-free number under the GO AI EZ
 * brand (`25` §1.3), and a carrier STOP is keyed on the sending number and the
 * recipient — not on whichever tenant prompted the message. So when two Lane A
 * tenants message the same person, the first STOP ends that number's right to
 * text them at all, and a tenant-scoped lookup authorises the second tenant's
 * send quite happily.
 *
 * NOT TENANT-OWNED, AND NO RLS. A platform-scoped row has no `business_id` to
 * predicate on, and a policy that admitted NULL would admit every row anyway.
 * The model joins the `TenancyTest` allowlist with its reasoning there —
 * the same shape as `PlacesApiCall`, whose nullable key is likewise the reason
 * the trait cannot go on.
 *
 * ⚠️ `value_hash`, NEVER THE IDENTIFIER. A platform-wide register of phone
 * numbers *is* a marketing list, and this one would be the highest-value table
 * in the schema — every number belongs to somebody who has demonstrably engaged
 * with a local business. A keyed hash answers "is this person opted out" without
 * being enumerable, and cannot be reversed by anybody who does not already hold
 * the application key. `App\Support\Identifier::hash()` is the only writer of
 * these values, and it normalises before hashing — because a hash only matches
 * exactly, and an un-normalised hash silently matches nothing.
 *
 * ⛔ AND THE KEY IS `APP_KEY`, SO ROTATING IT EMPTIES THIS REGISTER WITHOUT
 * DELETING A ROW. The paragraph above says the hash "cannot be reversed by
 * anybody who does not already hold the application key" and stops there, which
 * reads as a property of the *storage*. It is also a property of the *lookup*:
 * `ConsentService::hasOptedOut()` hashes the identifier freshly on every send
 * and compares it against this column, so a new key makes every stored row
 * unmatchable at once. `isSuppressed()` then answers false, `decide()` falls
 * through to the consent record — which exists, because that is why the person
 * was messaged — and the platform texts somebody who sent STOP.
 *
 * ⛔ AND "NOTHING RAISES, NOTHING LOGS, AND NO TEST CAN CATCH IT: THE SUITE
 * HASHES WITH THE SAME KEY IT COMPARES AGAINST" IS WHAT THIS PARAGRAPH SAID
 * UNTIL 2026-08-22, IN THE COMMIT RANGE THAT MADE ALL THREE FALSE (8192).
 * `identifier_hash_epochs` fingerprints the hashing function itself and
 * `ConsentService::decide()` refuses every send with
 * `SendRefusalReason::SuppressionUnreadable` while it does not match;
 * `php artisan consent:hash-epoch` reports it and exits non-zero;
 * `tests/Feature/ConsentKeyRotationTest.php` drives the whole transition with
 * one line — `config()->set('app.key', …)` between the write and the read.
 * ⚠️ THE STRUCTURAL CLAIM UNDERNEATH IT WAS RIGHT AND IS WHY THE FIX LOOKS THE
 * WAY IT DOES (3236's property, and 8064(a)'s send gate): the suite does hash
 * with the key it compares against, so what is detectable is the *transition*
 * and never the state. ⛔ AND NONE OF IT MAKES A ROTATION SAFE — the rows below
 * are still unmatchable and the only repair is the previous key.
 *
 * ⛔ AND THERE IS NO WAY BACK FROM IT, WHICH IS WHAT MAKES THIS DIFFERENT FROM
 * THE OTHER HASHED TABLES. `ConsentService::suppressFromCarrier()` — the writer
 * for every carrier STOP — writes this table and nothing else, and its log line
 * omits the identifier deliberately, for the same reason the column holds a
 * hash. **No clear copy of these identifiers exists anywhere in the schema**, so
 * the register cannot be re-derived; with the old key still in hand a partial
 * rebuild is possible from identifiers held in clear elsewhere
 * (`customers.phone`, `suppression_list.identifier`), and a STOP from somebody
 * no tenant holds as a contact is unrecoverable by any means. **The privacy
 * property and the recovery cost are the same property.**
 * `.claude/skills/deploying/` carries the operator's account of this, because
 * that is the file somebody reads before a rotation and this one is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opt_outs', function (Blueprint $table): void {
            $table->id();

            // A string cast to OptOutScope, never a database enum (`CLAUDE.md`).
            $table->string('scope')->default(OptOutScope::Platform->value);

            // ⚠️ NULLABLE, AND THAT IS WHY THIS TABLE CANNOT CARRY THE TENANT
            // TRAIT. A platform-scoped opt-out belongs to no business. A global
            // scope here would hide exactly the rows the boundary depends on —
            // and hide them from the one query whose job is to refuse a send.
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();

            // sms | email | whatsapp — the OutreachChannel that was opted out of.
            // Per channel rather than per person, because decision 286 dropped
            // `customers.is_suppressed` for exactly this: one boolean cannot say
            // "suppressed on SMS, reachable on email".
            $table->string('identifier_type');

            $table->string('value_hash', 64);

            // No updated_at: a row is written once and never edited. An opt-out
            // that can be amended is not evidence of anything.
            $table->timestamp('created_at')->nullable();

            // DATA-MODEL §5.6's key. Postgres treats NULLs as distinct in a
            // unique index by default, so the platform rows — where business_id
            // is NULL — would not be deduplicated by this alone. NULLS NOT
            // DISTINCT fixes that, and is why a second STOP on the shared number
            // cannot write a second row.
            $table->unique(
                ['scope', 'business_id', 'identifier_type', 'value_hash'],
                'opt_outs_scope_business_type_value_unique'
            );

            // The read path: one hash, one channel, both scopes at once.
            $table->index(['identifier_type', 'value_hash']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE opt_outs
                DROP CONSTRAINT opt_outs_scope_business_type_value_unique
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE opt_outs
                ADD CONSTRAINT opt_outs_scope_business_type_value_unique
                UNIQUE NULLS NOT DISTINCT (scope, business_id, identifier_type, value_hash)
        SQL);

        // The scope and the key must agree, and the damage of their disagreeing
        // is the one this table exists to prevent: a `platform` row carrying a
        // business_id would be read as platform-wide by the scope predicate
        // while looking tenant-scoped to anybody reading the row, and a `tenant`
        // row without one would silently suppress every tenant.
        //
        // Slice B's precedent (314–316), for a rule whose failure is a message
        // sent to somebody who said STOP.
        DB::statement(<<<'SQL'
            ALTER TABLE opt_outs
                ADD CONSTRAINT opt_outs_scope_matches_business
                CHECK (
                    (scope = 'platform' AND business_id IS NULL)
                    OR (scope = 'tenant' AND business_id IS NOT NULL)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('opt_outs');
    }
};
