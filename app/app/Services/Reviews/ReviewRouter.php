<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AutopilotActionType;
use App\Enums\FixThenAskResponse;
use App\Enums\ReviewReofferTrigger;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Enums\RoutingDecision;
use App\Enums\TriageStatus;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Models\ReviewDestinationSetting;
use App\Models\TriageConversation;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Destinations\DestinationSettings;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What happens to one first-party review (`17` FPR-03).
 *
 * TWO THRESHOLDS THAT RUN IN OPPOSITE DIRECTIONS. This is the off-by-one this
 * class exists to get right, so it is stated rather than implied:
 *
 *   invited  iff  rating >= review_destinations.invite_threshold   (per destination)
 *   triaged  iff  rating <= autopilot_settings.triage_threshold    (per location)
 *
 * Neither comparison is a judgement call. `reviews.default_invite_threshold` is
 * documented as "the rating at or above which a customer is invited", and `17`
 * FPR-04b pins the triage boundary as inclusive by example: "a
 * 3-star sees triage and, if Trustpilot is enabled, Trustpilot." FPR-03 agrees
 * at the other end — "a 2-star review produces a triage conversation and no
 * Google invite".
 *
 * DESTINATIONS NEVER CONSULT EACH OTHER. Decision 111 split the column per
 * destination before any code existed, because Google may be asked selectively,
 * Trustpilot may be asked only if everybody is asked, and Yelp may not be asked
 * at all. One number could not say all three, and a loop that let one
 * destination's answer influence another's would put the disagreement back.
 *
 * SYNCHRONOUS, AND `RouteReviewJob` IS NOT BUILT. FPR-03 names a job. Routing
 * has no I/O to wait on — it compares integers against two tables — so
 * queueing would add nothing but a window in which an unhappy customer's review
 * exists and their recovery path does not. Closing that window is precisely
 * what this slice's build-failing test asserts, and decision 351 is this
 * codebase's own account of what an async claim strands when nobody re-claims
 * it. With routing in the submission transaction, a job would additionally have
 * zero callers: Google reviews are never routed and no second arrival path
 * exists until slice I's review sync. A job with no dispatch site is decision
 * 256's vacuous lint wearing a different hat. It arrives with slice I, wrapping
 * this service.
 *
 * WHAT THAT COSTS, STATED: no `automation_runs` row and no kill switch on
 * routing. The exhaustive record is the three columns this class writes plus the
 * audit entry, so nothing is unrecoverable. And a kill switch here protects
 * nobody — routing sends no message, spends nothing, and calls no vendor, so
 * throwing it would stop unhappy customers reaching triage while their reviews
 * kept arriving.
 *
 * ROUTING FAILS OPEN. DISPLAY FAILS CLOSED. Nothing here reads
 * `moderation_flags`, `sentiment` or `themes` — decision 348. If routing waited
 * on an AI verdict, an exhausted monthly cap could strand a 2-star review short
 * of triage, and the build-failing test would pass in CI, where nothing is
 * capped, and fail in production. Review::displayable() is where the AI outcome
 * is felt, and it independently requires a non-null `moderation_flags`, so a
 * status of `approved` written here can never publish unmoderated text.
 */
final class ReviewRouter
{
    /**
     * Used only when a location has no `autopilot_settings` row.
     *
     * MIRRORS THE COLUMN DEFAULT, and a test asserts the two agree by reading
     * the live schema rather than by trusting this constant. Provisioning now
     * seeds the row (see TenantProvisioner), so this covers locations created
     * before it did — and a review at one of those must still reach triage,
     * which is the whole rule.
     */
    public const DEFAULT_TRIAGE_THRESHOLD = 3;

    /**
     * How long an invite deferred by a pause is still worth sending.
     *
     * **3 days, from `43` §4.3 and D-184's `max_queue_age_days`** — the same
     * figure that document uses to stale-cancel a queued invite, applied to the
     * same question. It is cited rather than chosen: the brief for this slice said
     * not to invent one, and `43` has one.
     *
     * ⚠️ **ITS PROVENANCE IS WEAKER THAN A NORMAL CITATION AND THAT IS STATED
     * RATHER THAN GLOSSED.** `43` is blocked on the never-delivered `42`
     * (CLAUDE.md's document table), so it is not an adopted spec here — this is
     * the best-supported default available, not a requirement, and it is a
     * constant rather than a registry key on purpose. A `DefaultsRegistry` entry
     * would invite an operator to tune a number nobody has ruled on, and decision
     * 502's posture is that an unset figure is refused rather than defaulted. This
     * one is not unset; it is borrowed, which is a different thing and wants a
     * different treatment.
     *
     * Why a ceiling exists at all: the customer wrote something the same day they
     * visited, and an invitation arriving a week later asks them to recall a
     * business they have stopped thinking about. `43` I12's principle is that caps
     * pace sends rather than destroying them — a deferral without any ceiling is
     * the opposite failure, an invite that arrives whenever an owner happens to
     * come back from a month away.
     */
    public const MAX_DEFERRAL_DAYS = 3;

    public function __construct(
        private readonly DestinationSettings $destinations,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
        private readonly TenantPause $pause,
        private readonly TenantSuspension $suspension,
        private readonly ReplyGuardrails $guardrails,
        private readonly ReplyGenerator $generator,
    ) {}

