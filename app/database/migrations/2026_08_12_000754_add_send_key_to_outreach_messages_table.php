<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The idempotency key for one send, and the unique index that enforces it
 * (T137 §3 rail 1, decision 2541).
 *
 * ⚠️ **THIS INDEX WAS CLAIMED BEFORE IT EXISTED, WHICH IS `CLAUDE.md`'s 314–316
 * FAILURE SHAPE.** `App\Contracts\SendDriver::deliver()`'s docblock has said
 * since the contract shipped that *"the sender's own dedupe (a unique index on
 * `SendKey`) closes the window before the call"*. There was no such index
 * anywhere in the schema — the only `send_key` column was
 * `campaign_recipients.send_key`, nullable and unconstrained, which is a
 * *record* of the key a runner computed and not a claim on it. A docblock
 * asserting enforcement is what stops the next reviewer looking, so the choice
 * was to build the mechanism or correct the claim. This is the mechanism.
 *
 * ## Why the claim lives on `outreach_messages` and not somewhere new
 *
 * The sender writes this row **before** the wire call, inside the transaction
 * that also debits the credit (`BUILD-PLAN` §2.10.1's third property, and
 * `SendCredits`' *"credit debit is transactional with the send"*). So the row is
 * already the record of *"we decided to send this exact message"* — which is
 * precisely what a second attempt must collide with. A separate `send_claims`
 * table would be a second store of the same fact, and the two would disagree the
 * first time one write succeeded and the other did not.
 *
 * ⚠️ **THE CLAIM IS THE INSERT, NEVER A CHECK-THEN-INSERT** — decision 350's
 * rule. Two queue workers handed the same retried job both read "no row" and
 * both send; only a unique index refuses the second. `PlatformMessageSender`
 * catches SQLSTATE 23505 and answers `SendOutcomeStatus::Duplicate`.
 *
 * ## Nullable, and unique across the whole table rather than per tenant
 *
 * ⚠️ **NULLABLE BECAUSE THE COLUMN ARRIVES AFTER ITS TABLE'S FIRST WRITERS.**
 * `ReviewInviteSender` has been writing `outreach_messages` since row 4 slice 4
 * and computes no `SendKey`; backfilling one would mean inventing the occasion
 * string those sends never had. PostgreSQL treats every NULL as distinct in a
 * unique index, so those rows and every future one that has no key coexist
 * freely — and a keyed send still collides with a keyed send.
 *
 * ⚠️ **NOT `unique(['business_id', 'send_key'])`, AND THE REASON IS IN
 * `SendKey` ITSELF.** The tenant id is already inside the hash
 * (`SendKey::for()`), so two tenants cannot compute the same value; adding
 * `business_id` to the index would widen it for a collision that is
 * cryptographically unavailable, and it would let a bug in tenant resolution
 * hide behind a composite key. A single-column unique index means a key
 * collides with itself and with nothing else.
 *
 * ⚠️ **RLS DOES NOT WEAKEN IT AND DOES NOT HELP IT EITHER.** A unique index is
 * enforced by the database under every policy, so a hypothetical cross-tenant
 * collision would be refused with rows the reader cannot see — a 23505 with no
 * visible duplicate. That is the correct behaviour and it is worth knowing
 * before meeting it; it is unreachable while the tenant is in the hash.
 *
 * Proven by `tests/Feature/Messaging/MessageSenderTest.php` — the double
 * dispatch test, which is driven red by dropping this index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            // 64 characters: `SendKey` is a hex sha-256 and is exactly that
            // long. Sized to the value rather than to `string`'s 255 default so
            // that a longer thing arriving here is a refusal rather than a
            // silent truncation into a key that deduplicates the wrong sends.
            $table->string('send_key', 64)->nullable()->after('provider_msg_id');

            $table->unique('send_key');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->dropUnique(['send_key']);
            $table->dropColumn('send_key');
        });
    }
};
