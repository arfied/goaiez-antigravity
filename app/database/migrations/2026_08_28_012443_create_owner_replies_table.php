<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the account holder said back — wave 39 lane C, decision row 10720+.
 *
 * ⛔ **`inbound_messages` REFUSES TO STORE A BODY, AND THIS TABLE IS THE
 * ARGUMENT THAT THE REFUSAL DOES NOT REACH HERE.** That table's own creating
 * migration (`2026_08_09_043337_create_inbound_messages_table.php:40`) is
 * explicit about who it is about: *"a member of the public's own writing"*,
 * on a table that is deliberately platform-scoped because an inbound STOP
 * carries no tenant. **An account holder replying to a text we sent them is a
 * different subject with a different table available to it**: by the time
 * `App\Services\Sms\InboundMessages::handle()` reaches this write, the reply
 * has already resolved to exactly one business — `OwnerConsentService::
 * businessesFor()`'s unambiguous case — so there is a tenant to own the row, a
 * retention policy to apply to it, and no platform-wide phone-number list to
 * protect against, which is the entire reason the other table's refusal exists.
 *
 * ⛔ **"A RETENTION POLICY TO APPLY TO IT" WAS FALSE ON THE DAY IT WAS WRITTEN
 * AND IS NOW TRUE, WITH A CAVEAT THAT MATTERS MORE THAN THE CORRECTION — wave
 * 40 lane A (10834).** Fourteen `Prune*` commands existed and **not one touched
 * this table**; the only retention keys in the registry were
 * `automation.retention_days`, `fetch.attempts_retention_days` and
 * `review_loss.snapshot_retention_days`. The free text of an account holder's
 * own messages left only by the `business_id` cascade. **This is `CLAUDE.md`'s
 * 314–316 shape: the paragraph asserting a protection was the argument used to
 * justify creating the column.** `App\Console\Commands\PruneOwnerChannel` and
 * `owner_channel.retention_days` now exist — and ⛔ **the key ships with no
 * seed, so on every deployment that exists today this table is still kept for
 * ever.** An unset period is a no-op, not a zero. **The number is the owner's
 * and counsel's**, for the reason `storage.retention_days.*` states.
 *
 * ⚠️ **AND THE TIEBREAKER RUNS THE OTHER WAY HERE.** `CLAUDE.md`'s ordering —
 * less support surface, less stored PII, lower marginal cost — argues against
 * a new column almost everywhere else in this schema. It argues *for* one here:
 * the entire premise of the owner channel (`CLAUDE.md`'s first paragraph, *"the
 * owner does nothing but reply to occasional text messages"*) is that a reply
 * is actionable, and an architecture that identifies an owner's reply and then
 * discards its words has built the half of the sentence that costs nothing and
 * skipped the half that was the point. **Not storing it is the choice that
 * costs the owner something** — the one case `CLAUDE.md`'s own tiebreaker
 * ordering does not settle by default, because "less stored PII" and "the
 * product does what it promises" point in different directions for exactly one
 * kind of message on this whole platform.
 *
 * TENANT-OWNED, SO ENABLE + FORCE AND A POLICY IN THIS MIGRATION —
 * `owner_notification_consents`' shape, for the same reason: a business's own
 * account holder is not a `Customer` row and this is not `consent_records`,
 * but the tenant boundary is identical.
 *
 * ⚠️ **`provider_message_id` IS UNIQUE, ON `inbound_messages`' OWN ARGUMENT.**
 * The carrier retries a webhook that did not answer promptly, so a redelivery
 * is ordinary rather than an attack, and `InboundMessages::record()`'s unique
 * index already refuses the second copy of the *inbound_messages* row before
 * this table is ever reached — this index is the second lock, for the same
 * reason `InboundMessages::recordCost()`'s idempotency key is, in case a future
 * caller ever reaches this write from somewhere that redelivery guard does not
 * cover.
 *
 * ⚠️ **NO `updated_at`, AND THE ARGUMENT IS `inbound_messages`' AND
 * `owner_notification_consents`' VERBATIM.** What somebody said is an event,
 * not a row anything should ever revise — and `Log::warning()` is what records
 * a write this table refuses, per every writer on this path swallowing its own
 * failure rather than turning a webhook 200 into a 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_replies', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The carrier's own id for the inbound message this reply came in
            // on — unique for InboundMessages' redelivery reason, and useful on
            // its own terms: it is the join key back to `inbound_messages` if
            // that table's own `to_number` or `keyword` is ever needed beside
            // this row, which no query in `app/` performs today.
            $table->string('provider_message_id')->unique();

            // What the account holder actually typed. Free text, deliberately:
            // see this migration's docblock for why the refusal `inbound_messages`
            // states in writing does not reach this subject.
            $table->text('body');

            // When the carrier says the handset sent it, not when we processed
            // it — `inbound_messages.received_at`'s own reasoning, nullable for
            // the same optional-field-in-the-webhook reason.
            $table->timestamp('received_at')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('business_id');
        });

        DB::statement('ALTER TABLE owner_replies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE owner_replies FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON owner_replies
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_replies');
    }
};
