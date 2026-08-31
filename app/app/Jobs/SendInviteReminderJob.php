<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\InviteAttemptStatus;
use App\Enums\SendRefusalReason;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Services\Feedback\FeedbackPages;
use App\Services\Messaging\MessageLog;
use App\Services\Messaging\ReviewInviteSender;

/**
 * Send one contact their single invite follow-up, off the request — T176 P14.
 *
 * ⚠️ **`SendReviewInviteJob`'s SHAPE, AND THE SIMILARITY IS THE POINT.** Same
 * base class, same reasons: this is a tenant's automation acting on their
 * behalf, so it wants the tenant established, the run row, the idempotency
 * claim, the kill switches and the activity feed. Read that job's docblock —
 * every argument in it applies here unchanged.
 *
 * ⚠️ **ITS OWN AUTOMATION KEY, DELIBERATELY**, rather than riding
 * `review.invite.email`. Three things hang off that string: the kill switch an
 * operator throws, the `automation_runs.automation_key` every historical row
 * carries, and the idempotency namespace. Sharing it would mean an operator who
 * stopped review invites could not stop the follow-ups separately — and, worse,
 * one who wanted to stop only the follow-ups would have to stop the invites too.
 * ⛔ **The reverse dependency is real and is not enforced here**: switching the
 * *invite* off does not switch this off, because the reminder is about invites
 * that already went. That is correct, and it is written down because it reads
 * like an oversight.
 *
 * ⚠️ **THE CHANNEL IS RESOLVED HERE AND PASSED IN, NOT RE-DERIVED BY THE
 * SENDER.** `ReviewInviteSender::remind()` takes the channel the invite itself
 * went out on, because a nudge on a channel the person never heard from us on
 * is a first contact wearing a follow-up's words. `MessageLog` is the one place
 * that fact is read from — the chokepoint lint's rule, and 624's.
 *
 * NO SEPARATE `handoff()` WORK, for `SendReviewInviteJob`'s reason and not
 * because it does not need one: nothing on this path touches the Google Business
 * Profile API. The links are our own redirect routes (387) and the send is our
 * own mailer or texter, so there is no reduced-capability version to describe —
 * which is `29` §2 rule 44 demonstrated rather than excepted.
 */