    /**
     * Decide, persist, and return what was decided.
     *
     * @return ?RoutingDecision null when this review is not ours to route.
     */
    public function route(Review $review): ?RoutingDecision
    {
        // `29` §2 rule 1. Never held, hidden, approved, or moderated — and
        // routing is all four in one call.
        //
        // FIRST, AND AHEAD OF THE TENANT GUARD ON PURPOSE. "Not ours to route"
        // is true of a Google review regardless of who is acting, and requiring
        // an ambient tenant to say so would break slice I's importer, which is
        // the one caller that will hand this method Google rows in bulk.
        if ($review->source !== ReviewSource::FirstParty) {
            return null;
        }

        // ABOVE THE IDEMPOTENCY RETURN, NOT BELOW IT. The guard's own docblock
        // says this class refuses to route another tenant's review, and with the
        // early return first that was false for one class of input: a foreign
        // review that had already been routed was answered with its decision,
        // leaking one enum value with Tenancy::idOrFail() never called. Nothing
        // can load a foreign Review today and no write occurs either way — but
        // slice I's RouteReviewJob, hydrating a review id from a queue payload,
        // is the exact caller the guard names as its reason to exist.
        $this->assertBelongsToTenant($review);

        // One review is routed once. The live path this guards is
        // FeedbackSubmission's duplicate collapse (decision 350), which returns
        // the ORIGINAL review for a resubmission — already routed. Without this,
        // one customer's one complaint opens a second triage conversation.
        // Decision 378 depends on this *return*, not on its position.
        if ($review->routing_decision !== null) {
            return $review->routing_decision;
        }

        $location = $review->location;

        if (! $location instanceof Location) {
            // REACHABLE, and the tenant guard above is what now refuses it
            // first. `location_id` is NOT NULL with a foreign key, so no row can
            // exist without one — but the relation resolves through the global
            // scope, so a review whose `business_id` and `location_id` disagree
            // about the tenant reads its own location as null. That is exactly
            // the confound that made the first tenant-guard test vacuous, and
            // ReviewRoutingTest still depends on this branch being reachable.
            throw new InvalidArgumentException(
                'That review has no location, so there is nothing to route it against — '
                .'thresholds and the triage setting are both per location.',
            );
        }

        $settings = AutopilotSettings::query()
            ->where('location_id', $location->id)
            ->first();

        // THRESHOLDS APPLY UNCONDITIONALLY — decision 2074, and this is the
        // line the ruling was about. Until 2026-08-12 there was a
        // `$thresholdsApply = $settings?->gating_ack_at !== null` here, and the
        // acknowledgement it read had no writer for any tenant provisioning
        // created (290/377), so the *only* branch that ever ran was the ungated
        // one: every enabled destination was offered to every rating and the
        // platform default of 4 reached nobody. The owner removed the
        // acknowledgement on 2026-08-11 and the resolved threshold is now the
        // whole answer — `review_destinations.invite_threshold`, per
        // destination, seeded from `reviews.default_invite_threshold`.
        //
        // ⚠️ WHAT DOES *NOT* MOVE WITH IT, because 2077 says removing the
        // acknowledgement screen removed the explanation and not the rule:
        // Trustpilot is still pinned at 0 by `forcedThreshold()` — its terms
        // require every customer be invited — and Yelp still only ever exists
        // as a row somebody confirmed (1161). Both are decided in
        // `DestinationSettings`, above this line, and a threshold applied here
        // reads whatever those two settled.

        // `29` §2 rule 40: gate on the toggle BEFORE any side effect. Fails open
        // when there is no settings row, the same as auto_approve_5_star below —
        // a location created before provisioning seeded the row must not
        // silently stop inviting anybody.
        $solicits = (bool) ($settings->send_review_requests ?? true);

        // ⚠️ THE OWNER'S STOP, ON THE INVITE HALF ONLY — decision 381's
        // asymmetry, applied to a bigger switch. Asking a customer to post in
        // public is this application acting on the tenant's behalf, which is
        // precisely what a pause stops (`28` §9.5's "all sending + actuation
        // off"). **Triage is not**: it is service recovery, and suppressing it
        // would leave an unhappy customer with no path back to a business whose
        // owner pressed pause *because* something was going wrong. `29` §12.1
        // says a below-threshold customer always reaches triage, with no
        // exception carved for this or anything else.
        $paused = $this->pause->isCurrentTenantPaused();

        // ⚠️ OUR STOP, ON THE SAME HALF AND FOR A DIFFERENT REASON. `28` §9.5's
        // Suspend is compliance and abuse, and the abuse this control exists
        // for is review gating and fake-review solicitation — so asking a
        // customer to post in public is the *first* thing it has to stop, not
        // an incidental consequence.
        //
        // ⚠️ AND TRIAGE IS UNTOUCHED HERE TOO, which is decision 822's asymmetry
        // holding under a harder trigger. Service recovery is not solicitation,
        // `29` §12.1 says a below-threshold customer always reaches triage with
        // no exception carved for anything, and a customer of a business under
        // a compliance hold is *more* likely to need the path back, not less.
        // Suppressing it would also destroy the record rather than defer it,
        // which is decision 890 exactly.
        $suspended = $this->suspension->isCurrentTenantSuspended();

        if ($paused || $suspended) {
            $solicits = false;
        }

        $rating = (int) $review->rating;

        ['offered' => $snapshot, 'undeliverable' => $undeliverable] =
            $this->snapshot($location, $rating, $solicits);

        $invited = $snapshot !== [];

        // ⚠️ THRESHOLDS REACH THE INVITE TEST AND NOTHING ELSE, which is the one
        // asymmetry the acknowledgement's removal must not disturb. Triage is
        // not a gate — it is service recovery — and `29` §12.1 says a
        // below-threshold customer ALWAYS reaches triage, with no exception
        // carved for a threshold, a toggle, a pause or a suspension. It is the
        // surviving half of decision 114 and it is still build-failing.
        $triaged = $rating <= $this->triageThreshold($settings);

        $decision = match (true) {
            $invited && $triaged => RoutingDecision::InvitedAndTriaged,
            $invited => RoutingDecision::Invited,
            $triaged => RoutingDecision::Triaged,
            default => RoutingDecision::NoAction,
        };

        $metadata = [
            'rating' => $rating,
            'decision' => $decision->value,
            // array_map over a list, rather than array_column: the shape is
            // declared, so this stays a list<string> for the audit payload
            // without a cast (array_column returns array<mixed>).
            'destinations' => array_map(
                static fn (array $row): string => $row['destination'],
                $snapshot,
            ),
            'solicitation_enabled' => $solicits,

            // ⚠️ ITS OWN KEY, BECAUSE AN EMPTY SNAPSHOT NOW HAS FOUR CAUSES.
            // Decision 381 named the two non-obvious ones rather than leaving
            // them to be inferred from `[]`; a pause is the third of that kind
            // and the most confusing, because the location's own settings all
            // still say yes. Folding it into `solicitation_enabled` would make
            // the audit row report a toggle the owner never touched.
            'tenant_paused' => $paused,

            // ⚠️ ITS OWN KEY BESIDE THE PAUSE'S, NOT FOLDED INTO IT. An empty
            // snapshot has five causes now, and these two are the pair most
            // easily confused: both empty it, both leave every per-location
            // setting saying yes, and only one of them is something the owner
            // did. An audit row reporting "paused" for an account under a
            // compliance hold would send whoever reads it to the owner instead
            // of to the finding — decision 381's reasoning, one cause later.
            'tenant_suspended' => $suspended,
            'undeliverable_destinations' => $undeliverable,
            'triage_threshold' => $this->triageThreshold($settings),

            // WHICH OF THE TWO NUMBERS DECIDED — 'settings' when a row exists,
            // 'fallback' when none does. ⚠️ It does NOT distinguish "configured"
            // from "left at the default": the column is NOT NULL DEFAULT 3, so a
            // settings row always reports 'settings' whether anybody chose the
            // number or not. What it answers is whether this location had a
            // settings row at all, which is decision 377's own gap — every
            // location created before provisioning seeded one is on the constant.
            // Decision 372's argument ("why was this customer sent to Google"
            // stops being answerable once somebody changes a threshold) applies
            // identically to "why did this 3-star customer not reach triage".
            'triage_threshold_source' => $settings?->triage_threshold !== null ? 'settings' : 'fallback',
        ];

        DB::transaction(function () use ($review, $decision, $snapshot, $rating, $triaged, $settings, $location, $metadata, $paused, $suspended): void {
            $review->update($this->attributesFor($decision, $snapshot, $rating, $triaged, $settings, $paused || $suspended));

            if ($triaged) {
                $this->openTriage($review);
            }

            $this->recordDecision($review, $location, $decision, $metadata);
        });

        return $decision;
    }

