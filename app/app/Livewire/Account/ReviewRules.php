<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Livewire\Setup\ReviewRules as SetupReviewRules;
use App\Models\AutopilotSettings;
use App\Models\Location;
use App\Services\Reviews\ReviewGating;
use App\Services\Tenant\LocationContext;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Who gets asked for a public review — the owner's own control, after setup.
 *
 * ⚠️ **THIS IS WHAT MAKES DECISION 1143 TRUE RATHER THAN A ONE-TIME QUESTION.**
 * The owner ruled that the threshold is *"based on client what they want"*,
 * overriding CLAUDE.md's "never add a tenant-facing toggle" and decision 523's
 * refusal of a picker. A control that exists only inside the wizard is a choice
 * a tenant makes once and can never revisit, which honours the letter of the
 * ruling and none of it — and `SetupFlow` will not re-enter a completed step, so
 * there would be no second door at all.
 *
 * ⚠️ **IT WRITES NOTHING ITSELF.** `ReviewGating` is the only writer of
 * `autopilot_settings.triage_threshold`, held there by a lint, and
 * `DestinationSettings` is the only writer of `review_destinations`. This
 * component validates an answer and calls one of two service methods; the
 * transaction, the paired triage boundary and the audit entry all belong to the
 * service. That is what stops a second screen becoming a second set of rules.
 *
 * ⚠️ **THE SAME DISCLOSURE, THE SAME WORDS, THE SAME VERSION CONSTANT.** The
 * audit row records what the owner had on screen when they chose, and
 * `SetupReviewRules::DISCLOSURE_VERSION` is what makes that answerable later.
 * This screen cites the wizard's constant rather than declaring its own, so the
 * two cannot drift apart while both keep claiming to have shown the same text.
 *
 * ⚠️ **THE ACKNOWLEDGEMENT CHECKBOX IS GONE** (2074, 2660) — see the wizard
 * step's docblock. The disclosure it sat under is not: 2077 records that
 * removing the acknowledgement removed one of the surfaces where the tenant was
 * told a platform's own rule is not theirs to move, and the rule lives in code
 * either way. It is restored here as plain copy.
 *
 * ⚠️ **NO CONFIRMATION STEP, DELIBERATELY, AND IT IS THE OPPOSITE CALL TO
 * `Settings`.** Decision 826 confirms *resuming* rather than pausing, because
 * starting again is what begins acting on customers. Here every answer is a
 * standing preference that takes effect on the next review rather than an act
 * against anybody. ⚠️ **The acknowledgement used to be the second half of this
 * argument and is no longer available to it** — that is a real reduction in
 * friction on the strictest answer, and it is the owner's ruling rather than an
 * oversight. A confirm dialog is still refused: it would be a gate on a standing
 * preference, which trains people to click through gates that matter.
 */
final class ReviewRules extends Component
{
    /**
     * The chosen rating, held as a string because that is what a radio submits.
     *
     * Not a typed `?int` for the reason the wizard's copy records: Livewire
     * hydrates a public property to its declared type before validation runs, so
     * a hand-posted `rating=abc` would be a TypeError rather than a validation
     * message.
     */
    public string $rating = '';

    public function mount(ReviewGating $gating): void
    {
        $location = $this->location();

        if ($location === null) {
            return;
        }

        $chosen = $gating->chosenThreshold($location);

        if ($chosen !== null) {
            $this->rating = (string) $chosen;
        }
    }

    public function save(ReviewGating $gating): void
    {
        $location = $this->location();

        abort_if($location === null, 404);

        $settings = $this->settings();

        abort_if($settings === null, 404);

        // ⛔ **THE ROLE GATE, AND IT WAS MISSING UNTIL 6440.** Every sibling
        // panel on this same page authorizes before it writes — `Knowledge`,
        // `Calls`, `AssistantAnswers`, `AssistantLinks`, `WidgetInstall` — and
        // this one, whose subject is which of a business's customers are invited
        // to leave a public review, did not. `AutopilotSettingsPolicy::update()`
        // is FOUND-04's *"a `staff` user cannot change autopilot settings"*
        // written down, and `autopilot_settings.triage_threshold` is the column
        // it is written about.
        //
        // ⚠️ **AUTHORIZATION BEFORE VALIDATION**, `Account\Knowledge`'s
        // reasoning: telling somebody their answer is malformed and then
        // refusing them for their role is two errors for one action, and the
        // second is the one that mattered.
        //
        // ⚠️ **ASKED WITH THE ROW RATHER THAN WITH THE CLASS**, which is the
        // `update` ability's own shape and `WidgetInstall`'s. The policy's
        // docblock leans on it: it deliberately does not compare tenants,
        // because a model that has been resolved has already passed the global
        // scope and row-level security, and a class name would not have.
        Gate::authorize('update', $settings);

        $validated = $this->validate([
            'rating' => ['required', Rule::in(array_map(strval(...), ReviewGating::choices()))],
        ], [
            'rating.required' => 'Choose one of the options so we know who to ask for reviews.',
            'rating.in' => 'Choose one of the options so we know who to ask for reviews.',
        ]);

        $rating = (int) $validated['rating'];
        $actor = 'user:'.(auth()->id() ?? 'unknown');

        if ($rating === ReviewGating::EVERYONE) {
            $gating->inviteEveryone($location, $actor);
        } else {
            $gating->gateAt($location, $rating, $actor, SetupReviewRules::DISCLOSURE_VERSION);
        }

        // Outcome language, and no personal data in the toast (104).
        Toaster::success($rating === ReviewGating::EVERYONE
            ? 'Everyone will be asked for a review'
            : 'Saved — we will ask customers who rated you '.$rating.' stars or better');
    }

