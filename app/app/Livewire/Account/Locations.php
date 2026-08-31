<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\ActuationTier;
use App\Enums\WordPressConnectionRefusal;
use App\Livewire\Admin\TenantLocations;
use App\Models\Business;
use App\Models\Location;
use App\Models\Subscription;
use App\Services\Actuation\ActuationActor;
use App\Services\Actuation\ActuationTiers;
use App\Services\Actuation\SpeedFixes;
use App\Services\Actuation\WordPress\ApplicationPassword;
use App\Services\Actuation\WordPress\WordPressCredentials;
use App\Services\Billing\LocationAllowance;
use App\Services\Billing\PlanCharges;
use App\Services\Billing\RenewalReminders;
use App\Services\Billing\Subscriptions;
use App\Services\Content\AuthorByline;
use App\Services\Feedback\ReviewSign;
use App\Services\Feedback\ReviewSigns;
use App\Services\Tenant\LocationDetails;
use App\Services\Tenant\LocationWebsite;
use App\Support\PlanPricing;
use App\Support\PlanSelection;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The business's locations, and what its plan covers (T176 P25).
 *
 * ⛔ **THE EXTRA-LOCATION SKU HAD A PRICE AND NO BUYER** (2753): `PlanCharges`
 * has priced additional locations correctly since row 22 slice D and *"every
 * purchase path passes zero"*. T176 P25's own words for the gap are *"no
 * Locations screen, no add-location billing flow"*, and this is the first half.
 *
 * ⛔ **IT READS AND IT DOES NOT WRITE, WHICH IS THE TICKET'S OWN SHAPE RATHER
 * THAN A SIMPLIFICATION.** T176 P25: *"operator attaches the SKU per schedule;
 * **self-serve + proration stay OUT**"*. A first draft of this screen had an Add
 * button on it and it was removed: adding a location to a plan that is already
 * running changes what a gateway charges partway through a cycle, and **open
 * question K — what a mid-cycle addition costs — is undecided and is the
 * owner's** (147–149, and `BUILD-PLAN` §K names it as theirs). A tenant-facing
 * button would have answered it in the place least likely to be read, which is
 * word-for-word the reason `BillingTermRequest` refused the quantity for as long
 * as it did. The operator path is {@see TenantLocations}.
 *
 * ⚠️ **WHAT AN OWNER IS SHOWN INSTEAD IS A PRICE AND A ROUTE TO A PERSON**
 * (1220's rule that the honest shape for "not available" is absent rather than
 * disabled). The figure comes from
 * {@see PlanCharges::agreedAdditionalLocationPriceFor()} on **their own term** —
 * a monthly tenant is quoted the monthly rate — and it is formatted by the one
 * formatter, never printed as a literal (512). ⚠️ **It is the rate they agreed
 * to rather than today's, since 4347**: 3443 grandfathers the add-on price and
 * the writer bills the stored one, so quoting today's put a number on this page
 * that `recordAdditionalLocations()` will not use.
 *
 * ⛔ **THIS SCREEN AND {@see RenewalReminders} NOW READ THE SAME FIGURE, AND THE
 * PARAGRAPH THAT SAID OTHERWISE OUTLIVED ITS OWN FIX BY ONE DIFF (4347).** It
 * read *"AND IT IS TODAY'S REGISTRY RATE, DELIBERATELY, WHICH IS THE ONE PLACE
 * THIS SCREEN AND `RenewalReminders` DIFFER FOR A REASON … this quotes what a
 * location they have not yet bought would cost, and that is the current price by
 * definition"* — the exact argument 4347 overturned, left standing three lines
 * below the sentence overturning it. **A docblock arguing for the behaviour the
 * code no longer has is worse than no docblock** (314–316): the next reader meets
 * two claims, believes the older and longer one, and puts the defect back.
 * `PlanCharges` does still tell "agreed" and "today's" apart by what you hand it
 * — a `PlanSelection` is something somebody is about to buy — which is why this
 * hands it **both** the selection and the row, and takes today's rate only when
 * there is no agreed one to honour.
 *
 * ---------------------------------------------------------------------------
 * IT WRITES ONE THING SINCE ROW 9 SLICE B, AND IT IS THE MOST CONSEQUENTIAL
 * SENTENCE ON THE SCREEN
 * ---------------------------------------------------------------------------
 * ⛔ **THE WEBSITE ADDRESS IS PASTED AND CONFIRMED HERE, NEVER INFERRED** (5540,
 * `BUILD-PLAN` §2.11.2). `locations.website_url` had no writer in `app/` at all —
 * `pixel_tenant_id`'s shape (4961) — and it is the address a later slice
 * publishes pages to and injects markup into. Decision 1083's rule for Search
 * Console properties applies verbatim and for a larger stake: *a business whose
 * site is a page on a franchisor's domain must not be silently actuated against
 * the franchisor's site.*
 *
 * ⚠️ **TWO PRESSES, AND THE SECOND ONE IS THE POINT.** {@see self::review()}
 * normalises what was typed and persists **nothing**;
 * {@see self::confirmWebsite()} is the press that writes, and it is the only
 * place in this application that narrows a boolean into
 * {@see LocationWebsite::confirm()}'s literal-`true` parameter (220). What the
 * owner confirms is the normalised address rendered back to them, so nothing is
 * silently stripped after they agreed to it.
 *
 * ⚠️ **HERE RATHER THAN ON `Account\Settings`, WHERE THE TIMEZONE LIVES** (5549).
 * That screen's location panel is absent unless the tenant has exactly one
 * location (1433), and a website is per-location: a two-location tenant would
 * have had no way to answer for their second site at all.
 *
 * ---------------------------------------------------------------------------
 * AND SINCE 2026-08-20 IT IS ALSO WHERE A BUSINESS TELLS US ITS OWN PHONE
 * NUMBER AND ADDRESS — THE TWO COLUMNS NOBODY HAD EVER WRITTEN
 * ---------------------------------------------------------------------------
 * ⛔ **`locations.primary_phone` AND `locations.address` HAD NO WRITER IN `app/`
 * EITHER** (6100) — `LocationFactory` filled both and nothing else did, which is
 * `website_url`'s own defect one paragraph above, on the pair with the larger
 * blast radius. The phone is printed verbatim into the carrier-mandated HELP
 * reply, so for every tenant in production a member of the public asking *"who
 * is texting me"* was answered with `support@goaiez.com` instead of the
 * business. See {@see LocationDetails}.
 *
 * ⚠️ **ONE PRESS, TWO BOXES, AND THE BOXES ARRIVE FILLED WITH WHAT WE HOLD**
 * (6103). `saveAbout()`'s ceremony rather than `confirmWebsite()`'s two-step,
 * because there is nothing to render back: an owner typing their own phone
 * number is not being shown a value we derived, and a second press confirming
 * the string they just typed would confirm nothing (5750's argument, applied the
 * other way round). **Pre-filling from the row is what makes an empty box mean
 * something** — *we do not have this* rather than *leave it alone* — which is
 * how a service-area business with no public address says so.
 *
 * ⛔ **AND NOTHING IS PRE-FILLED FROM GOOGLE.** `PlaceSummary` carries a
 * formatted address and a national phone number and this screen offers neither,
 * because rows 13 and 14 compare this record against Google and a record that is
 * Google's copy makes that comparison vacuous (6106).
 *
 * ---------------------------------------------------------------------------
 * AND SINCE ROW 9 SLICE G IT IS WHERE AN OWNER HANDS US WRITE ACCESS TO THEIR
 * WEBSITE — THE MOST CONSEQUENTIAL PRESS IN THIS APPLICATION
 * ---------------------------------------------------------------------------
 * ⛔ **HERE RATHER THAN ON A SCREEN OF ITS OWN, AND THE ARGUMENT IS THAT IT IS
 * THE SAME SUBJECT** (5748). The website address, the page a byline points at
 * and the login that lets us edit that website are three answers about one
 * website, and `CLAUDE.md`'s first tie-breaker is less support surface. A
 * separate screen would also need its own nav entry, which is a second place an
 * owner has to find before anything can be actuated at all.
 *
 * ⛔ **THE APPLICATION PASSWORD IS A PUBLIC LIVEWIRE PROPERTY AND THAT IS A
 * PROPERTY OF THE FRAMEWORK RATHER THAN A CHOICE — SO IT IS HANDLED
 * DELIBERATELY** (5749). Livewire serialises every public property into the
 * snapshot it sends back to the browser, so a secret left in one is echoed into
 * the DOM on every subsequent request. Three things follow and all three are in
 * the code: the field is **deferred** (plain `wire:model`, never `.live`), so it
 * crosses the wire once with the press rather than once per keystroke;
 * {@see self::connectWordPress()} calls `reset()` on **every** path including
 * the refusals, so the response snapshot never carries it; and it is never put
 * into a validation message, a toast, an exception or a log — those go through
 * {@see WordPressConnectionRefusal::owner()}, which is a fixed sentence per
 * refusal and nothing else. ⚠️ **THIS CARRIED THE COUNT AND THE COUNT WAS WRONG
 * FROM THE DAY 6262 ADDED AN EIGHTH CASE** — 9800–9819 made it a tenth, and the
 * number is deleted rather than corrected, which is what that enum's own
 * docblock already says about counting itself in prose.
 *
 * ⚠️ **`#[Locked]` ON EVERY PROPERTY THAT NAMES A LOCATION.** A client-settable
 * one would let a posted value point a connect, a disconnect or an About-page
 * write at a different location — and while the Gate and the tenant scope would
 * still refuse another tenant's, they would not refuse *this* tenant's other
 * site. Decision 5735 is the same finding on `Admin\AccountAudit` and cost an
 * unaudited cross-tenant read of the compliance record.
 *
 * ⚠️ **THE TIER BESIDE EACH ADDRESS IS DERIVED ON EVERY RENDER AND STORED
 * NOWHERE** ({@see ActuationTiers}), and "we do not know yet" is a state of its
 * own rather than the weakest tier — see that class for why collapsing the two
 * would be a claim about a website nobody has looked at.
 */
