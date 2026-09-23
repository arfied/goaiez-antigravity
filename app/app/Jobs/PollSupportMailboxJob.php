<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SupportChannel;
use App\Enums\SupportSenderStanding;
use App\Exceptions\GmailHistoryUnavailable;
use App\Exceptions\GmailMessageUnreadable;
use App\Services\Support\AccountDirectory;
use App\Services\Support\InboundSupportMessage;
use App\Services\Support\SupportInbox;
use App\Services\Support\SupportMailbox;
use App\Support\QueueBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Read the goaiez support mailbox and put what arrived on the desk — T176 §3.
 *
 * ⚠️ **A POLL, NOT A PUSH, AND THE REASON IS DEPLOYMENT RATHER THAN TASTE.**
 * `GmailInbox` records that `users.watch` cannot be registered until a Pub/Sub
 * topic exists and this deployment has none — so a support path built on push
 * would be inert the day it shipped, which is `CLAUDE.md`'s first recurring
 * failure shape with a webhook attached. `support:poll-mailbox` runs it on the
 * cron the box actually has.
 *
 * ## Idempotency, in two layers, and the cache is deliberately not one of them
 *
 * ⛔ **THE DATABASE IS WHAT REFUSES THE SECOND COPY.** `support_messages` carries
 * `unique (business_id, external_ref)` and {@see SupportInbox} now catches what
 * it throws, so a message read twice becomes one thread message however the two
 * reads are interleaved. `StripeEvent` and `InboundMessage` are this codebase's
 * established shape for exactly that, and the reason both exist is that an
 * `->exists()` check is passed by two concurrent runs.
 *
 * ⚠️ **THE CURSOR IS AN EFFICIENCY LAYER AND IS TREATED AS ONE.** It bounds what
 * is read; it is not what makes the outcome correct. It advances only when the
 * whole range has been recorded, so a worker that dies mid-run re-reads the
 * range and the unique index absorbs the repeats.
 *
 * ⛔ **THAT SENTENCE WAS FALSE ON THE ONE ARM WHERE IT MATTERED UNTIL
 * 9480–9499, AND IT IS THE REASON THIS SLICE EXISTS.** `SupportMailbox::fetch()`
 * answered a vendor refusal — a 429, a 500, a 503 — with the same `null` it
 * answers *"the message was deleted"* with, the loop below called all of it
 * ordinary, and the bookmark then advanced past mail **nobody had read**. There
 * was no repeat for the unique index to absorb, because nothing was ever
 * written: a customer's request for help was destroyed by a vendor hiccup, in a
 * run that logged nothing at all. `GmailMessageUnreadable` is what tells the two
 * apart now, and the bookmark is held whenever the count is non-zero — which is
 * what makes the paragraph above true rather than aspirational.
 *
 * ⛔ **AND THERE IS NO `Cache::add()` CLAIM PER MESSAGE, UNLIKE
 * {@see IngestGmailPushJob}.** A claim taken before the work and never given
 * back turns a failed run into lost mail: the retry skips the very message that
 * broke, and nothing anywhere says a support request was dropped. The push job
 * can afford it because its subject is a status flag it can recompute; this
 * one's subject is somebody asking us for help.
 */
