<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\Billing\BillingTermRequest;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\Location;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Billing\LocationAllowance;
use App\Services\Billing\PlanCharges;
use App\Services\Billing\Subscriptions;
use App\Services\Tenant\LocationProvisioner;
use App\Support\Admin\AdminAccess;
use App\Support\PlanPricing;
use App\Support\PlanSelection;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use RuntimeException;

/**
 * Extra locations, added by an operator (T176 P25).
 *
 * ⛔ **ADMIN-ASSISTED IS THE TICKET'S OWN ANSWER, NOT A REDUCED VERSION OF IT.**
 * T176 P25: *"operator attaches the SKU per schedule; **self-serve + proration
 * stay OUT**"*. The reason self-serve is out is `BUILD-PLAN`'s open question K —
 * *"'no proration' and 'no refunds' do not say whether adding a location
 * mid-cycle charges immediately at full price or waits for the next cycle"* —
 * which is listed there as **the owner's to answer** (147–149). A tenant-facing
 * button would answer it in the place least likely to be read, which is
 * word-for-word why `BillingTermRequest` refused the quantity for as long as it
 * did.
 *
 * ⛔ **SO THIS SCREEN DOES NOT CHARGE ANYBODY, AND NOTHING HERE CALLS A
 * GATEWAY.** The operator changes the recurring amount at the vendor — where the
 * subscription actually lives, and where a human can apply whatever the customer
 * was told — and then records it here.
 * {@see Subscriptions::recordAdditionalLocations()} writes **no status, no
 * vendor id and no period**, so 2056's webhooks-are-the-source-of-truth rule is
 * untouched. ⚠️ **The screen says this in as many words**, because an operator
 * who believes pressing a button took the money is an operator who does not take
 * it.
 *
 * ## The two acts are separate, and that ordering is the design
 *
 * **Attach the SKU** moves what the plan covers; **Set up a location** makes the
 * rows. They are two buttons rather than one because they can fail
 * independently and because the second is refused past the first: an operator
 * who sets up a location without attaching the SKU has given the product away,
 * and one who attaches the SKU without setting up a location has a customer
 * paying for something they cannot see. **Both halves being visible on one page
 * is what makes the mismatch obvious**, which is more use than a single button
 * that hides which half failed.
 *
 * ⚠️ **IT DOES NOT LIST TENANTS, FOR `PhiTenants`' REASON RATHER THAN THIS
 * SCREEN'S.** `businesses` carries two RLS policies and neither admits platform
 * staff, so the runtime role cannot enumerate businesses at all. An admin acts
 * on one business at a time, named by its number.
 *
 * ⚠️ **EVERY ACT RUNS INSIDE `Tenancy::actingAs()`** so the audit entry lands in
 * that tenant's own log rather than in whichever tenant the admin was viewing
 * (decision 419).
 *
 * ⚠️ **NO BUSINESS NAME AND NO LOCATION NAME EVER REACHES A TOAST** (104).
 * `TenantProvisioner::fallbackName()` copies the owner's own name when nobody
 * ran an audit (334), so neither is reliably impersonal.
 */
final class TenantLocations extends Component
{
    /**
     * The business number an admin typed. A string, because it comes from a text
     * input and an unparseable one has to be answerable rather than fatal.
     */
    public string $lookup = '';

    /** when the email owns more than one business, their ids and names — never anyone else's */
    public array $ownedChoices = [];

    /**
     * ⚠️ `#[Locked]` BECAUSE `lookUp()` IS THE ONLY THING THAT MAY SET IT, AND
     * THE AUDIT ROW IS WRITTEN THERE — `PhiTenants`' reasoning exactly. Without
     * it this arrives in the update payload, so anybody who can reach this
     * component could point it at any business and let `render()` read that
     * tenant's plan and location names with nothing recorded anywhere.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * How many locations beyond the first the plan is to cover, as typed.
     *
     * ⚠️ **A STRING, ON `Account\AssistantLinks`' REASoning.** Livewire hydrates a
     * public property to its declared type before validation runs, so a
     * hand-posted `locations=abc` on a typed `int` is a TypeError rather than a
     * message anybody can act on.
     */
    public string $additionalLocations = '';

