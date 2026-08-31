<?php

declare(strict_types=1);

namespace App\Enums;

use App\Notifications\UrgentMessageEscalated;
use App\Services\Agent\AgentThreadStates;

/**
 * What an activity-feed row records (DATA-MODEL §5.1 `autopilot_action_type`).
 *
 * Every automated action writes exactly one of these to the feed (`29` §2
 * rule 29). The set churns as automations land — which is precisely why it is
 * a PHP enum over a string column rather than a database enum: Postgres enum
 * values can never be dropped or reordered once added.
 */
enum AutopilotActionType: string
{
    case ReviewApproved = 'review_approved';
    case ReviewHeld = 'review_held';
    /**
     * A reply of the owner's is on their Google listing.
     *
     * ⛔ **IT SHIPPED WITH THE STAGE 0 SCHEMA AND HAD NO WRITER UNTIL 2026-08-21**
     * (7134, 7222). 1688 parked it as *"reserved for a real publish"* while
     * `PostReplyJob` could only hand off; `execute()` began genuinely publishing
     * on 2026-08-11 and nothing joined the two up — so a draft, an approval and
     * every failure reached the owner's feed and **the success did not**. Its
     * one writer is `ReviewReplies::markPosted()`, which is also the only writer
     * of `posted`, so the feed row and the audit row come from one decision made
     * under one row lock (6820).
     *
     * ⚠️ **THE CLAIM IS THE VENDOR'S ACKNOWLEDGEMENT, NOT A SIGHTING.** Google
     * moderates replies and this relay carries no verdict back (2332), so
     * *"Replied to a review"* is past tense about what we did and says nothing
     * about what is on the listing this minute. `ReplyPublicationState` is where
     * the finer states live.
     */
    case ReplyPosted = 'reply_posted';
    case ReplyFailed = 'reply_failed';
    /**
     * An AI draft is waiting on the owner (or on the unavailable post path).
     *
     * ⚠️ NOT ReplyPosted. Filing a draft under ReplyPosted would tell the owner
     * a public reply went out when nothing reached Google.
     *
     * ⛔ **THE REASON THIS GAVE WAS "posting to Google is slice J's GBP-04 half
     * and is handoff-only until GbpClient gains a confirmed create-reply
     * endpoint", AND THAT STOPPED BEING TRUE ON 2026-08-11 — CORRECTED
     * 2026-08-21 (7225).** `PostReplyJob::execute()` publishes, the owner set
     * `gbp.zernio_enabled` to `true` in production on 2026-08-21, and
     * {@see self::ReplyPosted} now has a writer. **The rule above survives the
     * reason whole**: a draft is not a publication whatever the transport can
     * do, which is the half that was load-bearing. ⚠️ It is worth reading twice
     * because the stale clause was doing work it should not have been — it read
     * as an argument for `ReplyPosted` having no writer at all, and that is how
     * a case with a title went three months without one.
     */
    case ReplySuggested = 'reply_suggested';

    /**
     * Configuration approved a reply and queued it to publish (1744).
     *
     * ⚠️ NOT ReplySuggested, AND THE OWNER'S FEED COULD NOT TELL THEM APART
     * UNTIL THIS EXISTED. `ReviewReplies::recordSuggestion()` filed
     * `ReplySuggested` for both outcomes it can write — a row that went to
     * `Suggested` and is waiting for the owner to read it, and a row that went
     * to `Approved` with `approved_by = 'system:auto_post'`, which is a decision
     * already taken and a reply queued to publish under the owner's name on a
     * public Google listing. Those are the two halves of the whole slice, and
     * *"A reply draft is ready for you"* describes only the first. `29` §2 rule
     * 42's feed is the surface the second one is accountable to: an owner who
     * later disputes a published reply is asking their feed what happened, and a
     * feed that recorded it as a draft awaiting them answers the wrong question.
     *
     * ⚠️ AND NOT ReplyPosted, WHICH IS ABOUT WHEN THE ROW IS FILED RATHER THAN
     * ABOUT WHAT THE TRANSPORT CAN DO. This is written at the moment of the
     * decision, before `PostReplyJob` has run at all, so the title says
     * *waiting*, not *sent*. `needsOwner()` is false: configuration decided, and
     * there is nothing here for the owner to do. When posting genuinely fails,
     * `markPostingUnavailable` files `OwnerActionNeeded`, which is the case that
     * does want them — and when it succeeds, `markPosted()` files
     * {@see self::ReplyPosted}, so both outcomes of this row's queue now reach
     * the feed.
     *
     * ⛔ **THIS SAID "Nothing has reached Google — `PostReplyJob` handoffs until
     * `GbpClient` gains a confirmed create-reply endpoint" AND THAT STOPPED
     * BEING TRUE ON 2026-08-11 — CORRECTED 2026-08-21 (7225).** A reply queued
     * here may well be on the listing minutes later. **The title is unchanged
     * and is still correct**, because it describes the instant this row records;
     * what was wrong was the reason, and the reason was the thing a reader would
     * have carried forward.
     */
    case ReplyAutoApproved = 'reply_auto_approved';