    /**
     * Give a review the invite evaluation a pause, or a confirmed fix, means
     * it never fairly got.
     *
     * THE OTHER HALF OF DECISION 822, AND THE ONLY THING THAT MAKES A PAUSE A
     * DEFERRAL. A pause empties the snapshot and route() is once-only, so
     * without this the invite is destroyed rather than postponed. Re-dispatching
     * `SendReviewInviteJob` on resume does not help and is worth being explicit
     * about, because it is the obvious fix and it is a no-op: the sender's third
     * gate asks `ReviewInvites::offerFor()`, which reads the empty snapshot.
     *
     * ⚠️ **GENERALISED AT WAVE 38 LANE C (10590–10609) TO A SECOND CALLER, VIA
     * {@see ReviewReofferTrigger} RATHER THAN A REWRITE.** `$trigger` defaults
     * to {@see ReviewReofferTrigger::PauseResumed}, so `ReinviteDeferredReviews`
     * calling `reoffer($review)` is byte-for-byte what it always was and every
     * test written against this method before this wave is unmoved.
     * {@see ReviewReofferTrigger::FixThenAskConfirmed} is the fix-then-ask
     * check-in's own caller, from `recordFixThenAskResponse()` below — **read
     * that enum's docblock for what it admits that the pause path does not**;
     * this method does not repeat the argument.
     *
     * ⚠️ **IT RE-EVALUATES THE INVITE HALF AND NOTHING ELSE.** Triage is read back
     * from the existing decision, never re-derived and never re-opened — calling
     * openTriage() again would give one customer two recovery conversations, and
     * `route()`'s own once-only guard exists because that already happened once
     * with duplicate collapse (350/378). `status` is untouched for the same
     * reason: triage already won it, or auto-approval already applied, both at
     * original routing time and both correctly, because neither depends on
     * solicitation.
     *
     * ⚠️ **THE MARKER IS CLEARED WHATEVER THE OUTCOME**, including when the
     * recomputed snapshot is still empty. A tenant who paused and then switched
     * solicitation off, or disabled every destination, has answered the question;
     * leaving the marker set would re-sweep that review every fifteen minutes
     * forever, which is decisions 356 and 364's stranding bug wearing the opposite
     * costume. One evaluation is what was deferred, and one is what is owed. It
     * is a harmless no-op write on the fix-then-ask path, whose reviews never
     * carried the marker in the first place.
     *
     * Returns the decision now recorded, or null when this review is not one to
     * re-offer — not ours, not eligible for this trigger, or the tenant is
     * paused/suspended. The pause and suspension re-check is not
     * belt-and-braces on either trigger: a sweep or a customer's own click
     * enumerates or arrives one at a time, and an owner can press pause or a
     * compliance hold can land in between.
     */
    public function reoffer(
        Review $review,
        ReviewReofferTrigger $trigger = ReviewReofferTrigger::PauseResumed,
    ): ?RoutingDecision {
        // First, and ahead of the tenant guard, on route()'s own reasoning:
        // "not ours to route" is true of a Google review regardless of who is
        // acting. A Google review can never carry the marker — a CHECK now says
        // so — but the order is kept identical so the two methods cannot drift.
        if ($review->source !== ReviewSource::FirstParty) {
            return null;
        }

        $this->assertBelongsToTenant($review);

        // ⚠️ **THE GATE THIS TRIGGER GENERALISES, AND EACH ARM IS VERIFIED
        // INDEPENDENTLY RATHER THAN TRUSTED FROM THE CALLER** — 398's rule,
        // that an outer filter must not be the only thing making a gate true.
        // `PauseResumed`'s own marker is `invite_deferred_at`, unchanged.
        // `FixThenAskConfirmed` carries no such marker on the review itself —
        // it was never deferred — so it is checked against the one durable
        // record of the customer's own answer, `hasConfirmedFixThenAsk()`,
        // rather than trusted because a caller says so.
        if ($trigger === ReviewReofferTrigger::PauseResumed) {
            if ($review->invite_deferred_at === null) {
                return null;
            }
        } elseif (! $this->hasConfirmedFixThenAsk($review)) {
            return null;
        }

        if ($this->pause->isCurrentTenantPaused()) {
            return null;
        }

        // ⚠️ BOTH STOPS RE-CHECKED, FOR THE SAME REASON THE PAUSE ONE IS HERE:
        // the sweep enumerates candidates and then acts on them one at a time,
        // and a suspension can land in between. Re-offering inside a compliance
        // hold would put a Google button in front of a customer of a business we
        // had just stopped for putting Google buttons in front of customers.
        if ($this->suspension->isCurrentTenantSuspended()) {
            return null;
        }

        $location = $review->location;

        if (! $location instanceof Location) {
            throw new InvalidArgumentException(
                'That review has no location, so there is nothing to re-offer it against — '
                .'thresholds and the solicitation setting are both per location.',
            );
        }

        $settings = AutopilotSettings::query()
            ->where('location_id', $location->id)
            ->first();

        // Re-read now rather than reconstructed from the deferral. The owner may
        // have switched solicitation off *during* the pause, and that later choice
        // is the one to honour — decision 381's gate, evaluated at the moment the
        // invite would actually be offered.
        $solicits = (bool) ($settings->send_review_requests ?? true);

        $rating = (int) $review->rating;

        // ⚠️ **THE ONE LINE THAT DIFFERS BETWEEN THE TWO TRIGGERS, AND IT IS
        // NAMED RATHER THAN BURIED IN A CONDITIONAL DEEPER IN.** `bypassThreshold`
        // is true only for a confirmed fix — see `ReviewReofferTrigger`'s own
        // docblock for why re-running the ordinary `rating >= threshold`
        // comparison would answer exactly the same `false` it answered the day
        // this review was left, and why that answer is the one this whole
        // feature exists to overturn on the strength of a fresher signal.
        ['offered' => $snapshot, 'undeliverable' => $undeliverable] =
            $this->snapshot(
                $location,
                $rating,
                $solicits,
                bypassThreshold: $trigger === ReviewReofferTrigger::FixThenAskConfirmed,
            );

        $invited = $snapshot !== [];

        // Read back, never re-derived. See above.
        $triaged = $review->routing_decision?->triaged() ?? false;

        $decision = match (true) {
            $invited && $triaged => RoutingDecision::InvitedAndTriaged,
            $invited => RoutingDecision::Invited,
            $triaged => RoutingDecision::Triaged,
            default => RoutingDecision::NoAction,
        };

        DB::transaction(function () use ($review, $location, $decision, $snapshot, $undeliverable, $rating, $solicits, $invited, $trigger): void {
            $review->update([
                'routing_decision' => $decision,
                'routed_destinations' => $snapshot,
                'invite_deferred_at' => null,
            ]);

            // Only the invite feed item, and only when there is one. TriageOpened
            // and OwnerActionNeeded were already filed at original routing time;
            // re-filing them would tell the owner twice about one conversation.
            if ($invited) {
                $this->activity->record(AutopilotActionType::ReviewInviteOffered, (int) $location->id, [
                    'review_id' => (int) $review->id,
                    'destinations' => array_map(
                        static fn (array $row): string => $row['destination'],
                        $snapshot,
                    ),
                ]);
            }

            // ⚠️ ITS OWN ACTION NAME PER TRIGGER, NOT A SECOND `review.routed`.
            // The audit trail has to be able to answer "why does this review
            // have an invite it did not have last week", and a duplicate
            // `review.routed` reads as the router having run twice — which is
            // precisely the thing its once-only guard forbids.
            // `review.invite_after_fix_confirmed` is its own name rather than a
            // reused `review.invite_resumed`, because a reader of one tenant's
            // audit log asking "why was this below-threshold customer invited"
            // must not have to cross-reference `invite_deferred_at`'s history to
            // learn the answer was never a pause at all.
            $this->audit->record(
                $trigger === ReviewReofferTrigger::FixThenAskConfirmed
                    ? 'review.invite_after_fix_confirmed'
                    : 'review.invite_resumed',
                'autopilot',
                $review,
                [
                    'rating' => $rating,
                    'decision' => $decision->value,
                    'destinations' => array_map(
                        static fn (array $row): string => $row['destination'],
                        $snapshot,
                    ),
                    'solicitation_enabled' => $solicits,
                    'undeliverable_destinations' => $undeliverable,
                ],
            );
        });

        return $decision;
    }

    /**
     * Give up on a deferral that has gone cold, and say so.
     *
     * `43` §4.3's `ReviewInviteStaleSweep` and its event 211, which cancels a
     * queued invite older than `max_queue_age_days` — **3 days** by that
     * document's own settled figure (D-184, §11's summary line). The figure is
     * cited rather than invented, and the citation is worth its caveat: `43` is
     * blocked on the never-delivered `42` and is not an adopted spec here, so this
     * is the most defensible default available rather than a requirement. `43`'s
     * `D-1xx` numbers are not ours and are deliberately not resolved against them.
     *
     * ⚠️ **CANCELLING IS RECORDED, BECAUSE IT IS THE ONE PATH THAT LOSES
     * SOMETHING.** Everything else here restores an invite; this one decides a
     * customer will not be asked, days after they wrote something nice, and does
     * it with nobody watching. An unaudited version would be indistinguishable
     * from the defect this whole slice exists to fix — a marker that quietly
     * became null and an invite that never went.
     *
     * The snapshot is left exactly as the pause wrote it. Recomputing it here
     * would record destinations that were never offered to anybody.
     */
    public function cancelDeferredInvite(Review $review): bool
    {
        if ($review->source !== ReviewSource::FirstParty) {
            return false;
        }

        $this->assertBelongsToTenant($review);

        $deferredAt = $review->invite_deferred_at;

        if ($deferredAt === null) {
            return false;
        }

        DB::transaction(function () use ($review, $deferredAt): void {
            $review->update(['invite_deferred_at' => null]);

            $this->audit->record('review.invite_stale_cancelled', 'autopilot', $review, [
                'deferred_at' => $deferredAt->toIso8601String(),
                'deferred_for_hours' => (int) $deferredAt->diffInHours(now()),
                'max_deferral_days' => self::MAX_DEFERRAL_DAYS,
            ]);
        });

        return true;
    }

