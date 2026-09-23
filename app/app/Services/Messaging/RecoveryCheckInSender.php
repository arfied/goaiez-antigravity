<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MarketingTouch;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\OutreachStatus;
use App\Enums\SendRefusalReason;
use App\Enums\TriageStatus;
use App\Exceptions\CreditMovementRefused;
use App\Exceptions\TextNotDeliverable;
use App\Http\Controllers\FixThenAskCheckInController;
use App\Models\Customer;
use App\Models\Location;
use App\Models\OutreachMessage;
use App\Models\Review;
use App\Models\TriageConversation;
use App\Notifications\RecoveryCheckInEmail;
use App\Services\Billing\EmailCredits;
use App\Services\Billing\SendCredits;
use App\Services\Campaigns\SendCollisionArbiter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Messaging\Outbound\SendSettlement;
use App\Services\Reviews\ReviewRouter;
use App\Services\Sms\PlatformTexter;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * "Did we get that sorted?" — T546 §37.3(1), wave 38 lane C (10590–10609).
 *
 * The first message this platform sends asking a previously-unhappy customer
 * anything, on the strength of an owner's own claim that the complaint is
 * fixed. Everything `ReviewInviteSender` argues for its own send applies here
 * unchanged and is deliberately not re-argued line by line — this class is
 * smaller because the message is simpler (one question, no per-destination
 * offer, no reminder), not because the compliance surface is.
 *
 * ⚠️ **THE FEATURE SWITCH IS THE FIRST GATE, SEEDED FALSE, AND IT IS CONFIRM'S
 * SUBSTITUTE.** `reviews.fix_then_ask_enabled` is what `DefaultsManifest`'s
 * own docblock for that key argues at length: `CLAUDE.md` reserves CONFIRM for
 * the first send of a new campaign type, CONFIRM itself is unbuilt and pinned
 * (8570), and this switch is the deliberate, off-by-default choice an operator
 * must make instead.
 *
 * ⚠️ **`MarketingTouch::Recovery` AND `outreach_messages.purpose = 'triage'`,
 * NOT A NEW VOCABULARY.** `DATA-MODEL` §5.7 already names `triage` among its
 * purpose values and `MarketingTouch::forOutreachPurpose('triage')` already
 * maps it to `self::Recovery` — *"reaching an unhappy customer after a low
 * rating"* — which is exactly what this message is. Nothing here invents a
 * case; it is the first writer of one the schema was already carrying.
 *
 * ⚠️ **THE LINK IS A LARAVEL SIGNED URL, NEVER A `ShortLinks` MINT.** See
 * `RecoveryCheckInEmail`'s own docblock — `campaign.media` and
 * `content.growth-page.hold` are the precedent this class follows verbatim,
 * and reusing `ShortLinks` here would misuse a mechanism built for redirecting
 * a click to somewhere *else*, tracked as a fetch of a review/booking/document
 * link this message is none of.
 *
 * ⚠️ **NO PER-DESTINATION OFFER, SO NO `ReviewInvites::offerFor()` GATE.**
 * `ReviewInviteSender`'s gate 2 asks "something to invite them to", which
 * would refuse almost every candidate this class is handed — these are, by
 * construction, reviews whose rating never cleared an invite threshold. This
 * message asks a different question and carries no destination at all; the
 * eventual invite, if one follows, is dispatched by
 * {@see FixThenAskCheckInController::answer()} out of what
 * {@see ReviewRouter::recordFixThenAskResponse()} hands back — never composed
 * here.
 *
 * ⛔ **THAT SENTENCE NAMED A METHOD THAT DOES NOT EXIST AND THE WRONG
 * RECOMPUTE, AND IT NAMED THEM REASSURINGLY — CORRECTED 2026-08-29** (12332).
 * It read *"`ReviewRouter::reoffer()`'s own recompute, dispatched by the caller
 * of `self::recordAnswer()`'s equivalent flow"*. **`recordAnswer()` has never
 * been declared on this class**, and `reoffer()` is a different path's
 * recompute — `ReinviteDeferredReviews` is its only caller and it never runs
 * here. What the answer actually dispatches is `SendConfirmedFixInviteJob`.
 * ⚠️ **The property the sentence exists to state is unchanged and still
 * holds**: nothing here composes an invite.
 *
 * ⚠️ **THE ARBITER AND THE DAYTIME WINDOW BOTH APPLY, ON `remind()`'s OWN
 * REASONING RATHER THAN THE IMMEDIATE INVITE'S.** This message is chosen by a
 * sweep running on our clock, days after the event it concerns — never in
 * direct response to something the customer just did — which is exactly the
 * shape T176 §3's R23 and `SendCollisionArbiter`'s own docblock write the
 * immediate invite *out* of and the reminder *into*.
 *
 * ⚠️ **THE INTERNAL COST BOOK (`MessageCostLedger`) IS DELIBERATELY NOT
 * WRITTEN HERE, AND THIS IS A STATED OMISSION RATHER THAN A SILENT ONE.** The
 * tenant-facing credit debit — the ledger 3297's rule is actually about — is
 * implemented in full, below. `recordCost()`'s own book is margin visibility
 * on top of that, and duplicating its `SmsSegments`/`MessageRates` wiring for
 * a brand-new, low-volume, off-by-default message was judged the wrong trade
 * for one overnight lane against the compliance-critical half. **Owed, not
 * dropped** — see this slice's report and `DECISIONS.md` 10590–10609.
 */
