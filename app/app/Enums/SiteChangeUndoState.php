<?php

declare(strict_types=1);

namespace App\Enums;

use App\Jobs\Actuation\UndoSiteChangeJob;
use App\Livewire\Account\SiteChanges as SiteChangesScreen;
use App\Services\Actuation\ActuationTiers;
use App\Services\Actuation\SiteChanges;

/**
 * What an owner can do about one change we made to their website, and what they
 * are told when the answer is *nothing* — `BUILD-PLAN` §2.11.3 slice J.
 *
 * ⛔ **"THE FIVE ARE FIVE" WAS WRITTEN WHEN THERE WERE SIX AND THERE ARE NOW
 * SEVEN — CORRECTED 2026-08-20 (6072).** The count is deliberately not written
 * here any more: 5845 argued six, this file said five the day it landed, and a
 * number in prose beside an enum the compiler already counts is 2505's shape at
 * its cheapest. **What matters is the rule, and the rule is unchanged:** 5524
 * says an owner undo and an auto-revert are two facts.** `rolled_back_by` already tells them apart on the row; this is
 * where that distinction becomes two different sentences on a screen, and the
 * one thing this enum exists to make unrepresentable is a platform self-
 * correction reported as something the owner did, or the reverse.
 *
 * ⛔ **AND {@see self::Unreachable} IS THE STATE 5759 AND 5819(d) LEFT OWED.**
 * An owner may disconnect their website while a change of ours is still on it —
 * `WordPressCredentials::forget()` is deliberately unconditional, because a
 * customer asking us to let go must never be refused over our own bookkeeping.
 * What that leaves is a `site_changes` row with no route back to the page it
 * describes: slice H's nightly sweep retries the revert for ever and files one
 * owner action item, and until this enum there was **no screen state at all**.
 * The card says so plainly and **offers no button**, because a button that
 * cannot work is a worse answer than a sentence that says why.
 *
 * ## The copy rules these sentences are written to (`22`, `29` §2 rule 47)
 *
 *   - **No blame.** The access is gone; who ended it is not the subject. An
 *     owner disconnecting their own website is a thing they are entitled to do,
 *     and a sentence that reads as a reproach for it would be a support ticket
 *     and a broken trust at once.
 *   - **No pretending.** The change is still on their page. Saying anything
 *     softer than that would leave them believing a page had been put back that
 *     had not.
 *   - **Name what they control.** Two routes out, both theirs: put it back
 *     themselves, or reconnect and we will.
 *   - **Never colour alone.** Every state carries a word, and
 *     {@see self::signal()} is what the pill is tinted from.
 *
 * ⚠️ **THE SENTENCES TAKE `createdThePage` BECAUSE ROLLBACK HAS TWO SHAPES**
 * (5806, 5771). Undoing an edit puts a page back as it was; undoing a creation
 * takes a page **off** their website — and it is never a delete, so the words
 * say where their words went. One sentence covering both would be wrong for one
 * of them every time it was read.
 *
 * ⚠️ **THE COPY IS HERE RATHER THAN IN THE TEMPLATE**, on `TriageStatus`' rule:
 * the screen and any later surface then cannot describe one event in two
 * vocabularies.
 *
 * @see SiteChanges::history() where the state is derived
 * @see SiteChangesScreen the one screen that renders it
 * @see ActuationTiers::canUndoAt() the reachability question behind Unreachable
 */
enum SiteChangeUndoState: string
{
    /** It is on the site, and we can still take it back off. */
    case Available = 'available';

    /** The owner has asked, and the answer has not come back yet. */
    case InProgress = 'in_progress';

    /**
     * It is on the site and we can no longer reach it — 5759's owed state.
     */
    case Unreachable = 'unreachable';

