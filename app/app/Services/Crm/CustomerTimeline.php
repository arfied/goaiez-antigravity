<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\ConsentEventType;
use App\Enums\RoutingDecision;
use App\Enums\TimelineEntryType;
use App\Models\CrmNote;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\OutreachMessage;
use App\Models\Review;
use App\Models\ShortLinkClick;
use App\Services\Consent\ConsentService;
use App\Services\Consent\ConsentTrailEntry;
use App\Services\Messaging\MessageLog;
use App\Services\ShortLinks\ShortLinkClicks;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

/**
 * A contact's history in one stream (`34` §1.2 — *"a timeline, not a form"*).
 *
 * ⚠️ **IT DOES NOT READ `crm_timeline`, AND THAT IS THE DECISION IN THIS FILE.**
 * The table exists, has a model, a factory, an RLS policy and a schema isolation
 * test, and nothing in `app/` has ever written a row to it. Reading it would
 * have been the tidy implementation and the worse mistake: the screen would come
 * up empty for every contact, and an empty timeline does not read as *"nothing
 * populates this yet"* — it reads as *"this customer has no history"*, about a
 * person who left a one-star review and got a recovery call. Decision 272's
 * shape says to check for a writer before depending on a table; the correction
 * this slice adds is that the check has to happen before the *design*, not
 * before the query.
 *
 * So the stream is a union over the stores that actually have writers — four
 * when this file was written, five since the follow-ups slice gave `crm_tasks`
 * its writer (`CrmTasks`), and six since `ReviewInviteSender` began minting
 * short links, which gave `short_link_clicks` one. That is decision 1222's rule
 * working as designed: a source joins the union when it gains a writer, never
 * before — and 2495 is the recorded instance of it being obeyed against
 * pressure, because the click case was written up as *deliberately withheld*
 * for exactly as long as nothing minted a link.
 *
 * ## Most of them are read through the service that owns them
 *
 * `outreach_messages` and `consent_records` each sit behind a chokepoint lint.
 * Decision 624's rule applies — *ask whether the caller belongs behind the
 * service before widening the allowlist* — and it does, twice: `MessageLog`
 * gains `forCustomer()`, `ConsentService::proofFor()` already existed and is
 * exactly this. Neither allowlist grew. Reading them here would have weakened
 * two chokepoints for one screen, each edit reasonable on its own diff.
 *
 * ⚠️ **Triage has no case of its own for the same reason.**
 * `triage_conversations` is held to `ReviewRouter`, a triage episode always
 * belongs to exactly one review, and the review's own status answers *"did this
 * go to a person"* without a router growing a reader. The cost is stated rather
 * than hidden: the timeline says a review went to triage and cannot say what was
 * said in it — which is true anyway, because a conversation's `transcript` is
 * written once as `[]` and nothing in `app/` ever appends to it.
 */