    public function render(): View
    {
        $settings = $this->settings();

        return view('livewire.account.review-rules', [
            // ⚠️ **THE SETTINGS ROW JOINS THE LOCATION IN DECIDING WHETHER THIS
            // PANEL EXISTS AT ALL**, on 1220's rule rather than as a new one. A
            // location with no `autopilot_settings` row has nowhere to record a
            // triage boundary — `ReviewGating::writeGatingRow()` refuses it by
            // name — so a rendered control would be one that cannot save.
            'available' => $settings instanceof AutopilotSettings,

            // ⚠️ **THE AFFORDANCE, ASKED THE SAME WAY THE ACTION IS.** The
            // sibling panels pair `Gate::authorize()` in the writer with
            // `Gate::allows()` in the view, so a person who may not change this
            // is told who can rather than shown a control that 403s. The blade
            // never disables a radio — `Account\Calls` records why.
            'mayChoose' => $settings instanceof AutopilotSettings
                && Gate::allows('update', $settings),
        ]);
    }

    /**
     * The tenant's one location, or null when there is not exactly one.
     *
     * ⚠️ NEVER A HELD PROPERTY AND NEVER AN ID FROM THE BROWSER. Decision 805
     * shipped a component whose `$businessId` was writable from the page; the
     * tenant here comes from `ResolveTenant` via the session, so there is no id
     * for a client to supply. The 403 rather than letting `Tenancy::idOrFail()`
     * throw is `Settings`' own reasoning: internal staff belong to no business,
     * so a signed-in support agent typing this URL is the ordinary way to arrive
     * with nothing resolved, and a 500 reads as our page being broken.
     *
     * ⚠️ AND `sole()` IS DELIBERATELY NOT USED HERE, WHICH IS WHERE THIS SCREEN
     * DIFFERS FROM THE WIZARD'S COPY. `Setup\ReviewRules` runs once, during
     * setup, for a tenant provisioning has just given exactly one location; this
     * panel sits on a page an owner returns to for the life of the account. A
     * `sole()` here turns "no location" or "two locations" into a
     * `ModelNotFoundException` that takes down **the whole `/account` page**,
     * including Pause Everything — a control decision 820 exists to keep
     * reachable in the minute somebody wants it. The first version of this
     * component did exactly that and three unrelated tests caught it.
     *
     * ⛔ **THIS RETURNED NULL FOR EVERY MULTI-LOCATION TENANT UNTIL 3060–3079**
     * (1433, on 1220's rule): gating is per location, this application had no
     * location picker anywhere, and a panel that silently wrote to whichever row
     * came back first would be worse than one that is not there. **That reasoning
     * was right and its premise is now false.** The invite threshold is the one
     * setting the owner explicitly granted a tenant (1143, overruling
     * `CLAUDE.md`'s no-toggles rule by name), and a multi-location tenant could
     * not reach it by any route.
     *
     * ⚠️ **A PICKER IS NOT THE TOGGLE THAT RULING WAS ABOUT** (3065). The
     * threshold is a stored preference a job reads; the selection is a cursor
     * nothing outside this request reads. See `LocationContext`'s docblock.
     */
    private function location(): ?Location
    {
        abort_if(Tenancy::id() === null, 403);

        return app(LocationContext::class)->current();
    }

    /**
     * The row `AutopilotSettingsPolicy` answers about, or null when there is none.
     *
     * ⚠️ **READ HERE AND WRITTEN NOWHERE HERE.** `ReviewGating` remains the only
     * writer of `autopilot_settings.triage_threshold` — this component reads the
     * row so that the policy can be asked about a *row* rather than about a
     * class, which is what lets the policy go on not comparing tenants. The
     * query carries no tenant predicate of its own for the same reason
     * everything else here does not: `AutopilotSettings` is `BelongsToTenant`
     * and the table is `FORCE` row-level secured beneath it.
     *
     * ⚠️ **NULL RATHER THAN A THROW, AND THAT IS 1220'S RULE AND NOT A NEW ONE.**
     * `TenantProvisioner` seeds one of these per location (377), so an absent row
     * means a location that predates that — and `ReviewGating` refuses it with a
     * sentence written for whoever has to backfill it. Reaching for that
     * exception from `render()` would take the whole of `/account` down, Pause
     * Everything included, which is exactly the failure this component's
     * `location()` docblock records.
     *
     * ⚠️ **ONE HELPER WITH A DECLARED RETURN TYPE RATHER THAN A TERNARY AT EACH
     * CALLER**, and the shape is the policy lint's doing rather than taste:
     * `uncalledPolicyOffences()` resolves an authorization subject by type, and
     * a `$x === null ? null : …` inline at the call site is an expression it
     * reports rather than reads. That is the trade 6442 makes on purpose — an
     * unreadable subject is an offence with a named remedy, because treating
     * *"I cannot tell"* as *"probably fine"* is what lets a lint pass
     * vacuously.
     */
    private function settings(): ?AutopilotSettings
    {
        $location = $this->location();

        if ($location === null) {
            return null;
        }

        return AutopilotSettings::query()
            ->where('location_id', $location->id)
            ->first();
    }
}
