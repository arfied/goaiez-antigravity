<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * That we texted the account holder, and about what — wave 40 lane A,
 * decision 10820.
 *
 * ⛔ **THE DEFECT THIS CLOSES: AN OWNER-DIRECTED SEND LEFT NO TRACE AT ALL.**
 * `App\Services\Sms\PlatformTexter::sendToOwner()` shipped at 10540 writing
 * nothing — no send row, no delivery receipt, no send key, no occasion, no
 * `sent_at` — so after an urgent-escalation text left this platform, **nothing
 * in the database recorded that it had happened.** `CLAUDE.md`'s opening
 * promise is that the owner *"does nothing but reply to occasional text
 * messages"*, and wave 39 lane C then built the inbound half: an owner's reply
 * is stored in `owner_replies`. **A reply with no record of a message it could
 * be a reply to is a half of a sentence**, and the missing half was this one.
 *
 * ⚠️ **`outreach_messages` WAS THE OBVIOUS HOME AND IT IS REFUSED — AND THE
 * REFUSAL SURVIVES THIS SLICE'S RE-READING.** `PlatformTexter::sendToOwner()`'s
 * own docblock argues it: that table is *"the customer-outreach ledger"*, which
 * `App\Services\Messaging\Outbound\SendSettlement` reconciles against
 * `SendPermit`-gated sends, and an owner-channel message is neither a campaign
 * nor a delivery a tenant needs reconciled against their own customer list.
 * ⚠️ **That argument is about RECEIPTS AND RECONCILIATION, never about
 * EXISTENCE** — read literally it says only that this send does not belong in
 * the ledger somebody else's reconciliation walks, and it says nothing at all
 * about whether the send should be recorded anywhere. **A separate table is
 * what honours it**: the reconciler's population is unchanged, the tenant's
 * outreach ledger is unchanged, and the send is nonetheless durable.
 *
 * TENANT-OWNED, SO ENABLE + FORCE AND A POLICY IN THIS MIGRATION —
 * `owner_replies`' shape one table over, for its reason: an
 * `App\Services\Consent\OwnerSendPermit` names exactly one business before a
 * single byte leaves, so there is always a tenant to own the row. **This is the
 * opposite of `inbound_messages`**, which is platform-scoped precisely because
 * an inbound STOP arrives with no tenant at all.
 *
 * ## ⛔ WHAT THIS TABLE DELIBERATELY DOES NOT CARRY
 *
 * **The number.** It is already on `owner_notify_numbers`, one row per
 * business, and copying it here would put an account holder's mobile into a
 * second table with a second lifetime — `CLAUDE.md`'s *less stored PII*
 * tiebreaker with nothing on the other side of it, because the send's
 * addressee is derivable and was never in question.
 *
 * **The body.** The words we sent are a template plus, for this one kind, a
 * tenant's own configured urgent word — `App\Jobs\EscalateUrgentThreadJob`'s
 * payload docblock is explicit that *"a tenant's own configured vocabulary is
 * not personal data"* — so `kind` plus `occasion` already says what the text
 * was about, and the prose adds a free-text column whose only reader would be
 * a screen that does not exist. ⚠️ **The one thing a body WOULD buy is a future
 * numbered-reply grammar knowing which options were offered** (`29` §4.5's
 * *"Reply STOP to hold, or 3 to read it"*), **and no owner-directed message
 * offers a numbered option today** — decision **10832** refuses that grammar in
 * writing and states what has to exist first. ⛔ **THIS LINE CITED 10824 UNTIL
 * WAVE 41 LANE E** (11105), which is *"one row per send, never one row per
 * occasion"* — the very heading two paragraphs below — and is a different
 * subject. A column added now for a reader refused in the same slice is 272's
 * shape.
 *
 * ⛔ **AND THE SCREEN THAT DOES NOT EXIST NOW DOES — WAVE 41 LANE E (11110),
 * SO HALF OF THIS REFUSAL HAS SPENT ITSELF.** `App\Livewire\Admin\
 * OwnerChannelTexts` renders every row of this table beside the replies it
 * drew, so a `body` column would today have a real reader. **The column is
 * still not added and this file is still not the place to add one**: what the
 * screen renders is `kind`'s own sentence, and the argument above — that the
 * words are a template plus a tenant's own configured word — is untouched by a
 * screen existing. ⚠️ **What has changed is that re-opening it is now a
 * question about duplication rather than about 272's shape**, and it is owed a
 * slice of its own rather than a lane's convenience.
 *
 * ## ⚠️ ONE ROW PER SEND, NEVER ONE ROW PER OCCASION
 *
 * `provider_message_id` is unique and `(business_id, kind, occasion)` is
 * deliberately **not**. `EscalateUrgentThreadJob::claimIsSpent()` is answered
 * from a flag on the job object, and `CLAUDE.md`'s own engineering rules say
 * that flag is thrown away by a worker-timeout kill — so a retry genuinely can
 * page and text an owner a second time about one occasion. **Two texts left, so
 * two rows is the truth.** A unique index on the occasion would have made this
 * table quietly under-report exactly the duplicate page an operator would want
 * to see, which is the `operator_alerts.emailed_at` shape `CLAUDE.md` names: a
 * column that cannot take the value the state it reports on would give it.
 *
 * ⚠️ **AND THE ROW IS WRITTEN AFTER THE CARRIER ANSWERS, NEVER BEFORE.** A row
 * written first would claim a send that a transport failure then never made —
 * the same *"a column that records a dispatch is not a column that records a
 * delivery"* inversion. `provider_message_id` being `NOT NULL` is what makes
 * that structural: the carrier's own handle for the message does not exist
 * until the carrier has taken it.
 *
 * ⛔ **IT IS STILL NOT A DELIVERY.** `App\Services\Sms\SentText`'s own
 * docblock: Infobip answers a successful submission with `PENDING_ACCEPTED`,
 * and whether a handset ever saw it arrives later on the delivery-receipt
 * webhook. **Nothing here reads that webhook** — `sendToOwner()` passes
 * `reference: null` and `App\Services\Sms\DeliveryReceipts` has no owner-channel
 * arm — so this table answers *"we handed it to the carrier"* and may never be
 * read as *"the owner got it"*. `provider_message_id` is stored so that the day
 * somebody wires the receipt, the join key is already here.
 *
 * ⚠️ **NO `updated_at`, ON `owner_replies`' AND `owner_notification_consents`'
 * VERBATIM ARGUMENT.** A send is a dated event, not a row anything should
 * revise.
 *
 * ⚠️ **RETENTION IS `owner_channel.retention_days` AND IT SHIPS UNSET**, which
 * means `owner-channel:prune` deletes nothing on every deployment that exists
 * today — a missing period is not zero days. That command sweeps this table and
 * `owner_replies` together, on one key, because half a conversation kept is a
 * record that reads as a reply to nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_notifications', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // `App\Enums\OwnerNotificationKind`, cast on the model — a string
            // and never a database enum, `CLAUDE.md`'s standing rule.
            $table->string('kind');

            // The event this send was ABOUT, in whatever vocabulary the sender
            // already keys its own idempotency on — for the one sender that
            // exists, the carrier's id for the inbound message that matched.
            // ⛔ **NOT UNIQUE**: see this migration's docblock. Two pages about
            // one occasion is a real state and the truth about it is two rows.
            $table->string('occasion');

            // The carrier's own handle for the message it accepted. NOT NULL,
            // which is what makes "this row exists" mean "a carrier took it"
            // rather than "we intended to send".
            $table->string('provider_message_id')->unique();

            $table->timestamp('sent_at');

            $table->timestamp('created_at')->nullable();

            // The one query in `app/`: the most recent notification for a
            // business, newest first, inside a window
            // (`App\Services\Sms\OwnerNotifications::latestFor()`).
            $table->index(['business_id', 'sent_at']);
        });

        DB::statement('ALTER TABLE owner_notifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE owner_notifications FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON owner_notifications
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_notifications');
    }
};