final class RecoveryCheckInSender
{
    /**
     * How long a check-in link stays clickable, in days.
     *
     * ⚠️ **LONGER THAN `ReviewRouter::MAX_DEFERRAL_DAYS`, DELIBERATELY.** That
     * ceiling bounds how stale a *deferred* invite may be before it is worth
     * nothing to the customer who wrote it. A check-in link is different: it
     * sits in somebody's inbox and there is no reason a reply next week is
     * worth less than one tomorrow, so this bounds the link's own lifetime as
     * a URL-signing hygiene practice — `campaign.media`'s and
     * `content.growth-page.hold`'s own precedent — rather than a claim about
     * the answer going stale.
     */
    public const int LINK_TTL_DAYS = 30;

    public function __construct(
        private readonly ConsentService $consent,
        private readonly PlatformMailer $mailer,
        private readonly PlatformTexter $texter,
        private readonly DefaultsRegistry $defaults,
        private readonly SendCredits $credits,
        private readonly EmailCredits $emailCredits,
        private readonly SendingGuard $guard,
        private readonly SendSettlement $settlement,
        private readonly SendCollisionArbiter $arbiter,
        private readonly ReviewRouter $router,
    ) {}

    public function linkTtlDays(): int
    {
        return $this->defaults->int('messaging.recovery.link_ttl_days');
    }

    /**
     * Send the check-in, or decline to and say why.
     *
     * ⚠️ **THE CALLER OWNS THE CONVERSATION AND THIS METHOD NEVER QUERIES
     * `TriageConversation` — IT IS HANDED ONE.** `Architecture\ReviewsTest`
     * holds that model reachable from `ReviewRouter` alone; this class reads
     * the object it was given (which the job loaded through
     * `ReviewRouter::findConversation()`) and, on an actual send, asks
     * `ReviewRouter::recordFixThenAskOffered()` to write the mark — the same
     * separation `DraftRecoveryOutreachJob` already keeps with this model.
     */
    public function attempt(TriageConversation $conversation): CheckInAttempt
    {
        // Gate 1 — the feature switch, `=== true` on ReviewInviteSender's own
        // asymmetry: anything malformed means do not send.
        if ($this->defaults->value('reviews.fix_then_ask_enabled') !== true) {
            return CheckInAttempt::notAttempted();
        }

        // Gate 2 — never asked twice. The durable marker, re-checked here as
        // well as by the sweep that dispatched this job (398's rule: an outer
        // filter must not be the only thing making a gate true).
        if ($conversation->fix_then_ask_offered_at !== null) {
            return CheckInAttempt::duplicate();
        }

        // Gate 3 — still resolved. A conversation can reopen between the sweep
        // enumerating it and this job running.
        if ($conversation->status !== TriageStatus::Resolved) {
            return CheckInAttempt::notAttempted();
        }

        $review = Review::query()->find($conversation->review_id);
        $location = $review === null ? null : Location::query()->find($review->location_id);
        $customer = $conversation->customer_id === null
            ? null
            : Customer::query()->find($conversation->customer_id);

        // An anonymous first-party review has no matched customer, and this
        // conversation has nobody to check in with — an ordinary outcome, not
        // a torn row.
        if (! $review instanceof Review || ! $location instanceof Location || ! $customer instanceof Customer) {
            return CheckInAttempt::notAttempted();
        }

        $checkInUrl = URL::temporarySignedRoute(
            'reviews.fix-then-ask.checkin.show',
            now()->addDays($this->linkTtlDays()),
            ['business' => Tenancy::idOrFail(), 'conversation' => $conversation->getKey()],
        );

        $attempt = $this->attemptChannel($conversation, $location, $customer, OutreachChannel::Email, $checkInUrl);

        if ($attempt->wasSent()) {
            return $attempt;
        }

        return $this->attemptChannel($conversation, $location, $customer, OutreachChannel::Sms, $checkInUrl);
    }