    case GooglePostPublished = 'google_post_published';
    case ReviewRequestSent = 'review_request_sent';
    case HubPageUpdated = 'hub_page_updated';
    case ProfileOptimized = 'profile_optimized';
    case BoostScoreUpdated = 'boost_score_updated';
    case QrGenerated = 'qr_generated';
    case PdfGenerated = 'pdf_generated';
    /**
     * A customer was shown where they could post publicly.
     *
     * ⚠️ NOT ReviewRequestSent, AND THE DISTINCTION IS THE WHOLE REASON THIS
     * CASE EXISTS. That one is row 4's *delivery* event — a message left this
     * system and reached somebody. This one is a routing decision: the customer
     * finished the feedback form and the destinations they cleared were put in
     * front of them. Nothing was sent, nobody was contacted, and no consent was
     * needed. Reusing the other case would put a send in the owner's history
     * that never happened, and would make "how many invites did we send" answer
     * a different question than it asks.
     */
    case ReviewInviteOffered = 'review_invite_offered';

    /**
     * A customer tapped one of those destinations and left for the platform.
     *
     * ⚠️ THE THIRD CASE IN A DELIBERATE LADDER, AND THE LAST RUNG WE CAN HONESTLY
     * CLIMB. ReviewInviteOffered says the buttons were shown; this says one was
     * pressed; nothing says a review was written, because **no destination
     * platform provides a completion callback** (`24` §2.3.2, decision 113). The
     * only true signal that a review landed is review sync observing it after
     * the fact — slice I's work, a different table, and never inferred from here.
     *
     * That is why this case exists rather than reusing ReviewInviteOffered: an
     * owner asking "did showing them Google actually do anything" is asking a
     * question the offer alone cannot answer and the click can, and squashing
     * the two would lose the only engagement signal this flow produces. It is
     * equally why it is not ReviewApproved or anything in that family.
     *
     * Its title is overridden per destination — see ReviewDestination::
     * openedTitle(), where the wording is pinned by a test.
     */
    case ReviewDestinationOpened = 'review_destination_opened';

    /**
     * A Google review landed on the listing and we recorded it.
     *
     * ⚠️ NOT ReviewApproved. That case is a *decision* the owner (or auto-
     * approval) made about a first-party review. Google rows are ingested facts
     * — decision 386 writes `approved` as bookkeeping so displayable() can see
     * them, and filing that under ReviewApproved would tell the owner they had
     * approved something they never saw. Slice I's only honest sentence.
     */
    case GoogleReviewReceived = 'google_review_received';

    /**
     * GO AI EZ support opened the owner's account to fix something.
     *
     * ⚠️ ACT-AS ONLY, AND THE ASYMMETRY IS `28` §9.4's RATHER THAN A SHORTCUT.
     * A view-only session reaches `audit_log` and stops there; this case is
     * reserved for the mode that can change something, because the feed is the
     * owner's plain-language history of *things that happened to their
     * business* and "somebody read your settings" is not one. A feed that
     * announces every support glance trains the owner to ignore it, and the
     * entry that matters — we changed something — arrives in the same stream
     * they have learned to skip.
     *
     * The transparency `28` requires for view-only is a different mechanism:
     * every page view is logged, and the owner can be told on request.
     */
    case SupportSessionOpened = 'support_session_opened';

    /**
     * Support changed something on the owner's behalf.
     *
     * `28` §9.4: *"the owner always sees that we were there and what we did."*
     * The title is overridden per write with the thing that changed and the
     * ticket reference, exactly as ReviewDestinationOpened is overridden per
     * destination — the fallback below names no thing, so an unnamed write
     * reads as the gap it is rather than as a fact nobody recorded.
     */
    case SupportMadeAChange = 'support_made_a_change';

    case TriageOpened = 'triage_opened';
    case TriageResolved = 'triage_resolved';

    /**
     * A resolved recovery conversation was asked, days later, whether it
     * actually got sorted — T546 §37.3(1), wave 38 lane C (10590–10609).
     *
     * ⚠️ **PHASE 4's VISIBILITY REQUIREMENT, AND WHY IT IS A FEED ROW RATHER
     * THAN A GATE PASSING SILENTLY.** This is the first time this platform
     * asks a previously-unhappy customer for anything, so an owner needs to
     * see that it happened and not have to trust that it did. Filed by
     * `SendRecoveryCheckInJob::activityAction()` only past an actual send,
     * on `SendReviewInviteJob`'s own precedent — a refused attempt writes no
     * feed row for the same reason a refused invite does not.
     */
    case RecoveryCheckInSent = 'recovery_check_in_sent';

    /**
     * A customer answered the fix-then-ask check-in: yes, it is fixed —
     * T546 §37.3(1), wave 38 lane C (10590–10609).
     *
     * ⚠️ **DISTINCT FROM `ReviewInviteOffered`, WHICH MAY OR MAY NOT FOLLOW
     * IT.** This case says what the customer told this platform; a review
     * being offered off the back of it is a separate fact `reoffer()` files
     * separately, and the two can come apart — every destination disabled, or
     * `send_review_requests` switched off, still leaves this one true.
     */
    case RecoveryConfirmedFixed = 'recovery_confirmed_fixed';