#[Layout('components.account.layout')]
final class Locations extends Component
{
    /**
     * The location whose address is being answered, if any.
     *
     * ⚠️ **`#[Locked]` ON ALL THREE.** `pendingUrl` is the normalised address the
     * owner is being shown, and a client-settable copy of it would let a posted
     * value skip {@see LocationWebsite::normalise()} entirely — the confirmation
     * would then be about a string the screen never rendered.
     * {@see self::confirmWebsite()} re-normalises regardless, on 398's rule that
     * an inner guard must not depend on an outer one, and both are cheap.
     */
    #[Locked]
    public ?int $editingLocationId = null;

    #[Locked]
    public ?int $pendingLocationId = null;

    #[Locked]
    public ?string $pendingUrl = null;

    public string $pastedUrl = '';

    /**
     * The location whose counter card is on screen, if any (6700).
     *
     * ⚠️ **`#[Locked]`, FOR THIS FILE'S OWN REASON RESTATED ON A NEW SUBJECT.**
     * A client-settable copy would put one shop's public feedback address on the
     * card an owner is about to print and stick to another shop's counter — and
     * the Gate and the tenant scope would refuse another *tenant's* location
     * while permitting this tenant's other branch, which is 5735's finding.
     *
     * ⚠️ **IT IS ALSO WHAT MAKES BUILDING THE CARD IN `render()` SAFE.** Nothing
     * a request can send sets this, so the card — and the one feed entry that
     * goes with it — exists only after {@see self::showSign()} has authorised
     * the location it names.
     */
    #[Locked]
    public ?int $signLocationId = null;