final class PollSupportMailboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts.
     *
     * A Gmail 429 or a timeout is ordinary and worth retrying; a revoked grant
     * is not, and will fail three times and land where an operator can read it.
     */
    public int $tries = 3;

    /**
     * ⚠️ **JITTERED, THE SAME AS THE PUSH JOB AND FOR A NARROWER REASON.** This
     * job is dispatched by a clock, so every deployment's retries would fall in
     * the same second of the same minute against an endpoint Google rate-limits
     * per user.
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

    public function handle(
        SupportMailbox $mailbox,
        AccountDirectory $directory,
        SupportInbox $inbox,
    ): void {
        // ⚠️ **RE-READ ON THE QUEUE RATHER THAN TRUSTED FROM THE SCHEDULER.** An
        // operator turning the path off while a backlog drains expects the
        // backlog to stop, not to finish — and this switch also carries the
        // refusal to share the relay's bookmark, which must not be bypassable by
        // a job that was queued before somebody noticed.
        if (! $mailbox->isEnabled()) {
            return;
        }

        $cursor = $mailbox->cursor();

        if ($cursor === null) {
            $this->establishBookmark($mailbox);

            return;
        }

        try {
            $page = $mailbox->messagesAddedSince($cursor);
        } catch (GmailHistoryUnavailable $e) {
            // ⛔ **NOT RETRIED, AND THE GAP IS ANNOUNCED.** Waiting cannot make an
            // expired bookmark valid. Said out loud because a silent reset is
            // indistinguishable from a quiet mailbox, and what is lost here is
            // somebody's request for help rather than a status flag.
            $this->establishBookmark($mailbox, $e->getMessage());

            return;
        }

        $recorded = 0;

        /** @var array<string, int> $unrouted */
        $unrouted = [];

        // ⛔ **THE COUNT THAT DECIDES WHETHER THE BOOKMARK MOVES** (9480–9499).
        $unread = 0;
        $unreadStatus = null;

        foreach ($page['ids'] as $id) {
            try {
                $mail = $mailbox->fetch($id);
            } catch (GmailMessageUnreadable $e) {
                // ⛔ **CAUGHT PER MESSAGE RATHER THAN LET OUT OF THE LOOP.** One
                // message Gmail will not hand over must not cost us the request
                // for help sitting beside it in the same batch — the same rule
                // the null arm below has always followed. What is different is
                // that this one is **not** ordinary, so it is counted, and the
                // bookmark stays where it is until somebody has read it.
                $unread++;
                $unreadStatus ??= $e->status;

                continue;
            }

            if ($mail === null) {
                // Deleted between the history record and the read, no sender we
                // can parse, or no plain-text part. All three are ordinary and
                // none of them may cost us the message beside it in the batch.
                continue;
            }

            // ⛔ **THE WHOLE MAIL, NOT THE ADDRESS** (4607). `From` is written by
            // the sender, and this call maps it to a tenant *and* to an author —
            // so passing the bare string let anybody who knew an owner's address
            // post into that tenant's thread as the owner. `accountOfSender()`
            // now refuses a mail the provider did not authenticate, and it takes
            // an object only the transport can build so that there is no call
            // shape which skips the check.
            $account = $directory->accountOfSender($mail);

            if ($account === null) {
                // ⛔ **NOT ROUTED TO A GUESS.** `InboundSupportMessage` refuses
                // to invent a tenant in capitals, and there is nothing to invent
                // one from: one support mailbox serves every client. The message
                // stays in the mailbox, where a person can read it — which is
                // also where an unauthenticated sender's mail waits, so a spoof
                // and a stranger cost the same and neither costs a thread.
                //
                // ⚠️ **THEY COST THE SAME AND THEY ARE NOT THE SAME FACT**
                // (4610, split at 4645). Counting them together was right about
                // the money and wrong about attention: a sustained spoofing
                // attempt and a quiet week produced one indistinguishable line.
                // ⛔ **THE STANDING NEVER ROUTES ANYTHING** — it carries no
                // tenant, no author and no address, and `accountOfSender()`
                // above is still the only call that resolves an account.
                $standing = $directory->senderStanding($mail);
                $unrouted[$standing->value] = ($unrouted[$standing->value] ?? 0) + 1;

                continue;
            }

            $inbox->receive(new InboundSupportMessage(
                businessId: $account['business_id'],
                channel: SupportChannel::Email,
                body: $mail->body,
                externalRef: $mail->externalRef,
                receivedAt: $mail->receivedAt,
                authorUserId: $account['user_id'],
                subject: $mail->subject,
            ));

            $recorded++;
        }

        // ⛔ **THE BOOKMARK IS HELD WHEN ANYTHING IN THE RANGE WAS NOT READ**
        // (9480–9499). This class's own docblock has always claimed the cursor
        // *"advances only when the whole range has been recorded"* — that was
        // true of every arm except the one that mattered, and the consequence of
        // advancing here is not a repeat but a **deletion**: the range is the
        // only record that those message ids ever existed. Re-reading is free,
        // because `support_messages (business_id, external_ref)` refuses the
        // second copy.
        //
        // ⚠️ **AND HOLDING IT HAS ITS OWN COST, WHICH IS NAMED RATHER THAN
        // HIDDEN**: a message Gmail refuses *permanently* pins the bookmark
        // until Gmail's own history window expires, at which point
        // `GmailHistoryUnavailable` fires and the gap is announced. That is a
        // week of re-reading a widening range, and it is still the better half
        // of the trade — the alternative is a support request nobody ever sees.
        if ($unread === 0 && is_string($page['historyId']) && $page['historyId'] !== '') {
            $mailbox->rememberCursor($page['historyId']);
        }

        $this->report($recorded, $unrouted, $unread, $unreadStatus);
    }

    /**
     * Say what the run did, in counts and in nothing else.
     *
     * ⚠️ **COUNTS AND NOTHING ELSE.** No address, no subject, no message id and
     * no domain — a log line naming who wrote to support is a correspondence
     * record in a file with no retention policy, and `IngestGmailPushJob`
     * refuses the same thing in the same words. **A sender's domain is not a
     * safe middle ground**: for the case worth logging it is a tenant's own
     * domain, which names the tenant.
     *
     * ⛔ **`left_for_a_person` KEEPS ITS OLD MEANING AND THE BREAKDOWN IS
     * ADDED BESIDE IT** (4646). It has always been *everything not recorded*,
     * and a key that quietly starts answering a narrower question is worse than
     * a new key: anything built on it — a grep, a saved search, somebody's
     * memory of last month's figure — goes on reading fine and reports
     * something else. The three standings sum to it, which is checkable rather
     * than promised.
     *
     * ⛔ **AND A SPOOF CLAIM IS A `warning`, DELIBERATELY NOT AN OPERATOR
     * ALERT** (4647). `Spoofed` means *the provider did not authenticate this
     * message and its `From` names an owner we hold*, and 4608 records that
     * this application's `Authentication-Results` check has **never seen a real
     * header**: if Gmail's `authserv-id` is not the one configured, every
     * genuine owner reads as `Spoofed` and an alert would page somebody
     * continuously on the first day of a live mailbox — the failure mode
     * peaking exactly where the signal is least trustworthy. So the line names
     * both explanations and a person decides. **Raising it to an alert is a
     * decision for after a real header has been seen**, not before.
     *
     * ⛔ **AND A RUN THAT READ NOTHING BECAUSE THE VENDOR REFUSED IS NO LONGER
     * A RUN THAT SAYS NOTHING** (9480–9499). The early return below is right
     * about a quiet mailbox and was wrong about an outage: a swallowed message
     * incremented neither count, so a poll in which Gmail refused fifty reads
     * produced **zero bytes of output** and advanced the bookmark past all
     * fifty. `$unread` is counted before the early return and is announced as a
     * warning, because the thing that did not happen is somebody being helped.
     *
     * ⚠️ **A COUNT AND A STATUS, AND STILL NOTHING ABOUT WHO WROTE TO US.** The
     * rule above is unchanged; an HTTP status is a fact about the vendor.
     *
     * @param  array<string, int>  $unrouted
     */
    private function report(int $recorded, array $unrouted, int $unread = 0, ?int $unreadStatus = null): void
    {
        $left = array_sum($unrouted);

        if ($unread > 0) {
            Log::warning(
                'Gmail refused to hand over support mail this run, so the mailbox bookmark is '
                .'being held where it is and the next run will re-read this range.',
                ['unread' => $unread, 'status' => $unreadStatus],
            );
        }

        if ($recorded === 0 && $left === 0) {
            return;
        }

        $context = [
            'recorded' => $recorded,
            'left_for_a_person' => $left,
        ];

        // ⚠️ **EVERY STANDING IS PRINTED, INCLUDING THE ZEROES.** A breakdown
        // that omits its empty cases makes "no spoof attempts this run" and
        // "this build does not count spoof attempts" the same log line, which
        // is the ambiguity this whole split exists to remove.
        foreach (SupportSenderStanding::cases() as $standing) {
            if ($standing === SupportSenderStanding::Routed) {
                // Recorded mail is `recorded`, and it cannot reach `$unrouted`.
                continue;
            }

            $context[$standing->value] = $unrouted[$standing->value] ?? 0;
        }

        Log::info('Support mail was read from the platform mailbox.', $context);

        foreach (SupportSenderStanding::cases() as $standing) {
            $count = $unrouted[$standing->value] ?? 0;

            if ($count === 0 || ! $standing->wantsAnOperator()) {
                continue;
            }

            Log::warning(
                'Support mail failed sender authentication while naming an account we hold — '
                .'either somebody is impersonating an account owner, or the mailbox provider\'s '
                .'authentication header is not the one this deployment recognises.',
                ['count' => $count],
            );
        }
    }

    /**
     * Point the bookmark at where the mailbox is now, and record nothing.
     *
     * ⚠️ **CORRECT RATHER THAN A GAP ON THE FIRST RUN, AND WORTH PINNING SO
     * NOBODY "FIXES" IT.** `users.history.list` requires a `startHistoryId`;
     * with no bookmark the only alternatives are inventing one, which Google
     * answers 404 to, or listing the whole mailbox, which would open a ticket
     * for every message the support account has ever received.
     *
     * ⚠️ **A PROFILE READ THAT FAILS LEAVES THE BOOKMARK UNSET RATHER THAN
     * GUESSING ONE.** The next poll tries again; a wrong bookmark would silently
     * skip everything before it. **That is the one of this path's four swallows
     * that was already correct** (9480–9499) — it loses nothing, because the
     * mail stays in the mailbox and the next run re-reads.
     *
     * ⛔ **IT WAS, HOWEVER, COMPLETELY SILENT, AND THAT IS THE HALF THAT MOVED.**
     * A profile read failing for ever means the support desk never starts at
     * all — a five-minute poll doing nothing, indefinitely, with no row and no
     * line anywhere, and an empty support queue is also what a quiet week looks
     * like. One warning is the whole of the fix; it is deliberately **not** an
     * operator alert, because this path is off on every deployment (see the
     * decision block) and a bell over a dark path is a door onto a room that has
     * never existed.
     */
    private function establishBookmark(SupportMailbox $mailbox, ?string $gap = null): void
    {
        $current = $mailbox->currentHistoryId();

        if ($current === null) {
            Log::warning(
                'The support mailbox position could not be read, so no bookmark was established '
                .'and this run recorded nothing. Nothing is lost — the mail is still in the '
                .'mailbox — but nothing will be read until this succeeds.',
            );

            return;
        }

        $mailbox->rememberCursor($current);

        if ($gap !== null) {
            Log::warning('A gap opened in the support mailbox history and was skipped.', [
                'reason' => $gap,
            ]);

            return;
        }

        Log::info('The support mailbox bookmark was established from the current mailbox position.');
    }
}