    /**
     * A customer answered the fix-then-ask check-in: not yet — T546 §37.3(1),
     * wave 38 lane C (10590–10609).
     *
     * ⚠️ **FILED BESIDE `OwnerActionNeeded`, NOT INSTEAD OF IT** — this
     * conversation was `Resolved` and `recordFixThenAskResponse()` has just
     * put it back at `Open`, which is the owner's "won back" quietly coming
     * apart. `recordTriageOutcome()`'s own activity write only fires for a
     * recovery outcome, which `Open` is not, so nothing else in this
     * codebase would tell the owner this happened.
     */
    case RecoveryNotYetFixed = 'recovery_not_yet_fixed';

    case CallMissed = 'call_missed';
    case VoicemailReceived = 'voicemail_received';

    /**
     * A person took a conversation over from the assistant — T176 rail 4's latch.
     *
     * ⚠️ **NOT AN AUTOMATED ACTION, AND IT BELONGS IN THE FEED ANYWAY** —
     * `CallRoutingChanged`'s precedent, for its reason. The latch is the single
     * event that decides whether a customer's next message is answered by a
     * model or by nobody until somebody notices, and an owner scrolling their
     * history needs to see the moment their assistant went quiet on a thread.
     *
     * ⛔ **THE NAME SAYS THE OPPOSITE OF THE TITLE, AND THAT IS THE TRAP —
     * 2026-08-22 (7340, 7346).** Read as English, `AssistantHandedOver` is *the
     * assistant handed it over*; the title is *"You took over a conversation"*,
     * which is the **owner's** act. So the case a developer picks by name at
     * {@see AgentThreadStates::escalate()} — where the
     * assistant genuinely does hand a thread over — is this one, and it files a
     * sentence claiming the owner did something they have not done. That is what
     * happened, and it shipped for three months.
     * ⚠️ **The case is deliberately NOT renamed** (7346): the value
     * `assistant_handed_over` is on an append-only column and cannot move, so a
     * rename leaves the name and the stored value disagreeing — one confusion
     * traded for another, with no precedent anywhere in this enum. What defuses
     * the trap instead is {@see self::AssistantAskedYouToTakeOver} existing with
     * a name that cannot be read the other way, and `ActivityTest`'s
     * method-level enumeration putting *"`latch()` files this"* in front of a
     * reviewer.
     *
     * ⛔ **"THREE CASES RATHER THAN ONE `AssistantStateChanged`" — AND THERE ARE
     * FOUR SILENCES. CORRECTED 2026-08-22 (7340–7342); BOTH READINGS KEPT.**
     * The paragraph read: *"The three silences have three different remedies — a
     * person is answering, a person handed it back, a long conversation ran out
     * of turns — which is `AgentThreadStatus`' own argument for keeping its four
     * apart."* **It cites a four-case enum and then counts three.** The fourth
     * silence is `AgentThreadStatus::Escalated`, it had no word of its own, and
     * `escalate()` filed **this** one — so the argument for keeping the silences
     * apart was written on the case that was answering for two of them.
     * ⚠️ **The reasoning survives whole and is what mints the fourth**: one case
     * with the reason buried in metadata would make the feed unable to say which
     * happened, and the feed is prose an owner reads rather than a payload.
     */
    case AssistantHandedOver = 'assistant_handed_over';

    /** A person handed a conversation back to the assistant (rail 4's re-arm). */
    case AssistantHandedBack = 'assistant_handed_back';

    /**
     * The assistant stopped and the thread is the owner's now — rail 9's
     * *escalated*, the fourth silence, and the one that had no word of its own.
     *
     * ⛔ **THE OPPOSITE OF {@see self::AssistantHandedOver}, WHICH IS WHAT IT WAS
     * FILED AS UNTIL 2026-08-22** (7340). `AgentThreadStates::escalate()`'s own
     * docblock, three lines above the write, said so: *"DISTINCT FROM THE LATCH,
     * AND THE DIFFERENCE IS WHO ACTED. A latch is a person starting to answer;
     * this is the assistant stopping. They read identically on a status column
     * and need opposite sentences."* It then filed the latch's sentence —
     * *"You took over a conversation"* — while writing the actor to `audit_log`
     * on the next line as the literal `'assistant'`. **The owner had done
     * nothing and their own history told them they had**, which is `29` §12.1's
     * fabricated activity.
     *
     * ⛔ **`needsOwner()` IS TRUE, AND THAT IS THE SECOND HALF THE WRONG
     * SENTENCE COST** (7343). `AssistantHandedOver` is `false` for a good reason
     * — a person who took a thread over is the person who acted, and flagging
     * their own act as needing them is noise — so an escalated thread reached
     * the feed with **no "Needs you" pill** while a member of the public waited.
     * The sibling {@see self::AssistantReachedItsTurnLimit} is `true` for
     * exactly this situation, and the two halves could never have been fixed
     * together on one case.
     *
     * ⚠️ **DISTINCT FROM {@see self::AssistantReachedItsTurnLimit} BY THE
     * REMEDY, WHICH IS THIS ENUM'S OWN TEST** (7341). A cap is the assistant
     * working correctly and re-arming genuinely resumes it. An escalation is the
     * assistant declining, and re-arming may not help at all: on
     * `AgentTurns::refuseForHealthTenant()` it definitively does not — *"the
     * next inbound message asks this question again and is refused again"*.
     * **Read it yourself** is the remedy here; **hand it back** is the remedy
     * there.
     *
     * ⚠️ **ONE CASE FOR THREE ESCALATION REASONS, ARGUED RATHER THAN ASSUMED**
     * (7342). The three callers are a needs-owner draft, a covered-entity
     * refusal and an urgent-terms match, and {@see self::AssistantFollowedUpOnce}'s
     * *"one case, not one per reason"* is the rule: a second case has to
     * distinguish something, and **the owner does the same thing in all three**
     * — open the thread and answer it. The urgent one additionally sends
     * {@see UrgentMessageEscalated}, *"a mail rather than a
     * feed row precisely because it has to arrive rather than be scrolled to"*,
     * so the one reason whose discrimination matters already has a louder
     * surface than this vocabulary could give it.
     */
    case AssistantAskedYouToTakeOver = 'assistant_asked_you_to_take_over';

