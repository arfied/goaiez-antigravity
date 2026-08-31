<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\AutopilotSettings;
use App\Models\Location;
use App\Policies\LocationPolicy;
use App\Services\Billing\LocationAllowance;
use App\Services\Destinations\DestinationSettings;
use App\Services\Feedback\FeedbackPages;
use App\Services\Reviews\ReviewHubPages;
use App\Services\TenantProvisioner;
use App\Services\Widgets\WidgetPlugins;
use Illuminate\Support\Facades\DB;

/**
 * Everything a location needs to exist, in the one place both callers reach.
 *
 * ⛔ **A LOCATION IS SIX ROWS AND UNTIL THIS SLICE ONLY ONE CALLER KNEW THAT.**
 * ⚠️ **It was five until 2026-08-21**, when the hosted review hub gained its
 * writer; the paragraph below is the extraction's argument and is unchanged by
 * the addition.
 * {@see TenantProvisioner} created the location, seeded its
 * destinations, made its autopilot settings, published its feedback page and
 * minted its widget key — inline, in the middle of a registration transaction
 * that also claims a phone number, opens a subscription and records a signup
 * origin. Adding a *second* location meant either calling all five again from a
 * screen, or calling registration's method and getting a second subscription with
 * it. Both are how the two paths drift, and the drift is silent: a location with
 * no feedback page is a 10DLC opt-in URL that does not resolve, and a location
 * with no autopilot settings is one that no automation is scoped to.
 *
 * ⚠️ **THE ORIGINAL FIVE ARE UNCHANGED AND SO ARE THEIR ARGUMENTS.** This is an extraction
 * rather than a redesign — the reasoning for each stayed at its call site in
 * `TenantProvisioner` and is not restated here, because a second copy is a copy
 * that drifts. What moved is the sequence; what did not move is the ordering,
 * which is load-bearing (destinations seed *disabled* before the feedback page
 * publishes, for decision 312's reason).
 *
 * ⛔ **NOTHING HERE DECIDES WHETHER A LOCATION MAY BE CREATED.** The allowance is
 * {@see LocationAllowance}'s and the authorization is
 * {@see LocationPolicy}'s. This class makes the rows; a service that
 * both billed and provisioned would be one whose tests could not tell which half
 * refused.
 */
final class LocationProvisioner
{
    public function __construct(
        private readonly DestinationSettings $destinations,
        private readonly FeedbackPages $feedbackPages,
        private readonly ReviewHubPages $reviewHubPages,
        private readonly WidgetPlugins $widgets,
    ) {}

