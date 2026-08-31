<?php

declare(strict_types=1);

namespace App\Livewire\Setup;

use App\Enums\WizardStep;
use App\Livewire\Setup\Concerns\SetupStep;
use App\Models\Business;
use App\Services\Billing\PaymentMethodReplacement;
use App\Services\Billing\Subscriptions;
use App\Services\Consent\OwnerConsentService;
use App\Services\Consent\OwnerNotifyDisclosure;
use App\Services\Setup\SetupFlow;
use App\Services\Trust\FirstWeekPath;
use App\Support\HashedIp;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The wizard's terminal step — and, since this class started calling
 * `SetupFlow::complete()`, the one genuine activation signal this application
 * has.
 *
 * ⚠️ **`SetupFlow::complete()` HAD NO CALLER AT ALL BEFORE THIS.** `29` §6.2's
 * signup order ends the wizard here, `mayComplete()`, `complete()` and their
 * own tests (`SetupFlowTest`) were all written and green, and nothing in
 * `app/` ever invoked the write path they exist to protect —
 * `wizard_progress.completed` stayed `false` for every tenant that ever
 * finished onboarding. Decision 272's shape: not a missing table this time, a
 * missing call to a method that already worked. `SetupController` already
 * *reads* `completed` to decide where `/setup` redirects, so the column had a
 * reader and no writer — 620's inversion, one column over from the more usual
 * shape.
 *
 * ⚠️ **GUARDED BY `mayComplete()`, NOT UNCONDITIONAL**, because `29` §7.2 lets
 * an owner skip every step except Google and consent basics — so a bookmark or
 * a Back-then-forward press can land here with `ReviewRules` unanswered, and
 * `complete()` throws in that case (its own docblock). This checks before
 * calling rather than catching after, and idempotently: a wizard already
 * marked complete (a second visit to this route) skips both writes rather than
 * re-running them.
 *
 * ⚠️ **`FirstWeekPath::begin()` RUNS ONLY ON THE TRANSITION**, not on every
 * mount — `$this->progress->completed` is checked *before* `complete()` runs,
 * so a returning owner who bookmarked this page does not restart a clock that
 * already started. `begin()` is itself idempotent besides (`business_id
 * UNIQUE`), so this guard is belt-and-braces rather than the only thing
 * standing between one tenant and two rows — the database still refuses the
 * second one either way.
 *
 * ⚠️ **`begin()` RUNS *BEFORE* `complete()`, AND THE ORDER IS THE RECOVERY**
 * (1890). It used to run after, which made the pair non-transactional in the
 * one direction that cannot heal: `complete()` persists `completed = true`, and
 * the early return above then means a `begin()` that threw is **never retried**
 * — that tenant silently never gets a first week, and nothing anywhere says so.
 * Reversed, both failures recover on the next visit: `begin()` throwing leaves
 * the wizard incomplete so this whole block runs again, and `complete()`
 * throwing leaves a run row that the second attempt's `begin()` returns
 * untouched. A `DB::transaction()` around both would also close it, and is the
 * worse trade — it rolls back a good wizard completion because a derived row
 * failed, when the wizard completion is the one of the two an owner would
 * notice losing.
 *
 * ⚠️ **"BOTH FAILURES RECOVER ON THE NEXT VISIT" ASSUMES THERE IS A NEXT VISIT,
 * AND 1890 DID NOT SAY SO** (1956). If `complete()` throws after `begin()`
 * succeeded and the owner never comes back, the tenant is left with a live
 * `first_week_runs` row while `wizard_progress.completed` is still false — and
 * the 05:00 sweep goes on emailing them about a first week they never finished
 * starting. This is strictly better than the direction it replaced (a tenant who
 * silently never gets a first week at all, with nothing recording that one was
 * owed) and it is still worth naming: the window is one failed `complete()`
 * wide.
 *
 * ⚠️ **1956 NAMED THE WRONG MECHANISM FOR IT, AND THE CONCLUSION ABOVE SURVIVES
 * ANYWAY** (2014). `trust:advance-first-week-paths` does **not** dispatch on the
 * run row: it walks every user and dispatches for *every* business of every
 * owner, run row or not, and says so in its own docblock. The run-row check
 * lives one layer down, in `AdvanceFirstWeekPathJob::tick()`, which returns
 * `skipped: not_started` when `FirstWeekPath::for()` finds nothing — so a
 * stranded row is still ticked, which is the part 1956 got right.
 *
 * That also moves where a fix would go, and makes it cheaper than 1956 claimed:
 * `tick()` runs **inside** the established tenant, so asking
 * `wizard_progress.completed` there is an ordinary scoped read next to the
 * `not_started` check — not the second `withoutGlobalScopes()` widening 1956
 * described, which is a thing the *sweep* would need and the sweep is not where
 * this belongs. What still keeps it out of a fix wave is that it makes
 * `wizard_progress.completed` a second definition of "this tenant has started"
 * alongside the existence of the run row — and 1890 deliberately creates the row
 * *first*, so the two disagree by design for the width of one request. Choosing
 * which one the sweep believes is a decision, not a line.
 */
#[Layout('components.setup.layout')]
final class Done extends Component
{
    use SetupStep;

    /**
     * Unchecked by default, `29` §2's own rule for every consent checkbox on
     * this platform (10540, the owner ruling of 2026-08-27).
     */
    public bool $ownerConsent = false;

    public string $ownerMobile = '';

    public function mount(SetupFlow $flow, FirstWeekPath $firstWeekPath): void
    {
        $this->mountSetupStep();

        if ($this->progress->completed) {
            return;
        }

        if (! $flow->mayComplete($this->progress)) {
            return;
        }

        $firstWeekPath->begin(Business::query()->sole());

        $flow->complete($this->progress);
    }