    /**
     * Rail 3's turn cap fired and the thread went to the owner.
     *
     * ⚠️ **A CAP IS THE ASSISTANT WORKING CORRECTLY**, so the title says what
     * happened to the owner rather than naming a limit they did not set.
     */
    case AssistantReachedItsTurnLimit = 'assistant_reached_its_turn_limit';

    /**
     * The assistant offered one customer the feedback page — T176 skill 13, P12.
     *
     * ⛔ **THE FEED SAYS *ASKED*, NEVER *GOT*.** No destination platform gives us
     * a completion callback (113), so the one fact this event may claim is that
     * a link went. A title reading "got you a review" would be the thing 113
     * forbids, printed in the one place an owner is most likely to believe it.
     */
    case AssistantAskedForAReview = 'assistant_asked_for_a_review';

    /**
     * The assistant sent one silent thread its single follow-up — skill 14, P11.
     *
     * ⚠️ **ONE CASE, NOT ONE PER REASON.** There is only ever one nudge per
     * thread, so there is nothing for a second case to distinguish.
     */
    case AssistantFollowedUpOnce = 'assistant_followed_up_once';

    /**
     * A thread finished and the owner was sent its one-line outcome — rail 9, P13.
     *
     * ⛔ **DISTINCT FROM {@see self::AssistantReachedItsTurnLimit}, WHICH IS A
     * HANDOVER RATHER THAN AN ENDING.** A capped thread is still live and still
     * needs a person; a closed one is done. Merging them would tell an owner a
     * conversation had finished when somebody is waiting on them.
     *
     * ⛔ **AND FROM {@see self::AssistantAskedYouToTakeOver} FOR THE SAME
     * REASON, WHICH THIS DOCBLOCK DID NOT SAY — CORRECTED 2026-08-22 (7460).**
     * Rail 9 names **three** endings and the paragraph above forbids the case
     * by name on one of them. The fourth silence had no word of its own until
     * 7340, so when this was written there was nothing to name; the sentence
     * was never updated when there was, and *"a capped thread is still live"*
     * is true of an escalated thread word for word.
     *
     * ⛔ **THE PROHIBITION WAS BEING BROKEN ON BOTH OF THEM WHILE IT WAS
     * WRITTEN HERE.** `SummariseClosedThreadJob::activityAction()` returned this
     * case without reading the thread's status, on all three of rail 9's
     * endings — so an escalated thread read *"Your assistant asked you to take a
     * conversation"* and then *"Finished a conversation for you"*, a capped one
     * the same after its own sentence, and a resolved one got this sentence
     * **twice**. That job files nothing now (4541's ruling on its own sibling),
     * and {@see AgentThreadStates::close()} is the single writer of this case.
     * ⚠️ **A docblock forbidding a thing is not a mechanism that prevents it**
     * (314–316); what enumerates the writers is `ActivityTest`'s method-level
     * list, and what proves the count is
     * `ThreadCloseSummaryTest`'s unfiltered whole-feed equality.
     */
    case AssistantFinishedAConversation = 'assistant_finished_a_conversation';

    /**
     * How the business's calls are handled changed — call forwarding and routing setting.
     *
     * ⚠️ NOT AN AUTOMATED ACTION, AND IT BELONGS IN THE FEED ANYWAY.
     * `TenantPaused` set that precedent and the reason carries: support can
     * reach this setting on a tenant's behalf, and the mode decides whether a
     * real customer call is intercepted at all — so an owner whose calls started
     * or stopped being answered needs the change in the history they read, not
     * only in the log an auditor reads.
     */
    case CallRoutingChanged = 'call_routing_changed';
    case ContentPublished = 'content_published';
    case SiteChangeApplied = 'site_change_applied';
    case SiteChangeReverted = 'site_change_reverted';

    /**
     * A page this platform created has been taken back off the public site.
     *
     * ⛔ **5807's OWED CASE, AND IT IS DERIVED RATHER THAN PASSED IN** (5831).
     * `SiteChanges::revert()` filed `SiteChangeReverted` — *"Undid a change that
     * was not working"* — for every revert there is, and **taking a new page
     * back down is a different thing happening to somebody's website from
     * putting an edited page back**: one leaves the site with less on it than
     * yesterday. 5669 gave `apply()` a caller-supplied case for the same reason;
     * this one is **not** a parameter, because `ChangeSet::isCreation()` already
     * answers it from the row — a caller could pick the wrong sentence and
     * cannot invent one, so removing the choice removes the mistake.
     *
     * ⛔ **THE TITLE MAY NEVER SAY "REMOVED" OR "DELETED"** (5771). This
     * platform does not delete anything on a customer's website: the page leaves
     * the public site and every word stays in the owner's own CMS.
     */
    case SitePageTakenDown = 'site_page_taken_down';
    case AppointmentBooked = 'appointment_booked';
    case FillCampaignSent = 'fill_campaign_sent';

