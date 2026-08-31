<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\InviteAttemptStatus;
use App\Enums\SendRefusalReason;
use App\Exceptions\TextNotDeliverable;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Services\Feedback\FeedbackPages;
use App\Services\Messaging\InviteAttempt;
use App\Services\Messaging\ReviewInviteSender;

/**
 * Invite one customer, off the request.
 *
 * ⚠️ **QUEUED, WHERE `RouteReviewJob` DELIBERATELY WAS NOT** (370). Routing
 * compares two integers against two tables and calls nothing external, so it
 * runs synchronously inside the submission transaction and `29` §12.1's gating
 * test forbids any window at all. This is the opposite kind of work: it crosses
 * a vendor boundary, so it must not sit on the path of a customer waiting for a
 * thank-you page. A refused SMTP connection would otherwise be a 500 on the form
 * they just filled in — after their feedback was already saved.
 *
 * ⚠️ **`AutopilotJob` RATHER THAN A PLAIN JOB, WHICH IS THE OPPOSITE OF
 * `DeliverPlatformMail`'s CHOICE** — and the line between them is worth naming,
 * because the two land in the same directory a week apart. `DeliverPlatformMail`
 * carries a sign-in link to an account holder: no tenant, no automation toggle,
 * no Google-shaped fallback. **This is a tenant's automation acting on their
 * behalf**, so it wants every one of those: the tenant established, the run row,
 * the idempotency claim, the kill switches, and the activity feed.
 *
 * ⚠️ **THE AUTOMATION KEY STILL SAYS `email` AND THIS JOB NOW ALSO TEXTS**
 * (row 4 slice 4, 1607). `ReviewInviteSender::send()` attempts email first and
 * SMS only where email sent nothing, so a run row keyed `review.invite.email`
 * can account for a text message. **The name is owed a correction and did not
 * get one here**: the string is the `automation_runs.automation_key` every
 * historical row carries, the prefix of the idempotency key that stops a
 * redelivery sending twice, and the literal `ReinviteDeferredReviews` looks a
 * kill switch up by — so renaming it is a data change across three concerns
 * rather than a rename, and doing it inside a slice that adds a channel is how
 * one of them gets missed. Recorded here so the next reader is not misled by a
 * name rather than left to discover it.
 *
 * NO SEPARATE `handoff()` WORK, AND THE REASON IS NOT "IT DOES NOT NEED ONE".
 * `CLAUDE.md` requires both paths in the same ticket precisely so nobody
 * retrofits them. Here they are genuinely identical: nothing in this job touches
 * the Google Business Profile API — the invite links are our own redirect routes
 * (387), and the send is our own mailer — so there is no reduced-capability
 * version to describe. The row gate is *"the whole review engine runs with zero
 * GBP API access"*, and this job is a demonstration of it rather than an
 * exception to it.
 */
