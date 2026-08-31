<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\SiteChanges;

/**
 * Why a page did not go onto a tenant's website on this attempt.
 *
 * ⛔ **A CLOSED VOCABULARY RATHER THAN A SENTENCE, AND THE REASON IS THAT
 * SOMETHING HAS TO BRANCH ON IT** (1854's argument). Three callers already read
 * a refusal and must tell these apart: the job records it on the run row, the
 * lapsed-hold sweep retries some of them and abandons others, and slice J's
 * screen has to explain the difference between *"we are waiting for you"* and
 * *"we have stopped publishing until this is fixed"*. A free-form string makes
 * those three agree by accident.
 *
 * ⚠️ **NONE OF THESE IS A FAILURE**, and none of them fails the run. Every one
 * is a state the publishing pipeline is designed to reach on somebody else's
 * website — `29` §2 rule 43's surviving half: graceful degradation, never
 * hard-fail.
 */
enum PublishRefusal: string
{
    /**
     * `actuation.enabled` is off. It seeds false and names slice H — measure and
     * auto-rollback — as what it waits for.
     */
    case ActuationDisabled = 'actuation_disabled';

    /**
     * The plan is not entitled. `Subscriptions::isEntitled()`'s answer, which is
     * the same one the monthly credit grant uses.
     */
    case NotEntitled = 'not_entitled';

    /**
     * There is no confirmed website address to publish to, or no tier could be
     * derived for it. `ActuationTiers::for()` returning null is *"we do not know
     * yet"*, which 5543 is explicit is not T4.
     */
    case NoConfirmedWebsite = 'no_confirmed_website';

    /** The quality gate turned the page down. `16` §15.3 — fail → hold. */
    case GateHeld = 'gate_held';

    /**
     * The 24-hour AUTO-WITH-HOLD window is open and the owner has been told.
     * Nothing is wrong; the page publishes when it lapses.
     */
    case AwaitingHold = 'awaiting_hold';

    /**
     * ⛔ **NO NOTICE COULD BE DELIVERED, SO NOTHING PROCEEDED.**
     * AUTO-WITH-HOLD proceeds on silence, and silence is only consent if the
     * owner could have broken it — so the hold is never *opened*, which means it
     * can never lapse. This is the fail-closed arm, and it is the one mutation
     * `BUILD-PLAN` §2.11.4 names by hand.
     *
     * ⚠️ **IT COVERS THE ADVISORY TOO, AND ONE CASE RATHER THAN TWO IS
     * DELIBERATE** (5670). The T4 hand-off *is* the delivery — the whole rung is
     * "the owner gets the copy" — so an undeliverable advisory and an
     * undeliverable hold notice are the same fact: we could not tell them, so we
     * did not proceed. Two cases would invite a caller to treat one of them as
     * softer than the other.
     */
    case OwnerNoticeUndeliverable = 'owner_notice_undeliverable';

    /**
     * A volume cap for this location is spent (`16` §15.3, `29` §2 rule 30).
     * Self-releasing at the period boundary — a count, never a stored flag.
     */
    case VolumeCapReached = 'volume_cap_reached';

    /**
     * The daily self-audit stopped generation for this location and nothing has
     * cleared it. Unlike a cap, this waits on the owner fixing something.
     */
    case SelfAuditPaused = 'self_audit_paused';

    /** The owner replied to the hold notice. The page waits for a person now. */
    case OwnerHeld = 'owner_held';

    /**
     * ⛔ **THE ADAPTER READ NOTHING, SO RULE 32's SNAPSHOT DOES NOT EXIST.**
     * {@see SiteChanges::open()} would refuse this
     * anyway — the refusal is the mechanism and this case is how the pipeline
     * declines without throwing. A page that 404s, an origin that timed out and
     * a credential that has been revoked inside WordPress all land here, and
     * every one of them is an ordinary condition on somebody else's property.
     */
    case SiteUnreadable = 'site_unreadable';

    /**
     * The adapter reported that it did not write the change set.
     *
     * ⚠️ **THE `site_changes` ROW SURVIVES AND IS NOT STAMPED `applied_at`**,
     * which is 5527's whole point: a change set opened against a site that could
     * not be reached must stay distinguishable from one that landed.
     */
    case AdapterWriteFailed = 'adapter_write_failed';

    /**
     * ⛔ **`29` §2 RULE 36: THERE IS NO BYLINE, OR THE PAGE IT POINTS AT DOES NOT
     * ANSWER.** *"Auto-published content carries a real author byline linked to a
     * genuine About page"*, and the owner's ruling of 2026-08-20 makes it a gate
     * rather than a field: **no About page means no publish** (5720, 5729).
     *
     * ⚠️ **IT COVERS BOTH HALVES DELIBERATELY** — nothing confirmed, and a
     * confirmed page that has since stopped resolving. The owner is told the same
     * thing either way, because the action is the same: give us a page on your
     * own site that says who you are. Two cases would ask a screen to explain a
     * distinction the person cannot act on differently.
     *
     * ⚠️ **THE ADVISORY RUNG IS NOT GATED ON IT** (5745). T4 puts nothing on a
     * website — the owner is the publisher there — so refusing to hand them their
     * own copy over our byline rule would withhold the only tier that works today.
     */
    case NoAuthorByline = 'no_author_byline';

    /**
     * ⛔ **THE BOUND ADAPTER CANNOT WRITE A PAGE'S TITLE OR ITS BODY, SO THERE IS
     * NO PAGE TO PUBLISH** (5772).
     *
     * ⚠️ **IT IS NOT THE MISSING META DESCRIPTION AND MUST NOT BECOME IT.** A
     * field the adapter cannot write is dropped from the change set and
     * **recorded** on `site_changes.withheld_fields` with its reason — the page
     * still publishes, which is rule 43's graceful degradation. This case is the
     * floor beneath that: a page with no title and no body is not a degraded
     * page, it is an empty one, and putting it on somebody's website would be a
     * worse outcome than not publishing.
     */
    case PageFieldsNotWritable = 'page_fields_not_writable';

    /** The page is already on the site. Idempotence, not a refusal to act. */
    case AlreadyPublished = 'already_published';
}
