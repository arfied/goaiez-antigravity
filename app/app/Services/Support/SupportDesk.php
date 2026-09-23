<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\SupportChannel;
use App\Enums\SupportMessageAuthor;
use App\Enums\SupportTicketStatus;
use App\Models\SupportMessage;
use App\Models\SupportQueueEntry;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportReplyPosted;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The support desk between a tenant and GO AI EZ — T137 `SL-7`.
 *
 * The only reader and the only writer of `support_tickets`, `support_messages`
 * and `support_queue_entries` in `app/`, held there by a chokepoint lint in
 * `Architecture/StaffTest`. Two screens sit on it: the tenant's own Support
 * page and the console queue.
 *
 * ## Why the tenant screen and the console cannot share one query
 *
 * A tenant reads their own thread with their tenancy established, so the global
 * scope and RLS both apply and neither screen has to remember anything. **Staff
 * belong to no business** (`28` §9.1), so every staff read here has to say which
 * account it is for: `Tenancy::actingAs()`, one account at a time, exactly as
 * `AccountDirectory` does it. The queue itself is the one thing that cannot work
 * that way — a list of *whose* work is waiting has to be readable before any
 * tenant is chosen — which is what `support_queue_entries` is, and why it
 * carries no text (see the model).
 *
 * ## What is recorded, and what deliberately is not
 *
 * Every act here writes to that tenant's own append-only `audit_log`: who
 * raised, who answered, who closed. ⚠️ **No subject line and no message body
 * ever reaches it.** `audit_log` is immutable and nothing prunes it, so free
 * text written by a person would be a retention decision nobody made —
 * `AccountDirectory` refuses the same thing for a typed email address, and the
 * metadata here is ids, a channel and a side.
 *
 * ⚠️ **A STAFF *READ* IS NOT AUDITED HERE, AND THAT IS A DELIBERATE DIFFERENCE
 * FROM `AccountDirectory`.** That class audits opening an account because
 * nothing invited staff into it. A support ticket is a message the tenant
 * addressed to us; reading what somebody sent you is not the surveillance read
 * `business.viewed_by_staff` exists to catch. Answering **is** recorded, because
 * that is an act performed in the platform's name inside the tenant's account.
 */
final class SupportDesk
{
    /**
     * The longest a subject or a message may be.
     *
     * Not in the Defaults Registry: these bound a text column, they are not a
     * threshold anybody tunes, and 1092's boundary puts that kind of number in
     * code. The body limit is generous on purpose — a truncated support request
     * costs a round trip to ask for the rest.
     */
    public const int SUBJECT_LIMIT = 120;

    public const int BODY_LIMIT = 5000;