final class CustomerTimeline
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly MessageLog $messages,
        private readonly CrmNotes $notes,
        private readonly CrmTasks $tasks,
        private readonly CustomerMerges $merges,
        private readonly ShortLinkClicks $clicks,
    ) {}

    /**
     * Everything we know about this contact, newest first.
     *
     * ⚠️ **SORTED IN PHP, AND IT CANNOT BE DONE IN SQL.** The four sources are
     * four tables whose ids are not comparable to each other, so there is no one
     * ORDER BY to write — `ConsentService::proofFor()` reached the identical
     * conclusion for two tables and this is the same problem with four. Undated
     * rows sort last rather than first because a row presented as the newest
     * event misstates the one thing a reader takes from the top line; the
     * sentinel below is what makes that true rather than incidental.
     *
     * @return Collection<int, TimelineEntry>
     */
    public function forCustomer(Customer $customer): Collection
    {
        Tenancy::idOrFail();

        // ⚠️ **THE UNION RUNS ACROSS MERGED IDS, AND THAT IS WHAT MAKES A MERGE
        // A MERGE.** `34` §1.2 words it *"timeline unions, IDs remapped"* — and
        // nothing is remapped: the rows keep pointing at the contact they were
        // written against, because rewriting `consent_records.customer_id` would
        // restate **who** consented, in the one table whose job is to be true
        // (552/1225's shape), and rewriting `reviews.customer_id` would move a
        // customer's own words onto a row they never wrote them about. The
        // remap is a *read*, which is also the only version of it an undo can
        // reverse for free.
        //
        // ⚠️ ONE LEVEL, AND `CustomerMerges::merge()` IS WHAT MAKES THAT
        // COMPLETE — it refuses either side that is already merged away, so a
        // chain cannot exist and a transitive walk would be code for a state the
        // service forbids.
        //
        // Read through `CustomerMerges`, never off `merged_into_id` here:
        // `customer_merges` sits behind a chokepoint lint like `crm_tasks` and
        // `crm_notes` do, and 1223's rule is to ask whether the caller belongs
        // behind the service rather than to widen an allowlist. It does.
        $people = collect([$customer])->concat($this->merges->mergedInto($customer));

        /** @var Collection<int, TimelineEntry> $entries */
        $entries = $people->flatMap(fn (Customer $person): Collection => collect()
            ->concat($this->reviews($person))
            ->concat($this->messagesFor($person))
            ->concat($this->consentEvents($person))
            ->concat($this->notesFor($person))
            ->concat($this->tasksFor($person))
            ->concat($this->linkClicksFor($person)));

        return $entries
            // ⚠️ THE SENTINEL IS WHAT PUTS AN UNDATED ROW LAST, AND THE FIRST
            // VERSION OF THIS SORT ONLY LOOKED AS THOUGH IT DID. It carried an
            // explicit `occurredAt === null ? 1 : 0` leading key, and mutation
            // found that key decorative: the tiebreak beneath it fell back to
            // `?? 0`, and every real timestamp is a positive integer, so nulls
            // already sorted last for a reason the code did not state. A
            // protection asserted before it is true (314–316), inside the sort
            // whose docblock explains why the ordering matters. `PHP_INT_MIN` is
            // the smallest thing a descending sort can put anywhere, so the
            // behaviour now depends on the line that claims it — swap it for
            // `PHP_INT_MAX` and the test goes red.
            ->sortByDesc(
                fn (TimelineEntry $entry): int => $entry->occurredAt?->getTimestamp() ?? PHP_INT_MIN,
            )
            ->values();
    }

    /**
     * Reviews this contact left, and what routing decided.
     *
     * ⚠️ **THE COMMENT IS SHOWN AND THE MODERATION VERDICT IS NOT.** A flagged
     * review is withheld from *display* (`Review::displayable()` — three
     * independent conditions, decision 358's neighbourhood); it is not withheld
     * from the owner, whose own customer wrote it and who is the person expected
     * to act on it. What the owner is not shown is the model's flag list, because
     * that is our moderation working note about their customer and putting it on
     * the screen invites an argument about a label the owner cannot change.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function reviews(Customer $customer): Collection
    {
        return Review::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('id')
            ->get()
            ->map(fn (Review $review): TimelineEntry => new TimelineEntry(
                type: TimelineEntryType::Review,
                occurredAt: $review->created_at?->toImmutable(),
                headline: self::reviewHeadline($review),
                detail: $review->comment,
            ));
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function messagesFor(Customer $customer): Collection
    {
        return $this->messages->forCustomer($customer)
            ->map(fn (OutreachMessage $message): TimelineEntry => new TimelineEntry(
                type: TimelineEntryType::Message,
                // `sent_at` rather than `created_at`, and null while it is
                // queued: the owner is being told when we reached their
                // customer, not when a row was made.
                occurredAt: $message->sent_at?->toImmutable(),
                headline: 'We sent a message — '
                    .mb_strtolower(MessageLog::statusLabel($message->status)),
                detail: $message->body,
            ));
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function consentEvents(Customer $customer): Collection
    {
        return $this->consent->proofFor($customer)
            ->map(fn (ConsentTrailEntry $entry): TimelineEntry => new TimelineEntry(
                type: TimelineEntryType::Consent,
                occurredAt: $entry->occurredAt,
                headline: $entry->type === ConsentEventType::Consent
                    ? 'They agreed to hear from you on '.$entry->channel->value
                    : 'They asked you to stop messaging them on '.$entry->channel->value,
            ));
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function notesFor(Customer $customer): Collection
    {
        return $this->notes->forCustomer($customer)
            ->map(fn (CrmNote $note): TimelineEntry => new TimelineEntry(
                type: TimelineEntryType::Note,
                occurredAt: $note->created_at?->toImmutable(),
                headline: 'You added a note',
                detail: $note->body,
                actor: $note->author?->name,
            ));
    }

    /**
     * Follow-ups on this contact — `44` §2's "created/completed, one sentence
     * each". A completed task is two events, because each answers a different
     * question: "when did I decide to do this" and "when did I actually".
     *
     * Read through `CrmTasks` — `crm_tasks` sits behind a chokepoint lint the
     * same way `outreach_messages` and `consent_records` do, and 1222's
     * tripwire on `crm_timeline` is untouched.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function tasksFor(Customer $customer): Collection
    {
        return $this->tasks->forCustomer($customer)
            ->flatMap(function (CrmTask $task): array {
                $entries = [new TimelineEntry(
                    type: TimelineEntryType::Task,
                    occurredAt: $task->created_at?->toImmutable(),
                    headline: 'You set a reminder',
                    detail: $task->title,
                )];

                if ($task->done_at !== null) {
                    $entries[] = new TimelineEntry(
                        type: TimelineEntryType::Task,
                        occurredAt: $task->done_at->toImmutable(),
                        headline: 'You completed a follow-up',
                        detail: $task->title,
                    );
                }

                return $entries;
            })
            ->values();
    }

    /**
     * Links we sent this contact that they actually opened (T137 `SL-5`).
     *
     * ⛔ **`countedForCustomer()` AND NEVER A RAW QUERY, BECAUSE THE FILTER IS
     * THE FEATURE.** 2484 is the decision the whole source turns on: carriers,
     * link checkers and messaging apps request every URL we send, so a timeline
     * built on raw fetches says *"they opened your message"* about somebody
     * whose carrier scanned it — decision 113's rule one layer down, about a
     * person rather than a platform. That method takes `counted` rows only, and
     * it is the reason this reads through `ShortLinkClicks` rather than through
     * the model: a second reader here would be a second idea of what a click is,
     * and the two would disagree on the one screen where it matters.
     *
     * ⚠️ **THE SIXTH SOURCE, AND IT JOINED ON 1222's TERMS** — when the store
     * gained a writer. 2495 recorded the gap and said explicitly that adding the
     * case earlier *"would render an empty history and read as 'this contact
     * clicked nothing'"*. `ReviewInviteSender::tracked()` is the writer.
     *
     * ⚠️ **THE SENTENCE NAMES NO DESTINATION, AND THAT IS A REFUSAL RATHER THAN
     * AN OMISSION.** A click on a review-invite link is not a review and must
     * never be reported as one (decision 113); *"they opened the link"* is the
     * whole of what this application can honestly claim, and naming Google in
     * the line is how somebody reads a click as a posted review.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function linkClicksFor(Customer $customer): Collection
    {
        return $this->clicks->countedForCustomer($customer)
            ->map(fn (ShortLinkClick $click): TimelineEntry => new TimelineEntry(
                type: TimelineEntryType::LinkClick,
                // `clicked_at` is written by `ShortLinkClicks::record()` on
                // every row and the column is NOT NULL, so this source is the
                // only one of the six that can never sort into the undated tail
                // — which is why there is no `?->` here and why there is one
                // everywhere else in this file.
                occurredAt: $click->clicked_at->toImmutable(),
                headline: 'They opened a link we sent them',
            ));
    }

    /**
     * What happened to a review, in one sentence.
     *
     * ⚠️ **EVERY VERB HERE IS LOAD-BEARING, ON DECISION 389'S RULE.** *Offered*
     * is never *sent* — a routed invite is a button we rendered, and row 4's
     * delivery is a different event. *Went to you* is never *resolved* — triage
     * opening says a person was asked to act, not that anybody did.
     */
    private static function reviewHeadline(Review $review): string
    {
        $rating = 'They left a '.$review->rating.'-star review';

        return match ($review->routing_decision) {
            RoutingDecision::Invited => $rating.', and we offered them somewhere to post it',
            RoutingDecision::Triaged => $rating.', and it went to you to put right',
            RoutingDecision::InvitedAndTriaged => $rating.
                ', and it went to you to put right — we also offered them somewhere to post it',
            RoutingDecision::NoAction => $rating,
            null => $rating,
        };
    }
}