    /**
     * A reactivation campaign finished a pass (T137 `SL-2`, `REACT-1`).
     *
     * ⚠️ **ONE ITEM PER PASS, NEVER ONE PER RECIPIENT.** A campaign of five
     * thousand contacts would otherwise bury every other thing that happened
     * that week, and the feed is what an owner reads to find out what we did on
     * their behalf. The per-contact record is `campaign_recipients`, which is
     * where a question about one person is answered.
     *
     * ⚠️ **NOT `FillCampaignSent`.** That one is the empty-slot offer, and its
     * title says so to an owner. Reusing it would put a reactivation in their
     * history under a sentence about their calendar.
     */
    case ReactivationCampaignSent = 'reactivation_campaign_sent';

    /**
     * A customer texted back, and we worked out which send they were answering
     * (T176 R20, patch P20).
     *
     * ⚠️ **ONE ITEM PER REPLY, WHICH IS THE OPPOSITE OF THE RULE ABOVE AND IS
     * RIGHT FOR THE OPPOSITE REASON.** A campaign pass is one act of ours
     * affecting thousands; a reply is one person choosing to answer, and burying
     * it in a rollup would hide the only inbound signal this feed ever carries.
     * Replies are also rare next to sends — R20's whole premise is that a
     * conversation happens *if they say anything*.
     *
     * ⚠️ **IT IS NOT `TriageOpened`.** That one is private feedback a customer
     * gave us through a form; this is a text message answering something we
     * sent. Reusing it would file a campaign reply under a sentence about the
     * feedback page.
     */
    case CampaignReplyReceived = 'campaign_reply_received';

    /**
     * A customer texted this business a picture — T176 P10, skill 12.
     *
     * ⚠️ **IT RECORDS THE ARRIVAL, NOT THE STORAGE, AND THE TWO ARE DIFFERENT
     * FACTS.** The payload is what establishes that somebody sent a photograph;
     * whether this platform ended up keeping it is decided afterwards, by a
     * fetch that can be refused five different ways and by a PHI ruling (4166)
     * that refuses before the fetch. So the entry is written once per message,
     * at arrival, and `metadata['kept']` says whether there is anything to open.
     * Waiting for the outcome would mean an owner never hearing about the
     * picture we could not fetch, which is the one they most need to know about.
     *
     * ⚠️ **NOT `CampaignReplyReceived`.** That case is about a *send of ours*
     * drawing an answer; this is a customer starting something. An MMS that also
     * answers a campaign writes both, which is correct — they are two true
     * statements about one message.
     */
    case PhotoReceived = 'photo_received';

    /**
     * The account holder texted their own business back — wave 39 lane C.
     *
     * ⚠️ **NOT `CampaignReplyReceived`.** That case is a customer answering a
     * message we sent on the business's behalf; this is the business's own
     * owner replying on the number `App\Services\Consent\OwnerConsentService`
     * registered for them. `App\Services\Sms\InboundMessages::handle()`
     * resolves one or the other before either case is reached, so the two never
     * fire on one message.
     *
     * ⛔ **THAT SENTENCE USED TO CARRY A SECOND CLAUSE — *"an owner's number is
     * never a customer's contact"* — AND NOTHING ENFORCES IT (10833).** Nothing
     * in the schema stops an owner-notify `e164` from also being a `Customer`
     * contact of the same business: a sole trader whose mobile is the number on
     * their own invoices is the ordinary shape of it, not a hypothetical. When
     * it happens, `handle()`'s `None` arm resolves the owner first, so **that
     * person's message is filed as an owner reply and is never threaded, never
     * in the Inbox and never answered** — silently, because both outcomes look
     * like success. **The resolution half of the sentence is true and the
     * premise under it was not**, and a docblock asserting a property the
     * schema does not hold is `CLAUDE.md`'s 314-316 shape. **Which of the two
     * should win is a ruling nobody has made** (10833).
     *
     * ⚠️ **THIS DOES NOT MEAN ANYTHING ACTED ON WHAT THEY SAID.** The reply is
     * stored — see `App\Models\OwnerReply` — and this row is the only
     * acknowledgement the owner gets that it arrived; **no automation resumes,
     * no campaign is triggered and no `AgentTurns` call is made**, which this
     * states plainly rather than implying a loop that does not exist.
     * ⛔ **THIS SAID *"nothing downstream reads the words yet"* AND THAT IS NO
     * LONGER TRUE AS WRITTEN — CORRECTED wave 41 lane E (11117).**
     * `App\Livewire\Admin\OwnerChannelTexts` reads them: a member of platform
     * staff, behind the admin gate and a second factor, with the read written to
     * this tenant's own `audit_log`. **A person looking is not the platform
     * acting**, and the sentence above is the half that was load-bearing —
     * *this row is still the only acknowledgement the owner gets.* ✅ **Since wave 40 lane A the row
     * records what it appears to be ANSWERING** (`in_reply_to_notification_id`,
     * 10829) — which is a correlation and still not a reader.
     *
     * ⛔ **AND IT IS NOT FILED AT ALL FOR AN OWNER WHO HAS SAID STOP** (10830).
     * Before that, an account holder who had opted out went on having their
     * words stored and this item filed, on a channel that would never text them
     * again.
     */
    case OwnerReplyReceived = 'owner_reply_received';

