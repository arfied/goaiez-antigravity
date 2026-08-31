<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\OutreachChannel;
use App\Exceptions\TextNotDeliverable;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendOutcome;

/**
 * One channel's way of putting a finished, authorised message on the wire.
 *
 * T137 §2 names this a day-0 stub-first contract, described as *"send-driver
 * contract (SMS/email share permit + suppression)"*. This interface is the half
 * they do **not** share — the wire — and it is deliberately thin, because the
 * half they do share is {@see OutboundMessage} and {@see MessageSender}.
 *
 * ## The layering, and why there are three levels rather than two
 *
 *   {@see MessageSender}   decides *whether* — kill switches, quiet hours, the
 *                          rate governor, the credit debit, the message row.
 *                          One implementation. Channel-agnostic.
 *   `SendDriver`           decides *how, on this channel* — which of our numbers,
 *                          which mailer identity, which vendor call. One per
 *                          channel.
 *   {@see Texter}          the SMS wire itself, and nothing else. Already
 *                          exists, confined to five files by a lint, and this
 *                          interface does not replace it.
 *
 * ⚠️ **A `SendDriver` IS NOT A `Texter` AND MUST NOT BECOME ONE.** `Texter`'s
 * docblock forbids the transport learning anything decision-shaped, and the
 * lint in `tests/Feature/Architecture/MessagingTest.php` holds it to five files
 * precisely so nothing can reach a carrier without going through
 * `PlatformTexter`'s permit gate. The SMS driver here **delegates to
 * `PlatformTexter`**; it does not call a `Texter` and it is not on that
 * allowlist.
 *
 * ## Why a driver cannot be reached without a permit
 *
 * Its only parameter is an `OutboundMessage`, whose constructor is private and
 * whose one factory requires a `SendPermit` — and only `ConsentService` mints
 * those (285, 1566, and an `ArchitectureTest` lint). So the compliance property
 * that `PlatformTexter` gives SMS today extends to email and to every future
 * channel **by construction rather than by a check somebody has to remember to
 * write in each new driver**.
 *
 * ⚠️ **AND THAT IS TRUE UNDER BOTH SENDING BASES** (2098–2099). An
 * `ImportAttestation` is a recorded sending basis beside platform-captured
 * consent — but it reaches a driver the same way, as a permit, through the same
 * gate. **A driver cannot tell the two apart and must never try**: `messaging_lane`
 * stays derived and unsettable, and the one place the distinction is made is
 * `ConsentService`. A driver that branched on `$message->permit->capturedBy`
 * would be the second implementation of the rule, and the second one drifts.
 *
 * ## What a driver may never do
 *
 * - **Never look up consent, suppression, or an opt-out.** It has already
 *   happened, one and two layers up, and a second check that disagrees is worse
 *   than none.
 * - **Never queue, retry, or hold.** Backoff belongs to the job that called the
 *   sender. A driver that retried would send twice on a timeout, because the
 *   vendor's first attempt may well have succeeded.
 * - **Never debit a credit or write `outreach_messages`.** T137 §3.1 makes the
 *   debit transactional with the send, which means it is the sender's, in one
 *   transaction, around the driver call. A driver filing its own record files it
 *   after the fact and loses every send the process dies in the middle of.
 * - **Never truncate, template or shorten a link.** The body arrives finished.
 */
interface SendDriver
{
    /**
     * The one channel this driver carries.
     *
     * A single value rather than a list: a driver that answered for two channels
     * would need a branch inside `deliver()`, and that branch is where an email
     * identity ends up chosen by SMS rules.
     */
    public function channel(): OutreachChannel;

    /**
     * Put this message on the wire, or say why not.
     *
     * ⚠️ **THE IDEMPOTENCY KEY TRAVELS ON THE MESSAGE. THE SENDER'S OWN DEDUPE
     * IS REAL; THE VENDOR-SIDE HALF IS NOT BUILT, AND THIS PARAGRAPH USED TO SAY
     * OTHERWISE.**
     *
     * ✅ **The application-side dedupe exists as of decision 2541**: a unique
     * index on `outreach_messages.send_key`, claimed by INSERT before the wire
     * call, answering `SendOutcomeStatus::Duplicate` on SQLSTATE 23505.
     *
     * ⛔ **The vendor-side half does not** (2545). This docblock previously
     * asserted a unique index that **did not exist anywhere in the schema** —
     * the only `send_key` column was `campaign_recipients.send_key`, nullable
     * and unconstrained — and went on to say *"both are needed and neither is
     * sufficient"*, which reads as two layers where there were none. That is
     * `CLAUDE.md`'s 314–316 shape exactly: a docblock claiming enforcement is
     * what stops the next reviewer looking. One half is now built and the other
     * is now named as absent.
     *
     * ⚠️ **WHAT THE MISSING HALF COSTS, SO NOBODY HAS TO REDERIVE IT**: a vendor
     * timeout *after* the carrier accepted the message rolls this application's
     * record back while the handset still receives it, and the retry claims the
     * key again and sends a second time. Infobip's `destinations[].messageId` is
     * the obvious candidate, and nothing in its generated client or models says
     * it deduplicates — so it is not used, because an idempotency guarantee
     * invented on a vendor's behalf is worse than a gap that is written down.
     *
     * ⛔ **THE LAST CLAUSE OF THAT PARAGRAPH — *"the retry claims the key again
     * and sends a second time"* — IS THE 2026-08-11 READING AND IS NOW TRUE OF
     * ONE CALLER OUT OF FOUR. BOTH KEPT AND DATED, 2026-08-21 (7068).** The SMS
     * transport now classifies its connection failure (7060) and the three jobs
     * that send one keyed message keep their `automation_runs` claim when the
     * carrier may already hold it, so no retry reaches a driver a second time.
     * **`RunCampaignJob` has no claim to keep and the sentence is unchanged for
     * it** (7069).
     *
     * ⚠️ **AND THE `messageId` HALF WAS RE-READ FROM THE VENDOR'S OWN OPENAPI
     * SPECIFICATION RATHER THAN ITS GENERATED CLIENT** — version `3.222.1`,
     * `x-generatedAt` 2026-08-20T08:26:36Z, fetched 2026-08-21. It confirms the
     * refusal: **no idempotency or deduplication vocabulary anywhere in the
     * document, and no header parameters on the send endpoint.** What it adds is
     * that the same field is echoed into `GET /sms/3/logs?messageId=…` for 48
     * hours, so supplying one would make an unknown outcome *answerable* without
     * claiming it *safe* — a different mechanism, unbuilt, recorded at 7066.
     *
     * @return SendOutcome Accepted, refused, or duplicate. ⚠️ **A driver may
     *                     only return `refused` for a fact about this channel's
     *                     own transport** — no inventory able to send, a mailer
     *                     identity not configured. Every compliance refusal
     *                     happened before it was called.
     *
     * @throws TextNotDeliverable when the vendor could not be reached or would
     *                            not take the message. **Never returned as a
     *                            value**: a send that did not happen must not be
     *                            indistinguishable from one that did, which is
     *                            the state the `log` mail transport put this
     *                            application in (700).
     */
    public function deliver(OutboundMessage $message): SendOutcome;
}