    /**
     * The owner asked, we tried, and their website did not take it back.
     *
     * ⛔ **5849's OWED FINDING, AND IT IS A READ RATHER THAN A COLUMN.** A
     * queued undo that the site refuses files an owner action item and clears
     * `undo_requested_at` — so before this case the card **went back to offering
     * Undo with no explanation at all**: somebody pressed a button, nothing
     * visible happened, and the button offered itself again. 5849 refused a
     * second column for it — *"one nullable column per screen state is how a
     * table acquires six of them"* — and this is the alternative it pointed at:
     * the failure already wrote a row, and {@see SiteChanges::history()} reads
     * that row back.
     *
     * ⛔ **AND IT IS NOT {@see self::Unreachable}, WHICH 5849 SUGGESTED MIGHT
     * COVER IT.** Unreachable means the credential is gone and **no button is
     * drawn**, because a control that cannot work is worse than a sentence.
     * Here the credential is still ours and the attempt did not land — so the
     * honest thing is to say so and **let them press again**. Telling somebody
     * we can no longer reach a site we can reach would send them to reconnect a
     * connection that is not broken.
     *
     * ⛔ **THE SENTENCE SAID "YOUR WEBSITE DID NOT ACCEPT THE CHANGE" AND NO
     * LONGER DOES — CORRECTED 2026-08-20 (6140).** That was true of the only
     * cause this case had when it was written: a plugin, a `500`, a WAF in a bad
     * mood. `AdapterOutcome` now has a third state, and two of the things that
     * reach this card through it were never a refusal at all — the page has
     * moved and we cannot find it (6040), or the page is still there and **is
     * not as we left it**, so we deliberately did not write over what the owner
     * has written since (6042). *"Your website did not accept the change"* is
     * false twice over about both: nothing was refused, and nothing was even
     * asked. **The wording is cause-neutral now**, and the two clauses that
     * carry the meaning — it is still there, you may press again — are true of
     * every arm.
     *
     * ⚠️ **THE EDIT ARM NO LONGER SAYS "IT IS STILL EXACTLY AS WE LEFT IT"**
     * either, for the same reason: on the 6042 arm it demonstrably is not, which
     * is the whole reason we refused.
     *
     * ⛔ **AND ONE CAUSE REACHED THE CARD THROUGH NOTHING AT ALL UNTIL
     * 2026-08-25 (9580).** The paragraph above describes a **refused** revert,
     * which is an outcome the job is handed and files. A revert that **throws**
     * is handed nothing: the two attempts were spent, the row went to
     * `failed_jobs`, and this state — minted precisely to end *"somebody pressed
     * a button, nothing visible happened, and the button offered itself again"* —
     * **was reached by exactly that**, an hour later, when
     * {@see SiteChanges::UNDO_IN_PROGRESS_MINUTES} let the card fall back to
     * `Available`. {@see UndoSiteChangeJob::failed()} files
     * the same row on that path now. ⚠️ **The wording did not have to move**,
     * which is 6145's cause-neutrality earning its keep a third time: *we tried
     * and could not, it is still there, you may press again* is true of a
     * website that said no, of a page that had moved, and of a worker that died
     * mid-attempt.
     */
    case UndoDidNotLand = 'undo_did_not_land';

    /**
     * It is on the site, and the site it is on is not the one this account
     * points to any more.
     *
     * ⛔ **5976's OWED SCREEN STATE, AND THE ONE 5970 REFUSED TO GUESS AT.**
     * `SiteMeasurements::addressesTheWebsite()` is the platform's rule for
     * *"which page would a write land on"*: a change set's URL was built by
     * concatenating `locations.website_url` with a slug, and
     * `LocationWebsite::confirm()` may move that column at any time afterwards.
     * When the two disagree, the page we measured and the page we would write
     * are two different pages on two different sites — so every automatic path
     * was closed at 5965 and 5974, and **the owner's own Undo button was not**.
     * Pressing it handed the adapter a URL to resolve against whatever
     * credential the location holds today.
     *
     * ⛔ **AND IT IS NOT {@see self::Unreachable}, WHICH IS THE NEAREST
     * NEIGHBOUR AND THE WRONG ANSWER.** Unreachable says *"reconnect your
     * website and we will put it back for you"* — a promise this platform could
     * not keep here, because reconnecting is not what is wrong and the revert
     * would refuse again the moment it was pressed. The routes out of **this**
     * one are to edit the page themselves or to point the account back at the
     * website the change is on, and both are theirs.
     *
     * ⚠️ **ASKED BEFORE `Unreachable`**, because a location can be both at once
     * and only one of the two sentences is true of what we would do next.
     */
    case WebsiteChanged = 'website_changed';

    /** A person on the tenant's side took it back off. */
    case UndoneByYou = 'undone_by_you';

    /** We took it back off ourselves, because we measured it doing harm. */
    case UndoneByUs = 'undone_by_us';

    /**
     * Somebody on our support team took it back off, acting for this account.
     *
     * ⛔ **THE THIRD UNDONE STATE EXISTS BECAUSE `SiteChangeActor` HAS THREE
     * CASES, AND COLLAPSING IT INTO {@see self::UndoneByYou} WOULD BE 5524's
     * DEFECT INSIDE THE SCREEN 5524 WAS WRITTEN FOR.** A support agent acting
     * through an impersonation session is precisely the case that would
     * otherwise be filed as the owner's own decision — and an owner reading
     * *"you undid this"* about something they never touched has been told
     * something false about their own website.
     */
    case UndoneByOurTeam = 'undone_by_our_team';

    /**
     * Which pill this card wears.
     *
     * ⚠️ **`Attention` RATHER THAN `Alert` FOR {@see self::Unreachable}.** Alert
     * is reserved for something going wrong right now; a change we cannot undo
     * is a thing to decide about, not an emergency, and spending the strongest
     * signal on it would leave nothing for the case that is.
     *
     * ⚠️ **AND THE TWO UNDONE STATES ARE BOTH `Ok`.** A change that has been
     * taken back off is a finished, correct end state whoever asked for it —
     * tinting the platform's own revert as a fault would make our honesty look
     * like our failure, and tinting the owner's as one would scold them.
     */
    public function signal(): SignalState
    {
        return match ($this) {
            self::Available, self::UndoneByYou, self::UndoneByUs, self::UndoneByOurTeam => SignalState::Ok,
            self::InProgress => SignalState::Unknown,
            // ⚠️ **`Attention` FOR BOTH OF THE "STILL THERE" CASES**, on the
            // same argument: a change we could not take back off is a thing to
            // decide about rather than an emergency, and spending `Alert` on it
            // leaves nothing for the case that is.
            self::Unreachable, self::UndoDidNotLand, self::WebsiteChanged => SignalState::Attention,
        };
    }

