<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\GmailHistoryUnavailable;
use App\Exceptions\GmailMessageUnreadable;
use App\Services\Mail\GmailInbox;
use App\Services\Mail\MailReplyRouter;
use App\Support\QueueBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read what arrived in the relay mailbox, and thread each reply to its contact.
 *
 * ⚠️ **THE WEB REQUEST DOES NOT DO THIS, AND THE REASON IS THE VENDOR'S OWN
 * DOCUMENTATION.** Pub/Sub treats any status outside `102, 200, 201, 202, 204`
 * as a negative acknowledgement and redelivers
 * (`cloud.google.com/pubsub/docs/push`, read 2026-08-12, page dated 2026-07-29).
 * Doing the Gmail reads inline would make a slow mailbox, a 429 or one bad
 * message into a nack, and the nack into another delivery of the same
 * notification — a retry storm driven by our own latency. The endpoint
 * acknowledges; the work happens here, where a failure is a failed job somebody
 * can read.
 *
 * ⛔ **"WHERE A FAILURE IS A FAILED JOB SOMEBODY CAN READ" IS FALSE AND WAS
 * FALSE FOR THE WHOLE LIFE OF THIS CLASS — CORRECTED 2026-08-25 (9584).** The
 * paragraph above is kept because its argument about the *split* is unchanged
 * and correct — this is `DeliverPlatformMail`'s own correction, one job over,
 * and 9370's measured instance is the evidence: **nobody reads `failed_jobs`.**
 * The only alerting reader of that table wants twenty-five rows in an hour,
 * which is a count of traffic rather than of severity, and `jobs:prune-failed`
 * deletes the row after thirty days. This path carries a tenant's customers'
 * replies and Pub/Sub has already been acked, so Google will not bring the
 * notification back.
 *
 * ✅ **WHAT IS TRUE INSTEAD IS THE PROPERTY BELOW, AND IT IS STRONGER THAN THE
 * SENTENCE IT REPLACES.** The bookmark only moves when a whole range has been
 * routed, so a job that throws **loses nothing**: the next notification reads
 * the same range again. ⚠️ **Bounded by Gmail's own history retention** — about
 * a week — after which {@see GmailHistoryUnavailable} fires, the bookmark is
 * reset and the gap is announced as a warning (9491). **So a permanent failure
 * here delays rather than deletes, and only a permanent failure that outlives
 * that window costs a reply.**
 *
 * ⛔ **NO BELL, AND 9494 IS WHY RATHER THAN COST.** `users.watch` still has no
 * caller in `app/`, so no Pub/Sub subscription exists and this job has never
 * been dispatched by a real notification — an `OperatorAlertKind` over it is
 * *a door onto a room that has never existed*. **What would arm one is written
 * down at 9494** and belongs to whoever switches the path on.
 *
 * ## Idempotency, in two layers, because one of them is not durable enough
 *
 * ⚠️ **THE FAST LAYER IS THE `messageId` CLAIM AND IT LIVES IN THE CONTROLLER.**
 * Pub/Sub delivers at least once, so it will deliver twice.
 *
 * ⛔ **THE DURABLE LAYER IS THE CURSOR, AND IT IS THE ONE THAT ACTUALLY HOLDS.**
 * A range is read from the bookmark and the bookmark only moves once the range
 * is routed, so a second run of the same notification reads from the *new*
 * position and finds nothing. Beneath even that, `MailReplyRouter` is itself
 * idempotent: `markReplied()` writes the first reply only, and
 * `OutreachStatus::canTransitionTo()` refuses `Replied → Replied`. Three layers,
 * and only the middle one is a design decision — the other two are properties
 * that already existed and are named so that removing either is a visible act.
 *
 * ## What a consumer of this receives
 *
 * ⚠️ **THIS JOB HANDS `MailReplyRouter` A LIST OF ADDRESSES AND NOTHING ELSE.**
 * That is the whole seam, and it is deliberately the same one
 * `SesFeedbackController` uses — a support lane building on inbound mail
 * consumes what the router produces (an `outreach_messages` row moved to
 * `Replied`, and `mail_tracking_codes.replied_at` set), never anything this job
 * holds. ⛔ **There is no message body anywhere in this path to consume**: under
 * `gmail.metadata` it cannot be fetched, and `MailReplyRouter` records why it
 * would not be stored if it could.
 */