    case SystemMessage = 'system_message';

    /** A piece of autopilot work finished. The AutopilotJob default. */
    case AutomationCompleted = 'automation_completed';

    /**
     * The First 7-Day Results Path emailed the owner (`28` §3.2, row 5).
     *
     * ⚠️ **ONE CASE FOR FIVE DIFFERENT EMAILS, AND THAT IS `AutopilotJob`'s
     * OWN LIMIT RATHER THAN A CHOICE.** `SupportMadeAChange` and
     * `ReviewDestinationOpened` above are overridden per call site because
     * their callers reach `ActivityService::record()` directly with a title —
     * `AdvanceFirstWeekPathJob::recordActivity()` runs through
     * `AutopilotJob`'s own, which does not forward one. A generic sentence
     * that is true of the audit summary, the import prompt, the celebration,
     * the fallback and the Day-7 close is the honest shape available here,
     * not an omission to fix later.
     *
     * ⚠️ **ONLY WRITTEN ON A TICK THAT ACTUALLY SENT SOMETHING** — see
     * `FirstWeekPath::advance()`'s `notified` key. Most days this automation
     * runs, it sends nothing; writing this case on every run would be a feed
     * entry with nothing behind it, decision 256's vacuity at the scale of a
     * sentence the owner reads.
     */
    case FirstWeekUpdateSent = 'first_week_update_sent';

    /**
     * Something is waiting on the owner.
     *
     * ⚠️ ITS TITLE PROMISED A REPLY AND MOST OF ITS USES DO NOT WANT ONE. An
     * expired OAuth connection wants a reconnect; a review nobody could moderate
     * wants nothing at all from the owner except that they know. "Something
     * needs your attention" is what is true of every use — `29` §2 rule 47 wants
     * outcome language, and naming an action the owner cannot take is worse
     * jargon than naming a mechanism.
     */
    case OwnerActionNeeded = 'owner_action_needed';

    /**
     * The owner stopped everything, or started it again.
     *
     * ⚠️ TWO CASES RATHER THAN ONE WITH A FLAG, because the feed is read as a
     * list of sentences and *"Everything is paused"* and *"Everything is running
     * again"* are the two states somebody scrolling needs to tell apart at a
     * glance. A single `TenantPauseChanged` whose title depended on metadata
     * would be one entry that reads correctly only if the reader opens it.
     *
     * ⚠️ AND THEY ARE `needsOwner() === false` DELIBERATELY. A pause is not
     * something waiting on the owner — it is a thing they (or support on their
     * behalf) already did, and flagging it for attention would put a permanent
     * "needs you" marker on an account that is doing exactly what was asked.
     */
    case TenantPaused = 'tenant_paused';

    case TenantResumed = 'tenant_resumed';

    /**
     * We stopped the account, or let it start again (`28` §9.5's Suspend).
     *
     * ⚠️ SEPARATE CASES FROM THE PAUSE'S, NOT A REUSE, and the reason is the
     * one the whole slice turns on: a pause is something the owner did (or
     * asked us to do), and a suspension is something done *to* them, for cause.
     * Filing both under `TenantPaused` would tell an owner scrolling their feed
     * that they had paused their own account on a day they did not — and the
     * feed is the record they would take to a dispute.
     *
     * `needsOwner()` is **true** for the suspension and false for the lift,
     * which is the opposite of the pause pair (they are both false, decision
     * 826's neighbourhood). A pause needs nothing from the owner; a suspension
     * needs them to talk to us, and it is the one feed entry in this
     * application where that is literally the only way out.
     */
    case TenantSuspended = 'tenant_suspended';

    case TenantSuspensionLifted = 'tenant_suspension_lifted';

    /**
     * A "Download my data" ZIP finished building (`28` §3.7).
     *
     * `needsOwner()` is false: the export is announced through the in-app link
     * and the email PlatformMailer attempts, not through a feed prompt asking
     * for a second action — there is nothing further to decide.
     */
    case DataExported = 'data_exported';