    /**
     * The location whose contact details are being answered (6100).
     *
     * ⚠️ **`#[Locked]`, AND THIS ONE DECIDES WHAT A STRANGER IS TOLD.** A
     * client-settable copy would let a posted value write one location's phone
     * number onto another's — and the Gate and the tenant scope would refuse
     * another *tenant's* location while permitting this tenant's other shop,
     * which is 5735's finding exactly. The number is what the HELP reply prints.
     */
    #[Locked]
    public ?int $detailsLocationId = null;

    /**
     * ⚠️ **DELIBERATELY NOT `#[Locked]` — THESE ARE THE BOXES.** They carry what
     * the owner typed, which is client-settable by definition; what may not be
     * client-settable is *which location it lands on*, and that is the property
     * above. {@see LocationDetails::state()} re-validates both regardless of what
     * the screen did (398).
     */
    public string $statedPhone = '';

    public string $statedAddress = '';

    /**
     * The location whose About page is being answered — rule 36's gate (5720).
     */
    #[Locked]
    public ?int $aboutLocationId = null;

    public string $pastedAboutUrl = '';

    /**
     * The location whose WordPress login is being pasted.
     */
    #[Locked]
    public ?int $connectingLocationId = null;

    public string $wpUsername = '';

    /**
     * ⛔ **A SECRET IN A PUBLIC PROPERTY. SEE THE CLASS DOCBLOCK** (5749).
     * Deferred on the way in, `reset()` on every way out.
     */
    public string $wpPassword = '';