    /** The date typed into the trial form, `Y-m-d`. */
    public string $trialUntil = '';

    public string $name = '';

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Put a business in view, and file that we did.
     *
     * ⚠️ **THE READ IS AUDITED IN THE LOOKED-UP TENANT'S OWN LOG**, on
     * `PhiTenants::lookUp()`'s reasoning: a staff read that leaves no trace lets
     * somebody walk the business id space with nothing anywhere to say so. What
     * this screen shows is that business's location names, which
     * `TenantProvisioner::fallbackName()` makes potentially personal (334).
     *
     * ⚠️ **AND IT CAN THROW.** The audit write is not wrapped and
     * `$this->businessId` is set after it, so an unwritable `audit_log` fails the
     * action instead of showing the tenant.
     */
    public function lookUp(AuditService $audit, LocationAllowance $allowance): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->resetErrorBag();

        $typed = trim($this->lookup);

        if ($typed === '') {
            $this->businessId = null;
            Toaster::error('Enter a business number or the owner’s email address.');

            return;
        }

        if (filter_var($typed, FILTER_VALIDATE_EMAIL) !== false) {
            $owner = User::query()->whereRaw('lower(email) = ?', [strtolower($typed)])->first();
            $owned = $owner instanceof User
                ? Tenancy::actingAsUser((int) $owner->id, fn () => Business::withoutGlobalScopes()->where('owner_user_id', (int) $owner->id)->orderBy('id')->get(['id', 'name']))
                : collect();

            if ($owned->isEmpty()) {
                $this->businessId = null;
                $this->ownedChoices = [];
                Toaster::error('No business is owned by that email address.');

                return;
            }

            if ($owned->count() > 1) {
                // The owner has several: show them (they are all this one owner's) and let the operator pick.
                $this->businessId = null;
                $this->ownedChoices = $owned->map(fn (Business $b): array => ['id' => (int) $b->id, 'name' => (string) $b->name])->all();

                return;
            }

            $id = (int) $owned->first()->id;
        } else {
            $id = (int) $typed;
        }

        $this->ownedChoices = [];

        if ($id <= 0) {
            $this->businessId = null;
            Toaster::error('Enter a business number or the owner’s email address.');

            return;
        }

        $business = $this->resolve($id);

        if (! $business instanceof Business) {
            $this->businessId = null;
            Toaster::error("There is no business {$id}.");

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record('business.viewed_by_staff', $this->actor(), $business),
        );

        $this->businessId = $id;