final class IngestGmailPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts.
     *
     * A Gmail 429 or a timeout is ordinary and worth retrying; a revoked grant
     * is not, and will fail three times and land where it can be read. The cron
     * worker passes no `--tries` (`CLAUDE.md` §Key commands), so a job wanting
     * more than one says so itself.
     */
    public int $tries = 3;

    /**
     * ⚠️ **JITTERED, AND NOT BY DECORATION.** Every push for one mailbox lands
     * in the same second, so a fixed ladder would have every failed job retry in
     * the same second too — a thundering herd against an endpoint that is rate
     * limited per user. The base is a minute and the spread is up to thirty
     * seconds either side.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        $base = QueueBackoff::fromSetting('queue.backoff.standard_seconds');

        return [
            $base[0] + random_int(-30, 30),
            $base[1] + random_int(-30, 30),
        ];
    }

    /**
     * ⚠️ **THE PAYLOAD CARRIES A COUNTER AND AN ADDRESS THAT IS OUR OWN.**
     * Nothing about who wrote to us and nothing about what they said — a queue
     * payload outlives the row it describes, and `DeliverPlatformMail` records
     * the same constraint for the same reason.
     */
    public function __construct(
        private readonly string $emailAddress,
        private readonly string $historyId,
    ) {}

    public function handle(GmailInbox $inbox, MailReplyRouter $router): void
    {
        // ⚠️ **RE-READ ON THE QUEUE RATHER THAN TRUSTED FROM THE REQUEST.** The
        // controller checked it too; an operator turning the path off while a
        // backlog drains expects the backlog to stop, not to finish.
        if (! $inbox->isEnabled()) {
            return;
        }

        if (! $inbox->watches($this->emailAddress)) {
            return;
        }

        $cursor = $inbox->cursor();

        if ($cursor === null) {
            // ⚠️ **THE FIRST NOTIFICATION ESTABLISHES THE BOOKMARK AND ROUTES
            // NOTHING, AND THAT IS CORRECT RATHER THAN A GAP.** With no start
            // point there is no range to ask for — `users.history.list` requires
            // `startHistoryId` — and the only alternatives are to invent one
            // (which 404s) or to list the whole mailbox (which would route every
            // message ever received). One notification's worth of mail is missed
            // exactly once, on the first ever push.
            $inbox->rememberCursor($this->historyId);

            Log::info('The Gmail inbox bookmark was established from the first push notification.');

            return;
        }

        try {
            $page = $inbox->messagesAddedSince($cursor);
        } catch (GmailHistoryUnavailable $e) {
            // ⛔ **NOT RETRIED, AND THE GAP IS ANNOUNCED.** Waiting cannot make
            // an expired bookmark valid; the only move is to abandon it. Said
            // out loud because a silent reset is indistinguishable from a quiet
            // mailbox, and what is lost is somebody's reply.
            $inbox->rememberCursor($this->historyId);

            Log::warning('A gap opened in the Gmail inbox history and was skipped.', [
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        $routed = 0;
        $unread = 0;
        $unreadStatus = null;

        foreach ($page['ids'] as $id) {
            // ⚠️ **ONE MESSAGE, ONE CLAIM.** Two notifications can legitimately
            // overlap on the same message — a `messageAdded` in one range and a
            // redelivery of the previous — and the router's own idempotency
            // covers the *outcome*, not the two Gmail reads it takes to get
            // there. This is the cheap layer; 20 quota units is what it saves.
            $claim = 'platform-mail:gmail:message:'.$id;

            if (! Cache::add($claim, true, now()->addDay())) {
                continue;
            }

            try {
                $recipients = $inbox->recipientsOf($id);

                if ($router->route($recipients)) {
                    $routed++;
                }
            } catch (GmailMessageUnreadable $e) {
                // ⛔ **THE CLAIM IS GIVEN BACK** (9480–9499). This job's own
                // docblock argues it can afford a claim taken before the work
                // *"because its subject is a status flag it can recompute"* —
                // and it cannot recompute it from a message it never read. A
                // claim held for a day over a read that failed is
                // `PollSupportMailboxJob`'s refusal of this pattern arriving
                // here as a defect: the retry skips the very message that broke.
                Cache::forget($claim);

                $unread++;
                $unreadStatus ??= $e->status;

                continue;
            } catch (Throwable $e) {
                // ⛔ **AND ON EVERY OTHER FAULT TOO, WHICH 9490's OWN ARGUMENT
                // REQUIRED AND 9490's FIX DID NOT DO — CORRECTED 2026-08-25
                // (9585).** The arm above catches one typed exception.
                // `GmailInbox::recipientsOf()` reaches Google through
                // `Http::get()`, which **throws** a `ConnectionException` on a
                // DNS failure, a timeout or a refused connection rather than
                // returning a failed response (`CLAUDE.md`'s recurring shape),
                // and `GmailApiClient::accessToken()` throws on an absent or
                // refused credential. None of those is a
                // `GmailMessageUnreadable`.
                //
                // ⛔ **AND THAT ARM WAS WORSE THAN THE ONE 9490 FIXED, NOT
                // EQUAL TO IT.** There the bookmark is held; here `$unread` was
                // never incremented, so the retry skipped the claimed message
                // **and then advanced the bookmark past it** — the reply gone
                // permanently, the contact still receiving outreach they had
                // answered, and every count in the log line zero.
                //
                // ⚠️ **RETHROWN RATHER THAN COUNTED.** A vendor that cannot be
                // reached at all is not one message we could not read: the range
                // is unfinished, the queue's ladder is what should absorb it,
                // and swallowing it here would advance the bookmark over
                // everything after this id as well.
                Cache::forget($claim);

                throw $e;
            }
        }

        // ⛔ **THE BOOKMARK IS HELD WHEN ANYTHING IN THE RANGE WAS NOT READ**
        // (9480–9499). `rememberCursor()`'s own docblock says it is *"called
        // only after the messages in that range have been routed"* and that
        // *"the bookmark is the only record of where we were"*; a message Gmail
        // refused was neither routed nor recorded, so moving past it is a
        // deletion rather than a repeat. What is lost is a contact's reply, and
        // what it costs them is more outreach they have already answered.
        if ($unread === 0 && is_string($page['historyId']) && $page['historyId'] !== '') {
            $inbox->rememberCursor($page['historyId']);
        }

        if ($unread > 0) {
            // ⚠️ **A COUNT AND A STATUS.** No address, no message id, no tracking
            // code — the rule below is unchanged and an HTTP status is a fact
            // about the vendor rather than about a person.
            Log::warning(
                'Gmail refused to hand over inbound mail this run, so the mailbox bookmark is '
                .'being held where it is and the next notification will re-read this range.',
                ['unread' => $unread, 'status' => $unreadStatus],
            );
        }

        if ($routed > 0) {
            // ⚠️ **A COUNT AND NOTHING ELSE.** No address, no message id, no
            // tracking code — a log line naming who replied to whom is a
            // correspondence record in a file with no retention policy.
            Log::info('Inbound mail was threaded to contacts.', ['routed' => $routed]);
        }
    }
}