    /**
     * Which destinations this rating reaches, and what it was judged against.
     *
     * ⚠️ THE ROW CARRIES THE NUMBER AND NO LONGER CARRIES A `threshold_applied`
     * FLAG (decision 2662). That key existed because the acknowledgement made
     * the answer false for every tenant alive, and a snapshot holding only the
     * threshold would have hidden the fail-open from whoever read the row a year
     * later. With 2074 there is no second answer: a threshold in this list was
     * applied, or the row would not be here. A permanently-true boolean beside
     * it is decoration, and decoration in a stored audit surface is worse than
     * absent — it invites a reader to believe something varies.
     * ⚠️ Rows written before 2026-08-12 still carry the key. Nothing reads it;
     * `ReviewInvites::routedDestinations()` takes `destination` alone.
     *
     * NO URL IS STORED. Decision 308: Google's link is derived from the
     * location's place id at read time so it cannot drift when a listing merge
     * changes the id. Slice F resolves links through
     * DestinationSettings::linkFor() and reads only the destination set here.
     *
     * SOLICITATION IS SUPPRESSED HERE AND TRIAGE IS UNTOUCHED, and that
     * asymmetry is the same one the threshold has, for the same reason.
     * Whether to ask customers for public reviews is the owner's choice and
     * `29` §2 rule 40 requires it be honoured before any side effect. Whether an
     * unhappy customer reaches a recovery path is not a choice at all — `29`
     * §12.1 makes it a build-failing test with no exception carved for a toggle.
     * So `send_review_requests = false` empties this list and route() still
     * opens the conversation.
     *
     * AN EMPTY LIST NOW HAS THREE CAUSES, which is why both non-obvious ones
     * are named in the audit row rather than left to be inferred: nothing
     * qualified, the owner switched solicitation off (`solicitation_enabled`),
     * or every enabled destination's link could not be derived
     * (`undeliverable_destinations`). A reader looking at [] a year from now has
     * no other way to tell them apart.
     *
     * @return array{
     *     offered: list<array{destination: string, threshold: int}>,
     *     undeliverable: list<string>,
     * }
     */
    private function snapshot(Location $location, int $rating, bool $solicits, bool $bypassThreshold = false): array
    {
        if (! $solicits) {
            return ['offered' => [], 'undeliverable' => []];
        }

        $offered = [];
        $undeliverable = [];

        foreach ($this->destinations->offeredFor($location) as $setting) {
            /** @var ReviewDestinationSetting $setting */

            // THE READ PATH FAILS CLOSED INDEPENDENTLY OF THE WRITE PATH, which
            // is DestinationSettings' own stated lesson (314–316) applied to its
            // consumer. enable() refuses Google without a confirmed place id
            // (decision 312), but offeredFor() filters on `enabled` alone — so a
            // listing merge, a repair script or an admin screen that clears
            // google_place_id leaves an enabled row whose link cannot be
            // derived. Recording it as offered would hand slice F a setting
            // whose linkFor() throws, taking down the whole post-submit page
            // instead of dropping one button.
            if ($setting->destination->linkIsDerived()
                && trim((string) $location->google_place_id) === '') {
                $undeliverable[] = $setting->destination->value;

                continue;
            }

            $threshold = (int) $setting->invite_threshold;

            // ⚠️ **`$bypassThreshold` IS THE ONE THING `ReviewReofferTrigger::FixThenAskConfirmed`
            // ADMITS THAT NOTHING ELSE ON THIS PLATFORM DOES** (wave 38 lane C,
            // 10590–10609) — see that enum's own docblock for the argument in
            // full. Every other line in this loop is unmoved: Trustpilot's
            // forced threshold of `0` never triggers this branch at all (it is
            // never `> $rating`), Yelp is still reachable only through the
            // confirmed-listing path, and the undeliverable check above still
            // runs first and still wins.
            if (! $bypassThreshold && $rating < $threshold) {
                continue;
            }

            $offered[] = [
                'destination' => $setting->destination->value,
                'threshold' => $threshold,
            ];
        }

        return ['offered' => $offered, 'undeliverable' => $undeliverable];
    }

    /**
     * The rating at or below which a recovery conversation opens.
     */
    private function triageThreshold(?AutopilotSettings $settings): int
    {
        return (int) ($settings->triage_threshold ?? self::DEFAULT_TRIAGE_THRESHOLD);
    }

    /**
     * The three routing columns, plus `status` when routing has a view on it.
     *
     * TRIAGE WINS THE STATUS COLUMN. Both outcomes co-occur and `status` holds
     * one value, so one of them has to; triage does, because it is the state
     * that needs a human. The invite is not lost by that choice — slice F
     * renders from `routed_destinations`, never from `status`.
     *
     * AUTO-APPROVAL IS KEYED ON THE RATING, NOT ON THE INVITATION.
     * `auto_approve_5_star` governs display, and a 5-star review at a tenant
     * with nothing enabled — NoAction, the default state of every fresh tenant —
     * is still a 5-star review the owner wants shown. Keying it on `Invited`
     * would leave every review at a newly provisioned tenant at `pending` with
     * nothing in the feed to explain it.
     *
     * WRITING `approved` HERE CANNOT PUBLISH ANYTHING UNMODERATED.
     * Review::displayable() independently requires `flagged_at IS NULL` and a
     * non-null `moderation_flags` for first-party rows, so this is one of three
     * conditions (decision 345). That separation is why routing may fail open
     * while display fails closed.
     *
     * `approved_by` IS AN ACTOR LABEL, NOT A USER ID — the column's own
     * migration comment says so, because autopilot approves most reviews.
     *
     * @param  list<array{destination: string, threshold: int}>  $snapshot
     * @return array<string, mixed>
     */
    private function attributesFor(
        RoutingDecision $decision,
        array $snapshot,
        int $rating,
        bool $triaged,
        ?AutopilotSettings $settings,
        bool $stopped,
    ): array {
        $attributes = [
            'routing_decision' => $decision,
            'routed_destinations' => $snapshot,
            'routed_at' => now(),

            // ⚠️ THE MARKER THAT MAKES A PAUSE A DEFERRAL RATHER THAN A DELETION.
            // Without it, `$solicits = false` above empties the snapshot, this row
            // records that nothing was offered, and the once-only guard at the top
            // of route() means nothing ever looks again — so the invite is not
            // waiting for a resume, it is gone. `ReviewInvites` reads this
            // snapshot as the authority, so re-dispatching SendReviewInviteJob
            // after a resume changes nothing at all.
            //
            // ⚠️ KEYED ON THE TWO TEMPORARY STOPS, NEVER ON `$snapshot === []`. An
            // empty snapshot has five causes (see the audit metadata above) and
            // three of them are not deferrals: a below-threshold rating, every
            // destination disabled, and `send_review_requests = false` — the last
            // being a standing choice the owner made, not a temporary stop.
            // Marking those would turn a preference into a backlog that fires the
            // moment somebody changes their mind.
            //
            // ⚠️ THE SUSPENSION IS THE SECOND ONE, AND IT HAD TO BE ADDED HERE OR
            // IT WOULD HAVE DESTROYED INVITES EXACTLY AS THE PAUSE ONCE DID (890).
            // A suspension can last weeks; every review submitted during one would
            // otherwise be recorded as having been offered nothing, permanently,
            // with the account looking correct on the day it was lifted.
            //
            // Set for every review routed during either stop, including ones that
            // would not have been invited anyway. The sweep recomputes and clears
            // the marker either way; the column means "the invite half was never
            // fairly evaluated", not "an invite is owed".
            'invite_deferred_at' => $stopped ? now() : null,
        ];

        if ($triaged) {
            $attributes['status'] = ReviewStatus::InTriage;

            return $attributes;
        }

        if ($rating === 5 && ($settings->auto_approve_5_star ?? true)) {
            $attributes['status'] = ReviewStatus::Approved;
            $attributes['approved_at'] = now();
            $attributes['approved_by'] = 'autopilot';

            // ⚠️ SLICE G ADDED THIS LINE, AND WITHOUT IT THE WIDGET FEED SHOWS
            // NOTHING. `display_on_website` defaults false and had no writer at
            // all until ReviewDisplay; the feed reads it on top of
            // displayable(), so an auto-approved 5-star — the most common
            // review this product will ever produce — would be approved,
            // unflagged, analysed, and still invisible. Decision 358's shape,
            // caught before it shipped rather than after. A test pins it.
            $attributes['display_on_website'] = true;
        }

        return $attributes;
    }

    /**
     * How many triage conversations reached `resolved` in a window.
     *
     * ⚠️ **HERE BECAUSE THE CHOKEPOINT LINT REFUSED THE READER, AND THE LINT WAS
     * RIGHT TO.** `28` §3.3's "unhappy customers recovered" is counted by
     * `ProofNumbers`, which has no business touching `TriageConversation`
     * directly — this service opens them and is the one file the lint admits.
     * Decision 624's rule, and its precedent exactly: `Impersonation::history()`
     * and `DefaultsRegistry::changesBy()` exist for the same reason, and neither
     * allowlist was widened. Adding `Services/Proof/ProofNumbers.php` to that
     * list would have granted it the *write* surface too, which is broader than
     * anything a count needs.
     *
     * ⚠️ **IT COUNTS `resolved`, WHICH IS NARROWER THAN §3.3's DEFINITION.** That
     * paragraph says triage that reached resolved *"with the customer confirmed
     * satisfied"*, and nothing in this schema records the customer's
     * confirmation — the status is set by whoever worked the conversation. The
     * caller states that to the owner rather than implying the stronger claim;
     * see `ProofNumbers::definitions()`.
     *
     * ⚠️ **"WHOEVER WORKED THE CONVERSATION" NAMED NOBODY UNTIL 2026-08-12, AND
     * THIS SENTENCE IS WHY THE DEFECT SURVIVED** (2689). No code in `app/` could
     * write any status but `Open`, so this method counted a state nothing could
     * reach and returned zero for every tenant that has ever existed — under a
     * docblock whose reassuring clause read as though a person were doing it.
     * CLAUDE.md's 314–316 shape, in a comment about a count rather than about a
     * guard. {@see self::recordTriageOutcome()} is that party now.
     *
     * Bucketed on `updated_at` because resolution is when the state was reached,
     * while `created_at` is when the customer complained: a conversation opened
     * in March and resolved in May is a May recovery. An undated row is counted
     * only in all-time, for the reason `ProofNumbers` gives.
     */
    public function recoveredCount(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $query = TriageConversation::query()->where('status', TriageStatus::Resolved);

        if ($from !== null && $to !== null) {
            $query->whereNotNull('updated_at')->whereBetween('updated_at', [$from, $to]);
        }

        return $query->count();
    }

