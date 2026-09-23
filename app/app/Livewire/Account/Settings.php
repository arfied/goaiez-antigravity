<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\Business;
use App\Models\Location;
use App\Services\Consent\OwnerConsentService;
use App\Services\Consent\OwnerNotifyDisclosure;
use App\Services\Export\ExportBuilder;
use App\Services\Tenant\LocationContext;
use App\Services\Tenant\LocationTimezone;
use App\Services\Tenant\TenantPause;
use App\Support\HashedIp;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The owner's account screen, and the home of Pause Everything (`29` §11.2 row
 * 5).
 *
 * ⚠️ **A CONTROL NOBODY CAN REACH IS THE DEFECT THIS SLICE IS FIXING**, so the
 * screen ships with the service rather than after it. `TenantPause` without a
 * door is decision 272's shape — `Business::provision()`, `autopilot_settings`,
 * `feedback_pages`, `plugins`, `subscriptions` and `users.role` were each
 * written, documented and unreachable, and every one of them looked finished.
 * The reachability half matters more than usual here: the whole point of a
 * pause is that the owner can use it *in the minute they want it*, without
 * asking us.
 *
 * ⚠️ **AND IT IS THE FIRST AUTHENTICATED OWNER SURFACE THAT IS NOT THE WIZARD.**
 * `/setup` was the only one, which is why `Impersonation` redirects there and
 * why `SetupController`'s docblock says a finished owner has nowhere to go.
 * This is where the rest of `29` §7's owner settings land as they are built;
 * today it holds one control, and says so rather than padding itself out.
 *
 * ## Two things it deliberately does not do
 *
 * **It asks for no reason.** An owner pressing their own stop button owes
 * nobody an explanation, and a required text box is one more thing between them
 * and the control at the moment they want it most. Support's path carries a
 * typed reason, because that is a different person acting on someone else's
 * account (`28` §9.5) — the column is nullable for exactly that split.
 *
 * **It confirms resuming, not pausing.** Pausing is the safe direction and is
 * fully reversible from this screen; starting the system up again is the one
 * that begins acting on customers, so it is the one that gets a second look.
 * A confirm step on the emergency stop is a confirm step nobody wants.
 */
#[Layout('components.account.layout')]
final class Settings extends Component
{
    /** Whether the resume confirmation is showing. */
    public bool $confirmingResume = false;

    /** Whether Advanced Dashboard power tools are enabled. */
    public bool $advancedEnabled = false;

    /**
     * The timezone this location keeps its hours in (1597).
     *
     * Held as a string rather than a `?string` for `Account\ReviewRules`' stated
     * reason: Livewire hydrates a public property to its declared type before
     * validation runs, so a hand-posted non-string would be a `TypeError`
     * instead of a validation message.
     */
    public string $timezone = '';

    /**
     * 10540, the owner ruling of 2026-08-27 — this screen's `Done.php`
     * sibling in the setup wizard, offered again here so that not filling it
     * in during onboarding is a deferral rather than a door that never opens
     * again (`FindBusiness::skip()`'s own shape, refused).
     */
    public bool $ownerConsent = false;

    public string $ownerMobile = '';

