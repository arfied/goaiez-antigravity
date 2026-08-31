<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\SupportTicket;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The one door an inbound support message comes through — T137 `SL-7`'s email
 * and SMS channels.
 *
 * ## ⛔ WHAT IS BUILT HERE, AND WHAT IS NOT
 *
 * **Built:** the seam. Hand it an {@see InboundSupportMessage} and it lands on
 * the desk — appended to that tenant's live thread on the same channel, or
 * opening a new one — exactly once, however many times the transport delivers it.
 *
 * ✅ **THE FIRST TRANSPORT NOW EXISTS, AND THIS PARAGRAPH SAID OTHERWISE UNTIL
 * 2026-08-15** — 2505's shape, in the docblock a reader trusts. It said *"not
 * built, and deliberately not stubbed: the transports themselves. Nothing in
 * `app/` calls this yet"*, which was honest when written and is `CLAUDE.md`'s
 * first recurring failure shape left to rot. One of the three doors is now open;
 * what each consumer owes is below, and two of the three are still owed:
 *
 * - ✅ **Email (Workspace mailbox, T137 `SL-7`/R3) — BUILT, T176 P24.**
 *   {@see SupportMailbox} polls the goaiez support account and
 *   `PollSupportMailboxJob` resolves the tenant *before* calling here, through
 *   `AccountDirectory::accountOfSender()`. `externalRef` is Gmail's own message
 *   id, prefixed with the transport that minted it. ⚠️ **A message that cannot
 *   be resolved to a tenant is not routed to a guess** — it stays in the mailbox
 *   for a person, which is what that job does with it.
 * - ⛔ **Generic IMAP/POP3 + SMTP (non-Google mailboxes) — STILL NOT BUILT, and
 *   it needs a dependency decision rather than a lane.** PHP 8.4 unbundled
 *   `ext/imap` to PECL and it is not installed here; no IMAP client is in
 *   `composer.json`. `externalRef` would be the RFC 5322 `Message-ID`, which is
 *   what makes a poll that re-reads the same mailbox idempotent — a UID is not,
 *   because POP3 renumbers.
 * - **SMS (the `SL-2` spine).** `InfobipInboundController` already receives
 *   inbound texts and belongs to another lane. It owes a tenant *and* a
 *   from-number that is a known account contact; `externalRef` is the carrier's
 *   `messageId`. ⚠️ **A text from a number nobody recognises is not a support
 *   request** — it is a consumer message on the tenant's own number, and routing
 *   it here would put a member of the public into our support queue.
 *
 * ## Why the routing is not done here
 *
 * Every one of those three resolves its tenant differently and each answer is a
 * fact about the transport. A resolver here would be a fourth place that decides
 * whose message this is, and the codebase already records what happens when two
 * places answer one question.
 */
final class SupportInbox
{
    public function __construct(private readonly SupportDesk $desk) {}

    /**
     * Record one routed message, once.
     *
     * Returns null when this exact message has already been recorded — an
     * ordinary outcome rather than an error, and the caller answers the webhook
     * 200 either way: a redelivery is the transport doing its job.
     *
     * ⛔ **ONLY THE DATABASE REFUSES THE SECOND COPY, AND UNTIL T176 P24 THE
     * ONLY THING ASKED WAS AN `->exists()` CHECK.** Two deliveries in flight at
     * once both find no row and both proceed — the shape `StripeEvent` and
     * `InboundMessage` exist to refuse. The unique index on
     * `support_messages (business_id, external_ref)` has been there since the
     * desk shipped and nothing was catching what it throws, so the second writer
     * got a 500 and its transport retried it for ever.
     *
     * ⚠️ **THE CHECK IS GONE RATHER THAN KEPT AS A FAST PATH IN FRONT OF THIS.**
     * A check that always wins first is a second answer to one question and it
     * makes the real answer untestable: the catch below could never be driven
     * red, so nothing would notice the day it stopped catching.
     */
    public function receive(InboundSupportMessage $message): ?SupportTicket
    {
        try {
            return $this->desk->record($message);
        } catch (UniqueConstraintViolationException) {
            // ⚠️ **CAUGHT AS NARROWLY AS LARAVEL LETS IT BE CAUGHT.** This type
            // is raised for a duplicate key and nothing else, so a broken
            // connection, a refused policy or a null violation still reach the
            // caller as failures. Catching `QueryException` here would swallow
            // an RLS refusal — a message recorded against no tenant, reported as
            // a duplicate.
            return null;
        }
    }
}