    /*
    |--------------------------------------------------------------------------
    | The recovery queue — decision 2689's feature
    |--------------------------------------------------------------------------
    |
    | ⚠️ THESE SEVEN METHODS ARE ON A CLASS CALLED `ReviewRouter` AND THAT IS A
    | DECISION RATHER THAN A DRIFT (2700; two more at T176 P15, 4351).
    | `Architecture\ReviewsTest` holds
    | `TriageConversation` to exactly one file — this one — and decision 906
    | already answered the widening question when `ProofNumbers` needed a count:
    | the allowlist was not widened, `recoveredCount()` was added here instead,
    | on 624's rule and on `Impersonation::history()`'s precedent. A
    | `TriageRecovery` service would need the same exemption, and its own half of
    | the lint would then be an exemption relaxed to admit the code that
    | prompted it — 906's closing sentence exactly. The seam cost one method
    | then and seven now, which is still cheaper than a lint that is green by
    | construction.
    |
    | ⚠️ WHAT IS *NOT* HERE, AND WHERE IT WENT: `owner_notified_at`. See
    | {@see self::recordTriageOutcome()}.
    */

    /**
     * Every recovery conversation in the current tenant, newest complaint first.
     *
     * ⚠️ **TERMINAL ROWS ARE RETURNED TOO, AND THAT IS THE COMPLIANCE HALF.**
     * The obvious queue lists `Open` and nothing else — and this table hangs off
     * `reviews`, where 2075's build-failing rule is that **every rating is
     * captured and kept, and none is ever deleted, suppressed or hidden**. A
     * screen that dropped a conversation the moment it was worked would be the
     * only surface an owner has for the feedback nobody else sees, quietly
     * showing less than was left. The caller groups them; nothing filters them
     * away.
     *
     * Eager-loads the review and the customer: the card renders the rating, the
     * words and the person, and one query per row is an N+1 on the screen most
     * likely to have a long list.
     *
     * @return Collection<int, TriageConversation>
     */
    public function recoveryQueue(): Collection
    {
        Tenancy::idOrFail();

        return TriageConversation::query()
            ->with(['review', 'customer'])
            // ⚠️ `id`, NOT `created_at`. That column is nullable on forty of
            // forty-three tables here and Postgres sorts NULL *first* on DESC —
            // the defect `Architecture\ConventionsTest` exists for, and the one
            // `ReviewReplies::latestForReviews()` names in its own docblock.
            ->orderByDesc('id')
            ->get();
    }

    /**
     * How many conversations are still waiting on somebody.
     *
     * The nav badge's number. Asks `TriageStatus::isOpen()` rather than
     * spelling the comparison, so the badge and the screen's own grouping
     * cannot disagree — `FollowUps`' stated trap, where a badge saying 3 sits
     * above a list showing 2.
     */
    public function openTriageCount(): int
    {
        Tenancy::idOrFail();

        return TriageConversation::query()
            ->where('status', TriageStatus::Open)
            ->count();
    }

    /**
     * Load one conversation under the current tenant scope, or null.
     *
     * The global scope and RLS are what make this safe, and the null return is
     * what the caller turns into a 404 — a foreign id is indistinguishable from
     * a deleted one on purpose, because telling them apart tells a stranger
     * that a row exists.
     */
    public function findConversation(int $conversationId): ?TriageConversation
    {
        Tenancy::idOrFail();

        return TriageConversation::query()->find($conversationId);
    }

    /**
     * The recovery conversation one review opened, or null (T176 P15).
     *
     * ⚠️ **HERE FOR THE SAME REASON THE FIVE ABOVE ARE** (2700, 906, 624).
     * `Architecture\ReviewsTest` holds `TriageConversation` to this one file,
     * and `DraftRecoveryOutreachJob` needs to know whether a review has a
     * conversation and whether that conversation already carries a draft.
     * Widening the allowlist to admit a job would hand it the whole write
     * surface of the table for two reads, which is the exemption-relaxed-to-
     * admit-the-code-that-prompted-it shape 906 refused.
     *
     * `first()` rather than `sole()`: `route()` is once-only and `reoffer()`
     * deliberately never re-opens, so one review has at most one conversation —
     * but a second row is a data fault, not a reason to throw on the path whose
     * only job is to draft the owner a message.
     */
    public function conversationForReview(int $reviewId): ?TriageConversation
    {
        Tenancy::idOrFail();

        return TriageConversation::query()
            ->where('review_id', $reviewId)
            ->orderBy('id')
            ->first();
    }

    /**
     * Store the win-back message the owner will send by hand (T176 P15).
     *
     * ⛔ **THIS IS A DRAFT AND IT IS NEVER A SEND, AND THE POSTURE IS UNCHANGED
     * BY IT** (4350–4355). Before P15 the recovery path reached an owner with
     * five buttons and a notes box and nothing to say to the customer; after it,
     * the same screen also carries a message they can copy. **No channel is
     * derived, no consent record is read, no suppression list is checked and no
     * arbiter is asked** — because nothing is sent, and every one of those is a
     * precondition of the slice that eventually does send it.
     *
     * ⚠️ **NO ACTIVITY FEED ITEM AND NO AUDIT ENTRY.** The feed is for automated
     * actions the owner should know about, and `TriageOpened` plus
     * `OwnerActionNeeded` already told them about this conversation at routing
     * time — a third item saying a draft is ready would be two notifications for
     * one event, on the screen they are already being sent to. `automation_runs`
     * is the exhaustive record here, exactly as it is for `GenerateReplyJob`,
     * and it carries `from_model` and the fallback reason. ⚠️ The **audit** log
     * is skipped for a different reason and it is the sharper one: this writes
     * no decision and moves no state anybody counts, and an audit entry carrying
     * the draft would put a second copy of words about a customer's complaint in
     * the one table an erasure request must be able to honour (`recordDecision()`
     * gives the rule).
     *
     * `updated_at` moves, which `recoveredCount()` buckets on. That is harmless
     * and worth naming: it only ever counts rows already at `Resolved`, and a
     * conversation cannot be resolved before it has been opened and drafted.
     *
     * ⛔ **AND IT IS THE SECOND GUARDRAIL PASS, WHICH DID NOT EXIST UNTIL 4468.**
     * `ReplyGenerator::draftRecovery()` checks **before** `applyVariables()`, and
     * `draft()`'s own comment says why that is safe there: *"CHECKED BEFORE
     * EXPANSION, NOT AFTER, AND THE WRITER IS WHAT CHECKS AFTER"* — the writer
     * being `ReviewReplies::recordSuggestion()`. `draftRecovery()` copied the
     * first half of that design and **no writer supplied the second**, so 1743's
     * case walked straight through on the new path: executed,
     * `allowsRecoveryOutreach('… would you leave us a {{business_name}}?',
     * ['Review Ltd'])` returned **true** and expanded to a review ask. A comment
     * describing a two-layer design is 314–316's failure the moment only one
     * layer is built.
     *
     * ⚠️ **THE EXEMPTIONS ARE RE-DERIVED FROM THE DATABASE, NEVER TAKEN FROM THE
     * CALLER** (1857). A chokepoint that believes its caller is not a
     * chokepoint: the generator passes the `Business` and `Location` its job
     * already loaded, and this re-reads both by key from the conversation's own
     * review. Threading them through `ReplyDraft` would let a caller hand this
     * method the exemption set that makes its own text pass.
     *
     * ⚠️ **IT FALLS BACK RATHER THAN THROWING, WHICH IS THE OPPOSITE OF
     * `recordSuggestion()` AND DELIBERATE.** That method throws because
     * `GenerateReplyJob` catches it and re-files the safe template — a caller
     * that can act. Here the only thing to do on refusal *is* file the platform
     * template, and doing it inside the writer means a second caller cannot
     * arrive later and forget to catch (1854's warning, answered the other way).
     * The refusal is recorded as its own fallback reason so `automation_runs` and
     * the row disagree loudly rather than quietly.
     */
    public function recordOutreachDraft(
        TriageConversation $conversation,
        ReplyDraft $draft,
    ): TriageConversation {
        $this->assertConversationBelongsToTenant($conversation);

        [$text, $reason] = $this->guardedRecoveryText($conversation, $draft);

        $conversation->outreach_draft = $text;
        $conversation->outreach_draft_at = now();
        $conversation->outreach_draft_fallback_reason = $reason;
        $conversation->save();

        return $conversation;
    }

