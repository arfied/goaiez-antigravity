<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\AdapterOutcomeState;
use App\Enums\SiteChangeUndoState;
use App\Models\Location;
use App\Services\Actuation\ActuationActor;
use App\Services\Actuation\ActuationTiers;
use App\Services\Actuation\OwnerSiteChange;
use App\Services\Actuation\SiteChanges as SiteChangeLog;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * What we changed on your website, and one Undo per change — `28` §3.5's Normal
 * surface, `BUILD-PLAN` §2.11.3 slice J, row 10 U1b.
 *
 * ⛔ **THE COPY IS THE PRODUCT HERE AS MUCH AS THE CODE.** This screen tells a
 * person what a machine did to their own website without asking them first. Two
 * rules follow and both are load-bearing: **nothing is ever hidden from the
 * list** — an undone change stays, saying who undid it, and a change we can no
 * longer reach stays, saying so — and **no button is offered that would lie**.
 *
 * ## Undo is the automatic rollback's own path, and adds nothing beside it
 *
 * ⛔ **THERE IS NO SECOND REVERT MECHANISM IN THIS FILE** (§2.11.3's J row,
 * 5801). Both presses end in {@see SiteChangeLog::revert()} — the identical
 * method `SiteMeasurements::revert()` hands slice H's auto-rollback to. What
 * differs between an owner undo and an auto-revert is **one enum on the row and
 * the actor in the audit line**, never the code that runs (5524), which is what
 * stops the feed and the site ever disagreeing about what an undo is.
 *
 * ⛔ **ONE PRESS IS SYNCHRONOUS AND THE OTHER IS QUEUED, AND THE TIER DECIDES**
 * (5834). Undoing a **T3** change is this platform ceasing to serve a payload —
 * it reaches nobody, so it happens in the request and the owner sees the answer.
 * Undoing a **T1** change is up to four requests to the tenant's own WordPress
 * at fifteen seconds apiece, so it is queued, and the row carries
 * `undo_requested_at` so the card still says *"undoing"* after a refresh (5835).
 *
 * ## The state 5759 left owed, and why it is the most important thing here
 *
 * ⛔ **AN OWNER MAY DISCONNECT THEIR WEBSITE WHILE A CHANGE OF OURS IS STILL ON
 * IT.** `WordPressCredentials::forget()` is deliberately unconditional — a
 * customer asking us to let go must never be refused over our own bookkeeping —
 * and 5819(d) records that slice H made the consequence worse rather than
 * better: the nightly revert now has a route back to a site it cannot reach, so
 * it fails for ever with one owner action item as the only thing that says so.
 * {@see SiteChangeUndoState::Unreachable} is the screen state that was owed, its
 * sentence names two routes out that both belong to the person reading it, and
 * **no Undo button is drawn**, because a control that cannot work is a worse
 * answer than a sentence that says why.
 *
 * ## Livewire hygiene
 *
 * ⚠️ **EVERY PROPERTY THAT NAMES A CHANGE SET IS `#[Locked]`.** A client-settable
 * one is a client choosing which change set this screen is about — the defect
 * found on `Admin\AccountAudit::$businessId` — and the thing on the other side
 * of it is an edit to somebody's website. The tenancy check underneath is what
 * makes it a defence rather than an inconvenience, and both are driven.
 *
 * ⚠️ **THE SCREEN NEVER HOLDS A `SiteChange`.** Only the two permitted services
 * may name that model (5521, 5800), so what arrives here is
 * {@see OwnerSiteChange} — which is the right shape anyway, because a snapshot
 * read off a live site can contain anything that was on that page and none of it
 * belongs in a Livewire snapshot the browser gets a copy of.
 *
 * ⚠️ **IT READS NO `LocationContext` AND RENDERS NO PICKER, DELIBERATELY**
 * (5837). Every location's changes are on one list with the site named on each
 * card: a history filtered by a cursor would hide changes to a second website
 * behind a control the owner may never have touched, on the one screen whose
 * entire subject is that nothing is hidden.
 */
#[Layout('components.account.layout')]
final class SiteChanges extends Component
{
    /**
     * The change the owner has pressed Undo on and not yet confirmed.
     *
     * ⛔ **THERE IS A CONFIRM STEP AND IT IS ARGUED RATHER THAN COPIED** (5839).
     * `CLAUDE.md`'s first tie-breaker is less support surface and 5750 refused a
     * second ceremony for the About address — because a wrong About address is
     * visible and changed in one press. **An undo cannot be undone**:
     * `revert()` refuses a second revert by construction and this application
     * has no redo, so a misplaced tap takes a live page off a business's website
     * with no route back but republishing it by hand. That is what the second
     * press is for, and the panel is where *"this cannot be undone again"* is
     * said before it happens rather than after.
     */
    #[Locked]
    public ?int $confirmingChangeId = null;

    public function confirm(int $changeId): void
    {
        $this->confirmingChangeId = $changeId;
    }