    /**
     * The past-tense sentence the owner reads.
     *
     * FOUND-06 requires feed titles to carry no technical jargon, and `29` §2
     * rule 47 requires outcome language only — every string names what happened
     * to the business, never how the system is built. Titles live here rather
     * than at each call site so the vocabulary stays closed and a single test
     * can assert all of it; checking free-form strings at runtime would be
     * brittle and easy to route around.
     */
    public function title(): string
    {
        return match ($this) {
            self::ReviewApproved => 'A review is now showing on your website',
            // ⚠️ THIS SAID "A review is waiting for your say-so" AND THERE IS NO
            // SAY-SO. Nothing in this codebase can clear `flagged_at`, nothing
            // can publish a withheld review, and no screen offers either — so
            // the title promised a control that does not exist and, on flagged
            // content, one that is its own compliance decision to build. Naming
            // what happened is honest; naming a choice the owner does not have
            // is not.
            self::ReviewHeld => 'A review was held back from your website',
            self::ReplyPosted => 'Replied to a review',
            self::ReplyFailed => 'A reply could not be posted — we will try again',
            self::ReplySuggested => 'A reply draft is ready for you',
            // Says who decided and says nothing has gone out. "We approved"
            // rather than "a reply was approved", because `22`'s rule is that a
            // string names what happened to the business — and the fact the
            // owner needs from this sentence is that the decision was not
            // theirs. "Waiting to publish" is the honest tense: it is queued,
            // and posting to Google is not available yet.
            self::ReplyAutoApproved => 'We approved a reply for you — it is waiting to publish',
            self::GooglePostPublished => 'Published a post to your Google listing',
            self::ReviewRequestSent => 'Asked a customer for a review',
            self::HubPageUpdated => 'Refreshed your reviews page',
            self::ProfileOptimized => 'Improved your business listing',
            self::BoostScoreUpdated => 'Your Boost Score changed',
            self::QrGenerated => 'Made you a review QR code',
            self::PdfGenerated => 'Made you something to print',
            // Names what the customer saw, not what we sent — because we sent
            // nothing. "Invited" and "asked" both imply outbound contact, which
            // is ReviewRequestSent's sentence and row 4's event.
            self::ReviewInviteOffered => 'Showed a customer where to leave a public review',
            // The fallback only. Every real call site overrides this with
            // ReviewDestination::openedTitle(), which names the platform — an
            // owner wants to know *which* one was opened, and "a review site"
            // answers nothing. It stays deliberately vague rather than guessing
            // Google, so a title that reaches the feed unnamed reads as the gap
            // it is instead of as a fact nobody checked.
            self::ReviewDestinationOpened => 'A customer opened a review site',
            self::GoogleReviewReceived => 'A new Google review came in',
            // Says who, says why they could, and says it in the owner's words.
            // Not "an impersonation session was started" — that is our word for
            // our mechanism, and `22`'s rule is that every string names what the
            // person controls rather than how the system is built.
            self::SupportSessionOpened => 'GO AI EZ support opened your account to fix something',
            // The fallback only. Every real call site overrides it with the
            // thing that changed and the ticket reference.
            self::SupportMadeAChange => 'GO AI EZ support made a change for you',
            self::TriageOpened => 'A customer shared private feedback',
            self::TriageResolved => 'Won back an unhappy customer',
            self::RecoveryCheckInSent => 'Checked in with a customer whose problem was fixed',
            self::RecoveryConfirmedFixed => 'A customer confirmed their problem was fixed',
            self::RecoveryNotYetFixed => "A customer said their problem isn't fixed yet",
            self::CallMissed => 'Texted back a missed call',
            self::VoicemailReceived => 'Someone left you a voicemail',
            // ⚠️ SECOND PERSON, BECAUSE THE OWNER'S SIDE DID THIS. The first
            // two are a person's own act — `22`'s rule is that a string names
            // what the person controls, and "the assistant was latched" names
            // our mechanism for something they did by pressing send.
            self::AssistantHandedOver => 'You took over a conversation',
            self::AssistantHandedBack => 'Your assistant is answering a conversation again',
            // ⚠️ **THE ASSISTANT ASKED, AND THE OWNER HAS NOT ANSWERED YET.**
            // The pair is deliberate: this sentence and the one two lines above
            // are a request and its fulfilment, so an owner who reads both in
            // order reads a story rather than two words for one thing. ⛔ NOT
            // *"your assistant could not answer"* — on the urgent path it could
            // and deliberately did not, and a title has to be true of all three
            // callers. ⛔ AND NOT a second *"was handed to you"*, which is the
            // cap's sentence: two near-identical titles would leave the feed
            // unable to say which silence happened, which is the whole reason
            // this case exists.
            self::AssistantAskedYouToTakeOver => 'Your assistant asked you to take a conversation',
            // Names the outcome to the owner, never the limit. A cap is the
            // assistant working correctly, and "turn cap reached" is a number
            // nobody set and a word nobody uses.
            self::AssistantReachedItsTurnLimit => 'A long conversation was handed to you',
            // ⛔ "ASKED", NEVER "GOT" — see the case. The owner-visible claim is
            // that a link went out, which is the only one this system can prove.
            self::AssistantAskedForAReview => 'Asked a happy customer for a review',
            // Names the courtesy, not the mechanism. Not "nudge job ran".
            self::AssistantFollowedUpOnce => 'Followed up with someone who went quiet',
            self::AssistantFinishedAConversation => 'Finished a conversation for you',
            // Names what changed for the business, not the column that moved.
            // Not "call routing mode updated" — `22`'s rule is that a string
            // names what happened to the business, and the fact an owner needs
            // from this sentence is that their phone behaves differently now.
            self::CallRoutingChanged => 'Changed how your calls are handled',
            self::ContentPublished => 'Published a page to your website',
            self::SiteChangeApplied => 'Improved a page on your website',
            self::SiteChangeReverted => 'Undid a change that was not working',
            self::SitePageTakenDown => 'Took a page we added back off your website',
            self::AppointmentBooked => 'Booked an appointment',
            self::FillCampaignSent => 'Offered your open slots to customers',
            // Outcome language: what the owner got, not what the runner did.
            // "Ran a campaign batch" names our machinery; this names theirs.
            self::ReactivationCampaignSent => 'Reached out to customers you had not heard from',
            // Names what happened to the business, not the mechanism. Not "an
            // inbound message was linked to a campaign send" — that is our word
            // for our machinery, and `22`'s rule is that every string names what
            // happened to them. "Texted you back" is also the honest tense: the
            // reply has arrived, and nothing has answered it yet.
            self::CampaignReplyReceived => 'A customer texted you back about a message we sent',
            // Names what the customer did, which is the part that is true
            // whatever happened next. Not "a photo was stored" — that is our
            // machinery, it is not always true, and `22`'s rule is that every
            // string names what happened to the business.
            self::PhotoReceived => 'A customer texted you a photo',
            // Second person, on `AssistantHandedOver`'s reasoning: this is the
            // owner's own act, and "an inbound message was identified as an
            // owner reply" is our machinery's name for it. It does not say
            // anything happened next, because nothing does yet.
            self::OwnerReplyReceived => 'You texted us back',
            self::SystemMessage => 'An update about your account',
            self::AutomationCompleted => 'Finished a piece of work for you',
            self::FirstWeekUpdateSent => 'Sent you an update about your first week',
            self::OwnerActionNeeded => 'Something needs your attention',
            // Says what stopped, in the words of the control that stopped it.
            // Not "autopilot disabled" or "automations suspended" — the button
            // says Pause Everything, so the feed says everything is paused.
            self::TenantPaused => 'Everything is paused',
            self::TenantResumed => 'Everything is running again',
            // ⚠️ "WE" AND "ON HOLD", NEVER "SUSPENDED" ALONE. `22`'s rule is
            // that every string names what happened to the business rather than
            // what the system did, and an owner reading their own feed after
            // the fact needs to see that this was our act — the pause pair
            // reads as theirs, and the two sentences sit next to each other in
            // one list. "Put your account on hold" is also the wording the
            // status page uses, so the feed and the page cannot describe the
            // same event in two vocabularies.
            self::TenantSuspended => 'We put your account on hold',
            self::TenantSuspensionLifted => 'Your account is off hold',
            self::DataExported => 'Your data download is ready',
        };
    }