    private function attemptChannel(
        TriageConversation $conversation,
        Location $location,
        Customer $customer,
        OutreachChannel $channel,
        string $checkInUrl,
    ): CheckInAttempt {
        // Before the permit and before any transaction — `ReviewInviteSender`'s
        // own ordering: a refusal here costs one query and no rollback.
        $containment = $this->guard->refusalFor($channel);

        if ($containment !== null) {
            return CheckInAttempt::refused($containment);
        }

        // ⚠️ **THE ARBITER, ON `remind()`'s OWN REASONING RATHER THAN THE
        // IMMEDIATE INVITE'S** — this message is chosen by a sweep running on
        // our clock, never in direct response to something the customer just
        // did.
        if (! $this->arbiter->allows($customer, $channel, MarketingTouch::Recovery)) {
            return CheckInAttempt::notAttempted();
        }

        $decision = $this->consent->decide($customer, $channel, OutreachPurpose::Transactional);

        if (! $decision->isGranted()) {
            return CheckInAttempt::refused($decision->reason);
        }

        $permit = $decision->permit;

        $windowRefusal = $this->consent->daytimeWindowRefusal($customer, $permit->consentType);

        if ($windowRefusal !== null) {
            return CheckInAttempt::refused($windowRefusal);
        }

        return $channel === OutreachChannel::Email
            ? $this->sendEmail($conversation, $location, $customer, $permit, $checkInUrl)
            : $this->sendText($conversation, $location, $customer, $permit, $checkInUrl);
    }