    /**
     * Create a location and everything scoped to it.
     *
     * ⛔ **`provisionLocation()` AND NOT `provision()`, WHICH IS A LINT'S RULE AND
     * A READER'S BOTH.** `Architecture/LegalTest`'s *"every door that provisions a
     * tenant records a terms acceptance"* matches `->provision(` across `app/` and
     * asserts that exactly two files carry it — `CreateNewUser` and
     * `OauthLoginController`, the two doors that open an account. This method
     * creates a **location inside a tenant that already exists**, so it is not a
     * third door; borrowing the spelling would have reddened that lint with a
     * false positive and, worse, invited somebody to answer it by widening a
     * control about signup consent. ⚠️ **The ambiguity is real for a human too**:
     * `$this->locations->provision(…)` and `$this->provisioner->provision(…)` read
     * identically at a call site and mean very different things.
     * `FeedbackPages::provisionFor()` and `WidgetPlugins::provisionFor()` avoid it
     * the same way, and the lint's own comment says so in as many words.
     *
     * ⚠️ **THE TRANSACTION IS THE POINT.** `TenantProvisioner` already runs inside
     * one — Laravel nests, so this becomes a savepoint there and changes nothing —
     * but the screen that calls this has no transaction of its own, and a partially
     * provisioned location is exactly what `TenantProvisioner`'s own header refuses
     * to leave behind: not partially set up, permanently and invisibly broken, with
     * no repair path because every repair path is tenant-scoped.
     *
     * @param  bool  $nameIsPersonal  True whenever `$name` is not a verified
     *                                business name. ⚠️ Passed through rather than
     *                                defaulted at this level, because the caller is
     *                                the only thing that knows which case it minted
     *                                the name from — {@see FeedbackPages::provisionFor()}
     *                                for what it decides and which way silence
     *                                fails.
     */
    public function provisionLocation(string $name, bool $nameIsPersonal = false): Location
    {
        return DB::transaction(function () use ($name, $nameIsPersonal): Location {
            // BelongsToTenant fills business_id from the established tenant on
            // create, which is the same ordering every other provisioned row in
            // this application relies on.
            $location = Location::query()->create(['name' => $name]);

            // Three destination rows, all disabled, each carrying its own default
            // threshold (decision 309). Seeded here rather than lazily so that a
            // tenant's configuration exists as a thing to read before anybody has
            // configured anything — offeredFor() on a fresh tenant returns an
            // empty set because nothing is enabled, not because nothing is known.
            //
            // GOOGLE IS SEEDED DISABLED TOO, and that is decision 312 rather than
            // an omission. This location has no google_place_id — the id is
            // resolved later, when the owner pastes their listing, and
            // PlaceConfirmation is the only thing that writes it. Enabling Google
            // here would mean either an enabled row with no derivable link, or
            // making "enable() without a place_id throws" false from the first
            // tenant.
            $this->destinations->seedDefaults($location);

            // The location's autopilot configuration, at its column defaults.
            //
            // THIS HAD NO CREATOR AT ALL until row 3 slice E went looking for it —
            // a factory, a policy and an admin screen, and nothing that made a
            // row. Decision 272's shape exactly: Business::provision() was
            // written, documented, and left uncalled until a slice needed it.
            //
            // The defaults are not restated here. They live in the migration,
            // they are the product's ungated defaults (`29` §2 rule 37), and a
            // second copy in PHP is a copy that drifts.
            //
            // ⚠️ A FRESH LOCATION IS GATED FROM THIS MOMENT, AND A TENANT'S WAS
            // NOT UNTIL 2026-08-12. This block used to explain that `gating_ack_at`
            // was left null on purpose (290), which `ReviewRouter` read as "apply
            // no invite threshold at all" — so every tenant provisioning ever
            // created invited everybody regardless of rating. Decision 2074
            // removed the acknowledgement and 2660 dropped the column, so the
            // thresholds `seedDefaults()` wrote a line above are in force
            // immediately: `reviews.default_invite_threshold` (4) on Google and
            // Facebook, Trustpilot's forced 0, and no Yelp row at all (1161).
            AutopilotSettings::query()->create(['location_id' => $location->id]);

            // The public capture surface, published immediately — and the
            // ordering against seedDefaults() above is the interesting part rather
            // than an accident. Destinations provision *disabled* because enabling
            // one sends a customer to a third-party platform under that platform's
            // terms (decision 312). A feedback page sends nobody anywhere: it is a
            // form on our own domain, and `24` 3.1 makes it
            // live-before-registration rather than live-when-configured, because a
            // 10DLC brand submission is rejected unless its opt-in URL already
            // resolves.
            $this->feedbackPages->provisionFor($location, nameIsPersonal: $nameIsPersonal);

            // The public place those reviews are read (`29` §7.6, `/r/{slug}`).
            //
            // ⚠️ A LOCATION IS SIX ROWS NOW, AND THE SIXTH HAD NO WRITER AT ALL
            // — `review_hub_pages` was a model, a factory, an RLS policy and a
            // schema isolation test with nothing in `app/` that could create a
            // row, and `autopilot_settings.update_review_hub` was a column
            // defaulting to true with nothing anywhere that read it. Decision
            // 272's shape twice over, one table apart.
            //
            // ⛔ AFTER THE FEEDBACK PAGE AND NOT BEFORE IT, AND THE ORDERING IS
            // LOAD-BEARING RATHER THAN TIDY. The hub's slug *is* the feedback
            // page's slug — one public address per location, two verbs — so this
            // call reads the row the line above just wrote, and reversing the two
            // throws. See ReviewHubPages for why a second public slug directory
            // was refused on privacy grounds.
            //
            // ⛔ "THERE IS NO BACKFILL" WAS TRUE FOR ONE DAY AND IS NOT —
            // CORRECTED 2026-08-21 (6620). It went on: *"exactly as
            // `feedback_pages` has none and for the same reason … the day one
            // is needed it is a loop around a method that already exists"*, and
            // 6552 was right about the shape — `reviews:backfill-hub-pages` is
            // that loop, around `provisionFor()` below, and it adds no second
            // way for a row to appear.
            //
            // ⚠️ **WHAT SURVIVES IS THE RLS HALF AND IT IS WHY THE BACKFILL IS
            // A COMMAND RATHER THAN A MIGRATION**: migrations run as the table
            // owner, every tenant-owned table is FORCEd so the owner is not
            // exempt, and a sweep reading `locations` with `app.business_id`
            // unset sees **zero rows rather than an error** (569, 6181) — a
            // silent no-op where a loud failure would have been safe. So it
            // walks owners and establishes a tenant, like every other
            // platform-wide sweep here.
            //
            // ⛔ AND IT DOES NOT RUN BY ITSELF. It is unscheduled, it previews
            // by default, and it refuses `--commit` without `--tenant` (6621) —
            // a location provisioned before the hub still answers 404 at
            // `/r/{slug}` until somebody deliberately backfills that account.
            $this->reviewHubPages->provisionFor($location);

            // The review feed's key, minted now so `plugins` has a writer at all.
            //
            // ⚠️ IT HAD NONE — a table, a model, a factory, an RLS policy and a
            // schema isolation test, and nothing in `app/` that could create a
            // row. The fifth instance of decision 272's shape, after
            // Business::provision(), autopilot_settings (377), feedback_pages and
            // review_destinations. A feed endpoint keyed on a row nobody mints
            // answers 404 for every tenant, forever, with nothing anywhere to
            // explain why.
            //
            // PROVISIONED WITH AN EMPTY DOMAIN ALLOWLIST, which serves nobody
            // until somebody names a host — the direction a cross-origin allowlist
            // has to fail, and the same shape as destinations provisioning
            // disabled.
            $this->widgets->provisionFor($location);

            return $location;
        });
    }
}