    /**
     * Whether this needs the owner to act rather than merely to see.
     *
     * Drives an icon and a label, never colour alone (`29` §2 rule 46).
     */
    public function needsOwner(): bool
    {
        return match ($this) {
            self::ReviewHeld, self::OwnerActionNeeded, self::ReplyFailed, self::ReplySuggested,
            // Rail 3's cap hands a live conversation to a person and nothing
            // else will answer it — T176 rail 9 requires the owner notify, and
            // this is the flag the same event raises in their own history.
            // ⚠️ THE OTHER TWO ASSISTANT CASES ARE DELIBERATELY NOT HERE: a
            // person who took a thread over, or handed it back, is the person
            // who acted, and flagging their own act as needing them is noise.
            self::AssistantReachedItsTurnLimit,
            // ⛔ **THE SAME SITUATION AS THE CAP AND A STRONGER CASE FOR IT**
            // (7343). The assistant has stopped, the thread is live, and nothing
            // else will answer it — and unlike the cap, re-arming may not help,
            // so the person this flag points at is the only remedy there is.
            // ⚠️ Escalation reached this list only when it stopped being filed
            // as the latch: `AssistantHandedOver` is `false` because a person
            // who acted does not need flagging, which is right for the latch and
            // was silently wrong for every escalation this platform has made.
            self::AssistantAskedYouToTakeOver,
            // The only entry in this list whose action is "talk to us", and the
            // only state in the product an owner cannot leave on their own.
            self::TenantSuspended => true,

            self::ReviewApproved,
            self::ReplyPosted,
            self::ReplyAutoApproved,
            self::GooglePostPublished,
            self::ReviewRequestSent,
            self::HubPageUpdated,
            self::ProfileOptimized,
            self::BoostScoreUpdated,
            self::QrGenerated,
            self::PdfGenerated,
            self::ReviewInviteOffered,
            self::ReviewDestinationOpened,
            self::GoogleReviewReceived,
            self::SupportSessionOpened,
            self::SupportMadeAChange,
            self::TriageOpened,
            self::TriageResolved,
            self::RecoveryCheckInSent,
            self::RecoveryConfirmedFixed,
            self::RecoveryNotYetFixed,
            self::CallMissed,
            self::VoicemailReceived,
            self::AssistantHandedOver,
            self::AssistantHandedBack,
            self::AssistantAskedForAReview,
            self::AssistantFollowedUpOnce,
            self::AssistantFinishedAConversation,
            self::CallRoutingChanged,
            self::ContentPublished,
            self::SiteChangeApplied,
            self::SiteChangeReverted,
            self::SitePageTakenDown,
            self::AppointmentBooked,
            self::FillCampaignSent,
            self::ReactivationCampaignSent,
            self::CampaignReplyReceived,
            self::PhotoReceived,
            self::OwnerReplyReceived,
            self::SystemMessage,
            self::AutomationCompleted,
            self::FirstWeekUpdateSent,
            self::TenantPaused,
            self::TenantResumed,
            self::TenantSuspensionLifted,
            self::DataExported => false,
        };
    }
}