    private function sendEmail(
        TriageConversation $conversation,
        Location $location,
        Customer $customer,
        SendPermit $permit,
        string $checkInUrl,
    ): CheckInAttempt {
        // Email's own transport readiness — the same call
        // `ReviewInviteSender::sendEmail()` asks, so there is exactly one
        // implementation of "may this platform mail a customer right now"
        // rather than a second reading of the rule.
        $mailRefusal = $this->mailer->customerMailRefusal();

        if ($mailRefusal !== null) {
            return CheckInAttempt::refused(SendRefusalReason::ChannelUnavailable);
        }

        $key = SendKey::for($permit, 'fix_then_ask_checkin:'.$conversation->getKey());

        try {
            $message = DB::transaction(function () use ($location, $customer, $permit, $key, $checkInUrl): OutreachMessage {
                /** @var OutreachMessage $message */
                $message = $this->emailCredits->debitForSend(
                    recordTheSend: fn (): OutreachMessage => OutreachMessage::create([
                        'business_id' => Tenancy::idOrFail(),
                        'location_id' => $location->id,
                        'customer_id' => $customer->id,
                        'channel' => OutreachChannel::Email,
                        'purpose' => 'triage',
                        'lane' => $permit->lane,
                        'send_key' => $key->value,
                        // ⚠️ **`0` AND NOT NULL, AND ON THE EMAIL CHANNEL IT IS
                        // A TYPE-LEVEL FACT** (12461, spelled `false` until
                        // 2026-08-30). Media cannot ride an email —
                        // `OutboundMessage::for()` throws on one — and this row
                        // is debited by `EmailCredits`, which prices a message
                        // and never a photo.
                        'media_count' => 0,
                        'status' => OutreachStatus::Queued,
                        'sent_at' => now(),
                        'created_at' => now(),
                    ]),
                    refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
                );

                $this->mailer->sendToCustomer(
                    $permit,
                    new RecoveryCheckInEmail($location->businessName(), $checkInUrl),
                    $location->businessName(),
                    $message,
                );

                return $message;
            });
        } catch (QueryException $e) {
            if (SqlState::of($e) !== '23505') {
                throw $e;
            }

            return CheckInAttempt::duplicate();
        } catch (CreditMovementRefused) {
            return CheckInAttempt::refused(SendRefusalReason::InsufficientCredit);
        }

        // ⚠️ **AFTER THE TRANSACTION HAS COMMITTED, NEVER NESTED INSIDE IT** —
        // `ReviewRouter::recordFixThenAskOffered()` opens its own transaction,
        // and 3880/3881's own lesson is that a nested `DB::transaction()` does
        // not contain a Postgres deadlock. A process dying in the gap between
        // the commit above and this call leaves the send genuinely sent with
        // the marker unset, which is recoverable: the next sweep pass finds
        // this conversation again, `attempt()` tries again, and the
        // deterministic `SendKey` this same occasion string derives collides
        // at the database and answers `Duplicate` — never a second message.
        $this->router->recordFixThenAskOffered($conversation);

        return CheckInAttempt::sent($message);
    }

    private function sendText(
        TriageConversation $conversation,
        Location $location,
        Customer $customer,
        SendPermit $permit,
        string $checkInUrl,
    ): CheckInAttempt {
        $key = SendKey::for($permit, 'fix_then_ask_checkin:'.$conversation->getKey());
        $body = "{$location->businessName()}: did we get that sorted for you? {$checkInUrl}";

        DB::beginTransaction();

        try {
            /** @var OutreachMessage $message */
            $message = $this->credits->debitForSend(
                recordTheSend: fn (): OutreachMessage => OutreachMessage::create([
                    'business_id' => Tenancy::idOrFail(),
                    'location_id' => $location->id,
                    'customer_id' => $customer->id,
                    'channel' => OutreachChannel::Sms,
                    'purpose' => 'triage',
                    'lane' => $permit->lane,
                    'send_key' => $key->value,
                    'body' => $body,
                    // ⛔ **A CHECK-IN IS TEXT AND NEVER CARRIES A PHOTO** (12461).
                    // ⚠️ **Here the value IS an input to the charge**, unlike the
                    // email site above: the credits spent are
                    // `ceil(characters / 160)` off the `body` written beside it.
                    'media_count' => 0,
                    'status' => OutreachStatus::Queued,
                    'created_at' => now(),
                ]),
                refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
            );

            $sent = $this->texter->sendToCustomer($permit, $body);

            if ($sent === null) {
                DB::rollBack();

                return CheckInAttempt::refused(SendRefusalReason::ChannelUnavailable);
            }

            $this->settlement->settle($message, $sent->providerMessageId, $sent->numberId);

            DB::commit();

            // ⚠️ **AFTER THE COMMIT, NOT NESTED INSIDE IT** — the email path's
            // own comment above carries the argument in full.
            $this->router->recordFixThenAskOffered($conversation);

            return CheckInAttempt::sent($message);
        } catch (QueryException $e) {
            DB::rollBack();

            if (SqlState::of($e) !== '23505') {
                throw $e;
            }

            return CheckInAttempt::duplicate();
        } catch (CreditMovementRefused) {
            DB::rollBack();

            return CheckInAttempt::refused(SendRefusalReason::InsufficientCredit);
        } catch (TextNotDeliverable $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