    public function cancel(): void
    {
        $this->confirmingChangeId = null;
    }

    /**
     * Take one change back off.
     *
     * ⛔ **THE STATE IS RE-DERIVED HERE AND NOT TRUSTED FROM THE PAGE** (398).
     * The card was rendered at some point in the past; the credential may have
     * been disconnected since, the change may already have been reverted by the
     * nightly measurement, or this tab may be somebody's second one. So the
     * press re-reads the history, finds this change in it, and asks the same
     * question the render asked — and a stale press is refused with the same
     * sentence the card would have carried.
     */
    public function undo(SiteChangeLog $log, ActuationTiers $tiers): void
    {
        abort_if(Tenancy::id() === null, 403);

        $changeId = $this->confirmingChangeId;

        abort_if($changeId === null, 404);

        $card = $this->cardFor($log, $tiers, $changeId);

        abort_if(! $card instanceof OwnerSiteChange, 404);

        // ⛔ **AUTHORIZATION IS THE POLICY'S, ON THE LOCATION** (`CLAUDE.md`).
        // The change set has no policy of its own and does not need one: what a
        // person may do to a change is what they may do to the website it is on,
        // and `LocationPolicy` already answers that.
        Gate::authorize('update', $this->locationOf($card));

        $this->confirmingChangeId = null;

        if (! $card->state->offersUndo()) {
            // ⚠️ **NOT A TOAST SAYING "SOMETHING WENT WRONG".** The card's own
            // sentence is the honest answer to every one of these — already
            // undone, being undone, or no longer reachable — and it is already
            // written for exactly this reader.
            Toaster::info($card->state->sentence($card->createdThePage));

            return;
        }

        if ($card->tier->undoReachesTheirServer()) {
            $log->requestUndo($card->id, $this->actuationActor());

            Toaster::success($card->createdThePage
                ? 'Taking the page back down — this takes a few minutes'
                : 'Putting the page back — this takes a few minutes');

            return;
        }

        $actor = $this->actuationActor();
        $outcome = $log->revertById($card->id, $actor, SiteChangeLog::ownerUndoReason($actor));

        if ($outcome !== null && $outcome->state === AdapterOutcomeState::Unverified) {
            // ⛔ **THE THIRD ANSWER, AND IT IS NOT A FAILURE TO APOLOGISE FOR**
            // (6140). *"Try again in a few minutes"* is the right thing to say
            // about a website that refused and the wrong thing to say about a
            // page we could not identify: nothing was refused, nothing was even
            // asked, and pressing again in five minutes changes nothing. The
            // card's own sentence carries the two routes out; this says what
            // just happened and, above all, that their page was left alone.
            Toaster::info('We have left this page alone — we could not confirm it is still the page we changed. Nothing on your website has changed.');

            return;
        }

        if ($outcome === null || ! $outcome->ok) {
            // ⚠️ **THE HONEST FAILURE, AND IT DOES NOT BLAME THEM EITHER.**
            // Nothing on their website changed, and saying so is the part that
            // matters: an owner who is not told that is left believing a page
            // was put back that was not.
            Toaster::error('We could not undo this just now — nothing on your website has changed. Please try again in a few minutes.');

            return;
        }

        // The verb survives the flow (`22`): Undo becomes Undone.
        Toaster::success($card->createdThePage ? 'Undone — the page is off your website' : 'Undone — the page is back as it was');
    }

    public function render(SiteChangeLog $log, ActuationTiers $tiers): View
    {
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.site-changes', [
            'changes' => $log->history($tiers),
        ]);
    }

    private function cardFor(SiteChangeLog $log, ActuationTiers $tiers, int $changeId): ?OwnerSiteChange
    {
        foreach ($log->history($tiers) as $card) {
            if ($card->id === $changeId) {
                return $card;
            }
        }

        return null;
    }

    /**
     * ⚠️ **TENANT-SCOPED BY THE GLOBAL SCOPE**, so another tenant's location is
     * a 404 rather than a refusal that confirms the row exists. The card came
     * out of a tenant-scoped read already; this is the second of the two layers,
     * and the service's own `assertSameTenant()` is the third.
     */
    private function locationOf(OwnerSiteChange $card): Location
    {
        $location = Location::query()->find($card->locationId);

        abort_if(! $location instanceof Location, 404);

        return $location;
    }

    /**
     * ⚠️ **STAFF AND OWNER ARE TOLD APART RATHER THAN COLLAPSED** (5751).
     * `site_changes.rolled_back_by` exists so that an owner undo and a platform
     * revert are two facts (5524), and a support agent acting through an
     * impersonation session is the third — filing it as the owner's own change
     * of mind would tell them, on this very screen, that they did something they
     * did not.
     */
    private function actuationActor(): ActuationActor
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        return $user->role->isPlatformStaff()
            ? ActuationActor::staff((int) $user->id)
            : ActuationActor::owner((int) $user->id);
    }
}