        // Pre-filled with what the plan already covers, so the field says "the
        // total" rather than leaving an operator to guess whether it is a delta.
        // `recordAdditionalLocations()` takes a total for the same reason.
        $this->additionalLocations = (string) Tenancy::actingAs(
            $id,
            fn (): int => $allowance->purchased($business),
        );
    }

    public function choose(int $id): void
    {
        $this->lookup = (string) $id;
        $this->ownedChoices = [];
        $this->lookUp(app(AuditService::class), app(LocationAllowance::class));
    }

    /**
     * Record the locations added to this plan at the gateway.
     *
     * ⛔ **THE WORDING OF EVERY MESSAGE HERE SAYS "RECORD", NEVER "CHARGE".**
     * `22`'s outcome language, with money attached: an operator who reads this as
     * having taken the payment will not go and take it, and the customer gets the
     * product for nothing. The screen's own copy carries the same warning.
     */
    public function attach(AuditService $audit, Subscriptions $subscriptions): void
    {
        $this->authorize(AdminAccess::GATE);

        $business = $this->inView();

        $this->validate([
            'additionalLocations' => [
                'required',
                'integer',
                'min:0',
                // ⚠️ THE SAME CEILING AS THE CHECKOUT THAT SELLS THIS, NAMED FROM
                // THE ONE PLACE. A second literal is a second ceiling, and the day
                // they disagree an operator can record a quantity no page could
                // ever have quoted.
                'max:'.BillingTermRequest::MAX_ADDITIONAL_LOCATIONS,
            ],
        ], [
            'additionalLocations.required' => 'Say how many locations the plan covers beyond the first.',
        ]);

        $count = (int) $this->additionalLocations;

        try {
            Tenancy::actingAs($business->id, function () use ($subscriptions, $business, $count, $audit): void {
                $subscriptions->recordAdditionalLocations($business, $count);

                $audit->record(
                    'subscription.additional_locations_recorded',
                    $this->actor(),
                    $business,
                    ['additional_locations' => $count],
                );
            });
        } catch (RuntimeException $refusal) {
            // ⚠️ THE SERVICE'S OWN SENTENCE, SHOWN RATHER THAN REPLACED. Both of
            // its refusals name something only a person can resolve — there is no
            // subscription, or nobody knows what rate this customer agreed to —
            // and a generic "that did not work" would hide the one useful fact.
            $this->addError('additionalLocations', $refusal->getMessage());

            return;
        }

        Toaster::success('Recorded — set the matching amount at the gateway if you have not already');
    }

    public function extendTrial(AuditService $audit, Subscriptions $subscriptions): void
    {
        $this->authorize(AdminAccess::GATE);

        $business = $this->inView();

        $this->validate([
            'trialUntil' => ['required', 'date', 'after:today'],
        ], [
            'trialUntil.after' => 'Pick a date after today.',
        ]);

        $until = Carbon::parse($this->trialUntil)->endOfDay();

        try {
            Tenancy::actingAs($business->id, function () use ($subscriptions, $business, $until, $audit): void {
                $subscriptions->extendNoCardTrial($business, $until);

                $audit->record(
                    'subscription.no_card_trial_extended',
                    $this->actor(),
                    $business,
                    ['until' => $until->toIso8601String()],
                );
            });
        } catch (RuntimeException $refusal) {
            $this->addError('trialUntil', $refusal->getMessage());

            return;
        }

        Toaster::success('Trial extended to '.$until->format('j M Y').'.');
    }

    /**
     * Set up a location the plan already covers.
     *
     * ⛔ **THE ALLOWANCE IS CHECKED HERE AND NOT ONLY ON THE RENDER.** The render
     * decides whether to draw the form; this decides whether rows are made, and
     * the two are separated by however long an operator leaves a tab open. A
     * guard living only in `render()` is `CLAUDE.md`'s unfalsifiable-inner-guard
     * shape (398): the outer one refuses first in every test, so deleting this
     * line would leave the suite green.
     *
     * ⛔ **AND IT IS CLAIMED UNDER A LOCK, IN THE TRANSACTION THAT MAKES THE
     * ROWS** (4642). Checking and then creating with nothing in between let two
     * presses landing together both be told there was room: the tenant holds one
     * more location than the plan covers, publishing a feedback page and billed
     * for nothing, and every screen afterwards renders a coherent wrong number.
     * ⚠️ **A Livewire button is a plausible place for that to happen** — a slow
     * request and an impatient second click is the ordinary way, and this screen
     * is reachable by more than one operator at once.
     * {@see LocationAllowance::hasRoomUnderLock()} carries the reasoning and takes
     * the same business-row lock `CreditLedger::move()` does.
     */
    public function setUp(
        AuditService $audit,
        LocationAllowance $allowance,
        LocationProvisioner $provisioner,
    ): void {
        $this->authorize(AdminAccess::GATE);

        $business = $this->inView();

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'name.required' => 'Give this location a name — it is what customers will see.',
        ]);

        $name = trim($this->name);

        $refusal = Tenancy::actingAs($business->id, fn (): ?string => DB::transaction(function () use (
            $allowance,
            $business,
            $provisioner,
            $name,
            $audit,
        ): ?string {
            // ⚠️ THE TRANSACTION IS THIS METHOD'S RATHER THAN THE PROVISIONER'S,
            // because the lock has to be held across the check AND the create.
            // `provisionLocation()` opens one of its own; Laravel nests, so that
            // becomes a savepoint inside this one and the lock is released when
            // this commits.
            if (! $allowance->hasRoomUnderLock($business)) {
                return 'This plan covers '.$allowance->permitted($business).' locations and '
                    .$allowance->used($business).' are set up. Attach another to the plan first.';
            }

            // ⚠️ `nameIsPersonal: false` IS AN ARGUED ANSWER RATHER THAN THE
            // DEFAULT BEING TAKEN. The flag exists because
            // `TenantProvisioner::fallbackName()` copies the signing-up person's
            // own name when no audit verified the business, and that name becomes
            // a permanent public slug. There is no fallback on this path: an
            // operator typed this name from what the customer told them, naming a
            // place of business rather than a person.
            $location = $provisioner->provisionLocation($name, nameIsPersonal: false);

            // `29` §2: every sensitive action reaches the append-only log.
            // Setting up a location publishes a feedback page on a public domain
            // and consumes a paid entitlement — and the toast is not the record
            // (104).
            $audit->record(
                'location.created_by_staff',
                $this->actor(),
                $location,
                ['permitted' => $allowance->permitted($business), 'used' => $allowance->used($business)],
            );

            return null;
        }));

        if (is_string($refusal)) {
            $this->addError('name', $refusal);

            return;
        }

        $this->name = '';

        Toaster::success('Set up — this location is live and ready to collect reviews');
    }

    public function render(LocationAllowance $allowance, PlanCharges $charges): View
    {
        $business = $this->businessId === null ? null : $this->resolve($this->businessId);

        if (! $business instanceof Business) {
            return view('livewire.admin.tenant-locations', [
                'business' => null,
                'locations' => new Collection,
                'permitted' => 0,
                'used' => 0,
                'remaining' => 0,
                'addOnPrice' => null,
                'term' => null,
            ]);
        }

        $subscription = Tenancy::actingAs(
            $business->id,
            fn (): ?Subscription => (new Subscriptions)->for($business),
        );

        $selection = $this->selection($subscription);

        /** @var array{locations: Collection<int, Location>, permitted: int, used: int, remaining: int} $state */
        $state = Tenancy::actingAs($business->id, fn (): array => [
            'locations' => Location::query()->orderBy('id')->get(),
            'permitted' => $allowance->permitted($business),
            'used' => $allowance->used($business),
            'remaining' => $allowance->remaining($business),
        ]);

        return view('livewire.admin.tenant-locations', [
            'business' => $business,
            ...$state,

            // ⛔ THE RATE THIS TENANT AGREED TO, ON THEIR OWN TERM (4347). It was
            // today's registry figure, on the argument that an operator is
            // quoting a location "not yet bought" — and the very next thing the
            // operator does is press a button that records the count against the
            // **stored** rate, because `recordAdditionalLocations()` never
            // re-reads a price (3443). The screen and the writer therefore
            // disagreed by exactly the amount any offer moves the add-on by, on
            // the screen whose whole job is to quote it.
            'addOnPrice' => PlanPricing::format(
                $charges->agreedAdditionalLocationPriceFor($subscription, $selection),
            ),
            'term' => $selection->term,
            'onNoCardTrial' => $subscription?->status === SubscriptionStatus::PendingCheckout,
            'noCardTrialEndsAt' => Tenancy::actingAs($business->id, fn (): ?Carbon => (new Subscriptions)->noCardTrialEndsAt($business, $subscription)),
        ]);
    }

    /**
     * The tenant's own term, so an add-on is quoted on the price they are on.
     *
     * A row with no term is monthly — the same default both checkout paths apply
     * to a caller that made no choice.
     */
    private function selection(?Subscription $subscription): PlanSelection
    {
        return PlanSelection::fromInput($subscription?->term?->value, false);
    }

    /**
     * The business an action is about, or a refusal.
     *
     * ⚠️ **RE-RESOLVED RATHER THAN CARRIED.** `$businessId` is `#[Locked]`, so it
     * cannot be moved from the browser; what this closes is the other direction —
     * a business deleted between the lookup and the press — where a stale model
     * would have an action write into a tenant that no longer exists.
     */
    private function inView(): Business
    {
        $business = $this->businessId === null ? null : $this->resolve($this->businessId);

        abort_if(! $business instanceof Business, 404);

        return $business;
    }

    private function resolve(int $id): ?Business
    {
        return Tenancy::actingAs(
            $id,
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );
    }

    /**
     * Who is recording this, for the audit entry.
     *
     * An actor label, matching `PhiTenants` and `LegalDocuments`: automation is a
     * first-class actor in this log, so a user foreign key would have nothing to
     * point at for most entries.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
