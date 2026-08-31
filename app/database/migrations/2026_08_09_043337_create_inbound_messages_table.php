<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every message a customer sent *us* — row 4 slice 2's inbound half.
 *
 * ⚠️ **THIS TABLE EXISTS BECAUSE `outreach_messages` COULD NOT HOLD THESE ROWS,
 * AND `BUILD-PLAN` §2.10.3 SAID IT WOULD.** The slice plan lists *"`outreach_messages`
 * gaining the **direction** column 941 records as absent"*, written before
 * anybody checked that table's tenancy. It is tenant-owned — `BelongsToTenant`,
 * with `business_id` guarded — and **an inbound carrier STOP has no tenant**:
 * it arrives with a sender, our receiving number and a body, and today there is
 * exactly one sending number, shared, on Lane A. Nothing maps a number to a
 * business until slice 6's `numbers` table exists.
 *
 * So a `direction` column added now would have had no writer — the first of
 * `CLAUDE.md`'s recurring failure shapes, whose instruction is to *"check for a
 * writer before depending on any table in this schema — and before **designing**
 * against one"*. It is deliberately **not** added; this table is added instead,
 * and it has a writer on the day it ships.
 *
 * NOT TENANT-OWNED, AND NO RLS — the third table of `opt_outs`' shape and for
 * `opt_outs`' reason. A platform-scoped row has no `business_id` to predicate
 * on, and a policy admitting NULL would admit every row anyway. The model joins
 * the `TenancyTest` scope allowlist with its reasoning there.
 *
 * ⚠️ **`value_hash`, NEVER THE SENDER'S NUMBER**, and the argument is `opt_outs`'
 * verbatim because the exposure is identical: a platform-wide table of phone
 * numbers *is* a marketing list, and this one would be worse than the register
 * it accompanies — every number here belongs to somebody who not only engaged
 * with a local business but replied. `App\Support\Identifier::hash()` is the
 * only writer of these values and normalises first, because a hash matches only
 * exactly.
 *
 * ⚠️ **AND THE BODY IS NOT STORED AT ALL.** Only the keyword we resolved it to.
 * `24` gives us no reason to keep the text of an inbound message, the STOP path
 * needs the classification rather than the words, and a free-text column here
 * would be the one place in this schema where a member of the public's own
 * writing sits beside their phone number under no tenant's retention policy.
 * The one thing lost is a support conversation about *why* a message classified
 * as it did — which is what `keyword` plus the carrier's own logs answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_messages', function (Blueprint $table): void {
            $table->id();

            /*
             * The carrier's own id for this message.
             *
             * ⚠️ UNIQUE, AND THAT UNIQUENESS IS THE REPLAY DEFENCE. Infobip
             * retries a webhook that did not answer promptly, so a redelivery
             * is the ordinary case rather than an attack — and the two things
             * that must not happen twice are a HELP auto-reply (which costs a
             * segment and looks like a loop to the recipient) and an audit
             * entry claiming a second STOP arrived.
             *
             * ⚠️ IT IS THE INDEX RATHER THAN A CHECK IN CODE, deliberately.
             * Two concurrent redeliveries both pass a `->exists()` and both
             * proceed; only the database can refuse the second, and
             * `StripeEvent` established the same shape for the same reason.
             */
            $table->string('provider_message_id')->unique();

            // The channel this arrived on, cast to OutreachChannel. A string
            // rather than a DB enum, per CLAUDE.md's standing rule.
            $table->string('identifier_type');

            $table->string('value_hash');

            /*
             * What we decided the message meant, cast to InboundKeyword.
             *
             * `none` is a real, common and deliberately recorded answer: most
             * inbound text is somebody replying to a review invite in words,
             * and a table that only recorded the three control keywords would
             * report that no such messages exist.
             */
            $table->string('keyword');

            /*
             * When the carrier says the handset sent it — NOT when we processed
             * it, which is `created_at`.
             *
             * The two differ by the retry interval on a redelivery, and the
             * difference matters exactly once: proving a send went out *before*
             * a STOP arrived rather than after. Nullable, because the field is
             * optional in Infobip's own inbound model and a missing timestamp
             * must not cost us the STOP.
             */
            $table->timestamp('received_at')->nullable();

            $table->timestamp('created_at')->nullable();

            /*
             * ⛔ **THIS HAS NO READER, AND THE SENTENCE THAT USED TO BE HERE
             * SAID IT HAD ONE — CORRECTED 2026-08-23 (8326).** It read: *"The
             * lookup the handler makes: has this person sent us anything, and
             * what."* **The handler makes no such lookup and never has.**
             * `InboundMessages::record()` inserts and reads nothing back;
             * the redelivery defence is the unique index on
             * `provider_message_id` one field up; `NumberHealthService` and
             * `MessageCostLedger` both filter on `to_number`; and
             * `CampaignReplyResolver::contactBehind()` — the one thing in
             * `app/` that touches `value_hash` at all — reads it **off a row
             * already in hand** and hashes the tenant's *contacts* to compare
             * in PHP, because the key lives in the application and no SQL join
             * can bridge it.
             *
             * ⚠️ **THE COST OF THE OLD SENTENCE IS MEASURABLE AND IS WHY THIS
             * IS A PARAGRAPH RATHER THAN A DELETION.** It is the source of
             * `ConsentTest`'s hash census calling this digest a *"redelivery
             * dedupe and the keyword history"* and of decision 8094 repeating
             * it — **an index comment that misdescribed a query propagated into
             * a lint and then into an append-only decision row.** It is 272's
             * shape at index level and 314–316's in prose: a reader who wants
             * to know whether the STOP history is queryable finds a sentence
             * saying it is, and stops.
             *
             * ⚠️ **THE INDEX IS NONETHELESS KEPT, ON EVIDENCE RATHER THAN ON
             * INERTIA** (8327). `ExportBuilder`'s `CONTACT_EXCLUSIONS` names the
             * reader that is owed and names this exact key: `44` §10's *files*
             * category is missing *"because `inbound_media` hangs off
             * `inbound_messages`, which is platform-scoped and keyed on
             * `identifier_type` + `value_hash` rather than on a contact — so
             * there is no reader anywhere in `app/` that turns a contact into
             * their own photographs"* (6568). That is a data-subject answer
             * about somebody's own pictures, and it is the one query this
             * composite is right for.
             *
             * ⛔ **SO ITS FATE IS TIED TO 6568 AND TO NOTHING ELSE.** If that
             * export category is ruled out of scope, this index goes with it in
             * the same commit; it may not be kept a second time on the strength
             * of this paragraph.
             *
             * ⚠️ **AND THE COLUMN THAT DOES HAVE TWO LIVE READERS HAS NO INDEX
             * AT ALL — RAISED, NOT BUILT** (8328). `to_number` is filtered by
             * `NumberHealthService::inboundCounts()` (once per number per
             * window, from a scheduled rollup) and grouped by
             * `MessageCostLedger::unattributedInboundCount()`, and it arrived
             * without one because 1623 shipped the writer deliberately ahead of
             * any reader. **It is not added here**: this table holds no rows in
             * production, nothing has been measured, and adding an index on the
             * instinct that produced the paragraph above — inside the change
             * that is correcting it — would be the same mistake with the
             * argument pointing the other way. The difference is the one worth
             * writing down: **those readers exist in `app/` today.**
             *
             * ⛔ **RE-EXAMINED 2026-08-23 AND UPHELD — AND THE COUNT ABOVE IS
             * ONE THAT NEEDS ITS WORD** (8437). *"Two live readers"* is right
             * only if it means *two that FILTER*; four things in `app/` touch
             * `to_number`, and `CampaignReplyResolver` and `InboundMediaCapture`
             * read it off a row already in hand. ⚠️ **The re-examination
             * weakened the case for an index rather than strengthening it**:
             * `MessageCostLedger::unattributedInboundCount()` carries no `WHERE`
             * at all, so the leading-column seek that makes
             * `(to_number, created_at)` worth having serves **one** reader, not
             * two. **The refusal above is unchanged and the standard for
             * overturning it is unchanged with it: a measurement, not a
             * paragraph.**
             *
             * Composite because neither half is selective alone — one channel
             * today, and a hash is meaningless without it.
             */
            $table->index(['identifier_type', 'value_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_messages');
    }
};