    /**
     * The end of onboarding, and the first place this application asks for a
     * card (9201, 9229).
     *
     * ⛔ **REGISTRATION USED TO OPEN ON A CHECKOUT AND NO LONGER DOES**, so an
     * owner reaches this screen having bought nothing — and until 9229 the only
     * thing on it was one link reading *"Your account"*. 9201's own words are
     * *"the card is offered at the END of onboarding and from the account
     * page"*, and this is the first of the two.
     *
     * ⛔ **IT ASKS THE SERVICE THAT ALREADY DECIDES THIS, RATHER THAN READING
     * THE SUBSCRIPTION ROW ITSELF.** `PaymentMethodReplacement::offer()` reads
     * our own row and opens no socket — its docblock says so, and `/account/plan`
     * asks it on every render for the same reason. There are already **seven**
     * spellings of *"is there a vendor subscription id on this row"* in `app/`;
     * an eighth, on the terminal wizard screen, is the second reader that grows
     * its own opinion and disagrees with the first the day a gateway is added.
     *
     * ⚠️ **THE INVITATION IS WITHHELD RATHER THAN SHOWN AND DISABLED** (1220).
     * Somebody who has already bought — a returning owner who bookmarked this
     * page, or one who bought from `/account/plan` and walked the wizard again —
     * is offered nothing here, because *"Add a card"* to a paying customer is a
     * false offer and a support ticket. The way back to their own plan is the
     * account link below, which every visitor gets.
     *
     * ⚠️ **`sole()` IS THIS FILE'S EXISTING IDIOM AND `mountSetupStep()` IS WHAT
     * MAKES IT SAFE**: it aborts 403 when no tenant is resolved, so the scoped
     * query beneath this line can only ever see the one business the global
     * scope admits.
     */
    public function render(PaymentMethodReplacement $replacements, Subscriptions $subscriptions, OwnerConsentService $owner): View
    {
        $business = Business::query()->sole();

        return view('livewire.setup.done', [
            'mayBuyAPlan' => $replacements
                ->offer($business)
                ->invitesAFirstPlan(),

            // ⛔ THE TRIAL IS BOUNDED NOW, AND THIS SCREEN SAID IT WAS NOT
            // (9328, 9332). Its own comment argued at length that a panel here
            // reading "your free trial has started" would describe a clock
            // nothing in this application runs. One runs. Withholding the date
            // now would make this the last screen of onboarding that knows when
            // the product stops and does not say.
            //
            // ⚠️ NULL FOR ANYBODY NOT ON THE NO-CARD TRIAL — somebody who
            // bought in another tab and walked the wizard again gets no date,
            // for the same reason they get no card invitation.
            'trialEndsOn' => $subscriptions
                ->noCardTrialEndsAt($business, $subscriptions->for($business))
                ?->translatedFormat('j F Y'),

            // 10540, phase 5 — `setup/done.blade.php` has promised a text
            // message since the day it shipped, and this is what makes the
            // sentence true rather than aspirational for a given owner.
            'ownerNotifyPermit' => $owner->permit($business),
            // ⚠️ RENDERED FROM THE STORED CONSTANT, NEVER TYPED IN THE
            // TEMPLATE (10660) — `OwnerNotifyDisclosure`'s own docblock: "the
            // version and the words live together so the two cannot drift".
            'ownerNotifyDisclosure' => OwnerNotifyDisclosure::TEXT,
        ]);
    }

    /**
     * Capture the owner's own consent to be texted (10540, the owner ruling
     * of 2026-08-27) — `ImportCustomers::proof()`'s pattern for the proof
     * blob, `AutoRenewalAcknowledgements`' pattern for the rest.
     *
     * ⚠️ **A WIZARD STEP IS A SUPPORT SURFACE, AND THIS ONE IS DELIBERATELY
     * SKIPPABLE** — this screen renders and `mount()` still completes the
     * wizard whether or not this method is ever called. What stops the skip
     * being `FindBusiness::skip()`'s kind of silent-forever is that the same
     * capture is offered again on `/account/settings`, so leaving this blank
     * is a deferral rather than a door that closes.
     */
    public function saveOwnerNotify(): void
    {
        $this->validate([
            'ownerMobile' => ['required', 'string'],
            'ownerConsent' => ['accepted'],
        ], [
            'ownerConsent.accepted' => 'Check the box to get texts about your account.',
        ]);

        $request = request();

        try {
            app(OwnerConsentService::class)->capture(
                $this->ownerMobile,
                [
                    // ⚠️ NEVER `$request->ip()` — `29` §2 forbids storing a
                    // raw IP, `ImportCustomers::proof()`'s own reasoning.
                    'ip_hash' => HashedIp::of($request),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                    'url' => $request->fullUrl(),
                ],
                'user:'.Auth::id(),
                // ⚠️ `$this->ownerConsent` RATHER THAN A LITERAL — the
                // `validate()` call above already requires it to be true to
                // reach this line; passed through explicitly rather than
                // assumed so `OwnerConsentService::capture()`'s own guard has
                // something real to check (10660, "the guard that cannot
                // fail").
                consentChecked: $this->ownerConsent,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('ownerMobile', $e->getMessage());

            return;
        }

        $this->ownerMobile = '';
        $this->ownerConsent = false;

        Toaster::success("You're set — we'll text that number when something needs you.");
    }

    protected function step(): WizardStep
    {
        return WizardStep::Done;
    }
}