    /**
     * The word inside the pill.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'On your website',
            self::InProgress => 'Undoing',
            // ⚠️ **ONE WORD FOR TWO STATES, DELIBERATELY.** The pill says what
            // is true of the website — this change is still on it — and the
            // sentence beneath says what is true of us. A pill reading *"undo
            // did not work"* would put our machinery in the one place on the
            // card that is meant to describe their page.
            self::Unreachable, self::UndoDidNotLand, self::WebsiteChanged => 'Still on your website',
            self::UndoneByYou, self::UndoneByUs, self::UndoneByOurTeam => 'Undone',
        };
    }

    /**
     * Whether this card may offer the Undo button at all.
     *
     * ⛔ **THE SCREEN ASKS THIS RATHER THAN RE-DERIVING IT**, and so does the
     * press handler. A render-only check is a guard one stale tab away from
     * being skipped (398), and the thing on the other side of it is a write to
     * somebody else's website.
     */
    public function offersUndo(): bool
    {
        return $this === self::Available || $this === self::UndoDidNotLand;
    }

    /**
     * Whether this change is still on the customer's website.
     *
     * ⚠️ **IT IS NOT `! offersUndo()` AND THE TWO MUST NOT BE CONFLATED.**
     * {@see self::Unreachable} is on the site and offers nothing;
     * {@see self::InProgress} is on the site and is already being dealt with.
     * This answers *"is our work still on their page"*, which is what decides
     * whether *"we are still watching this"* is a true sentence to print.
     */
    public function stillOnTheSite(): bool
    {
        return match ($this) {
            self::Available, self::InProgress, self::Unreachable, self::UndoDidNotLand, self::WebsiteChanged => true,
            self::UndoneByYou, self::UndoneByUs, self::UndoneByOurTeam => false,
        };
    }

    /**
     * What this card says under its heading.
     */
    public function sentence(bool $createdThePage): string
    {
        return match ($this) {
            self::Available => $createdThePage
                ? 'This page is live on your website. Undo takes it back down — your words stay in your own website, so you can put it back yourself whenever you like.'
                : 'This is on your website now. Undo puts the page back exactly as it was before we touched it.',

            self::InProgress => $createdThePage
                ? 'We are taking this page back down. It takes a few minutes.'
                : 'We are putting this page back as it was. It takes a few minutes.',

            // ⛔ **THE SENTENCE 5759 ASKED FOR.** No blame, no pretending, and
            // two routes out that both belong to the person reading it.
            self::Unreachable => $createdThePage
                ? 'This page is still live on your website, and we can no longer reach your website to take it down. You can unpublish it yourself, or reconnect your website and we will take it down for you.'
                : 'This is still on your website, and we can no longer reach your website to change it back. You can edit the page yourself, or reconnect your website and we will put it back for you.',

            // ⛔ **NO BLAME AND NO PRETENDING, 5844's RULES ON A SECOND
            // STATE.** Their website refused; whose fault that is is not the
            // subject and we do not know anyway. What they need is that the
            // change is still there, that we did not put it back, and that
            // pressing again is a reasonable thing to do.
            self::UndoDidNotLand => $createdThePage
                ? 'We tried to take this page back down and could not, so it is still live. '
                    .'You can try again, or unpublish it yourself.'
                : 'We tried to put this page back and could not, so our change is still on it. '
                    .'You can try again, or change the page yourself.',

            // ⛔ **THE SENTENCE 5976 ASKED FOR.** No blame — an owner is
            // entitled to move their website — no pretending, and two routes out
            // that both belong to the person reading it.
            self::WebsiteChanged => $createdThePage
                ? 'This page is still live where we published it, and that is not the website your account '
                    .'points to now — so we will not take it down from here, in case we take down the wrong '
                    .'page. You can unpublish it yourself, or point your account back at the website we '
                    .'published it on.'
                : 'This is still on the page we changed, and that page is not on the website your account '
                    .'points to now — so we will not write to it, in case we write to the wrong page. You can '
                    .'change that page yourself, or point your account back at the website we changed.',

            self::UndoneByYou => $createdThePage
                ? 'You took this page back down. Nothing was deleted — the page is still in your website, unpublished, if you want it again.'
                : 'You put this page back the way it was.',

            // ⚠️ **"WE" AND NEVER "YOU"** (5524). The platform measured its own
            // work doing harm and took it off; reporting that as the owner's
            // decision would credit them with a call they never made, and the
            // reason on the row is the evidence.
            self::UndoneByUs => $createdThePage
                ? 'We took this page back down ourselves, because it was not working. Nothing was deleted — it is still in your website, unpublished.'
                : 'We put this page back the way it was ourselves, because the change was not working.',

            self::UndoneByOurTeam => $createdThePage
                ? 'Someone on our team took this page back down for your account. Nothing was deleted — it is still in your website, unpublished.'
                : 'Someone on our team put this page back the way it was for your account.',
        };
    }
}