    /**
     * The draft's words, or the platform template when they do not pass here.
     *
     * @return array{string, ?string} text, fallback reason
     */
    private function guardedRecoveryText(
        TriageConversation $conversation,
        ReplyDraft $draft,
    ): array {
        $review = Review::query()->find($conversation->review_id);
        $location = $review === null ? null : Location::query()->find($review->location_id);
        $business = Business::query()->find(Tenancy::idOrFail());

        if ($this->guardrails->allowsRecoveryOutreach(
            $draft->text,
            ReplyGuardrails::tenantValues($business, $location),
        )) {
            return [$draft->text, $draft->fallbackReason];
        }

        // ⚠️ THE TEMPLATE IS BUILT FROM THE SAME TWO READS, NOT FROM THE DRAFT.
        // A refused draft is text nobody may reuse any part of, including the
        // greeting.
        return [
            $this->generator->recoverySafeTemplate(
                businessName: (string) ($business->name ?? ''),
                reviewerLabel: $this->generator->reviewerLabel($review?->reviewer_name),
            ),
            'guardrail_blocked_at_writer',
        ];
    }

    /**
     * Record what came of a recovery conversation.
     *
     * **THE WRITER `TriageStatus` NEVER HAD** (2689). Until this existed the
     * only status any code in `app/` could write was `Open`, so
     * `ProofNumbers`' owner-facing *"customers recovered"* counted `Resolved`
     * rows that nothing could produce and was structurally zero — under a
     * docblock on this very class saying *"the status is set by whoever worked
     * the conversation"*, which named a party that did not exist.
     *
     * ⚠️ **`resolution` IS REQUIRED FOR A RECOVERY AND OPTIONAL OTHERWISE**
     * (2702). `28` §3.3 says the number counts triage that reached resolved
     * *"with the customer confirmed satisfied"* and nothing in this schema
     * records the customer's confirmation — so the strongest thing behind that
     * number is the sentence the person who made the call wrote about what they
     * did. A resolve with no note would put a number on the owner's Home screen
     * whose entire evidence is that somebody clicked a button, which is the
     * modeled number §3.3's integrity rule forbids wearing a different hat. The
     * other three outcomes take a note and do not demand one: "I left two
     * voicemails" is worth having and "nothing more to do" is complete without
     * elaboration.
     *
     * ⚠️ **ANY STATE MAY MOVE TO ANY OTHER, AND NOTHING IS DELETED.** A customer
     * who was unreachable on Tuesday and rings back on Friday is an ordinary
     * event, and refusing the correction would leave the owner with a permanent
     * lie on a card they cannot edit. Every move writes both sides to
     * `audit_log`, which is append-only — so the history lives in the record
     * that cannot be rewritten rather than in the row that can. That is also
     * why `resolution` is *kept* when a conversation reopens: it is what was
     * tried, and the audit entry carries the replacement if one is written.
     *
     * ⚠️ **`owner_notified_at` IS DELIBERATELY NOT WRITTEN HERE, AND THIS IS
     * THE THIRD TIME THIS FILE HAS REFUSED IT** (2703). `openTriage()` refuses
     * it below because no messaging layer existed; the reason here is stronger
     * and different. The column is `17` TRIAGE-03's — *"notify owner via SMS +
     * email … set `owner_notified_at`"* — and its readers, when they exist,
     * will use it to decide **not to tell somebody twice**. An owner who found
     * this conversation themselves on a screen has not been notified, and
     * stamping the column because they clicked would permanently suppress the
     * urgent notification TRIAGE-03 owes them for the very conversations they
     * flagged as serious. Decision 290's mistake with a different column name,
     * and the failure would be silent and one-way. **Its writer is the
     * notification path on the messaging lane, not this screen.**
     *
     * @param  ?string  $resolution  what the owner did about it, trimmed; null
     *                               or blank leaves whatever is already stored
     * @param  string  $actor  `user:14` or `autopilot` — never a user id alone
     *
     * @throws InvalidArgumentException
     */
    public function recordTriageOutcome(
        TriageConversation $conversation,
        TriageStatus $to,
        ?string $resolution,
        string $actor,
    ): TriageConversation {
        $this->assertConversationBelongsToTenant($conversation);

        $resolution = trim((string) $resolution);

        if ($to->isRecovery() && $resolution === '') {
            throw new InvalidArgumentException(
                'Say what you did to win them back before marking this one won back — '
                .'it is the only record behind the number on your home screen.',
            );
        }

        return DB::transaction(function () use ($conversation, $to, $resolution, $actor): TriageConversation {
            $statusBefore = $conversation->status;
            $resolutionBefore = $conversation->resolution;

            $conversation->status = $to;

            // Blank leaves the stored note alone. A reopen is not an erasure of
            // what was tried, and the three optional outcomes are reachable with
            // an empty box.
            if ($resolution !== '') {
                $conversation->resolution = $resolution;
            }

            // ⚠️ ESCALATION TAKES THE CONVERSATION OFF AUTOPILOT, WHICH IS
            // TRIAGE-02's *"AI stops generating once escalated"* — the one line
            // of that ticket this slice can honestly perform. It is a write
            // rather than a second button because the two are one act: an owner
            // marking feedback serious has already said a human is handling it,
            // and a flag they could set without the other would be a state where
            // the ticket's own rule was off.
            if ($to === TriageStatus::Escalated) {
                $conversation->ai_paused = true;
            }

            // ⚠️ **`resolved_at`, WAVE 38 LANE C (10590–10609) — WHEN, NOT
            // `updated_at`'s "WHEN WAS THIS ROW LAST TOUCHED".** The
            // fix-then-ask sweep has to run its delay from the moment
            // resolution happened, and `updated_at` moves on every edit to
            // `resolution`, every `ai_paused` toggle and every later re-open —
            // none of which is a second resolution. Written every time this
            // method reaches `Resolved`, including a second time: "ANY STATE
            // MAY MOVE TO ANY OTHER" above means a conversation can resolve,
            // reopen and resolve again, and the second fix earns its own
            // delay rather than inheriting the first one's clock.
            if ($to === TriageStatus::Resolved) {
                $conversation->resolved_at = now();
            }

            $conversation->save();

            // ⚠️ ONE ACTION NAME FOR FIVE DESTINATIONS, WHICH IS THE OPPOSITE OF
            // `reply.invite_resumed`'s CALL (2704). That one took its own name
            // because a second `review.routed` would read as the router having
            // run twice — a claim about a *different act*. This is one act, a
            // recorded outcome, and the destination is data. Splitting it would
            // mean "what happened to this conversation" needed five queries and
            // a reader who knew all five names to be sure they had them all.
            $this->audit->recordChange(
                'triage.outcome_recorded',
                $actor,
                before: [
                    'status' => $statusBefore->value,
                    'resolution' => $resolutionBefore,
                ],
                after: [
                    'status' => $to->value,
                    'resolution' => $conversation->resolution,
                ],
                entity: $conversation,
            );

            // ⚠️ THE FEED ITEM IS FOR THE RECOVERY AND FOR NOTHING ELSE (2705).
            // `29` §2 rule 29's feed is for *automated* actions, and all five of
            // these are the owner's own clicks — so on the face of it none
            // belongs. The recovery is the exception because it is not a click,
            // it is the outcome this product exists to produce: it is the only
            // one that moves a number on their Home screen, and a number that
            // changed with nothing in the history to explain it is the thing
            // `28` §3.3's integrity rule is trying to prevent. The other three
            // record that this one did not work, and a feed of "couldn't reach
            // them" is `AutopilotJob`'s stated trap — an entry whose only honest
            // title makes the feed worse to read. `audit_log` has all five.
            if ($to->isRecovery()) {
                $review = $conversation->review;

                $this->activity->record(
                    AutopilotActionType::TriageResolved,
                    $review instanceof Review ? (int) $review->location_id : null,
                    [
                        // ⚠️ IDS ONLY. The resolution note is the owner's words
                        // about a customer's complaint and the feed payload must
                        // be safe if it is ever broadcast — `recordDecision()`'s
                        // rule below, and the audit log is the record an erasure
                        // request has to be able to honour without hunting a
                        // second copy.
                        'triage_conversation_id' => (int) $conversation->id,
                        'review_id' => (int) $conversation->review_id,
                    ],
                );
            }

            return $conversation;
        });
    }