final class SendInviteReminderJob extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $reviewId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'review.invite.reminder';
    }

    /**
     * One reminder per review, ever — the layer that holds under retry.
     *
     * `MessageLog::reviewInviteReminderSent()` is a SELECT with no unique index
     * behind it and says so; this is the database-arbitrated claim a duplicate
     * dispatch, a queue replay or a retry after a timeout cannot get past.
     *
     * ⚠️ **KEYED ON THE REVIEW WHILE THE SERVICE CHECK IS KEYED ON THE
     * CONTACT**, which looks inconsistent and is the same asymmetry
     * `SendReviewInviteJob` carries. The key namespaces *this dispatch*; the
     * service answers the product question, which is that one person gets one
     * nudge however many reviews they leave. The narrower key cannot let a
     * second message through, because the service refuses it.
     */
    protected function idempotencyKey(): string
    {
        return 'review-invite-reminder:'.$this->reviewId;
    }

    /**
     * ⚠️ FALSE UNTIL `remind()` RETURNS A ROW, on `SendReviewInviteJob`'s
     * reasoning and with one addition that matters more here than there.
     *
     * ⛔ **NEARLY EVERY REFUSAL ON THIS PATH IS TEMPORARY, WHICH IS WHAT MAKES
     * RELEASING THE CLAIM LOAD-BEARING RATHER THAN TIDY.** A closed daytime
     * window, an arbiter holding for the rest of the day, a global halt, a
     * `SendingPause`, an exhausted balance and a contact whose state nobody has
     * filled in yet all answer null — and every one of them will have cleared by
     * some later sweep. Keeping the claim would turn each into "this contact
     * never gets a reminder", silently, which is exactly the gate-3-forever
     * problem `ReviewInviteSender` rolls its transaction back to avoid.
     *
     * ⚠️ RELEASING IS SAFE ONLY BECAUSE OF WHERE THE ROW IS WRITTEN. The
     * `outreach_messages` row is written inside the send transaction, so a throw
     * rolls it back and a retry genuinely re-decides; a row that committed
     * before the send would make this "did the row land" instead.
     */
    private bool $claimSpent = false;

    protected function claimIsSpent(): bool
    {
        return $this->claimSpent;
    }

    /**
     * ⛔ **THE SAME FLAG AS {@see self::$claimSpent} TODAY, AND DELIBERATELY A
     * SECOND ONE.** Both are `$attempt->wasSent()` on this path because the
     * claim happens to be earned by exactly the thing the feed is about. They answer
     * different questions — *may this run again?* and *did a person hear from
     * us?* — and {@see SendReviewInviteJob} is the proof they come apart: there
     * the claim is earned by an arm where the sender returned `null` and nothing
     * went. Collapsing them here would make the next arm added to
     * {@see self::follow()} silently decide both.
     */
    private bool $reminded = false;

    /**
     * ⛔ **THIS JOB DECLARED NOTHING AND SO FILED `AutomationCompleted` ON EVERY
     * ARM — WHICH IS 7223, IN THE FILE WHOSE OWN DOCBLOCK SAYS SO** (7323). The
     * class docblock reads *"`SendReviewInviteJob`'s SHAPE, AND THE SIMILARITY
     * IS THE POINT … every argument in it applies here unchanged"*, and the one
     * argument that did not survive the copy is the one 7223 had to add: every
     * `return null` in {@see self::follow()} is an ordinary outcome — a review
     * that is gone, an anonymous submission with no contact, a location with no
     * feedback page, **an invite that never actually left**, and a sender
     * refusing for a closed window, an arbiter, the halt, a `SendingPause` or an
     * empty balance. This job's own `claimIsSpent()` docblock calls that last
     * group *"nearly every refusal on this path"*, and each of them told the
     * owner a piece of work had been finished.
     *
     * ⚠️ **NOT `ReviewRequestSent`, AND THE CHOICE IS ARGUED RATHER THAN
     * INHERITED** (7333). *"Asked a customer for a review"* is true of a
     * reminder and would read better than the catch-all — but that case is
     * {@see SendReviewInviteJob}'s, given its writer at 7223, and a second
     * writer would put two identical sentences in one owner's feed for one
     * customer with nothing in the title to tell the invite from the nudge. The
     * vocabulary belongs to `AutopilotActionType`, which is another lane's file
     * in this wave; the falsehood is what is fixed here.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->reminded ? AutopilotActionType::AutomationCompleted : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->follow();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->follow();
    }

    /**
     * ⛔ **EVERY ARM RETURNS AN ACCOUNT NOW, AND UNTIL 10180 FOUR OF THEM
     * RETURNED `null`** — `SendReviewInviteJob::invite()`'s reasoning one file
     * over, and the same comment below the send made the same claim. A `null`
     * is written into `automation_runs.output` verbatim and the row closes
     * `Succeeded`, so the four commonest outcomes of the follow-up sweep left
     * no record of themselves at all.
     *
     * ⚠️ **`skipped` FOR THE FOUR PRE-ATTEMPT GUARDS BELOW, `refusal` FOR A
     * TYPED RULE** — the sibling's rule: `refusal` carries a
     * {@see SendRefusalReason}, and the four early returns declined to
     * message nobody in particular; there was nothing to follow up.
     *
     * ⛔ **AND THE HOLE IN THIS RECORD WAS HERE TOO, AND IS CLOSED — 10211,
     * PHASE 1.** This called `ReviewInviteSender::remind()`, which returns
     * `?OutreachMessage`, so a closed daytime window, an arbiter collision, a
     * global halt, a tenant pause, an exhausted balance or an unknown state all
     * answered the same `null`. **This job now asks
     * {@see ReviewInviteSender::attemptReminder()} instead**, on
     * `SendReviewInviteJob`'s own reasoning: the four `InviteAttemptStatus`
     * cases become four different rows, and `Duplicate` reads
     * `already_reminded` because `remind()`'s own idempotency check above this
     * method makes the race a duplicate rather than a refusal of anybody.
     *
     * ⚠️ **`$this->claimSpent` AND `$this->reminded` STAY `wasSent()`, NOT
     * WIDENED TO "THE ATTEMPT COMPLETED".** `claimIsSpent()`'s own docblock is
     * explicit that nearly every refusal here is temporary and must release the
     * claim so a later sweep re-decides — a `Refused` attempt must behave
     * exactly as the old `null` did, not as `SendReviewInviteJob`'s unconditional
     * `$this->claimSpent = true` does one file over. The two jobs share a
     * sender and do not share this rule.
     *
     * @return array<string, mixed>
     */
    private function follow(): array
    {
        $review = Review::query()->find($this->reviewId);

        if (! $review instanceof Review) {
            return ['reminded' => false, 'skipped' => 'review_missing'];
        }

        $location = $this->location();
        $customer = $review->customer_id === null
            ? null
            : Customer::query()->find($review->customer_id);

        if (! $location instanceof Location || ! $customer instanceof Customer) {
            return ['reminded' => false, 'skipped' => 'no_customer_or_location'];
        }

        // ⚠️ The invite is what says which channel to answer on and whether
        // there was an invite at all. A review the sweep offered whose invite
        // never actually left has nothing to follow up, and refusing here rather
        // than trusting the sweep is 398's rule.
        $invite = app(MessageLog::class)->reviewInviteFor($customer);

        if ($invite === null) {
            return ['reminded' => false, 'skipped' => 'no_invite_to_follow'];
        }

        // ⚠️ Through the owning service, never `FeedbackPage::query()` — decision
        // 624's chokepoint rule, the same call `SendReviewInviteJob` makes.
        $page = app(FeedbackPages::class)->forLocation($location);

        if (! $page instanceof FeedbackPage) {
            return ['reminded' => false, 'skipped' => 'no_feedback_page'];
        }

        $attempt = app(ReviewInviteSender::class)
            ->attemptReminder($review, $location, $page, $customer, $invite->channel);

        // Past the send, so the claim is earned. Every early return above is an
        // ordinary outcome that cost nothing, and re-making one is free.
        // ⚠️ **IT SAID `return null` AND THOSE ARMS NOW CARRY A `skipped` CODE**
        // (10180): the claim was right and the record was empty.
        $this->claimSpent = $attempt->wasSent();
        $this->reminded = $attempt->wasSent();

        // ⚠️ Whether one went, never to whom. `AutopilotJob` writes this onto the
        // run row, which staff and the Ops console read; an address or a number
        // here would put personal data in a table nobody thinks of as holding it
        // (627). `SendRefusalReason` is safe to show an operator by that enum's
        // own contract — each case names a rule, never a contact detail.
        return match ($attempt->status) {
            InviteAttemptStatus::Sent => ['reminded' => true],
            InviteAttemptStatus::Refused => [
                'reminded' => false,
                'refusal' => $attempt->reason?->value,
            ],
            InviteAttemptStatus::Duplicate => ['reminded' => false, 'skipped' => 'already_reminded'],
            InviteAttemptStatus::NotAttempted => ['reminded' => false, 'skipped' => 'not_attempted'],
        };
    }
}