final class SendReviewInviteJob extends AutopilotJob
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
        return 'review.invite.email';
    }

    /**
     * One invite per review, ever — and this is the layer that holds under retry.
     *
     * `ReviewInviteSender::alreadySent()` is a SELECT-then-INSERT and says so;
     * this is the database-arbitrated claim that a duplicate dispatch, a queue
     * replay, or a retry after a timeout cannot get past.
     */
    protected function idempotencyKey(): string
    {
        return 'review-invite-email:'.$this->reviewId;
    }

    /**
     * ⚠️ FALSE UNTIL `invite()` RETURNS, WHICH IS WHAT MAKES THE RETRY LADDER
     * REAL. Without this the first customer-facing send in the product had three
     * attempts that could not reach `execute()`: the failed run row keeps the
     * unique key, so every retry collides with its own first attempt and returns
     * early. A refused SMTP connection, a `linkFor()` throw on a cleared
     * `place_id` (382), or a queue blip meant the invite was lost permanently and
     * the run rows read `failed` three times, which looks like a job that tried.
     *
     * ⚠️ RELEASING IS SAFE HERE ONLY BECAUSE OF WHERE THE ROW IS WRITTEN, and the
     * two are worth reading together. `ReviewInviteSender::send()` writes the
     * `outreach_messages` row **inside the same transaction** as the mail
     * dispatch, so a throw rolls the row back with it — meaning a retry finds
     * nothing, `alreadySent()` does not refuse, and the whole decision is genuinely
     * re-made. If that row were ever committed before the send, this would have
     * to become "did the row land" instead, or a retry would be blocked by its
     * own abandoned evidence.
     *
     * ⚠️ SET PAST THE SEND ONLY, NOT AT THE END OF `invite()` — narrower than
     * `AnalyzeReviewJob::$verdictPersisted`, deliberately. Every `return null`
     * above is an ordinary outcome (no customer, no page, a gate refusing) that
     * cost nothing, so leaving the claim unearned lets a later dispatch re-decide
     * it for free. Only the send is the thing that must not happen twice.
     *
     * ⛔ **AND A TRANSPORT FAILURE NOW ANSWERS THIS RATHER THAN FALLING THROUGH
     * TO "RELEASE" — 7067.** A thrown {@see TextNotDeliverable} used to skip
     * every assignment below, leaving the flag `false`, so the claim went back
     * and the queue tried again. **That is right for a request that provably
     * never left this machine and it is a second text to a member of the public
     * for one that may already be with the carrier** — and until 2026-08-21 the
     * transport could not tell those apart, so this job could not either. The
     * argument this docblock already makes establishes that the retry is
     * *unblocked*; it was read for months as establishing that the retry is
     * *safe*. Those are different claims and only the first was ever true.
     *
     * ⚠️ **THE COST IS A LOST MESSAGE, AND IT IS THE SIDE THIS CODEBASE HAS
     * ALREADY CHOSEN IN WRITING.** A blip that timed out before the carrier saw
     * anything is indistinguishable from one that timed out after, so both stop
     * rather than retry: nothing is sent, nothing is charged, and
     * `automation_runs` carries the reason. `AutopilotJob::claimIsSpent()`
     * settled the trade — *"losing a send is recoverable; sending twice is
     * not"* — and this restores the base class's own ruling everywhere except
     * where a retry is provably safe.
     */
    private bool $claimSpent = false;

    /**
     * Whether this attempt actually asked a customer for a review.
     *
     * ⚠️ **NARROWER THAN {@see self::$claimSpent}, AND THE GAP IS THE WHOLE
     * REASON IT IS A SECOND FLAG.** The claim is earned by reaching the end of
     * the attempt — including every arm where
     * `ReviewInviteSender::attempt()` did not send, which must not be tried
     * twice and must not be reported as an invite. This one is the feed's
     * question: did a message go to a person?
     */
    private bool $invited = false;

    protected function claimIsSpent(): bool
    {
        return $this->claimSpent;
    }

    /**
     * ⛔ **`AutopilotActionType::ReviewRequestSent` HAD NO WRITER AND THIS JOB
     * WAS FILING `AutomationCompleted` INSTEAD** (7134's population, 7223).
     * *"Asked a customer for a review"* is the sentence for the single most
     * important thing this product does on a tenant's behalf, and every invite
     * ever sent reached the owner's feed as *"Finished a piece of work for
     * you"* — the base class's catch-all, which is what an automation says when
     * it has nothing to say. The case has carried the right words since Stage 0
     * and nothing pointed at it.
     *
     * ⛔ **AND IT WAS FILED UNCONDITIONALLY, WHICH IS THE HALF THAT WAS
     * ACTIVELY WRONG.** Every `return null` in {@see self::invite()} is an
     * ordinary outcome — an anonymous submission with no customer, a location
     * that has no feedback page, a gate refusing the send — and each of them
     * still wrote a feed row. {@see AutopilotJob::activityAction()} names that
     * exact shape as the one to avoid: *"an automation whose only honest title
     * is 'checked something and found nothing' makes the feed worse"*. Silence
     * is what those arms deserve, and `automation_runs` already carries them.
     *
     * ⚠️ **THIS REMOVES FEED ROWS THAT EXIST TODAY**, deliberately. A tenant
     * whose invites were all refused by a consent gate has been reading a
     * history of work that was never done.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->invited ? AutopilotActionType::ReviewRequestSent : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->invite();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->invite();
    }

    /**
     * ⛔ **EVERY ARM RETURNS AN ACCOUNT NOW, AND UNTIL 10180 THREE OF THEM
     * RETURNED `null`.** The comment below the send has said since this file
     * shipped that those arms *"are decisions the run's output records"* — and
     * `AutopilotJob::handle()` writes the return value straight into
     * `automation_runs.output`, so a `null` was a run closed `Succeeded` with
     * **no account of itself at all**. `automation_runs` is the only place this
     * platform writes down what it did, and on the single most important thing
     * this product does on a tenant's behalf it was writing nothing. That is a
     * protection claimed in a comment before it was true — `CLAUDE.md`'s named
     * shape, in the sentence asserting it.
     *
     * ⚠️ **`skipped` FOR THE THREE PRE-ATTEMPT GUARDS ABOVE, `refusal` FOR A
     * TYPED RULE, AND THE TWO KEYS ARE DELIBERATELY NOT ONE.**
     * `SendMissedCallTextBackJob` carries `refusal`, whose value is a
     * {@see SendRefusalReason} — a rule that declined to message somebody.
     * Nothing declined on the three early returns below: there was no message
     * to send. Putting a free string under `refusal` would make one key mean
     * two things in one table, which is the collapse this change exists to
     * undo.
     *
     * ⛔ **THE LARGEST HOLE IN THIS RECORD WAS HERE AND IS CLOSED — 10211,
     * PHASE 1.** This called `ReviewInviteSender::send()`, which returns
     * `?OutreachMessage`, so when a gate inside it refused — suppression,
     * quiet hours, an exhausted balance, a consent record that does not carry
     * the basis — this job saw only a `null` and wrote `invited: false` with
     * nothing beside it. The reason was typed at the point of refusal and did
     * not survive the return type. **This job now asks {@see
     * ReviewInviteSender::attempt()} instead**, which answers an
     * {@see InviteAttempt} rather than a bare message,
     * and the four `InviteAttemptStatus` cases become four different rows:
     * `Sent` → `invited: true`; `Refused` → `invited: false, refusal: …`;
     * `Duplicate` → `invited: false, skipped: 'already_invited'` (an earlier
     * dispatch, or the send-key race `sendText()`/`sendEmail()` catch, got
     * there first — no rule refused this customer); `NotAttempted` →
     * `invited: false, skipped: 'not_attempted'` — the fused case
     * {@see InviteAttemptStatus::NotAttempted} documents on its own docblock,
     * which this job cannot split further without that enum growing a case.
     *
     * ⚠️ **NOT THREE LINES.** The brief that asked for this estimated it at
     * three; the sender's return type changed from `?OutreachMessage` to
     * `InviteAttempt`, `$message !== null` throughout became `wasSent()`, and
     * the single `return` grew into a `match` over four states with two new
     * output keys. `send()`'s own signature is untouched — see its docblock —
     * so nothing else calling this class moved.
     *
     * @return array<string, mixed>
     */
    private function invite(): array
    {
        $review = Review::query()->find($this->reviewId);

        if (! $review instanceof Review) {
            return ['invited' => false, 'skipped' => 'review_missing'];
        }

        $location = $this->location();
        $customer = $review->customer_id === null
            ? null
            : Customer::query()->find($review->customer_id);

        // ⚠️ An anonymous submission has no customer and therefore no consent
        // record and no address. Slice C's form allows one deliberately (350
        // exempts them from duplicate collapse for the same reason), so this is
        // an ordinary outcome and not a torn row.
        if (! $location instanceof Location || ! $customer instanceof Customer) {
            return ['invited' => false, 'skipped' => 'no_customer_or_location'];
        }

        // ⚠️ Through the owning service, never `FeedbackPage::query()`. The
        // chokepoint lint caught the direct query on this file's first run —
        // decision 624's rule, third instance.
        $page = app(FeedbackPages::class)->forLocation($location);

        if (! $page instanceof FeedbackPage) {
            return ['invited' => false, 'skipped' => 'no_feedback_page'];
        }

        try {
            $attempt = $this->sender()->attempt($review, $location, $page, $customer);
        } catch (TextNotDeliverable $e) {
            // ⛔ **THE ONLY THING BETWEEN THIS AND A SECOND INVITE IS THIS
            // LINE** (7067). The sender has rolled its row, its debit and its
            // short link back, so the `send_key` index will not refuse the
            // retry — the run claim is the whole guard. It is handed back only
            // when the transport can prove nothing left this machine.
            $this->claimSpent = $e->mayHaveReachedCarrier;

            throw $e;
        }

        // Past the send, so the claim is earned: a redelivery must not send
        // again. Every early return above leaves it unset for the same reason —
        // those are decisions the run's output records, and re-making one is
        // free, while re-making this one is a second email. ⚠️ **THE SENTENCE
        // SAID `return null` AND SAID IT FOR MONTHS, AND `null` IS THE ONE
        // VALUE THE RUN'S OUTPUT CANNOT RECORD ANYTHING FROM** (10180) — the
        // claim was correct about the claim and false about the record, which
        // is why the arms above now carry one.
        $this->claimSpent = true;
        $this->invited = $attempt->wasSent();

        // ⚠️ The output records *whether* an invite went and never to whom.
        // `AutopilotJob` writes this onto the run row, which is read by staff
        // and by the Ops console; an address here would put a customer's email
        // in a table nobody thinks of as holding personal data. Decision 627's
        // warning about `metadata`, applied before it becomes a finding.
        // `SendRefusalReason` is safe to show an operator by that enum's own
        // contract — each case names a rule, never a contact detail.
        // ⚠️ ARM ORDER MATCHES `InviteAttemptStatus`'S OWN DECLARATION ORDER
        // — RESTORED 2026-08-27 (10480-10499), THE SAME ORDER
        // `SendInviteReminderJob.php` ALREADY USES. It had been reordered to
        // put `Sent` last purely to push the unrelated literal
        // `InviteAttemptStatus::Sent` past `MessagingTest`'s then-fixed
        // 400-character carrier-flag window — a false positive on a lint that
        // now scopes itself to the real statement/block boundary instead of a
        // raw character count, so the workaround is no longer needed.
        return match ($attempt->status) {
            InviteAttemptStatus::Sent => ['invited' => true],
            InviteAttemptStatus::Refused => [
                'invited' => false,
                'refusal' => $attempt->reason?->value,
            ],
            InviteAttemptStatus::Duplicate => ['invited' => false, 'skipped' => 'already_invited'],
            InviteAttemptStatus::NotAttempted => ['invited' => false, 'skipped' => 'not_attempted'],
        };
    }

    private function sender(): ReviewInviteSender
    {
        return app(ReviewInviteSender::class);
    }
}