    /**
     * Every resolved recovery conversation whose fix-then-ask delay has
     * passed and which has never been asked — wave 38 lane C (10590–10609).
     *
     * ⚠️ **THE THREE PREDICATES ARE THE WHOLE POLICY AND EACH IS NAMED FOR
     * WHY IT IS THERE.** `status = Resolved` reads the *current* state — a
     * conversation that resolved and reopened is not asked while it is
     * reopened, however old `resolved_at` still reads. `resolved_at` not null
     * and at or before the cutoff is the delay itself, on `resolved_at`
     * rather than `updated_at` for `recordTriageOutcome()`'s own reason.
     * `fix_then_ask_offered_at` null is "never asked" — this method is the
     * sweep's read half and the write half is `recordFixThenAskOffered()`
     * below, so a review swept twice before the first send commits is not
     * this method's problem: the sender's own `SendKey` and duplicate guard
     * are what a race between two sweeps needs, not a second predicate here.
     *
     * `$limit` on `ReinviteDeferredReviews::PER_BUSINESS_LIMIT`'s own
     * precedent: a cap per business per sweep rather than a chunked walk, so
     * a backlog drains across sweeps instead of one sweep trying to hold an
     * unbounded result set in memory.
     *
     * @return Collection<int, TriageConversation>
     */
    public function resolvedConversationsAwaitingCheckIn(CarbonInterface $resolvedBefore, int $limit = 100): Collection
    {
        Tenancy::idOrFail();

        // Eager-loads the review: the sweeper reads `location_id` off it to
        // dispatch the job, and one query per row is an N+1 on a sweep that
        // may hold up to $limit rows.
        return TriageConversation::query()
            ->with('review')
            ->where('status', TriageStatus::Resolved)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $resolvedBefore)
            ->whereNull('fix_then_ask_offered_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Record that the fix-then-ask check-in actually went — wave 38 lane C
     * (10590–10609).
     *
     * ⚠️ **THE SENDER'S OWN CALL, AND THE SENDER DOES NOT TOUCH THIS TABLE
     * DIRECTLY.** `Architecture\ReviewsTest` holds `TriageConversation`
     * reachable from exactly this file, on 624's precedent and its own
     * neighbours in this class; `RecoveryCheckInSender` is handed the
     * `TriageConversation` its caller already loaded (through
     * {@see self::findConversation()}) and asks this method to record the
     * send rather than writing the column itself. Called only past a send
     * that actually happened — a refused attempt writes nothing here, which
     * is what leaves the conversation eligible for `resolvedConversationsAwaitingCheckIn()`'s
     * next pass rather than silently retired by a switch that was off for an
     * hour.
     */
    public function recordFixThenAskOffered(TriageConversation $conversation): void
    {
        $this->assertConversationBelongsToTenant($conversation);

        DB::transaction(function () use ($conversation): void {
            $conversation->fix_then_ask_offered_at = now();
            $conversation->save();

            $this->audit->record('triage.fix_then_ask_offered', 'autopilot', $conversation);
        });
    }

    /**
     * Record the customer's own answer, and act on it — wave 38 lane C
     * (10590–10609).
     *
     * ⛔ **THIS IS PHASE 2's BRANCH, AND BOTH ARMS ARE WRITTEN, NEVER
     * INFERRED.** *Yes* re-evaluates the invite through
     * {@see self::reoffer()} with {@see ReviewReofferTrigger::FixThenAskConfirmed}
     * — the same recompute the pause path already uses, generalised rather
     * than duplicated — and returns the {@see Review} to invite when that
     * recompute actually offers a destination, so the caller dispatches
     * `App\Jobs\SendConfirmedFixInviteJob` (never `SendReviewInviteJob` — see
     * that class's own docblock for why the two cannot share an idempotency
     * key) rather than this class sending anything itself. *No* is not silence
     * and is not nowhere: it puts
     * the conversation back at {@see TriageStatus::Open} through
     * {@see self::recordTriageOutcome()}, which is the recovery queue this
     * customer is already known to, rather than a state this schema has never
     * modelled. **A non-answer reaches neither arm** — this method is never
     * called for one, because there is nothing to call it with; silence is
     * the "ask anyway after a while" outcome `CLAUDE.md`'s low-rating gate
     * exists to prevent, and the guard against it is that nothing here ever
     * treats the passage of time as an answer.
     *
     * ⚠️ **IDEMPOTENT ON A SECOND VISIT TO AN ALREADY-ANSWERED LINK.** A mail
     * client re-fetching a page it already POSTed to, or a person pressing
     * back and clicking again, must not dispatch a second review invite or
     * reopen a conversation a second time. `fix_then_ask_responded_at` is the
     * guard, read before anything is written — the same "answers the same way
     * twice" posture `Content\HoldGrowthPageController`'s own docblock
     * states for the identical shape of problem.
     *
     * ⚠️ **NEITHER TRANSACTION HERE IS NESTED INSIDE THE OTHER, DELIBERATELY.**
     * The response is recorded and committed first; `recordTriageOutcome()`
     * and `reoffer()` each open their own transaction afterwards, run
     * sequentially rather than nested — 3880's own lesson, that a nested
     * `DB::transaction()` does not contain a Postgres deadlock the way its own
     * docblock once claimed.
     *
     * @return ?Review the review to invite, when — and only when — the answer
     *                 was "yes" and the recompute actually offers a
     *                 destination; null otherwise, including every refusal
     *                 this method's own gates make
     */
    public function recordFixThenAskResponse(
        TriageConversation $conversation,
        FixThenAskResponse $response,
        string $actor,
    ): ?Review {
        $this->assertConversationBelongsToTenant($conversation);

        if ($conversation->fix_then_ask_responded_at !== null) {
            return null;
        }

        // Defensive rather than reachable in the shipped flow: nothing mints
        // this page's URL before the check-in has actually been sent, on
        // 398's own rule that an outer filter (here, the fact that a link
        // exists at all) must not be the only thing making a gate true.
        if ($conversation->fix_then_ask_offered_at === null) {
            return null;
        }

        DB::transaction(function () use ($conversation, $response, $actor): void {
            $before = $conversation->fix_then_ask_response?->value;

            $conversation->fix_then_ask_response = $response;
            $conversation->fix_then_ask_responded_at = now();
            $conversation->save();

            $this->audit->recordChange(
                'triage.fix_then_ask_answered',
                $actor,
                before: ['fix_then_ask_response' => $before],
                after: ['fix_then_ask_response' => $response->value],
                entity: $conversation,
            );
        });

        $review = Review::query()->find($conversation->review_id);

        if ($response === FixThenAskResponse::NotResolved) {
            if ($review instanceof Review) {
                $this->recordTriageOutcome(
                    $conversation,
                    TriageStatus::Open,
                    resolution: 'The customer answered the fix-then-ask check-in: not resolved yet.',
                    actor: $actor,
                );

                // ⚠️ **THE FEED ITEM `recordTriageOutcome()` DOES NOT FILE ON
                // ITS OWN.** That method only files an activity row when
                // `$to->isRecovery()`, which `Open` is not — correctly, for
                // every OTHER caller, none of which reopens a conversation the
                // owner had already closed as won. This one does, and an
                // owner needs to see it: their "won back" just came apart.
                $this->activity->record(AutopilotActionType::RecoveryNotYetFixed, (int) $review->location_id, [
                    'triage_conversation_id' => (int) $conversation->id,
                    'review_id' => (int) $review->id,
                ]);

                $this->activity->record(AutopilotActionType::OwnerActionNeeded, (int) $review->location_id, [
                    'review_id' => (int) $review->id,
                    'reason' => 'fix_then_ask_not_resolved',
                ]);
            }

            return null;
        }

        if (! $review instanceof Review) {
            return null;
        }

        $this->activity->record(AutopilotActionType::RecoveryConfirmedFixed, (int) $review->location_id, [
            'triage_conversation_id' => (int) $conversation->id,
            'review_id' => (int) $review->id,
        ]);

        $decision = $this->reoffer($review, ReviewReofferTrigger::FixThenAskConfirmed);

        return $decision?->invited() === true ? $review : null;
    }

    /**
     * Whether this review's customer has confirmed, through the fix-then-ask
     * check-in, that the thing they complained about was fixed — wave 38
     * lane C (10590–10609).
     *
     * ⚠️ **THE INDEPENDENT VERIFICATION {@see self::reoffer()}'S
     * `FixThenAskConfirmed` ARM ASKS FOR, RATHER THAN TRUSTING ITS CALLER.**
     * A review carries no marker of its own for this — unlike
     * `invite_deferred_at`, which the pause path can read straight off the
     * review — because the confirmation lives on the conversation, not the
     * review. `exists()` rather than loading the row: this method answers one
     * boolean and the caller that needs the conversation already has it.
     */
    private function hasConfirmedFixThenAsk(Review $review): bool
    {
        return TriageConversation::query()
            ->where('review_id', $review->getKey())
            ->where('fix_then_ask_response', FixThenAskResponse::Confirmed)
            ->exists();
    }

    /**
     * Record that a person, rather than automation, is handling this one.
     *
     * `17` TRIAGE-04's *"dashboard toggle setting `ai_paused = true`"* and its
     * resume half, which is why one method takes a boolean rather than two
     * methods naming the directions.
     *
     * ⛔ **NOTHING IN `app/` READS `ai_paused` TODAY, AND NO COPY ON THE SCREEN
     * CLAIMS IT PREVENTS ANYTHING** (2706). Its reader is TRIAGE-01's AI triage
     * loop, which is unbuilt — nothing generates a turn, nothing sends on this
     * path, and nothing appends to `transcript` (942). A writer with no reader
     * is decision 272's shape inverted and it is worth stating plainly rather
     * than leaving for somebody to discover; what it must **not** become is
     * CLAUDE.md's 314–316 failure, a screen promising *"nothing automated will
     * contact this customer"* on top of a mechanism that does not exist. So the
     * column records the instruction, the screen says only who is handling it,
     * and the claim is written the day the loop reads it.
     */
    public function setTriageTakeover(
        TriageConversation $conversation,
        bool $paused,
        string $actor,
    ): TriageConversation {
        $this->assertConversationBelongsToTenant($conversation);

        return DB::transaction(function () use ($conversation, $paused, $actor): TriageConversation {
            $before = (bool) $conversation->ai_paused;

            $conversation->ai_paused = $paused;
            $conversation->save();

            // Its own action name, unlike the five outcomes above, because this
            // genuinely is a different act: it says who is handling a
            // conversation, never what came of it, and the two answer different
            // questions about the same row.
            $this->audit->recordChange(
                'triage.takeover_changed',
                $actor,
                before: ['ai_paused' => $before],
                after: ['ai_paused' => $paused],
                entity: $conversation,
            );

            return $conversation;
        });
    }

    /**
     * Refuse to work another tenant's recovery conversation.
     *
     * `assertBelongsToTenant()`'s reasoning, on the other model. A conversation
     * loaded under the global scope is already the right tenant — one hydrated
     * from a queue payload or through `withoutGlobalScope()` is not, and this
     * writes a status the owner's proof numbers count and an audit entry filed
     * against the acting business.
     */
    private function assertConversationBelongsToTenant(TriageConversation $conversation): void
    {
        if ((int) $conversation->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That recovery conversation belongs to another tenant. Recording an outcome '
            .'writes a status one business\'s "customers recovered" counts, under another '
            .'business\'s name.',
        );
    }

    /**
     * The recovery path a below-threshold customer always reaches.
     *
     * `customer_id` IS NULLABLE AND OFTEN NULL. An anonymous submission has no
     * customer at all (FeedbackSubmission::upsertCustomer() returns null with no
     * phone and no email), and that person still gets a conversation — the
     * on-screen half of the recovery path is what reaches them, which
     * BUILD-PLAN §2.6.5 question 4 settled.
     *
     * `owner_notified_at` STAYS NULL. No messaging layer exists until row 4, and
     * the field's only job is to record that somebody was told. A value written
     * before that is true destroys it — decision 290's mistake with a different
     * column name.
     *
     * ⚠️ `channel` IS THE COLUMN DEFAULT, NOT A DECISION. Nothing here knows how
     * this person can be reached: the customer may have no phone, no email and
     * no consent record at all, and an anonymous submission has no customer
     * row. Leaving the column at `'sms'` while refusing to write
     * `owner_notified_at` for exactly that reason is the two halves of one
     * method disagreeing, so it is written down rather than papered over. **Row
     * 4 must derive the channel from the customer's own consent records, never
     * read it from here** — an implementer iterating conversations and calling
     * permit($customer, $conversation->channel) would ask for SMS on every one,
     * and the follow-up would silently reach nobody. Bounded in practice only
     * because row 4's senders take a SendPermit by type (decision 285).
     */
    private function openTriage(Review $review): TriageConversation
    {
        return TriageConversation::query()->create([
            'review_id' => $review->id,
            'customer_id' => $review->customer_id,
            'status' => TriageStatus::Open,
            'transcript' => [],
        ]);
    }

    /**
     * Refuse to route another tenant's review.
     *
     * Decision 301's shape. A Review loaded under the global scope is already
     * the right tenant — but one loaded with withoutGlobalScope(), or hydrated
     * from a queue payload, is not, and this class writes to `reviews` and
     * `triage_conversations` with `business_id` taken from ambient context. That
     * is the *wrong tenant* case CLAUDE.md says RLS cannot catch.
     */
    private function assertBelongsToTenant(Review $review): void
    {
        if ((int) $review->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That review belongs to another tenant. Routing writes a decision and can '
            .'open a recovery conversation, both filed against the acting business — so '
            .'this would record one tenant\'s decision under another tenant\'s name.',
        );
    }

    /**
     * What the owner sees, and what an investigation can reconstruct.
     *
     * NEITHER PAYLOAD CARRIES CUSTOMER CONTENT, and the mechanism is worth
     * stating correctly because stating it wrongly invites the inverse mistake.
     * ⚠️ Activity metadata is **not** broadcast today —
     * ActivityRecorded::broadcastWith() returns an explicit allowlist of `id`,
     * `action`, `title` and `needs_owner`, and a test pins those keys. So the
     * rule here is not "it goes on the wire": it is that this payload must be
     * safe *if it ever does*, that the feed is owner-visible on every staff
     * screen, and that the audit log is the record an erasure request has to be
     * able to honour — a comment copied into it is a second place the words
     * live. The review id is enough to find everything else, under scope.
     *
     * THREE FEED ITEMS AT MOST, AND EACH IS A DIFFERENT CLAIM.
     * ReviewInviteOffered says where the customer was pointed; TriageOpened says
     * a recovery conversation exists; OwnerActionNeeded says somebody has to do
     * something about it, and is what stops that conversation sitting open
     * because nobody read the first.
     *
     * ⚠️ NoAction IS DELIBERATELY SILENT, on AutopilotJob's own precedent:
     * "every action is visible" and "the feed is worth reading" are in tension,
     * and an automation whose only honest title is "checked something and found
     * nothing" makes the feed worse. NoAction is the default state of every
     * fresh tenant (decision 312), so feeding it would fill an owner's history
     * with nothing happening. The audit row below is the exhaustive record here,
     * the way automation_runs is there — and it is written for every decision
     * including this one.
     *
     * ⚠️ `thresholds_applied` IS GONE FROM THIS PAYLOAD (2662). It was here
     * because decision 290 made it false for every tenant alive, so an entry
     * recording only the destinations would have read as though somebody had
     * configured this. After 2074 it is true on every row this method will ever
     * write, and a key that cannot vary teaches a reader nothing while
     * suggesting it might.
     *
     * @param  array{
     *     rating: int,
     *     decision: string,
     *     destinations: list<string>,
     *     solicitation_enabled: bool,
     *     undeliverable_destinations: list<string>,
     *     triage_threshold: int,
     *     triage_threshold_source: string,
     * }  $metadata
     */
    private function recordDecision(
        Review $review,
        Location $location,
        RoutingDecision $decision,
        array $metadata,
    ): void {
        if ($decision->invited()) {
            // The destination keys and nothing else. No rating: TriageOpened
            // carries one because the owner is being asked to act on how bad it
            // was, and an invite is not about the number.
            $this->activity->record(AutopilotActionType::ReviewInviteOffered, (int) $location->id, [
                'review_id' => (int) $review->id,
                'destinations' => $metadata['destinations'],
            ]);
        }

        if ($decision->triaged()) {
            $this->activity->record(AutopilotActionType::TriageOpened, (int) $location->id, [
                'review_id' => (int) $review->id,
                'rating' => (int) $review->rating,
            ]);

            $this->activity->record(AutopilotActionType::OwnerActionNeeded, (int) $location->id, [
                'review_id' => (int) $review->id,
                'reason' => 'triage_opened',
            ]);
        }

        $this->audit->record('review.routed', 'autopilot', $review, $metadata);
    }
}