    /**
     * The location whose application password WordPress could not revoke (5595).
     *
     * ⛔ **THE SCREEN SLICE F1 SAID WAS OWED.** `WordPressCredentials::forget()`
     * destroys our copy whether or not the site accepted the revocation, and
     * *"what is owed is a screen telling them so, and it is slice G's"* — the
     * audit row records it and no human reads audit rows.
     */
    #[Locked]
    public ?int $removeByHandLocationId = null;

    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);
    }

    /**
     * Open the address field for one location.
     */
    public function edit(int $locationId): void
    {
        $this->authorizeLocation($locationId);

        $this->reset('pastedUrl', 'pendingLocationId', 'pendingUrl');
        $this->resetErrorBag();

        $this->editingLocationId = $locationId;
    }

    public function cancel(): void
    {
        $this->reset('editingLocationId', 'pastedUrl', 'pendingLocationId', 'pendingUrl');
        $this->resetErrorBag();
    }

    /**
     * Put this location's printable review card on screen.
     *
     * ⛔ **A PRESS, NOT A RENDER, AND THAT IS WHAT MAKES THE FEED ENTRY TRUE.**
     * {@see ReviewSigns} files *"Made you a review QR code"* the first time a
     * location's card is built — `AutopilotActionType::QrGenerated`'s first
     * writer since it shipped — so building one for every location on every load
     * of this page would put that sentence in an owner's history as a
     * consequence of visiting a screen, and would run Reed-Solomon per location
     * per render for a card nobody asked to see.
     *
     * ⚠️ **THE SAME `authorizeLocation()` AS EVERY OTHER ACTION HERE, INCLUDING
     * ITS `update` ABILITY** — so a `staff` user may read this page and may not
     * produce the card. That is the narrower answer of the two available and it
     * is deliberate: the card carries a permanent public address that is
     * physically handed to the business's customers, it is what a person prints
     * once and leaves out for a year, and `LocationPolicy::view()` returns true
     * for everybody, so gating on it would be a guard that refuses nobody (398).
     * `CLAUDE.md`'s first tie-breaker is less support surface.
     */
    public function showSign(int $locationId): void
    {
        $this->authorizeLocation($locationId);

        $this->signLocationId = $locationId;
    }

    public function hideSign(): void
    {
        $this->reset('signLocationId');
    }

    /**
     * Work out what the pasted address means. **Persists nothing.**
     *
     * ⛔ **THE STEP THAT EXISTS SO THE CONFIRMATION IS ABOUT SOMETHING.** Asking
     * somebody to confirm the string they just typed confirms nothing; asking
     * them to confirm the address we will actually act on is the check 1083
     * requires. A test drives this arm and asserts the row is untouched.
     */
    public function review(): void
    {
        $location = $this->authorizeLocation($this->editingLocationId);

        $this->validate([
            'pastedUrl' => ['required', 'string', 'max:2048'],
        ], [
            'pastedUrl.required' => 'Paste the web address of your website.',
            'pastedUrl.max' => 'That address is too long to be a website address.',
        ]);

        try {
            // ⚠️ THE SERVICE'S OWN NORMALISER, NOT A SECOND COPY OF THE RULES. A
            // screen that decided separately what a website address is would let
            // the two disagree, and the one that matters is the one that writes.
            $this->pendingUrl = LocationWebsite::normalise($this->pastedUrl);
        } catch (InvalidArgumentException $refusal) {
            $this->addError('pastedUrl', $refusal->getMessage());

            return;
        }

        $this->pendingLocationId = (int) $location->id;
    }

    /**
     * The press that writes.
     */
    public function confirmWebsite(LocationWebsite $websites): void
    {
        $location = $this->authorizeLocation($this->pendingLocationId);

        if ($this->pendingUrl === null) {
            return;
        }

        try {
            // ⛔ THE LITERAL `true` IS NARROWED HERE AND NOWHERE ELSE (220). This
            // line is reachable only from the second press, after the address has
            // been rendered back to the person pressing it.
            $websites->confirm($location, $this->pendingUrl, $this->actor(), true);
        } catch (InvalidArgumentException $refusal) {
            $this->addError('pastedUrl', $refusal->getMessage());

            return;
        }

        $this->cancel();

        // Outcome language, and no address in the toast: `22`'s rule is that a
        // string names what the person controls, and the address is on the screen
        // behind it either way.
        Toaster::success('Saved — we know which website is yours');
    }

    /**
     * Open the contact-detail boxes for one location, filled with what we hold.
     *
     * ⚠️ **FILLED FROM THE ROW RATHER THAN LEFT EMPTY, WHICH IS WHAT MAKES A
     * BLANK BOX A STATEMENT.** An owner who clears the address is telling us
     * they have no public address; an owner looking at an empty box they never
     * filled is being asked a question. The two are only distinguishable because
     * the form always shows the current state.
     */
    public function editDetails(int $locationId): void
    {
        $location = $this->authorizeLocation($locationId);

        $this->resetErrorBag();

        $this->statedPhone = $location->primary_phone ?? '';
        $this->statedAddress = $location->address ?? '';
        $this->detailsLocationId = $locationId;
    }

    public function cancelDetails(): void
    {
        $this->reset('detailsLocationId', 'statedPhone', 'statedAddress');
        $this->resetErrorBag();
    }

    /**
     * Record how customers reach this business.
     *
     * ⛔ **THIS IS THE WRITER THE CARRIER-MANDATED HELP REPLY HAS BEEN WAITING
     * FOR SINCE STAGE 0** (6100, 6101). Until this press existed
     * `ComplianceReplies::contactFor()` returned null for every tenant that ever
     * registered, so the platform support address — the fallback written for the
     * tenant who has no number — was what every member of the public got.
     *
     * ⚠️ **ONE PRESS, AND `saveAbout()`'s ARGUMENT IS WHY** (5750). Two presses
     * exist for `website_url` because the owner is confirming a value we
     * *normalised* — something they did not type. Nothing here is derived, so a
     * second press would ask somebody to confirm the string they typed a moment
     * ago, which confirms nothing.
     *
     * ⚠️ **BOTH FIELDS ARE `nullable`, AND NEITHER IS `required`.** A
     * service-area business has no public address and some businesses publish no
     * number at all; making either mandatory would be this screen inventing a
     * rule about what a business must be.
     */
    public function saveDetails(LocationDetails $details): void
    {
        $location = $this->authorizeLocation($this->detailsLocationId);

        // ⚠️ **THE CEILINGS ARE THE SERVICE'S CONSTANTS, NOT LITERALS.** The
        // phone's is derived from the carrier's 320-character field limit, and a
        // second copy here is a copy that stops matching the thing it protects.
        $this->validate([
            'statedPhone' => ['nullable', 'string', 'max:'.LocationDetails::PHONE_MAX],
            'statedAddress' => ['nullable', 'string', 'max:'.LocationDetails::ADDRESS_MAX],
        ], [
            'statedPhone.max' => 'That is longer than a phone number. Type just the number your customers ring.',
            'statedAddress.max' => 'That is longer than we can hold. Give the address the way it appears on your door.',
        ]);

        // ⚠️ **THE SERVICE'S OWN RULES, ASKED FIELD BY FIELD SO THE REFUSAL LANDS
        // ON THE BOX IT IS ABOUT.** `review()`'s pattern one panel up: a screen
        // that decided separately what a phone number is would let the two
        // disagree, and the one that matters is the one that writes. Reading the
        // exception message to guess which field it came from is the alternative,
        // and it is a string comparison holding a compliance control together.
        try {
            LocationDetails::normalisePhone($this->statedPhone);
        } catch (InvalidArgumentException $refusal) {
            $this->addError('statedPhone', $refusal->getMessage());

            return;
        }

        try {
            LocationDetails::normaliseAddress($this->statedAddress);
        } catch (InvalidArgumentException $refusal) {
            $this->addError('statedAddress', $refusal->getMessage());

            return;
        }

        // ⛔ THE LITERAL `true` IS NARROWED HERE (220). This line is reachable
        // only from a person pressing the button on their own location.
        //
        // ⚠️ **NOT WRAPPED, AND THE ONE THROW LEFT IS WHY.** Both normalisers
        // have already run and `state()` runs them again (398); what remains is
        // the wrong-tenant refusal, which `authorizeLocation()` above turns into a
        // 404 before this line — and degrading a tenant-boundary violation into a
        // field error would file a leak as a typo.
        $details->state($location, $this->statedPhone, $this->statedAddress, $this->actor(), true);

        $this->cancelDetails();

        // Outcome language, and neither value in the toast: `22`'s rule is that a
        // string names what the person controls, and both are on the screen
        // behind it either way.
        Toaster::success('Saved — we know how your customers reach you');
    }

    /**
     * Open the About-page field for one location — `29` §2 rule 36's input.
     */
    public function editAbout(int $locationId): void
    {
        $this->authorizeLocation($locationId);

        $this->reset('pastedAboutUrl');
        $this->resetErrorBag();

        $this->aboutLocationId = $locationId;
    }

    public function cancelAbout(): void
    {
        $this->reset('aboutLocationId', 'pastedAboutUrl');
        $this->resetErrorBag();
    }

    /**
     * Record the page this business's byline will point at.
     *
     * ⚠️ **ONE PRESS RATHER THAN THE ADDRESS FIELD'S TWO, AND THE DIFFERENCE IS
     * WHAT THE VALUE IS FOR** (5750). `website_url` gets two presses because it
     * is the address we **write to**, and 1083's franchisor case is what a wrong
     * one costs. This address is only ever **linked from**, and the writer
     * refuses any host but the one already confirmed — so the worst a mistake
     * does is point a byline at the wrong page of their own site, which they can
     * see and change. A second ceremony here would be ceremony.
     */
    public function saveAbout(LocationWebsite $websites): void
    {
        $location = $this->authorizeLocation($this->aboutLocationId);

        $this->validate([
            'pastedAboutUrl' => ['required', 'string', 'max:2048'],
        ], [
            'pastedAboutUrl.required' => 'Paste the address of the page on your website that says who you are.',
            'pastedAboutUrl.max' => 'That address is too long to be a web address.',
        ]);

        try {
            // ⛔ THE LITERAL `true` IS NARROWED HERE (220). This line is reachable
            // only from a person pressing the button on their own location.
            $websites->confirmAbout($location, $this->pastedAboutUrl, $this->actor(), true);
        } catch (InvalidArgumentException $refusal) {
            $this->addError('pastedAboutUrl', $refusal->getMessage());

            return;
        }

        $this->cancelAbout();

        Toaster::success('Saved — your name will link to that page');
    }

    /**
     * Open the WordPress login panel for one location.
     */
    public function beginConnect(int $locationId): void
    {
        $this->authorizeLocation($locationId);

        $this->reset('wpUsername', 'wpPassword', 'removeByHandLocationId');
        $this->resetErrorBag();

        $this->connectingLocationId = $locationId;
    }

    public function cancelConnect(): void
    {
        $this->reset('connectingLocationId', 'wpUsername', 'wpPassword');
        $this->resetErrorBag();
    }

    /**
     * Hand this platform write access to a WordPress site.
     *
     * ⛔ **§19.7's LEAST-PRIVILEGE GATE RUNS HERE, INSIDE THE SERVICE, AND THIS
     * METHOD CANNOT SKIP IT.** {@see WordPressCredentials} has no method that
     * stores a credential without probing it (5582) — both arms, the `allcaps`
     * read and the `GET /wp/v2/plugins` behavioural probe that catches a
     * multisite Super Admin an `allcaps` map cannot show. What this screen does
     * is render the refusal, and the sentence is
     * {@see WordPressConnectionRefusal::owner()}'s rather than this
     * file's, so no vendor error string ever reaches a person.
     *
     * ⛔ **THE SECRET IS RESET ON EVERY PATH, INCLUDING THE REFUSALS** (5749).
     * An owner who pasted an administrator's password gets a sentence telling
     * them to make an Editor — and the password they pasted does not travel back
     * to their browser in the response snapshot while they read it.
     *
     * ⚠️ **THE AUDIT ROW IS THE SERVICE'S, ON BOTH ARMS.** `29` §2 rule 42 makes
     * this a sensitive action, and a *refused* connection is audited too: an
     * owner repeatedly pasting an administrator's password is exactly the
     * pattern support needs to see.
     */
    public function connectWordPress(WordPressCredentials $credentials): void
    {
        $location = $this->authorizeLocation($this->connectingLocationId);

        $this->validate([
            'wpUsername' => ['required', 'string', 'max:191'],
            'wpPassword' => ['required', 'string', 'max:191'],
        ], [
            'wpUsername.required' => 'Type the WordPress username you made the application password for.',
            'wpPassword.required' => 'Paste the application password WordPress showed you.',
        ]);

        if ($location->website_url === null || $location->website_confirmed_at === null) {
            $this->addError('wpUsername', 'Tell us your website address first, then connect it.');
            $this->reset('wpPassword');

            return;
        }

        $connection = $credentials->connect(
            $location,
            $location->website_url,
            trim($this->wpUsername),
            // ⚠️ **WRAPPED BEFORE IT CROSSES A FRAME BOUNDARY** (5596). A `string`
            // argument renders its first fifteen characters into every stack
            // trace below this line; an object renders as its class name.
            new ApplicationPassword($this->wpPassword),
            $this->actuationActor(),
        );

        $this->reset('wpPassword');

        $message = $connection->ownerMessage();

        if ($message !== null) {
            $this->addError('wpUsername', $message);

            return;
        }

        $this->cancelConnect();

        Toaster::success('Connected — we can make these changes on your website now');
    }

    /**
     * Give the site back.
     *
     * ⛔ **THREE OUTCOMES, NOT TWO, AND THE THIRD IS THE ONE SOMEBODY OWES AN
     * ACTION ON** (5595). `forget()` destroys our copy whether or not the site
     * accepted the revocation, because a password we have thrown away and the
     * owner has not yet deleted is strictly safer than a working one we are
     * still holding for a site we were told to leave. **When the site could not
     * revoke it, the owner has to delete it themselves inside WordPress** — and
     * until this screen there was nowhere that was said.
     */
    public function disconnectWordPress(int $locationId, WordPressCredentials $credentials): void
    {
        $location = $this->authorizeLocation($locationId);

        $revoked = $credentials->forget($location, $this->actuationActor());

        $this->cancelConnect();

        if ($revoked === null) {
            return;
        }

        if ($revoked === false) {
            $this->removeByHandLocationId = $locationId;

            return;
        }

        $this->removeByHandLocationId = null;

        Toaster::success('Disconnected — we can no longer change your website');
    }

    /**
     * The location this action is about, or a refusal.
     *
     * ⚠️ **RENAMED FROM `authorizeWebsite()` ON 2026-08-20** (6103). It has
     * guarded more than the website since the WordPress connect landed, and from
     * this slice it guards a phone number that is printed to a member of the
     * public — a helper whose name says *website* standing in front of that write
     * is exactly the drift this file writes essays about elsewhere. The check is
     * unchanged.
     *
     * ⚠️ **ONE HELPER FOR EVERY ACTION, INCLUDING THE ONES THAT WRITE
     * NOTHING.** `Account\AssistantLinks` records the ordering — authorization
     * before validation — and the reason it also guards the read-only step is
     * 398: a `staff` user who can reach `review()` learns nothing they may not
     * see, but a guard that only sits on the last press is a guard one refactor
     * away from being skipped.
     */
    private function authorizeLocation(?int $locationId): Location
    {
        abort_if(Tenancy::id() === null, 403);
        abort_if($locationId === null, 404);

        // Tenant-scoped by the global scope, so another tenant's id is a 404
        // rather than a refusal that confirms the row exists.
        $location = Location::query()->find($locationId);

        abort_if(! $location instanceof Location, 404);

        Gate::authorize('update', $location);

        return $location;
    }

    /**
     * Who is acting, in the shape the actuation services take.
     *
     * ⚠️ **STAFF AND OWNER ARE TOLD APART RATHER THAN COLLAPSED** (5751).
     * {@see ActuationActor} exists so that `site_changes.rolled_back_by` and the
     * audit trail can say *which kind* of decision this was, and a support agent
     * acting on a tenant's behalf through an impersonation session is exactly the
     * case that would otherwise be filed as the owner's own choice to hand us a
     * login. `auditActor()` is `user:<id>` either way; the kind is the part that
     * would have been lost.
     */
    private function actuationActor(): ActuationActor
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        return $user->role->isPlatformStaff()
            ? ActuationActor::staff((int) $user->id)
            : ActuationActor::owner((int) $user->id);
    }

    /**
     * `Account\Settings`' actor label, verbatim: an actor string, never a bare id.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'owner' : 'user:'.$id;
    }

    public function render(
        LocationAllowance $allowance,
        PlanCharges $charges,
        ActuationTiers $tiers,
        WordPressCredentials $credentials,
        SpeedFixes $speed,
        AuthorByline $byline,
        ReviewSigns $signs,
    ): View {
        $business = $this->business();

        // `Subscriptions::for()` rather than a relation on `Business`: there is no
        // relation, and adding one would be a second way to read the row that the
        // service's own docblock keeps to itself.
        $subscription = (new Subscriptions)->for($business);
        $selection = $this->selection($subscription);

        $locations = Location::query()->orderBy('id')->get();

        return view('livewire.account.locations', [
            'locations' => $locations,
            'permitted' => $allowance->permitted($business),
            'used' => $allowance->used($business),
            'remaining' => $allowance->remaining($business),
            'addOnPrice' => PlanPricing::format(
                $charges->agreedAdditionalLocationPriceFor($subscription, $selection),
            ),
            'term' => $selection->term,
            'reach' => $this->reach($locations, $tiers, $credentials, $speed, $byline),
            // ⚠️ **THE NAME, NOT THE MODEL.** It is the byline rule 36 asks for
            // (5720) and the screen prints it so an owner can see whose name
            // goes on a page before one is ever published.
            'bylineName' => $business->name,
            'sign' => $this->sign($signs),
        ]);
    }

    /**
     * The counter card for the location whose card is open, if one is.
     *
     * ⚠️ **IT AUTHORISES AGAIN RATHER THAN TRUSTING {@see self::showSign()}**,
     * on 398's rule: an inner guard that depends on an outer one is a guard that
     * disappears the day somebody sets the property another way. Both are cheap,
     * and {@see ReviewSigns::for()} asserts the tenant a third time on its own.
     *
     * Null covers two different states and the template tells them apart with
     * `$signLocationId`: nothing open, and a location that has no feedback page
     * to point a code at — the second is an honest sentence rather than a broken
     * card, because minting a page belongs to the provisioner (decision 334).
     */
    private function sign(ReviewSigns $signs): ?ReviewSign
    {
        if ($this->signLocationId === null) {
            return null;
        }

        return $signs->for($this->authorizeLocation($this->signLocationId));
    }

    /**
     * What we can reach on each location's website, in the words an owner uses.
     *
     * ⛔ **"WE DO NOT KNOW YET" IS ONE OF THE ANSWERS AND IS NOT T4** (229, and
     * {@see ActuationTiers}' docblock). T4 is a real tier — a prioritised list the
     * owner applies — and saying it about a website nobody has looked at is a
     * claim we have not earned. The null arm says so in plain words instead.
     *
     * ⚠️ **THE WORDPRESS LINE CARRIES NO ACTION, DELIBERATELY** (5547). `41` Part
     * 1 makes the stronger tier an *offer*, and the thing being offered — the
     * plugin — is slice F. A button here would ask an owner to accept something
     * that does not exist, and a sentence with no button is not a nag.
     *
     * ⚠️ **`isConnected()` RATHER THAN `WordPressAdapter::health()`** (5599). The
     * store's answer is a row read; health is two HTTP requests to a customer's
     * website, and asking it here would put a network round trip — one per
     * location — inside a screen render.
     *
     * ⚠️ **THE SPEED SENTENCE IS `28` §4.1's *"tells the owner the truth once,
     * simply"*, AND IT IS A SCREEN STATE RATHER THAN A PROMPT** — which is what
     * makes *once* structural instead of a thing to count. §4.1 asks for *"one
     * prompt, then a Setup Center item. No repeated nagging"*, and 5542 already
     * settled the shape beside it: a sentence with no button is not a nag.
     *
     * ⚠️ **`SpeedFixes::plan()` TOUCHES NO NETWORK, WHICH IS WHY IT MAY BE
     * ASKED HERE AT ALL** (5599's rule, third time). It asks the adapter's
     * offline `fieldSupport()` and reads two tenant-scoped tables; the live
     * *"is this site writable right now"* question stays in the job.
     *
     * @param  EloquentCollection<int, Location>  $locations
     * @return array<int, array{tier: ?ActuationTier, sentence: string, speed: string, wordpress: bool, connected: bool, about: ?string, aboutBlocked: ?string}>
     */
    private function reach(
        EloquentCollection $locations,
        ActuationTiers $tiers,
        WordPressCredentials $credentials,
        SpeedFixes $speed,
        AuthorByline $byline,
    ): array {
        $reach = [];

        foreach ($locations as $location) {
            $tier = $tiers->for($location);

            $reach[(int) $location->id] = [
                'tier' => $tier,
                'sentence' => $this->sentenceFor($location, $tier),
                'speed' => $speed->sentenceFor($location, $tier),
                'wordpress' => $tiers->upgrade($location) === ActuationTier::T1,
                'connected' => $credentials->isConnected($location),
                'about' => $location->about_url,
                // ⛔ **THE ONE REFUSAL AN OWNER CAN FIX AND COULD NOT SEE**
                // (6065, and the defect half of 5744). Rule 36's gate refuses to
                // publish when we cannot verify the About page, and a tenant
                // whose own `robots.txt` disallows us is refused for ever — with
                // the only record of it in an activity feed **no screen in this
                // application renders**. It is read here rather than fetched:
                // `AuthorByline::unreachableAboutPageSentence()` asks the last
                // recorded attempt and touches no network, on 5599's rule.
                'aboutBlocked' => $byline->unreachableAboutPageSentence($location),
            ];
        }

        return $reach;
    }

    /**
     * ⚠️ **FOUR SENTENCES, AND TWO OF THEM ARE THE UNKNOWN ARM SPLIT IN HALF.**
     * *"You have not told us"* and *"we are looking now"* are different things to
     * be told: the first asks for an answer and the second asks for patience, and
     * one sentence covering both would ask a person who has just answered to
     * answer again.
     */
    private function sentenceFor(Location $location, ?ActuationTier $tier): string
    {
        if ($location->website_url === null) {
            return 'Tell us your website address and we will look at what we can improve on it.';
        }

        if ($tier === null) {
            return 'We are looking at your website now. Check back in a few minutes.';
        }

        // ⛔ **THE T1 ARM IS NEW AND ITS ABSENCE WOULD HAVE BEEN A FALSE
        // SENTENCE** (5747). Until this slice `for()` could not return T1, so
        // the `else` below caught it — and *"we cannot change your website for
        // you"* printed on a location whose owner had just handed us a login is
        // the exact opposite of what happened.
        return match ($tier) {
            ActuationTier::T1 => 'We can make changes on your website directly. Everything we change '
                .'is written down, and you can undo any of it.',
            ActuationTier::T3 => 'We can add your business details, page descriptions and common '
                .'questions to your website. Some search engines are slow to show them and some '
                .'never will, so we will also tell you exactly what to change and where.',
            ActuationTier::T0, ActuationTier::T2, ActuationTier::T4 => 'We cannot change your website for you. We will tell you exactly what to '
                .'change and where, so you or whoever looks after it can do it.',
        };
    }

    /**
     * The selection an add-on would be quoted on — this tenant's own term.
     *
     * ⚠️ **THE TERM COMES FROM THE SUBSCRIPTION AND SO, SINCE 4347, DOES THE
     * RATE.** This page said the opposite and quoted today's registry figure,
     * on the argument that a stored rate would be "the price of a location they
     * have not bought". That argument is the wrong way round and 3443 settles it:
     * the add-on rate is grandfathered exactly like the plan price — it is on the
     * row for this reason — and `Subscriptions::recordAdditionalLocations()`
     * bills the stored one. Quoting today's meant the page showed a number the
     * next line of code refuses to use, invisibly, until an offer priced the
     * add-on differently from retail.
     *
     * A row with no term is monthly — the same default both checkout paths apply
     * to a caller that made no choice.
     */
    private function selection(?Subscription $subscription): PlanSelection
    {
        return PlanSelection::fromInput($subscription?->term?->value, false);
    }

    /**
     * ⚠️ 403 RATHER THAN LETTING `Tenancy::idOrFail()` THROW — `Account\Plan`'s
     * reasoning. Internal staff belong to no business, so a signed-in support
     * agent typing this URL is the ordinary way to arrive with nothing resolved,
     * and a 500 reads as our page being broken.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, 403);

        $business = Business::query()->find($id);

        abort_if(! $business instanceof Business, 404);

        return $business;
    }
}