    public function __construct(
        private readonly AuditService $audit,
        private readonly PlatformMailer $mailer,
        private readonly SupportMacros $macros,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function subjectLimit(): int
    {
        return $this->registry->int('support.ticket.subject_limit');
    }

    public function bodyLimit(): int
    {
        return $this->registry->int('support.ticket.body_limit');
    }

    /**
     * The tenant raises a request. Runs in the tenant already established by
     * `ResolveTenant`, so nothing here names a business id.
     */
    public function raise(User $author, string $subject, string $body): SupportTicket
    {
        $businessId = Tenancy::idOrFail();

        $ticket = $this->openThread(
            businessId: $businessId,
            channel: SupportChannel::App,
            subject: $subject,
            body: $body,
            openedByUserId: (int) $author->id,
            externalRef: null,
        );

        $this->audit->record('support.ticket_raised', 'user:'.$author->id, $ticket, [
            'channel' => SupportChannel::App->value,
        ]);

        return $ticket;
    }

    /**
     * The tenant says something more on a thread they own.
     *
     * ⚠️ **THE TICKET IS RE-READ UNDER THE TENANT'S OWN SCOPE RATHER THAN
     * TRUSTED**, so an id typed into a Livewire action for another tenant's
     * ticket finds nothing — the boundary refuses it, not a comparison in a
     * screen that somebody could delete.
     */
    public function replyAsTenant(int $ticketId, User $author, string $body): SupportTicket
    {
        Tenancy::idOrFail();

        $ticket = $this->requireLiveTicket($ticketId);

        $this->append($ticket, SupportMessageAuthor::Owner, $body, (int) $author->id, $ticket->channel, null);

        // Back to waiting on us: the queue's subject is work we owe, so the
        // state moves backwards when the ball does.
        $this->markWaiting($ticket);

        $this->audit->record('support.ticket_replied', 'user:'.$author->id, $ticket, [
            'side' => SupportMessageAuthor::Owner->value,
        ]);

        return $ticket->refresh();
    }

    /**
     * Everything this tenant has ever asked us, most recent activity first.
     *
     * @return Collection<int, SupportTicket>
     */
    public function mine(): Collection
    {
        Tenancy::idOrFail();

        // ⚠️ `NULLS LAST` SPELLED OUT, THOUGH THE COLUMN IS NOT NULL. Postgres
        // puts NULL first on a `DESC` sort, and `ConventionsTest` requires every
        // descending order on anything but `id` to say so out loud rather than
        // to depend on a schema fact that a later migration could change.
        return SupportTicket::query()
            ->orderByRaw('last_message_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * One of this tenant's own threads, or null when it is not theirs.
     */
    public function thread(int $ticketId): ?SupportThread
    {
        Tenancy::idOrFail();

        $ticket = SupportTicket::query()->whereKey($ticketId)->first();

        return $ticket instanceof SupportTicket ? $this->snapshot($ticket) : null;
    }

    /**
     * The console queue: every request nobody here has closed, oldest first.
     *
     * Read with no tenant established, which is the whole reason
     * `support_queue_entries` exists — see the model.
     *
     * @return Collection<int, SupportQueueEntry>
     */
    public function waiting(): Collection
    {
        return SupportQueueEntry::query()
            ->whereNull('resolved_at')
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * One account's thread, read for the console.
     *
     * The business id comes from the queue row rather than from the screen, so a
     * console that asks for ticket 9 cannot be pointed at another tenant by
     * editing a second field.
     */
    public function openForStaff(int $ticketId): ?SupportThread
    {
        $entry = SupportQueueEntry::query()->where('support_ticket_id', $ticketId)->first();

        if (! $entry instanceof SupportQueueEntry) {
            return null;
        }

        return Tenancy::actingAs($entry->business_id, function () use ($ticketId): ?SupportThread {
            $ticket = SupportTicket::query()->whereKey($ticketId)->first();

            return $ticket instanceof SupportTicket ? $this->snapshot($ticket) : null;
        });
    }

    /**
     * We answer. The one act on this desk performed in the platform's name
     * inside somebody else's account, so it is audited there and the account is
     * told it happened.
     *
     * @throws InvalidArgumentException when the ticket is gone or already closed
     */
    public function answer(int $ticketId, User $staff, string $body): SupportThread
    {
        // ⛔ **THE SLOT-RESOLUTION GUARD, AND THIS IS THE ONLY PLACE IT CAN
        // STAND** (CC-5 §0). A macro is a draft with `{cancel_link}`-shaped
        // holes in it; this method is the last point at which a staff reply is
        // still a draft, and the only one every route into a tenant's thread
        // passes through — the console, and any second screen that ever calls
        // this service. Checked on the console component instead, the reply that
        // skipped the component would arrive with the braces showing and nothing
        // between here and the account holder would have shown them to anybody.
        //
        // ⚠️ **BEFORE THE QUEUE LOOKUP, DELIBERATELY.** 398's shape is an outer
        // guard refusing first: with this below the `$entry` check, a test
        // proving the guard could pass because the ticket did not exist. It is
        // a fact about the string, so it is asked about the string — and moving
        // it down two lines is driven red by
        // `SupportMacroTest::the guard is asked before the ticket is looked up`.
        //
        // ⚠️ **NOT ASKED ON `replyAsTenant()`** — that takes whatever a business
        // owner types, braces included, and refusing it would be our own macro
        // vocabulary policing somebody else's words.
        $this->macros->assertEverySlotResolved($body);

        $entry = SupportQueueEntry::query()->where('support_ticket_id', $ticketId)->first();

        if (! $entry instanceof SupportQueueEntry) {
            throw new InvalidArgumentException('That request no longer exists.');
        }

        $thread = Tenancy::actingAs(
            $entry->business_id,
            function () use ($ticketId, $staff, $body, $entry): SupportThread {
                $ticket = $this->requireLiveTicket($ticketId);

                $this->append($ticket, SupportMessageAuthor::Staff, $body, (int) $staff->id, $ticket->channel, null);

                $now = CarbonImmutable::now();

                DB::transaction(function () use ($ticket, $entry, $now): void {
                    $ticket->status = SupportTicketStatus::Answered;
                    $ticket->last_message_at = $now;
                    $ticket->save();

                    // ⚠️ `??=` RATHER THAN AN OVERWRITE. First response is the
                    // one figure on this row that cannot be recomputed once a
                    // second reply lands, and a support desk that reports its
                    // first-response time as its *latest* one flatters itself.
                    $entry->first_response_at ??= $now;
                    $entry->save();
                });

                $this->audit->record('support.ticket_answered', 'user:'.$staff->id, $ticket, [
                    'side' => SupportMessageAuthor::Staff->value,
                    'channel' => $ticket->channel->value,
                ]);

                return $this->snapshot($ticket->refresh());
            }
        );

        $this->announce($entry->business_id, $ticketId);

        return $thread;
    }

    /**
     * Close a thread — by us.
     *
     * @throws InvalidArgumentException when the ticket is gone or already closed
     */
    public function resolveAsStaff(int $ticketId, User $staff): void
    {
        $entry = SupportQueueEntry::query()->where('support_ticket_id', $ticketId)->first();

        if (! $entry instanceof SupportQueueEntry) {
            throw new InvalidArgumentException('That request no longer exists.');
        }

        Tenancy::actingAs($entry->business_id, function () use ($ticketId, $staff, $entry): void {
            $ticket = $this->requireLiveTicket($ticketId);

            $this->close($ticket, $entry);

            $this->audit->record('support.ticket_resolved', 'user:'.$staff->id, $ticket, [
                'closed_by' => SupportMessageAuthor::Staff->value,
            ]);
        });
    }

    /**
     * Close a thread — by the tenant, on their own screen.
     *
     * ⚠️ **THE TENANT MAY CLOSE THEIR OWN AND MAY NOT REOPEN IT BY BUTTON.**
     * Replying is what reopens a thread, which is the same act in the language
     * the person is already using — a Reopen control beside a reply box is two
     * ways to say one thing, and `CLAUDE.md` refuses the second.
     *
     * @throws InvalidArgumentException when the ticket is gone or already closed
     */
    public function resolveAsTenant(int $ticketId, User $author): void
    {
        Tenancy::idOrFail();

        $ticket = $this->requireLiveTicket($ticketId);
        $entry = SupportQueueEntry::query()->where('support_ticket_id', $ticket->id)->first();

        $this->close($ticket, $entry);

        $this->audit->record('support.ticket_resolved', 'user:'.$author->id, $ticket, [
            'closed_by' => SupportMessageAuthor::Owner->value,
        ]);
    }

    /**
     * A request that arrived from outside the application — the inbound seam.
     *
     * ⚠️ **CALLED BY {@see SupportInbox} AND NOTHING ELSE.** The routing (which
     * tenant is this from?) and the idempotency decision happen there, because
     * they are facts about a transport; this method is what a routed message
     * does to the desk. It takes a business id explicitly for that reason: an
     * inbound message arrives with no tenancy established, and guessing one is
     * the failure `MailReplyRouter` documents on the mail side.
     */
    public function record(InboundSupportMessage $message): SupportTicket
    {
        return Tenancy::actingAs($message->businessId, function () use ($message): SupportTicket {
            $existing = $this->liveThreadOn($message->channel);

            if ($existing instanceof SupportTicket) {
                // ⚠️ **THE APPEND AND THE STATUS MOVE ARE ONE ACT, AND WRAPPING
                // THEM IS ALSO WHAT MAKES THE DUPLICATE REFUSAL SURVIVABLE**
                // (T176 P24). `support_messages` carries
                // `unique (business_id, external_ref)`; in PostgreSQL a
                // constraint violation on a bare statement aborts the whole
                // enclosing transaction, so a redelivery caught by
                // {@see SupportInbox} would leave the caller unable to run
                // another query. Inside a transaction it is a savepoint
                // rollback, and the caller carries on with the next message in
                // the batch. `openThread()` below has always had one.
                DB::transaction(function () use ($existing, $message): void {
                    $this->append(
                        $existing,
                        SupportMessageAuthor::Owner,
                        $message->body,
                        $message->authorUserId,
                        $message->channel,
                        $message->externalRef,
                    );

                    $this->markWaiting($existing);
                });

                $this->audit->record('support.ticket_replied', $message->actor(), $existing, [
                    'side' => SupportMessageAuthor::Owner->value,
                    'channel' => $message->channel->value,
                ]);

                return $existing->refresh();
            }

            $ticket = $this->openThread(
                businessId: $message->businessId,
                channel: $message->channel,
                subject: $message->subject(),
                body: $message->body,
                openedByUserId: $message->authorUserId,
                externalRef: $message->externalRef,
            );

            $this->audit->record('support.ticket_raised', $message->actor(), $ticket, [
                'channel' => $message->channel->value,
            ]);

            return $ticket;
        });
    }

    /*
     * ⛔ `alreadyRecorded()` WAS HERE AND IS DELETED (T176 P24). It asked
     * *"does a message with this `external_ref` exist?"* and `SupportInbox`
     * consulted it before recording — which is the `->exists()` shape
     * `StripeEvent` and `InboundMessage` exist to refuse, because **two
     * concurrent deliveries both pass it**. The refusal that holds is
     * `unique (business_id, external_ref)`, which this table has carried since
     * the day it shipped and which nothing was catching; `SupportInbox` now
     * catches it and is the only place that decides. Keeping the check as a
     * "fast path" beside it would have left a second answer to the same
     * question and made the real one untestable — the check would always win
     * first, so the catch could never be driven red.
     */

    /**
     * The ticket, or a refusal naming what a person can do about it.
     *
     * @throws InvalidArgumentException
     */
    private function requireLiveTicket(int $ticketId): SupportTicket
    {
        $ticket = SupportTicket::query()->whereKey($ticketId)->first();

        if (! $ticket instanceof SupportTicket) {
            throw new InvalidArgumentException('That request no longer exists.');
        }

        if (! $ticket->status->isLive()) {
            throw new InvalidArgumentException('That request is closed. Raising a new one keeps the history readable.');
        }

        return $ticket;
    }

    private function openThread(
        int $businessId,
        SupportChannel $channel,
        string $subject,
        string $body,
        ?int $openedByUserId,
        ?string $externalRef,
    ): SupportTicket {
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($businessId, $channel, $subject, $body, $openedByUserId, $externalRef, $now): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'opened_by_user_id' => $openedByUserId,
                'channel' => $channel,
                'subject' => $this->trimTo($subject, $this->subjectLimit()),
                'status' => SupportTicketStatus::Open,
                'last_message_at' => $now,
            ]);

            $this->append($ticket, SupportMessageAuthor::Owner, $body, $openedByUserId, $channel, $externalRef);

            // ⚠️ THE QUEUE ROW IS WRITTEN IN THE SAME TRANSACTION AS THE
            // THREAD. A ticket without one is a request nobody here can see,
            // which is worse than no request at all: the tenant has been told
            // it was received.
            SupportQueueEntry::query()->create([
                'business_id' => $businessId,
                'support_ticket_id' => $ticket->id,
                'channel' => $channel,
                'opened_at' => $now,
            ]);

            return $ticket;
        });
    }