    /**
     * Whether the capture form is showing for a business that already has a
     * live permit — the mistyped-digit correction 10660 names. False by
     * default: a consented owner sees the confirmation and a way to stop,
     * never the form, until they ask for it via {@see self::editOwnerMobile()}.
     */
    public bool $editingOwnerMobile = false;

    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);

        $business = Business::find(Tenancy::id());
        $this->advancedEnabled = (bool) ($business?->hasAdvancedDashboard() ?? false);
        $location = $this->location();

        $this->timezone = $location === null ? '' : ($location->timezone ?? '');
    }

    /**
     * See `App\Livewire\Setup\Done::saveOwnerNotify()` — the identical
     * capture, offered on the standing surface rather than the one-time walk.
     */
    public function saveOwnerNotify(OwnerConsentService $owner): void
    {
        $this->validate([
            'ownerMobile' => ['required', 'string'],
            'ownerConsent' => ['accepted'],
        ], [
            'ownerConsent.accepted' => 'Check the box to get texts about your account.',
        ]);

        $request = request();

        try {
            $owner->capture(
                $this->ownerMobile,
                [
                    'ip_hash' => HashedIp::of($request),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                    'url' => $request->fullUrl(),
                ],
                $this->actor(),
                // ⚠️ SEE `OwnerConsentService::capture()`'s OWN DOCBLOCK
                // (10660) — `$this->ownerConsent` RATHER THAN A LITERAL, so
                // the guard downstream has something real to check.
                consentChecked: $this->ownerConsent,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('ownerMobile', $e->getMessage());

            return;
        }

        $this->ownerMobile = '';
        $this->ownerConsent = false;
        $this->editingOwnerMobile = false;

        Toaster::success("You're set — we'll text that number when something needs you.");
    }

    /**
     * An owner changing their mind, from the screen where they said yes.
     */
    public function stopOwnerNotify(OwnerConsentService $owner): void
    {
        $owner->stop((int) $this->business()->getKey(), $this->actor());

        Toaster::success('We will stop texting that number.');
    }

    /**
     * Open the capture form for a business that already has a live permit —
     * the mistyped-digit correction 10660 names: without this, correcting a
     * typo meant pressing "Stop texting me" first, which is the one path
     * `OwnerConsentService::stop()` writes an audit row for and therefore
     * the more visible way to change a number, not the more natural one.
     *
     * ⚠️ **CAPTURING AGAIN IS THE EXISTING MECHANISM, NOT A NEW ONE** — see
     * `OwnerConsentService::capture()`'s own "a fresh capture always clears
     * a prior stop" argument (decision 10545), which applies identically to
     * a live permit: a fresh, unchecked box and the full disclosure shown
     * again is a distinct, provable act of consent, whether or not a STOP
     * came first.
     */
    public function editOwnerMobile(): void
    {
        $this->editingOwnerMobile = true;
    }

    public function cancelEditOwnerMobile(): void
    {
        $this->editingOwnerMobile = false;
        $this->ownerMobile = '';
        $this->ownerConsent = false;
        $this->resetErrorBag(['ownerMobile', 'ownerConsent']);
    }

    /**
     * Save the timezone — the second control on this screen, and the reason
     * slice 5 unblocks marketing anywhere a statute actually applies (1597).
     *
     * ⚠️ **THE OWNER SETS IT AND NOTHING DERIVES IT** (1598). Six US states
     * straddle two zones, and a state-to-zone table is wrong in the direction
     * that texts people earlier — see `LocationTimezone`, which is the only
     * writer and refuses independently of this screen (398).
     *
     * No confirm step: this is a standing preference that takes effect on the
     * next send, which is `Account\ReviewRules`' call rather than `pause()`'s.
     */
    public function saveTimezone(LocationTimezone $timezones): void
    {
        $location = $this->location();

        abort_if($location === null, 404);

        $this->validate([
            'timezone' => ['required', 'string', Rule::in(LocationTimezone::offered())],
        ], [
            'timezone.required' => 'Choose the timezone this business keeps its hours in.',
            'timezone.in' => 'Choose the timezone this business keeps its hours in.',
        ]);

        try {
            $timezones->set($location, $this->timezone, $this->actor());
        } catch (InvalidArgumentException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        // Outcome language, and no business name in the toast (104).
        Toaster::success('Saved — we will only text your customers during their day');
    }

    public function pause(TenantPause $pause): void
    {
        $business = $this->business();

        $pause->pause($business, $this->actor());

        $this->confirmingResume = false;

        // Outcome language, and the verb survives the flow (`22`, `29` §5.7).
        // No business name — toast text carries no personal data (104), and
        // `TenantProvisioner::fallbackName()` copies the owner's own name when
        // nobody ran an audit, so a business name is not reliably impersonal
        // (decision 334's finding, one surface over).
        Toaster::success('Everything is paused');
    }

    public function confirmResume(): void
    {
        $this->confirmingResume = true;
    }

    public function cancelResume(): void
    {
        $this->confirmingResume = false;
    }

    public function resume(TenantPause $pause): void
    {
        $business = $this->business();

        $pause->resume($business, $this->actor());

        $this->confirmingResume = false;

        Toaster::success('Everything is running again');
    }

    public function toggleAdvanced(): void
    {
        $business = Business::find(Tenancy::id());
        if ($business && $business->owner_user_id !== Auth::id()) {
            abort(403);
        }
        if (! $business) {
            return;
        }

        $this->advancedEnabled = ! $this->advancedEnabled;
        $business->update(['advanced_dashboard_enabled' => $this->advancedEnabled]);

        if ($this->advancedEnabled) {
            Toaster::success('Advanced tools turned on. You can now access power features under Advanced.');
        } else {
            Toaster::success('Advanced tools turned off. Power features hidden.');
        }
    }

    public function render(TenantPause $pause, ExportBuilder $exports, OwnerConsentService $owner): View
    {
        $business = $this->business();

        // ⚠️ "DOWNLOAD MY DATA" IS A PLAIN FORM POST, NOT A METHOD ON THIS CLASS
        // (1900). It was `requestExport()` here, and a *suspended* owner could
        // never reach it: `SuspendedTenantStatus` redirects them off `/account`,
        // and a Livewire button posts to `livewire.update` rather than to this
        // screen's own route — so no route-name exemption narrower than "every
        // Livewire action in the application" could have rescued it. `28` §3.7
        // says exporting is never gated, so the control moved to
        // `account.data-export.request` and this screen renders the form.
        // See `TenantExportRequestController`.
        $latestExport = $exports->mostRecent();

        return view('livewire.account.settings', [
            'paused' => $pause->isPaused($business),
            'pausedAt' => $business->paused_at,
            // ⚠️ SHOWN ONLY WHEN SOMEBODY ELSE PAUSED IT, which is the state
            // that otherwise reads as the product being broken. Support pauses
            // on a tenant's behalf under `28` §9.5, and an owner arriving at a
            // stopped account with no explanation on the screen files a ticket
            // about an outage.
            'pauseReason' => $business->pause_reason,
            'pausedBySupport' => is_string($business->paused_by)
                && ! str_starts_with($business->paused_by, 'user:'),
            // Absent rather than disabled when there is not exactly one location
            // (1220's rule), which is `Account\ReviewRules::location()`'s call
            // for the same reason: the timezone is per location and this
            // application has no location picker anywhere.
            'timezoneAvailable' => $this->location() !== null,
            'timezones' => LocationTimezone::offered(),
            // ⚠️ ONE PICKER FOR THE WHOLE OF `/account`, NOT ONE PER PANEL. This
            // screen hosts both the timezone panel and the nested
            // `Account\ReviewRules`, and both act on the same location — two
            // controls setting one cursor would let an owner put them out of
            // step with each other on the same page.
            'locationOptions' => app(LocationContext::class)->options(),
            'selectedLocation' => app(LocationContext::class)->current(),
            'latestExport' => $latestExport,
            'exportDownloadUrl' => $latestExport === null ? null : $exports->downloadUrl($latestExport),
            // 10540 — null when nobody has consented, or the owner said STOP;
            // the identifier when there is a live permit. `permit()` refuses
            // exactly one way for both, on purpose (its own docblock), so this
            // screen shows one control either way rather than a third state.
            'ownerNotifyPermit' => (function () use ($owner, $business) {
                try {
                    return $owner->permit($business);
                } catch (\Throwable) {
                    return null;
                }
            })(),
            'editingOwnerMobile' => $this->editingOwnerMobile,
            // ⚠️ RENDERED FROM THE STORED CONSTANT, NEVER TYPED IN THE
            // TEMPLATE (10660) — `OwnerNotifyDisclosure`'s own docblock: "the
            // version and the words live together so the two cannot drift".
            // The blade used to carry its own copy of this sentence.
            'ownerNotifyDisclosure' => OwnerNotifyDisclosure::TEXT,
        ]);
    }

    /**
     * The location this screen is acting on, or null when the tenant has none.
     *
     * ⛔ **THIS RETURNED NULL FOR EVERY MULTI-LOCATION TENANT UNTIL 3060–3079,
     * AND THIS PANEL WAS THE LEAST HONEST OF THE FOUR THAT WENT DARK** (3062).
     * `Account\Visibility` explains its suppression and `Account\WidgetInstall`
     * at least prints a sentence; the timezone panel has no `@else` branch, so
     * it simply was not rendered. **`locations.timezone` decides the hours a
     * tenant may lawfully text their customers** (1597, 1598), so the control
     * that sets it disappeared without a word — a compliance surface going
     * quiet on exactly the tenants most likely to span two of them.
     *
     * ⚠️ **`sole()` IS STILL DELIBERATELY NOT USED**, and the reason is this
     * screen rather than a style preference: a `ModelNotFoundException` here
     * takes down the whole `/account` page, Pause Everything included — the
     * control decision 820 exists to keep reachable in the minute somebody
     * wants it. `Account\ReviewRules` records the same finding after three
     * unrelated tests caught it (1433).
     */
    private function location(): ?Location
    {
        if (Tenancy::id() === null) {
            return null;
        }

        return app(LocationContext::class)->current();
    }

    /**
     * The tenant in context, re-read on every action.
     *
     * Never a held property: an action would otherwise run against a pause
     * state that changed in another tab, or — worse — that support changed
     * while the owner had this screen open. `ResolveTenant` establishes the
     * tenant from the session, so there is no id here for a client to supply
     * and nothing to lock.
     */
    private function business(): Business
    {
        // ⚠️ REFUSED RATHER THAN RESOLVED WHEN THERE IS NO TENANT, and 403
        // rather than letting `Tenancy::idOrFail()` throw. Internal staff belong
        // to no business by design (`28` §9.1), so a signed-in support agent who
        // types this URL is the ordinary way to arrive here with nothing
        // resolved — and `TenantNotResolved` reaching the renderer is a 500,
        // which reads as our page being broken rather than as a page that is
        // not theirs.
        $id = Tenancy::id();

        abort_if($id === null, 403);

        return Business::query()->findOrFail($id);
    }

    /**
     * Who did this, in `audit_log`'s vocabulary.
     *
     * `user:` is what distinguishes an owner's own pause from support's on the
     * row itself, which is what `render()` reads to decide whether to explain
     * the pause to somebody who did not apply it.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'owner' : 'user:'.$id;
    }
}