    private function append(
        SupportTicket $ticket,
        SupportMessageAuthor $author,
        string $body,
        ?int $authorUserId,
        SupportChannel $channel,
        ?string $externalRef,
    ): SupportMessage {
        return SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'author' => $author,
            'author_user_id' => $authorUserId,
            'channel' => $channel,
            'body' => $this->trimTo($body, $this->bodyLimit()),
            'external_ref' => $externalRef,
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    private function markWaiting(SupportTicket $ticket): void
    {
        $ticket->status = SupportTicketStatus::Open;
        $ticket->last_message_at = CarbonImmutable::now();
        $ticket->save();
    }

    private function close(SupportTicket $ticket, ?SupportQueueEntry $entry): void
    {
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($ticket, $entry, $now): void {
            $ticket->status = SupportTicketStatus::Resolved;
            $ticket->resolved_at = $now;
            $ticket->save();

            if ($entry instanceof SupportQueueEntry) {
                $entry->resolved_at = $now;
                $entry->save();
            }
        });
    }

    private function liveThreadOn(SupportChannel $channel): ?SupportTicket
    {
        return SupportTicket::query()
            ->where('channel', $channel->value)
            ->where('status', '!=', SupportTicketStatus::Resolved->value)
            ->orderByRaw('last_message_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->first();
    }

    private function snapshot(SupportTicket $ticket): SupportThread
    {
        // ⚠️ **BUILT WITH A LOOP RATHER THAN `->map()->all()`**, and it is a
        // type fact rather than a style one: an Eloquent collection is keyed by
        // whatever the query returned, so `all()` is an `array<int, …>` and
        // `SupportThread` takes a `list<…>`. Appending is the shape, rather than
        // a cast or a docblock talking the checker round.
        $messages = [];

        foreach (SupportMessage::query()->where('support_ticket_id', $ticket->id)->orderBy('id')->get() as $message) {
            $messages[] = new SupportThreadMessage(
                id: (int) $message->id,
                author: $message->author,
                channel: $message->channel,
                body: $message->body,
                writtenAt: $message->created_at,
            );
        }

        return new SupportThread(
            ticketId: (int) $ticket->id,
            businessId: $ticket->business_id,
            subject: $ticket->subject,
            status: $ticket->status,
            channel: $ticket->channel,
            openedAt: $ticket->created_at,
            lastMessageAt: $ticket->last_message_at,
            messages: $messages,
        );
    }

    /**
     * Tell the person who asked that we have replied.
     *
     * The person who raised it, and only them: a support thread is a
     * conversation with one person, and copying it to an owner who never asked
     * would disclose what somebody on their staff wrote to us. Nothing is sent
     * for an inbound message from an address matching no account user — that
     * reply lands on the screen rather than being emailed into the dark.
     *
     * ⚠️ **THE SUBJECT LINE OF THE EMAIL CARRIES THE TENANT'S OWN WORDS BACK TO
     * THE TENANT'S OWN ADDRESS**, which is the only direction that is safe:
     * {@see SupportReplyPosted} carries no part of our reply and no part of any
     * other thread.
     */
    private function announce(int $businessId, int $ticketId): void
    {
        Tenancy::actingAs($businessId, function () use ($ticketId): void {
            $ticket = SupportTicket::query()->whereKey($ticketId)->first();

            if (! $ticket instanceof SupportTicket || $ticket->opened_by_user_id === null) {
                return;
            }

            $user = User::query()->whereKey($ticket->opened_by_user_id)->first();

            if (! $user instanceof User) {
                return;
            }

            // Platform mail to somebody who holds an account with us — the
            // authorisation is the account relationship and there is no consent
            // record to ask about (`PlatformMailer`'s own distinction). It
            // queues and cannot throw, so a mail system that is not configured
            // yet never costs an agent their reply.
            $this->mailer->send($user->email, new SupportReplyPosted($ticket->subject));
        });
    }

    private function trimTo(string $value, int $limit): string
    {
        $trimmed = trim($value);

        return mb_strlen($trimmed) > $limit ? mb_substr($trimmed, 0, $limit) : $trimmed;
    }
}
