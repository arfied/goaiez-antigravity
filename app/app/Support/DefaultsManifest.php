<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AiTask;
use App\Enums\Plan;
use App\Enums\StoredObjectKind;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X205\Domain\AffiliateEngine;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteMeasurements;
use App\Services\Actuation\SpeedDecider;
use App\Services\Actuation\SpeedFixes;
use App\Services\Actuation\T3AltText as AltText;
use App\Services\Actuation\T3FaqBlock as FaqBlock;
use App\Services\Actuation\T3InternalLink as InternalLink;
use App\Services\Actuation\T3MetaUpsert as MetaUpsert;
use App\Services\Actuation\WordPress\WordPressRestClient;
use App\Services\Billing\PurchaseReconciliation;
use App\Services\Gbp\ZernioSpend;
use App\Services\Mail\MailDrivers;
use App\Services\Mail\MailQuota;
use App\Services\Mail\MailSendRate;
use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Ops\ScheduledRunMeter;
use App\Services\Pixel\IngestRejects;
use App\Services\Support\DataRequests;
use App\Services\Visibility\ReviewLossDetection;
use App\Services\Warehouse\L1Derivation;
use App\Services\Warehouse\PixelSightings;
use App\Services\Warehouse\Replayer;
use App\Services\Warehouse\WarehouseRetention;

/**
 * THE SEED MANIFEST — doc `38` Part 2's "one reviewed file", CFG1.
 *
 * `38` Part 2: "every number, percentage, price, cap, threshold, toggle, and
 * window written in Docs 28–37 is a seeded, admin-editable value … The docs
 * remain the rationale; the registry is the runtime truth." This file is the
 * seed half of that. `DefaultsRegistry` is the runtime half, and `defaults:sync`
 * is what moves values from here into the database.
 *
 * ⚠️ **THIS FILE EXECUTES. THAT IS WHY IT IS ONE FILE AND WHY IT IS REVIEWED.**
 * Decision 204: every other superseded figure in the 30–45 pack sits in prose,
 * where a reader has a chance to catch it; `38`'s manifest is loaded on a fresh
 * install, so a stale number writes itself into the database where nobody is
 * watching and is then read back as fact. `38`'s own list still carries the
 * **Free/49/149/349** tiering that decisions 95–99, 146 and 154–157 replaced.
 * **None of it is copied from `38`.** The prices below are rebuilt from
 * `CLAUDE.md`'s commercial-model table, which is the authority, and
 * `ArchitectureTest` parses that table and compares it against this file, so the
 * two cannot drift silently.
 *
 * ## The three rules for editing this file
 *
 * 1. **A number goes in only when this codebase reads it.** `38`'s manifest
 *    lists credits pricing, referral rewards, affiliate splits, print margins
 *    and more — for features that do not exist here. Seeding them would mean
 *    copying unverified figures out of the document that decision 204 exists to
 *    warn about, into rows nothing reads. Docs `43`, `44` and `45` each declare
 *    their own numbers registry seeds; each adds its own keys when it is built.
 *    That is how the registry is meant to grow.
 *    ⚠️ **THE CREDITS KEYS ARE A DELIBERATE EXCEPTION AND THE ONLY ONE** (3329).
 *    Nothing reads them yet: the ledger that spends them and the metering that
 *    prices them are separate lanes of the same slice. The exception was taken
 *    because these figures are the *input* to those readers rather than an
 *    output of them, and because 3316 records the model as scheduled work that
 *    had not started. **Until those readers land the keys are rows in a table,
 *    and a row in a table is not a ceiling** — which is 272's shape, said out
 *    loud here rather than discovered later.
 * 2. **An undecided number is never seeded.** It goes in {@see self::withheld()}
 *    with the decision that left it open. `DefaultsRegistry` then throws a
 *    message naming that decision instead of returning a plausible figure.
 *    ⚠️ **Withheld is the fail-closed state** (3317), so a key leaves it when
 *    the owner rules and for no other reason — not because its feature has
 *    become urgent, and not because a reading has become obvious.
 * 3. **The seed is the fail-closed value.** Callers do not pass their own
 *    default any more — a literal at a call site is exactly what `38`'s registry
 *    lint refuses — so whatever is written here is what runs when the database
 *    has no row. Write the conservative value.
 */
final class DefaultsManifest
{
    /**
     * Platform-wide seeds → `platform_settings`.
     *
     * `group` is the Ops editor's heading (`38` Part 2: "the Settings editor
     * groups by domain"). `description` is written into the row, because
     * `platform_settings.description` exists for whoever finds the row in three
     * months and a null one wastes it.
     *
     * @return array<string, array{seed: mixed, group: string, description: string}>
     */
    public static function settings(): array
    {
        $settings = [
            'routing.default_order' => [
                'seed' => 'returning_caller,territory,workload,default_staff',
                'group' => 'Routing',
                'description' => 'Default order of lead routing rules applied to a new tenant.',
            ],
            'routing.workload_window_days' => [
                'seed' => 30,
                'group' => 'Routing',
                'description' => 'Number of days to look back when evaluating a staff member\'s workload.',
            ],
            'crm.lead_score.tier_hot' => [
                'seed' => UnifiedInboxManager::TIER_HOT,
                'group' => 'Marketing',
                'description' => 'Lead score hot tier.',
            ],
            'crm.lead_score.tier_warm' => [
                'seed' => UnifiedInboxManager::TIER_WARM,
                'group' => 'Marketing',
                'description' => 'Lead score warm tier.',
            ],
            'crm.lead_score.tier_cool' => [
                'seed' => UnifiedInboxManager::TIER_COOL,
                'group' => 'Marketing',
                'description' => 'Lead score cool tier.',
            ],
            'crm.lead_score.tier_cold' => [
                'seed' => UnifiedInboxManager::TIER_COLD,
                'group' => 'Marketing',
                'description' => 'Lead score cold tier.',
            ],
            'affiliate.tier.gold_referrals' => [
                'seed' => AffiliateEngine::GOLD_REFERRALS,
                'group' => 'Affiliate',
                'description' => 'Affiliate gold tier referrals count.',
            ],
            'affiliate.tier.silver_referrals' => [
                'seed' => AffiliateEngine::SILVER_REFERRALS,
                'group' => 'Affiliate',
                'description' => 'Affiliate silver tier referrals count.',
            ],
            'affiliate.cookie_lifetime_days' => [
                'seed' => AffiliateEngine::COOKIE_LIFETIME_DAYS,
                'group' => 'Affiliate',
                'description' => 'Affiliate cookie lifetime days.',
            ],
            'sites.deploy.speed_budget_ms' => [
                'seed' => EdgeDeployAction::SPEED_BUDGET_MS,
                'group' => 'Content',
                'description' => 'Deploy speed budget ms.',
            ],
            'sites.deploy.pricebook_items_max' => [
                'seed' => EdgeDeployAction::PRICEBOOK_ITEMS_MAX,
                'group' => 'Content',
                'description' => 'Deploy pricebook items max.',
            ],

            /*
             * Billing shape. Not prices — those are per-plan and live in
             * entitlements() below — but the terms every plan shares.
             */
            'billing.currency' => [
                'seed' => 'USD',
                'group' => 'Billing',
                'description' => 'ISO 4217 code every stored cents figure is denominated in. Money is integer cents plus this code, never a float (`18` §Money handling).',
            ],

            /*
             * ⚠️ 14, not 7 — the owner reversed this on 2026-08-04 (decision
             * 544), which restores what `18` said before decision 98 narrowed
             * it. The old note read "it exactly equals the activation window,
             * so it has no slack"; that reasoning is what was overruled, not
             * mislaid, and the slack is now deliberate.
             *
             * A 7 + 7 extension mechanism was the alternative and was refused:
             * an extension is a screen, a request path and a support ticket for
             * a number that is now simply the default.
             *
             * The marketing home renders this figure as a numeral rather than
             * spelling it out, so the page follows the seed (518). Changing it
             * here changes what the page promises — which is the point, and the
             * reason nobody should write the number anywhere else.
             */
            /*
             * ⚠️ **THE CARD REQUIREMENT IS GONE AS OF 2026-08-11 (2065), AND THE
             * 14 IS RE-CONFIRMED RATHER THAN MERELY SURVIVING.** The owner was
             * asked the length directly and answered "14 days, as today", so
             * 544's figure stands on its own evidence. What changed beside it is
             * decision 98's card at signup, which is now fully superseded — the
             * no-card offer is the default half of 1152, a card may still be
             * added, and every feature is included except the SMS marketing
             * system.
             *
             * ⚠️ **A NO-CARD TRIAL THAT GRANTS 500 REAL SMS IS A FRAUD SURFACE**
             * (2066), and this key is not where that is solved — the abuse
             * controls belong in the same slice as the offer.
             */
            'billing.trial_days' => [
                'seed' => 14,
                'group' => 'Billing',
                'description' => 'Free trial length in days, no card required and a card optional (decision 544 for the length, re-confirmed 2026-08-11; decision 2065 for dropping the card, superseding 98). The marketing home reads this key, so the promise on the page moves with it.',
            ],

            /*
             * ⚠️ ROW 22 SLICE B ADDS IT, WITH THE READER, WHICH IS WHAT CFG1
             * ASKED FOR. The note this replaces said: "Nothing in this codebase
             * reads a cycle length — billing is row 22 — and a seeded key with
             * no reader is the mirror of the defect this project has now found
             * six times. Row 22 adds it, together with the Stripe price object
             * it changes."
             *
             * The reader is `BillingCheckout`, and it is the **recurring
             * interval of the price sent to Stripe** — `interval=day` with
             * `interval_count=30`. That is not a stylistic choice: decision 147
             * bills every 30 days rather than on calendar months, and
             * `interval=month` is a different promise (28 to 31 days, drifting
             * with the anchor date). ⚠️ **12.17 cycles a year, not 12** — the
             * `cashier-billing` skill spells out that it changes annual revenue
             * arithmetic.
             *
             * ⚠️ **CHANGING THIS DOES NOT MOVE ANY EXISTING SUBSCRIPTION.** A
             * Stripe subscription carries the interval of the price it was
             * created with, forever; the new value applies to the next Checkout
             * Session and to nothing already running. That is the correct
             * behaviour — a customer's billing period is not something an Ops
             * edit should silently re-cut — and it is the opposite of how every
             * other key in this file behaves, which is why it is stated here
             * rather than left to be discovered from a support ticket.
             */
            'billing.cycle_days' => [
                'seed' => 30,
                'group' => 'Billing',
                'description' => 'Days between charges (decision 147 — 30 days, not calendar months, which is 12.17 cycles a year). Sent to Stripe as the price\'s recurring interval when a Checkout Session is opened. ⚠️ Applies to new subscriptions only: an existing one keeps the interval it was created with.',
            ],

            'billing.risk.days_overdue_high' => [
                'seed' => 30,
                'group' => 'Billing',
                'description' => 'Invoices past this number of days overdue put a customer in the High risk tier.',
            ],

            /*
             * The free instant audit (row 2). These two are the reason
             * `platform_settings` exists at all: `29` §6.2 puts the audit's
             * "daily global budget in platform_settings", and both of them
             * bound real money on an unauthenticated endpoint.
             */
            /*
             * Decision 193, the owner's figure, accepted at 224 with the cost
             * in front of them. The seed is not a second opinion about the
             * right number — it is the same number in the one place that
             * cannot be deleted.
             */
            'public_audit.daily_budget' => [
                'seed' => 250,
                'group' => 'Free instant audit',
                'description' => 'Audits served per day before the endpoint fails closed (decision 193, accepted by the owner at 224). At 9.20¢ an audit that is $23.00/day, ~$470/month.',
            ],

            /*
             * Autocomplete's ceiling: Google's own free monthly allowance,
             * daily. 10,000 free Autocomplete requests a month is about 333 a
             * day, so this seed makes the feature cost **nothing** until
             * somebody deliberately decides it should cost something.
             *
             * Every other figure in this file traces to a source — the owner,
             * or a decision. A default autocomplete ceiling has no such source:
             * sizing it properly needs a submit rate nobody has measured, and
             * `BUILD-PLAN` §3 is blunt about what to do in that position ("do
             * not invent the numbers to get green"). Google's free tier is a
             * real number from a real source that happens to also be the safest
             * one.
             *
             * WHAT EXHAUSTION LOOKS LIKE, and why it is survivable: suggestions
             * stop appearing. The input still accepts typing, the visitor still
             * submits, and Text Search still finds the business on submit. The
             * audit is unaffected. That is why autocomplete is allowed to fail
             * closed cheaply. Raising it is a settings row, not a deploy — and
             * the owner should, with the real cost in front of them, once there
             * is a measured submit rate to size it from (BUILD-PLAN §5.1).
             */
            'public_audit.autocomplete_daily_budget' => [
                'seed' => 333,
                'group' => 'Free instant audit',
                'description' => 'Autocomplete requests per day (decision 242). Google\'s free allowance, so autocomplete costs $0 until this is raised. Its own budget, not a share of the audit one, because a shared ceiling would let typing starve audits (243).',
            ],

            /*
             * The third Places budget, and the only per-tenant one.
             *
             * WHAT IT COSTS, so the seed is a derivation rather than a feeling:
             * one location's nightly competitor refresh is a Place Details
             * (Atmosphere) at 2.50c plus a Nearby Search (Enterprise) at 3.50c
             * — 6.00c a location a night. At 100c a tenant a day that is about
             * sixteen locations refreshed nightly before the ceiling bites,
             * roughly $30 a month at the very top, inside decision 150's $50
             * per-tenant cap with room for the onboarding place lookups that
             * bill the same budget.
             *
             * A typical tenant has one to three locations and will spend 6-18c a
             * night — under $6 a month — so this ceiling should never fire. If
             * it does, read it the way `ai.monthly_cap_per_tenant` says to read
             * its own: as a signal that something is looping, rather than as a
             * number to raise.
             *
             * ⚠️ PER TENANT PER DAY, NOT PLATFORM-WIDE. A platform-wide ceiling
             * here would only move the starvation down a level, letting one
             * forty-location account exhaust every other account's refresh, and
             * `29` §2 rule 43 asks for the cap per tenant regardless.
             */
            'places.tenant_daily_spend_ceiling_cents' => [
                'seed' => 100,
                'group' => 'Places',
                'description' => 'One tenant\'s Places spend ceiling for a day, in integer cents. A nightly competitor refresh costs 6.00¢ a location, so 100¢ covers about sixteen locations. Its own budget, per tenant, because tenant background work sharing the free-audit ceiling starved visitor audits.',
            ],

            /*
             * The AI layer (row 3 slice A0).
             *
             * ⛔ THIS KEY WAS REMOVED AT 3608 AND RESTORED AT 3820, WITHIN THE
             * SAME BRANCH AND BEFORE EITHER SHIPPED. 3608 removed it because the
             * AI credit balance had become a real ceiling and 3295 said this key
             * went with it. The removal was right about the funded tenant and
             * wrong about everybody else: 3609 deliberately permits an account
             * that has never been funded, and 3612 recorded the cost of that
             * escape as `Plan::Free` and `Plan::Limited` — an enumeration that is
             * wrong in both directions. Nothing in app/ writes either plan to a
             * subscription, `Subscriptions` provisions every new business as
             * Plan::Base/pending_checkout, and ResetMonthlyCredits grants NO
             * product at all to any account TrialEligibility refuses — which is
             * every account without a confirmed Google listing. The unfunded set
             * is therefore the whole install base until each account verifies, so
             * removing this key left every one of them with unlimited AI.
             *
             * ⚠️ IT IS OUTER CONTAINMENT NOW, NOT THE CEILING. `AiSpend::allows()`
             * asks the credit balance first and this second, so a funded tenant is
             * bounded by their balance and an unfunded one is bounded by this.
             * 3297's rule holds either way: a cap comes out once its replacement
             * bounds the path, and the balance bounds only the path of tenants who
             * have one. It goes when the reset has demonstrably granted in
             * production and the owner has ruled on the unfunded account.
             *
             * ⚠️ SEEDING A KEY AGAIN IS FREE WHERE UNSEEDING ONE IS NOT (3271):
             * `defaults:sync` writes the row on any database that lost it, and a
             * database seeded before 3608 still holds the same figure, so the two
             * states converge on the same number rather than on a gap.
             */
            'ai.monthly_cap_per_tenant' => [
                'seed' => 500_000,
                'group' => 'AI',
                'description' => 'Per-tenant AI spend ceiling for a calendar month, in hundredths of a cent of OUR cost — not of the tenant\'s charge, which is eight times it and lives in ai_calls.retail_hundredths_cents (3304, 3358). 500,000 is $50. ⛔ DECISION 3293 DELETED THE PER-TENANT DOLLAR COST CAP AND 3295 SAYS THIS KEY GOES WITH IT: "an AI-only dollar cap is still a dollar cap, so it goes the same way, and the AI credit balance replaces it." IT IS STILL HERE, DELIBERATELY, AND 3820 IS THE ARGUMENT. The AI credit balance now exists and does refuse (3419, 3424, 3608) — but only for a tenant who HAS a balance. 3609 permits an account that has never been funded, because gating a bare zero would have stopped all AI for every tenant at once, and the unfunded set is not a corner of the plan ladder: Subscriptions provisions every new business as Plan::Base/pending_checkout, and ResetMonthlyCredits grants nothing at all to any account TrialEligibility refuses — which is every account with no confirmed Google listing. Deleting this key therefore handed every unverified account unlimited AI, permanently, in a state under the tenant\'s own control. It is OUTER CONTAINMENT behind the balance gate: AiSpend::allows() asks the balance first and this second. ⚠️ IT IS NOT RULE 43\'S CAP, which the owner deleted: rule 43 capped a tenant\'s TOTAL service cost, and messaging, Places and everything else sit outside this key entirely (3107). The four plan.*.cost_cap.* entitlements that did claim to be rule 43\'s cap are gone (3364) — they had no reader in app/ at all. This one fires. ⚠️ At AiTask\'s defaults a busy tenant costs about 61c a month, so it should never fire; if it does, read it as a signal rather than raise it. It comes out when the monthly reset has demonstrably granted in production and the owner has ruled on the unfunded account.',
            ],

            'ai.eval.max_cases_per_run' => [
                'seed' => 20,
                'group' => 'AI',
                'description' => 'Maximum number of test cases to evaluate in a single evaluation run (C2c).',
            ],

            'ai.eval.similarity_pass_pct' => [
                'seed' => 70,
                'group' => 'AI',
                'description' => 'Percentage score from similar_text above which a golden case evaluation passes (C2c).',
            ],

            /*
             * Google Business Profile, read through Zernio while our own API
             * application clears.
             *
             * Seeded **off**, and this is rule 3 doing its job rather than
             * caution for its own sake. The conservative value for a flag that
             * turns on a live third party holding every tenant's Google
             * authorisation is off: a branch reaching production must not start
             * reading a vendor because it was merged.
             *
             * It is a toggle rather than a threshold, which the other entries
             * here are not — and it belongs anyway, because the alternative is a
             * `.env` flag that only a deploy can flip. Decision 193 settled that
             * shape for the audit budget: the value moves without a deploy, so
             * turning a misbehaving vendor off during an incident is one query
             * rather than one release.
             *
             * ⚠️ TURNING THIS ON IS NOT SUFFICIENT AND NOT SAFE ON ITS OWN.
             * `ZernioGbpClient` is not tenant-scoped — nothing maps a location to
             * its Zernio account id yet, so nothing can check that an account
             * belongs to the tenant being served. Its docblock names that store
             * as the first job of whatever slice gives it a caller. Until that
             * exists the only permitted callers are tests and an Ops command.
             */
            'gbp.zernio_enabled' => [
                'seed' => false,
                'group' => 'Google Business Profile',
                'description' => 'Whether Google Business reads route through Zernio while our own GBP API application is pending. Off until a tenant-scoped account mapping exists — the client cannot verify that an account id belongs to the tenant it is called for. Retire this key when direct access is approved; the swap re-authorises every tenant and is a commercial decision, not a deploy.',
            ],

            /*
             * The platform's monthly Zernio ceiling — decision 4720, closing
             * 4685's "no meter, no budget, no ceiling and no debit".
             *
             * ⚠️ IT IS IN DOLLARS PER MONTH BECAUSE ZERNIO BILLS PER CONNECTED
             * ACCOUNT PER MONTH, NOT PER CALL. Verified against
             * docs.zernio.com/pricing and /billing on 2026-08-17: accounts 1-2
             * free, 3-10 at $6 each, 11-100 at $3, 101+ at $1, graduated on the
             * monthly total, with API calls explicitly not billed at all —
             * "no post caps, no add-ons, and no per-feature pricing". A daily
             * per-call budget of PlacesSpend's shape would have bounded a
             * quantity that costs nothing.
             *
             * WHERE 50000 COMES FROM, so the seed is a derivation rather than a
             * feeling. One connected account is one location with a Google
             * binding, and a location is a billed line — $179.99 for the first
             * and $99.99 for each additional. Running the published ladder:
             *
             *   10 accounts    $6 x 10 - $12 credit                    $48/mo
             *   100 accounts   $60 + $3 x 90 - $12                    $318/mo
             *   282 accounts   $60 + $270 + $1 x 182 - $12            $500/mo
             *
             * $500 therefore covers exactly 282 connected accounts. Against 282
             * locations at the base plan that is well under one percent of the
             * revenue they carry, and it is many times the install base this key
             * is seeded into — `gbp.zernio_enabled` is still off and no tenant
             * has connected, so the honest count today is zero. It should never
             * fire; if it does, read it the way `ai.monthly_cap_per_tenant` and
             * `places.tenant_daily_spend_ceiling_cents` say to read theirs — as
             * a signal that something changed, before it is a number to raise.
             *
             * ⚠️ WHAT HAPPENS AT THE CEILING IS NOT AN ERROR AND MUST NOT
             * BECOME ONE. `29` §2 rule 44 requires the whole review engine to
             * run with zero GBP API access, so a tenant refused a connection
             * lands on the Copy + Open Google handoff — a first-class path this
             * product already guarantees. That is what makes a ceiling here
             * compatible with rule 43's surviving half (3294): graceful
             * degradation, never hard-fail.
             *
             * ⚠️ PLATFORM-SCOPED, NOT PER TENANT, and deliberately unlike
             * `places.tenant_daily_spend_ceiling_cents` — whose fate is an open
             * question at 4688 and which this key does not touch. A per-tenant
             * Zernio cap could not do anything: a tenant has one or a few
             * connected accounts, always inside any per-tenant figure, so the
             * cap would be decoration on every account while the platform total
             * ran away unwatched.
             */
            'gbp.zernio_monthly_ceiling_cents' => [
                'seed' => 50_000,
                'group' => 'Google Business Profile',
                'description' => 'The platform\'s Zernio bill ceiling for a calendar month, in integer cents. Zernio bills per CONNECTED ACCOUNT per month — graduated $6/$3/$1 with the first two free — and does not bill per API call at all, so this bounds connections rather than requests (verified against docs.zernio.com/pricing and /billing, 2026-08-17). 50,000¢ is $500, exactly 282 connected accounts. Past it, new Google connections are refused and the tenant lands on rule 44\'s Copy + Open Google handoff, which is a built path and not a failure — so this ceiling degrades rather than hard-failing. Platform-scoped: a per-tenant Zernio cap would be decoration, because no single tenant\'s two or three accounts can approach any per-tenant figure.',
            ],

            /*
             * The bell over Zernio accounts nothing here is using — 6779(d),
             * 6917, 7393, 7394, answered by the owner at 9200.
             *
             * ⛔ **THIS IS A BELL AND THE ENTRY ABOVE IS A BRAKE, AND THEY MEASURE
             * DIFFERENT QUANTITIES.** The ceiling asks what OUR OWN bindings
             * would cost with one more on them and refuses a connection. This
             * asks what the VENDOR'S list costs minus what our bindings cost, and
             * refuses nothing at all: the account is already connected, the
             * grant is already live, and the only thing that ends one is a person
             * in Zernio's own console (4884, 6767). Wiring this figure into
             * `ZernioSpend::allowsNewAccount()` would make a ceiling that asks a
             * third party, which `ZernioReconciliation`'s own docblock refuses in
             * bold: a vendor outage would then refuse every connection on the
             * platform.
             *
             * ⛔ **SEEDED, AND 7394(a) ASKED FOR NO SEED — THE DEPARTURE IS
             * DELIBERATE AND IS 9232's** (see `docs/DECISIONS.md`). 7394 was
             * written while the figure was **unknown** and put the key on
             * `storage.retention_days.*`'s precedent for that reason. The owner
             * answered at 9200 — **$10 a month** — and the precedent stops
             * applying at the point that made it a precedent: a retention period
             * carries no seed because **seeding one destroys other people's
             * photographs on the first scheduled run of every install** (4942),
             * where an unset threshold here is merely inert. ⚠️ **And inert is
             * the failure 7393 refused**: *"a bell that cannot ring on any
             * install"*. Shipping the answered figure as a seedless key would
             * have left `app/` behaving exactly as it did before the owner
             * answered, which is 2074's shape — *a ruling with no writer looks
             * exactly like a ruling with one* — inside the wave that fixed
             * another instance of it (9201).
             *
             * ⚠️ **SEEDING DOES NOT TAKE THE ROW AWAY FROM AN OPERATOR.**
             * `DefaultsRegistry::int()` reads the stored row and falls back to
             * the seed, so moving this is still an Ops edit rather than a deploy,
             * which is the property 9200 asked for by name.
             *
             * ⛔ **AND THE FIGURE DOES NOT MEAN WHAT ITS OWN RATIONALE IMPLIES,
             * WHICH IS 9233 AND IS THE OWNER'S TO MOVE.** 9200 reasons about $10
             * as *"2% of the $500/month ceiling"*. The quantity it is compared
             * against is `ZernioReconciliationReport::orphanMonthCents()`, which
             * is a **difference of two ladder readings** and subtracts the $12
             * free-tier credit on both sides, flooring each at zero — so at one
             * connected account the first ring is at **three** orphans, at fifty
             * it is at four, and at nought bound it is at four. There is no
             * figure that makes it ring at one, because there is no price for one
             * orphan (`ZernioReconciliationReport`'s own docblock). The
             * description below states the trip in orphans as well as in cents,
             * because cents is not the unit the operator is looking at.
             */
            'gbp.zernio_orphan_alert_cents' => [
                'seed' => 1_000,
                'group' => 'Google Business Profile',
                'description' => 'How much unaccounted-for Zernio spend, per month in integer cents, before the operator is alerted (decisions 7394, 9200). 1,000¢ is $10, the owner\'s figure. 0 disables the check. ⛔ THIS IS A BELL AND NOT A BRAKE: nothing is refused, no connection is stopped and nothing on this platform can end an orphaned account — the account is disconnected in Zernio\'s own console, which this application cannot reach. ⚠️ THE QUANTITY IS A DIFFERENCE OF TWO LADDER READINGS AND NOT A PER-ACCOUNT RATE: Zernio\'s graduated $6/$3/$1 applies to the platform total and its $12 free-tier credit is subtracted from each side, so there is no price for one orphan and no figure here can make this ring at one. At one connected account it first rings at three unaccounted-for accounts; at fifty, at four. ⚠️ AND THE MONEY IS THE MEASURABLE HALF RATHER THAN THE IMPORTANT ONE: an unaccounted-for account is a subprocessor holding read AND write access on a real business\'s Google listing with no record here of whose, so the figure errs low on purpose. ⚠️ Raising it does not make the accounts go away; it makes the nightly sweep stop saying so.',
            ],

            /*
             * Support impersonation, `28` §9.4.
             *
             * ⚠️ **THE ONE KEY IN THIS FILE THE SOURCE DOCUMENT NAMES BY NAME**,
             * as `platform_settings.impersonation_notify_owner = true`. The
             * spelling here is dotted because every key in this manifest is and
             * a lone underscored one would be the odd row an operator misreads;
             * the value and the default are `28`'s.
             *
             * ⚠️ **Seeded `true`, and that is rule 3 running the *opposite* way
             * to `gbp.zernio_enabled` two entries up.** Rule 3 says write the
             * conservative value, and conservative is a property of the risk,
             * not of the word "off". A flag authorising third-party spend is
             * conservative at off. A flag authorising **the email that tells a
             * customer we were inside their account** is conservative at on:
             * an unnecessary email is noise, an unsent one is an undisclosed
             * support session. `Impersonation::notifyOwner()` reads it as
             * `=== false` for the same reason, so a malformed row still sends.
             */
            /*
             * The review-invite email (`17` FPR-04's second delivery channel).
             *
             * ⚠️ **Seeded `false`, and this is rule 3 pointing the ordinary way
             * again — the entry below it is the exception, not this one.** The
             * reason is specific rather than general caution: **open question H
             * is not built** (714). Nothing consumes Azure Communication
             * Services' Event Grid bounce and complaint reports, nothing writes
             * `contact_suppressions` from a bounce, and a brand-new ACS sending
             * domain is in warmup — which is the single worst place to discover
             * you have been mailing addresses that no longer exist. Reputation
             * lost during warmup is not quickly recovered.
             *
             * WHAT IS LOST WHILE IT IS OFF, so nobody reads this as the feature
             * being broken: nothing. The customer still submits feedback, still
             * sees the on-screen picker, and still reaches Google — that path
             * needs no email and is the one row 3's gate is written against
             * (`BUILD-PLAN` §2.6.4 conflict 2). The email is a second chance for
             * somebody who closed the tab.
             *
             * Turning it on is a settings row rather than a deploy, and the
             * thing to build first is the Event Grid webhook.
             */
            'review_invite.email_enabled' => [
                'seed' => false,
                'group' => 'Reviews',
                'description' => 'Whether a customer who is invited to review is also emailed the destination links (`17` FPR-04). Off until bounce and complaint handling exists — open question H, now buildable on ACS Event Grid (714). The on-screen picker is unaffected either way.',
            ],

            /*
             * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 4's monthly event cap —
             * decision 5000s.
             *
             * §21's frozen decisions state the figure verbatim: *"Event cap —
             * 500k/tenant/month."* §2.1's free-tier budget table is the reason
             * one exists at all: *"Cloudflare Workers 100k req/day free, then
             * $5/10M … Collector must be the only hot path … Total marginal
             * cost per tenant at cap: under $0.05/month."* This platform's
             * ingest runs in Laravel + Postgres rather than Workers, but the
             * discipline the figure encodes — one tenant's traffic must not be
             * able to run the platform's marginal cost away — is the same one.
             *
             * ⚠️ **A VOLUME CAP, NOT A DOLLAR CAP, AND 3293 DOES NOT TOUCH IT.**
             * `CLAUDE.md`'s commercial-model section deletes the per-tenant
             * *cost* ceiling in favour of the credit balance; this key never
             * reaches `credit_ledger` and nothing here is billed. §11 row 4's
             * own words are the rule this key enforces: *"pageviews continue,
             * others dropped, `events_dropped++`, ops alerted once. Never bill,
             * never hard-fail."* `App\Services\Pixel\MonthlyEventCap` is where
             * that reads.
             *
             * ⚠️ **ZERO OR UNSET MEANS NO CAP, NOT "REFUSE EVERYTHING" —
             * DELIBERATELY THE OPPOSITE OF `gbp.zernio_monthly_ceiling_cents`
             * TWO ENTRIES ABOVE.** A dollar ceiling of zero refusing every new
             * connection is the fail-closed reading of a *cost* control; a
             * volume cap of zero refusing every pixel event on a fresh install
             * would be a hard-fail on a brand-new tenant's very first pageview,
             * which is exactly what §11 row 4's own "never hard-fail" forbids.
             * `MonthlyEventCap::admit()`'s docblock names this divergence and
             * why it is not an inconsistency.
             */
            'pixel.free_event_cap_monthly' => [
                'seed' => 500_000,
                'group' => 'Pixel',
                'description' => 'The free-tier monthly event ceiling per tenant (`GOAIEZ_PIXEL_MASTER_BUILD` §21: "Event cap — 500k/tenant/month"). At the cap, pageviews keep landing and every other event type is dropped, counted on `pixel_monthly_usage.events_dropped`, and the operator is alerted once per quiet window. Never bills, never hard-fails. Zero or unset means uncapped, the opposite direction from a dollar ceiling, because a volume cap that refused a fresh tenant\'s first pageview would itself be the hard-fail this row exists to avoid.',
            ],

            /*
             * The review-invite text message (`17` FPR-04's third delivery
             * channel), row 4 slice 4.
             *
             * ⚠️ **A THIRD SWITCH, AND THE THIRD ONE IS DELIBERATE** (1603).
             * `sms.enabled` below is **the channel** — may this application send
             * a text message at all, the one an operator flips to stop every
             * outbound text in an incident. This is **the feature** — may the
             * review invite use that channel. Either one off means no send, and
             * collapsing them would mean stopping the review invite and stopping
             * every future SMS the platform sends were the same act. That is the
             * same split as `review_invite.email_enabled` above sitting beside
             * the mailer's own guards, and decision 193's deployment-fact versus
             * operational-fact division one layer up.
             *
             * ⚠️ **Seeded `false`, and what it waits for is a third party rather
             * than any code of ours: the 10DLC campaign.** The brand cleared
             * (1562) and the campaign was filed and REJECTED (11617); a
             * *successful* campaign takes roughly one to two weeks from
             * submission, and the rejection reason has not been obtained. Until it is approved the carriers filter
             * an A2P message on an unregistered campaign — silently, with no
             * error path back to us — so a send made in the meantime looks
             * perfectly successful from every screen in this application and
             * reaches nobody. That is the failure this codebase records most
             * often, on the channel where the recipient is somebody else's
             * customer.
             *
             * WHAT IS LOST WHILE IT IS OFF, so nobody reads it as broken: the
             * same as the email switch above — nothing. The customer still
             * submits feedback, still sees the on-screen picker and still reaches
             * Google, which is the path row 3's gate is written against.
             */
            'review_invite.sms_enabled' => [
                'seed' => false,
                'group' => 'Reviews',
                'description' => 'Whether a customer who is invited to review is also texted the destination link (`17` FPR-04). Off until the 10DLC campaign is approved — the brand cleared, the campaign was filed and REJECTED, and a successful filing takes roughly one to two weeks from submission (1562, 11617). ⚠️ This is the feature switch; `sms.enabled` is the channel switch, and either one off means no text. The on-screen picker is unaffected either way.',
            ],

            /*
             * Whether this application may send a text message at all.
             *
             * ✅ **SEEDED TRUE AS OF ROW 4 SLICE 2, AND WHAT CHANGED IS THE ONE
             * THING 1567 SAID HAD TO** — inbound STOP handling exists. It
             * seeded **false** from slice 1, and the reason was never symmetry
             * with the email switch above but sequence: **you may not send what
             * you cannot stop.**
             *
             * An inbound STOP that fails silently is the worst defect available
             * in this product. The customer's instruction is honoured nowhere,
             * every later send looks perfectly permitted because consent still
             * says yes, and the failure is invisible from every screen until it
             * arrives as a complaint — or as a TCPA letter, which is the version
             * with a statutory damages figure attached.
             *
             * ✅ **`opt_outs` NOW HAS A WRITER FROM A REAL CHANNEL.**
             * `InboundMessages` writes it through
             * `ConsentService::suppressFromCarrier()`, platform-scoped, and
             * `ConsentService::isSuppressed()` asks `hasOptedOut()` before
             * anything tenant-scoped — so one row refuses every send to that
             * person on every tenant, which is what a carrier STOP on a shared
             * number means. Decision 272's shape is closed on the one table
             * where being writerless was a legal exposure.
             *
             * ⚠️ **THIS SWITCH BEING TRUE STILL SENDS NOTHING ON ITS OWN, AND
             * READING IT AS "SMS IS LIVE" IS THE MISTAKE TO AVOID.** Three
             * further things stand between here and a message reaching a
             * handset, and none of them is this row:
             *
             *   `SMS_DRIVER` seeds `log` — a driver that reaches nobody.
             *   `INFOBIP_WEBHOOK_SECRET` is unset, so inbound STOP is refused
             *     with a 401 and the stop path, though built, is not *running*.
             *   The 10DLC campaign was filed and REJECTED (11617), so the
             *     carrier would filter the message anyway.
             *
             * The switch records that the stop path **exists**. An operator
             * turning sending on for real is checking all four.
             *
             * ⚠️ **IT IS NOT THE SAME SWITCH AS `SMS_DRIVER`**, and the pair is
             * deliberate: the driver is a deployment fact (which carrier, or the
             * log driver that reaches nobody) and this is the operational one an
             * operator flips in Ops without a deploy — decision 193's split,
             * applied where an incident response needs to be one query long.
             */
            'sms.enabled' => [
                'seed' => true,
                'group' => 'Messaging',
                'description' => 'Whether this application may send text messages. Turned on in row 4 slice 2, when inbound STOP, HELP and START handling shipped — you may not send what you cannot stop. ⚠️ This alone sends nothing: a carrier must also be selected on this deployment, the inbound webhook refuses everything until its signing key is set, and the 10DLC campaign must be filed. ⚠️ Which carrier a deployment has selected is a setting on the machine, not in this database, so nothing written here can tell you. Turn this off to stop all outbound text immediately, without a deploy.',
            ],

            /*
             * Whether voice events are acted on at all — T176 P2, the voice
             * forwarding path.
             *
             * ⛔ **SEEDED `false`, AND WHAT IT WAITS FOR IS ENTIRELY EXTERNAL.**
             * T176 §7 item 3: *"Infobip — account steps: activate Voice/Calls API
             * (gates P2)."* Nothing in this repository can unblock it. Until then
             * the whole path is built, migrated, routed and tested — a webhook is
             * verified, queued and answered `provider_unavailable`.
             *
             * ⚠️ **IT IS NOT THE SAME SWITCH AS `VOICE_DRIVER`**, and the pair is
             * `sms.enabled`/`SMS_DRIVER`'s split exactly: the driver is a
             * deployment fact and this is the operational one an operator flips
             * in Ops without a deploy. **Both must be set** — this row on, and
             * `VOICE_DRIVER=infobip` — before a single call is recorded.
             *
             * ⚠️ **AND IT IS A STOP FOR THE INGEST, NOT FOR THE TEXT-BACK.**
             * Turning it off stops calls being recorded, which stops
             * `CallMissed` firing, which stops the SM-001 text-back — but the
             * switch that stops **all** outbound text in one query is still
             * `sms.enabled`, and `SendingGuard`'s global halt outranks both.
             * Reading this row as an outbound kill switch is the mistake to
             * avoid.
             */
            'voice.enabled' => [
                'seed' => false,
                'group' => 'Messaging',
                'description' => 'Whether inbound call events are recorded and acted on. ⛔ Off until Infobip activates Voice/Calls on the account (T176 §7 item 3) — an external step nothing in this application can perform. ⚠️ This alone answers nothing: a voice provider must also be selected on this deployment. ⚠️ Which provider a deployment has selected is a setting on the machine, not in this database, so nothing written here can tell you. Turn this off to stop recording calls and firing missed-call text-backs immediately, without a deploy — but `sms.enabled` is the switch that stops all outbound text.',
            ],

            /*
             * The two ceilings on inbound voice — decision 4686, closing the
             * sharpest of the three gaps 4684 enumerated.
             *
             * ⛔ **MINUTES, NOT MONEY, AND THAT IS A VENDOR FACT RATHER THAN A
             * PREFERENCE.** Infobip publishes no base voice per-minute rate:
             * `https://www.infobip.com/voice/pricing`, read 2026-08-17 —
             * *"We display the average price across all supported networks for
             * each country. Per-network pricing is available in Portal."* The
             * rate is per account, per destination network, behind a login.
             * `messaging.carrier_cost_*` sits unseeded for the identical reason
             * on the identical vendor, and `CLAUDE.md` records four burns from
             * writing a plausible vendor figure from memory. Minutes is the unit
             * the vendor bills in and the one an operator can size without a
             * rate.
             *
             * WHAT 240 MINUTES IS, so the seed is a derivation rather than a
             * feeling. The missed-call voice path: the caller rings,
             * the tenant's own handset does not answer, the carrier's
             * conditional forward hands the leg to us, and the caller leaves a
             * message. A busy local business missing twenty calls a day, each
             * running two minutes from forward to hang-up, spends forty. Four
             * hours is therefore roughly **six times a busy day** on a single
             * number — it should never fire, and if it does it should be read
             * the way `places.tenant_daily_spend_ceiling_cents` says to read its
             * own: **as a signal that something is looping, rather than as a
             * number to raise.**
             *
             * ⚠️ **SEEDED RATHER THAN WITHHELD, AND THE FAIL-CLOSED DIRECTION IS
             * WHY.** A withheld figure raises `WithheldRegistryValue`, and the
             * fail-closed state on this path is *no voicemail audio for anybody*
             * — the ceiling gates `FetchVoicemailRecordingJob`. A price is the
             * thing worth refusing to quote (157); an operational ceiling on a
             * live product feature is not.
             */
            'voice.tenant_daily_inbound_minutes_ceiling' => [
                'seed' => 240,
                'group' => 'Messaging',
                'description' => 'One account\'s inbound call minutes in a day before this platform stops downloading their voicemail recordings and alerts the operator (decision 4686). ⚠️ About six times a busy day on one number, so it should never fire — read it as a signal that something is looping, not as a number to raise. ⛔ It does NOT stop the calls: the minutes are billed at the carrier by a forwarding rule and no code path here can decline one. What it stops is the recording download and the transcription behind it; the missed call, the caller\'s number, the owner\'s email and the text-back all survive. ⚠️ ZERO MEANS ZERO — it stops every recording download platform-wide, without a deploy, and a negative figure is read as zero and does the same. ⛔ DO NOT TYPE A ZERO HERE TO QUIETEN THE ALERT: the alert goes quiet, because at a ceiling of zero every call would cross it, and the recordings stop with it for every account on the platform. That is an emergency brake and it is kept as one deliberately; it is not a mute.',
            ],

            /*
             * The same ceiling for the calls nobody owns.
             *
             * ⛔ **THIS IS THE STRANGER'S ONE, AND IT IS THE REASON THE METER IS
             * A PLATFORM TABLE RATHER THAN A COLUMN ON `calls`.**
             * `VoiceCalls::record()` answers `UnknownNumber` and writes **no row
             * at all** when a called number resolves to no business — which its
             * own docblock calls *"the common case today"*, because the shared
             * Lane A pool number belongs to no tenant. So before 4686 the calls
             * with nobody to bill were also the ones with nobody counting.
             *
             * WHY IT IS A QUARTER OF THE TENANT FIGURE. Nothing in this product
             * routes voice to a number no account owns: the pool exists for Lane
             * A texting, and a voice leg arriving there is either a misdialled
             * digit or somebody working through a number range. An hour a day is
             * generous for the first and small for the second, and what it pulls
             * is a bell rather than a brake — there is no tenant, so there is no
             * voicemail and no download to refuse.
             */
            'voice.unattributed_daily_inbound_minutes_ceiling' => [
                'seed' => 60,
                'group' => 'Messaging',
                'description' => 'Inbound call minutes in a day, on numbers no account owns, before the operator is alerted (decision 4686). ⚠️ Alert only — there is no tenant, so there is no voicemail to withhold and nothing to brake. Nothing in this product routes voice to a pool number, so any sustained volume here is a misdial or somebody walking a number range.',
            ],

            /*
             * The welcome text a customer gets once, after they tick the box on
             * `/f/{slug}` — the opt-in confirmation the 10DLC campaign filing
             * declares (3260).
             *
             * ⛔ **SEEDED `false`, AND UNLIKE `review_invite.sms_enabled` ABOVE
             * WHAT IT WAITS FOR IS PARTLY OURS** (3270). The external half is the
             * same: an unapproved campaign is filtered silently. The half that is
             * ours is that arming this converts a **public, unauthenticated
             * endpoint** into one that sends a text message to any number typed
             * into it. `/f/{slug}` has previously been found writing consent
             * against identifiers the submitter did not own (334–337), and the
             * per-IP-hash limiter that bounds the abuse collapses to one global
             * bucket while `TrustProxies` is unconfigured (336).
             *
             * ⛔ **AND THIS SWITCH IS A DEFERRAL RATHER THAN THE MITIGATION —
             * THE PARAGRAPH ABOVE SAID OTHERWISE AND WAS WRONG** (3279). It
             * instructs an operator to arm this *before* the invite switch, so
             * the intended deployment path is the exploit path, and a switch on
             * a feature that does nothing while off protects nobody the day it
             * is turned on. **The containment is
             * `sms.optin_confirmation_daily_cap` below.**
             *
             * WHAT STILL STANDS BEHIND IT WHEN IT IS ON, so nobody reads the
             * switch as the only control: the daily ceiling below; the consent
             * permit, with the platform-wide `opt_outs` register and the
             * litigator list behind it (⚠️ **federal and state DNC do not apply
             * to a `Transactional` send** — 3281); one message per contact **per
             * tenant** for ever, held by a `SendKey` unique index rather than by
             * a check; the tenant's SMS credit, which every send debits; and
             * `SendingGuard`'s global halt, per-tenant pause and automatic
             * complaint-rate trip.
             *
             * WHAT IS LOST WHILE IT IS OFF: the customer still submits feedback,
             * still sees the on-screen picker, and still gets the review invite
             * if that switch is on. What they do not get is the message that
             * would have told them who we are before the invite arrives — which
             * is why turning this on should come *before* turning that one on,
             * not after.
             */
            'sms.optin_confirmation_enabled' => [
                'seed' => false,
                'group' => 'Messaging',
                'description' => 'Whether a customer who ticks the SMS consent box is sent the one-time welcome text the 10DLC campaign filing declares. Off until the campaign is approved. ⛔ Arming this makes the public feedback page able to send a text to any number typed into it (3278). This switch is a deferral and NOT the mitigation (3279) — the containment is `sms.optin_confirmation_daily_cap`. Turn it on before `review_invite.sms_enabled`, so nobody receives an invite as their first ever message.',
            ],

            /*
             * ⛔ **THE CONTAINMENT ON 3278, AND THE ONLY THING BOUNDING A FORGED
             * OPT-IN ONCE THE SWITCH ABOVE IS ARMED** (3280). `/f/{slug}` is
             * public and unauthenticated and accepts a phone the submitter does
             * not own (334–337), so a script can cause a confirmation to be sent
             * to any real mobile. **The message itself is deliberately kept** —
             * it is the standard mitigation for a forged opt-in, reaching the
             * victim in seconds and handing them a platform-wide STOP — so what
             * is bounded is the volume.
             *
             * ⚠️ **PER TENANT PER DAY, COUNTING SENDS RATHER THAN REQUESTS.** A
             * per-visitor limiter is the obvious control and it is the one that
             * is not there: 336 records `TrustProxies` as unconfigured, so every
             * hashed-IP bucket in this codebase collapses into one global bucket
             * behind a reverse proxy — and a botnet defeats a per-visitor bucket
             * regardless. A send counter is immune to both.
             *
             * ⚠️ **50 IS A CONSERVATIVE GUESS AND IT IS THE OWNER'S TO RAISE.** A
             * safety ceiling is the one place a guess in the strict direction is
             * the right kind of guess — unlike a price, which 153's rule forbids
             * guessing at all, because a low cap costs a tenant a day's silence
             * while a wrong price silently becomes policy. Fifty SMS opt-ins in
             * one day is far above any realistic single-location feedback-page
             * volume. **A tenant who legitimately hits it gets silence**, which
             * is why `OptInConfirmations` logs it rather than only refusing.
             *
             * ⛔ **ZERO MEANS STOP, NEVER UNLIMITED.** Read as unlimited, a
             * misconfigured cap is the permissive branch applied exactly where
             * the strict rule was meant to bind (1568), on the one control
             * standing between a public form and a carrier.
             */
            'sms.optin_confirmation_daily_cap' => [
                'seed' => 50,
                'group' => 'Messaging',
                'description' => 'The most SMS opt-in confirmations one tenant may send in a day — a hard server-side ceiling (`29` §2 rule 43) bounding the forged-opt-in exposure of the public feedback page (3278, 3280). It counts sends rather than requests, because the per-visitor limiter collapses to one global bucket while TrustProxies is unconfigured (336). ⛔ Zero means stop, never unlimited. A conservative default; raising it is the owner\'s call.',
            ],

            /*
             * ⚠️ **THE PLATFORM QUIET-HOURS FLOOR, AND IT EXISTS BECAUSE THE
             * STATE TABLE SHIPS EMPTY** (1609). `ConsentService::stateRefusal()`
             * was the only quiet-hours enforcement in `app/`, and it asked
             * `StateMessagingRules::for($state)` — which returns null for a
             * state with no row and is correctly read as "nothing stricter than
             * federal here", i.e. **permits**. That was harmless only while
             * `StateUnknown` refused every marketing send outright; slice 5 gave
             * `region_code` a writer and removed exactly that. `state_messaging_
             * rules` ships with no rows at all (they are counsel's), so on the
             * day the three registers load, every state is a no-row state and
             * marketing would send at any hour, anywhere.
             *
             * The figures are 47 CFR 64.1200(c)(1)'s federal window —
             * solicitation permitted 8am to 9pm local — so the prohibited band
             * is 21:00 to 08:00.
             *
             * ⛔ **A STATE ROW UNIONS WITH THESE RATHER THAN OVERRIDING THEM**
             * (1617). This comment said the opposite for one commit, which is
             * what a *default* means and is not what a *floor* means: the
             * federal band binds nationwide, a state may be stricter, and no
             * state may authorise an hour federal forbids. A counsel row
             * narrower than federal — `22:00`–`07:00`, say — otherwise permitted
             * 21:30 in that state, on the strength of a row somebody entered in
             * order to be *more* careful.
             *
             * ⛔ **AND THEY BIND MARKETING ONLY, WHICH OVERRIDES `24` §3.4 AND
             * `29` §754** (1618). Both word the quiet-hours row as applying to
             * **All** message types, and this comment quoted that wording as
             * though the code implemented it. It never did:
             * `ConsentService::stateRefusal()` returns before the floor for any
             * purpose outside `isSubjectToDoNotCall()`, so `Transactional` — the
             * only thing this product sends today — has never had a window. The
             * owner ruled that it should not: the review invite answers a
             * feedback form the customer just submitted, which is a response to
             * a completed interaction rather than a solicitation, and refusing
             * it would **lose** the message rather than delay it, because
             * `ReviewInviteSender` never retries and the deferral scheduler is
             * blocked on doc `42`.
             *
             * ⚠️ **REGISTRY KEYS RATHER THAN CONSTANTS, on 1420's rule.** These
             * are hours, which is `38` Part 2's "window" — the named category
             * that may not be a literal (505). An operator who has to narrow the
             * band for an incident should not need a deploy.
             *
             * ⚠️ **THE ROW MAY ONLY WIDEN THE PROHIBITED BAND, NEVER NARROW IT**
             * (1619). `StateMessagingRules::platformFloorRefusal()` evaluates the
             * seed below **as well as** whatever is stored, so an Ops keystroke
             * can close more of the day and can never reopen the federal band.
             * Range and degeneracy checks alone could not make that true —
             * `07:00`–`07:30` is well-formed, in range and non-degenerate, and
             * removes the floor almost entirely. Moving the federal figures is an
             * edit to this file and costs somebody a code review.
             *
             * ⚠️ **THEY ARE NOT A TENANT TOGGLE AND MUST NOT BECOME ONE.** No
             * screen reads them and none may: the whole point of a floor is that
             * it is the same everywhere and nobody local can widen it.
             */
            'messaging.quiet_hours_start' => [
                'seed' => '21:00',
                'group' => 'Messaging',
                'description' => 'When the platform quiet-hours band opens, in the recipient\'s local time — the hour after which no marketing message goes out (47 CFR 64.1200(c)(1)). It binds in every state: a state_messaging_rules row adds to this band rather than replacing it, so the strictest of the two applies and a state row narrower than federal cannot reopen an hour federal forbids. Marketing only — transactional messages are not held to a window. Editing this can only close more of the day; it can never open the federal band back up. Not per tenant and never surfaced to one.',
            ],

            'messaging.quiet_hours_end' => [
                'seed' => '08:00',
                'group' => 'Messaging',
                'description' => 'When the platform quiet-hours band closes, in the recipient\'s local time — marketing may resume at this hour (47 CFR 64.1200(c)(1)). It binds in every state: a state_messaging_rules row adds to this band rather than replacing it, so the strictest of the two applies. Marketing only — transactional messages are not held to a window. Editing this can only close more of the day; it can never open the federal band back up.',
            ],

            /*
             * ⚠️ **THE CONTAINMENT FOR 2101, AND IT IS THE ONLY THING STANDING
             * BETWEEN ONE TENANT'S LIST AND EVERY OTHER TENANT'S DELIVERY.**
             * Tenant number isolation sends attested lists over the GOAIEZ 10DLC brand from our
             * own number pool, so the complaint rate accrues to the *platform*
             * across every tenant at once — Lane A infrastructure carrying Lane
             * B consent. 2102: the trip is automatic, *"because a kill switch
             * that needs somebody awake is the mitigation this override cannot
             * rely on."*
             *
             * ⚠️ **THESE ARE THE TWO NUMBERS SOMEBODY WILL BE TEMPTED TO RAISE
             * AFTER AN ALERT**, and 511 is the record of where that ends: a
             * lint tuned until it stops crying wolf is one tuned until it
             * catches nothing. Raising the threshold is a decision with an
             * audit trail, not a config tweak.
             */
            'messaging.complaint_trip_bp' => [
                'seed' => 300,
                'group' => 'Messaging',
                'description' => 'The complaint rate, in basis points of delivered messages, at which a tenant\'s sending is paused automatically (T137 §3.2, decision 2102). 300 = 3%. ⚠️ Basis points rather than a float, for the reason money is integer cents: this figure is compared against a stored snapshot and a float that survives a JSON round trip as 0.0299999 compares wrong against itself. Zero or negative disables the automatic trip entirely — recorded deliberately, because a misconfigured threshold that paused every tenant on their first message would be indistinguishable from a platform outage. The complaint signal today is a STOP arriving in reply to a message we sent; there is no carrier feedback loop wired yet, and an ordinary opt-out is deliberately NOT counted as a complaint.',
            ],

            'messaging.complaint_trip_min_delivered' => [
                'seed' => 50,
                'group' => 'Messaging',
                'description' => 'How many messages must have been delivered in the rolling 24-hour window before a complaint rate is allowed to trip the pause. ⚠️ Without this floor the trip fires for every tenant: one STOP out of a new tenant\'s first two deliveries is a 5,000bp rate, and every tenant starts at zero traffic. It is a significance floor, not a grace period — it does not exempt anybody, it waits until the arithmetic means something. ⚠️ Zero disables the trip, threshold or no threshold — the same sentence its platform twin `messaging.platform_complaint_min_delivered` has always carried, and it became true of this key on 2026-08-16 (4492–4495), when `SendingGuard::shouldTrip()` gained the refusal `PlatformRateSample::trips()` already had. Blanking this box does not arm the trip on a floor of nothing; it switches the trip off.',
            ],

            /*
             * The platform twin of the two keys above — 2119(b), and the
             * automatic half of 2102 that 2118 records was never built.
             *
             * ✅ **ARMED BY THE OWNER ON 2026-08-12 (2684): 100bp over a floor of
             * 50 delivered.** Both seeded to `0` from 2119 until then, which is
             * 2409's ruling and was the right call at the time: the platform
             * threshold was *"the one figure this lane must not invent"*, because
             * a per-tenant basis-point number is not a platform one and reusing
             * `messaging.complaint_trip_bp` for both would have been a guess
             * wearing a seeded key's clothes. **The mechanism was the
             * deliverable; the number was the owner's**, exactly as decision
             * 153's add-on cap is theirs — and it now has an answer, so the
             * mechanism is switched on rather than merely built.
             *
             * ⚠️ **ARMING THIS ONLY BECAME SAFE WHEN THE COUNTERS GAINED
             * WRITERS** (2520–2539, closing 2496–2499). Before that,
             * `sending_health_windows` had no writer in `app/` at all, so every
             * rate was permanently zero and a threshold set against it would have
             * been a decoration — `docs/FAILURE-SHAPES.md`'s *"a set threshold
             * on a dead counter"*. A trip armed on a dead counter never fires
             * and reads,
             * from the outside, exactly like one that is working.
             *
             * ⛔ **100 IS TIGHTER THAN THE PER-TENANT 300 ON PURPOSE, AND NOT BY
             * OVERSIGHT.** 2101: every tenant sends over the *same* GOAIEZ 10DLC
             * brand from our own number pool, so a rate that is unremarkable for
             * one tenant is a brand-level problem in aggregate — a carrier sees
             * one sender, not ten thousand. Do not "correct" the two figures
             * toward each other; the aggregate crosses a sensible line long
             * before any one tenant does.
             *
             * ⚠️ **THE TWO ARE ONE SETTING IN TWO BOXES.** A threshold with no
             * floor trips on the first complaint of the first hour; a floor with
             * no threshold does nothing. Either one set back to zero disables the
             * trip, and `PlatformRateSample::trips()` is where that is enforced
             * rather than remembered.
             */
            'messaging.platform_complaint_trip_bp' => [
                'seed' => 100,
                'group' => 'Messaging',
                'description' => 'The complaint rate, in basis points of messages delivered across EVERY tenant in the rolling window, at which all platform sending halts automatically (decision 2102, 2119, armed at 2684). 100 = 1%. ⛔ Deliberately TIGHTER than `messaging.complaint_trip_bp`, which is 300: that one contains a single tenant, this one is what a carrier sees across the shared GOAIEZ brand and our own number pool (2101), where every tenant looks like one sender — so the aggregate crosses a sensible line long before any one tenant does. Do not raise this to match the per-tenant figure. ⚠️ Must be set together with `messaging.platform_complaint_min_delivered`; either at zero disables the trip entirely, which is how it shipped from 2119 until the owner chose these numbers.',
            ],

            'messaging.platform_complaint_min_delivered' => [
                'seed' => 50,
                'group' => 'Messaging',
                'description' => 'How many messages must have been delivered across every tenant in the rolling window before the aggregate complaint rate may halt the platform (decision 2684). ⚠️ The floor is the load-bearing half, and it matters more here than in the per-tenant case rather than less: on a soft-launch platform with a handful of tenants the whole aggregate can be a few dozen messages, and one STOP out of three deliveries is a 3,333bp rate that would halt every tenant at once on the strength of one person changing their mind. It is a significance floor, not a grace period — it exempts nobody, it waits until the arithmetic means something. ⚠️ Zero disables the trip, threshold or no threshold.',
            ],

            /*
             * The short-link domain — T137 `SL-5`, settled by decision 2116.
             *
             * ⚠️ **A SEED RATHER THAN A CONSTANT OR A CONFIG VALUE**, and the
             * reason is decision 30's history: it survived four vendor changes
             * because `PlatformMailer` enforced it in one place instead of
             * anybody remembering it, and 2114 kept it a seed for the same
             * reason when the sending domain finally did move. The redirector
             * reads this to decide which host it answers on and the minter
             * reads it to build the URL, so **one value cannot drift from the
             * other into links that resolve nowhere.**
             *
             * ⛔ `goaiez.site` appears in several of the 29–32 documents and is
             * **not owned** (2116). A spec naming it is wrong, not a
             * requirement.
             */
            'messaging.short_link_domain' => [
                'seed' => 'goaiez.ai',
                'group' => 'Messaging',
                'description' => 'The domain short links are minted on and the only host the redirector answers (decision 2116, T137 SL-5). ⚠️ Already owned and already aged, which is what T137 §5 wanted a same-day domain purchase to start earning — a new domain is the worst case for carrier link filtering. It also fits the 159-character SMS composer budget exactly. ⛔ `goaiez.site` is NOT owned despite appearing in several of the 29–32 documents. Changing this moves both the address in new messages and the host the redirector serves, and it does NOT reach links already sent — those keep resolving only while the old domain still points here. ⛔ The 10DLC campaign is registered with "embedded links: branded domain only, no bit.ly", so a link on any other host is a false statement to the carrier as well as an unshortened waste of the 159-unit budget.',
            ],

            /*
             * ⚠️ **THE LAST SENTENCE OF THIS DESCRIPTION SAID THE OPPOSITE OF
             * WHAT THE CODE DOES, FROM 2119 UNTIL 2630.** It read *"Nothing sets
             * this automatically today; the per-tenant trip is the automatic
             * half of 2102"*, which was accurate when it was written and was
             * falsified by 2400–2410 in the very slice that quoted it:
             * `WatchPlatformComplaintRate::halt()` writes `true` here on a
             * schedule.
             *
             * ⛔ **AND IT IS RENDERED TO OPERATORS**, on the settings screen and
             * now on the sending-controls screen beside the switch itself — so
             * an operator reading it would conclude that a platform found
             * halted must have been halted by a person, which is the wrong end
             * of an incident to start from. `CLAUDE.md` 2505's shape exactly: a
             * claim that was true about one mechanism, restated as a fact about
             * the world, and then relied on.
             *
             * The replacement said what sets it **and** that the automatic path
             * shipped disarmed (2409), because "something sets this" and "the
             * thing that sets it is switched off" were both true and neither was
             * safe to leave out.
             *
             * ⚠️ **AND THE SECOND HALF WENT STALE ON 2026-08-12, WHICH IS THE
             * SAME DEFECT AGAIN AND IS WHY THIS PARAGRAPH IS BEING WRITTEN
             * TWICE.** 2684 armed both platform keys, so *"that trip ships
             * DISARMED"* became false the moment the seeds changed — in a
             * sentence rendered to operators on two screens, describing a switch
             * that stops every message the company sends. **The seed and the
             * sentence describing the seed must move in the same edit**; there is
             * no version of this where one lands without the other.
             */
            'messaging.global_halt' => [
                'seed' => false,
                'group' => 'Messaging',
                'description' => 'Stops ALL outbound messaging on the platform, for every tenant and every channel, immediately and without a deploy — T137 SL-8\'s global halt, and A PERSON\'S SWITCH ONLY. ⚠️ Distinct from `sms.enabled`, which is one channel\'s switch: this one outranks every channel and every tenant. Checked before the per-tenant pause so that an operator who halted the platform gets a refusal naming the switch they actually threw, rather than reasoning about ten thousand tenant rows. ⛔ NOTHING WRITES THIS AUTOMATICALLY, AND THAT CHANGED ON 2026-08-15 (3980–3983): `messaging:watch-platform-complaint-rate` wrote `true` HERE from 2400–2410 until then, which meant an automatic trip also silenced every carrier-mandated STOP confirmation and HELP answer, because `ComplianceReplies` honours this key. The sweep now writes `messaging.automatic_halt` instead — same effect on every send, no effect on the two replies 2099 makes unconditional. ⚠️ THE AUTOMATIC TRIP IS ARMED (2684): `messaging.platform_complaint_trip_bp` is 100 basis points over a floor of 50 delivered messages, and either platform key set back to zero disables it again (2409). ⚠️ NOTHING EVER WRITES `false` AUTOMATICALLY on either key (2408): a rate that has fallen back under the threshold is not evidence that whatever produced it was dealt with, so releasing is always a person\'s act with an actor, on the sending-controls screen — and that screen releases both.',
            ],

            /*
             * ⛔ **THE SECOND HALT KEY, AND IT EXISTS BECAUSE ONE KEY COULD NOT
             * CARRY TWO MEANINGS** (3980–3983).
             *
             * `ComplianceReplies` defers to `sms.enabled` and
             * `messaging.global_halt` and argues, in its own docblock, that
             * *"each is an operator's decision to make knowingly"* — an
             * operator who halts the platform accepts that HELP goes
             * unanswered. **That argument was false for one of the two**:
             * `WatchPlatformComplaintRate` writes the halt on a schedule with
             * nobody awake, so the first automatic trip silenced every STOP
             * confirmation and every HELP answer on the platform — at the exact
             * moment a carrier is already looking at us, because the complaint
             * rate is what tripped it. 2099 makes both replies unconditional.
             *
             * ⚠️ **IT STOPS EXACTLY WHAT THE OTHER KEY STOPS.** `SendingGuard`
             * reads both and refuses identically, so the containment 2102 asks
             * for is unchanged in strength. The only difference is the one
             * class that must never be stopped by a machine.
             *
             * ⚠️ **AND IT IS RELEASED FROM THE SAME BUTTON.** A halt an
             * operator cannot clear from the screen is the writerless control
             * from the other end (2478's shape), so `SendingControls` shows
             * "stopped" when *either* key is on and clears both.
             */
            'messaging.automatic_halt' => [
                'seed' => false,
                'group' => 'Messaging',
                'description' => 'Stops ALL outbound messaging on the platform exactly as `messaging.global_halt` does — but this is the one the platform throws BY ITSELF. `messaging:watch-platform-complaint-rate` writes `true` here when the aggregate complaint rate crosses `messaging.platform_complaint_trip_bp` (2102, 2400–2410), and records the measurement in `platform_halt_incidents`. ⛔ THE TWO KEYS ARE SEPARATE FOR ONE REASON (3980–3983): the carrier-mandated STOP confirmation and HELP answer honour the operator\'s switch and MUST NOT honour this one. 2099 makes those two replies unconditional, and a machine silencing them while the complaint rate is high is the worst possible moment to stop answering a carrier\'s test message. ⚠️ NOTHING WRITES `false` HERE AUTOMATICALLY (2408) — releasing is a person\'s act on the sending-controls screen, which clears both keys together so "sending is stopped" has one answer.',
            ],

            /*
             * T137 §3 rail 2's alert thresholds for the two rates that had
             * none — row 4 slice 9, decisions 2637–2639.
             *
             * ⚠️ **BOTH SEED TO `0`, WHICH MEANS "NOBODY IS WATCHING THIS
             * RATE", AND THAT IS 2409'S RULING APPLIED RATHER THAN COPIED.**
             * §3.2 asks for delivery, opt-out and complaint rates *"with alert
             * thresholds"*. The complaint one already exists and is
             * `messaging.complaint_trip_bp` — deliberately reused, because a
             * dashboard alerting at a different number from the one that stops
             * the tenant is two answers to one question (2402's rule). The
             * other two are figures nobody has ever set, and 2409's reasoning
             * transfers exactly: *"the mechanism is the deliverable; the number
             * is the owner's"*. A plausible 95% delivery floor typed here would
             * be a guess wearing a seeded key's clothes, and it would begin
             * paging somebody about a number no one chose.
             *
             * ⚠️ **A ZERO IS RENDERED AS "No alert set", NEVER AS A PASSING
             * GRADE.** `RateReading` treats a non-positive threshold as the
             * absence of one, so the screen says nothing is watching rather
             * than showing a green tick against a threshold of nought — which
             * every rate trivially satisfies.
             */
            'messaging.delivery_rate_alert_bp' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'The delivery rate, in basis points of messages THE CARRIER HAS REPORTED ON, BELOW which the per-tenant sending dashboard raises an alert. ⛔ THE DENOMINATOR CHANGED ON 2026-08-22 (7660, on 7560-7571): it was messages handed to a carrier, which read 0% for every tenant whose receipts had not landed yet — so this figure is now a judgement about messages that were judged, and a tenant whose receipts have stopped is answered by a separate state rather than by this threshold (T137 §3.2, §3 rail 2). 9500 = 95%. ⚠️ A floor rather than a ceiling — this is the one rate here where lower is worse. ⚠️ Seeded at 0, which means no alert threshold is set and the screen says so; 2409\'s rule, because nobody has chosen this figure and a plausible one typed at a call site becomes policy silently. ⛔ It raises an alert; it stops nothing. The only automatic stop is the complaint-rate trip.',
            ],

            'messaging.opt_out_rate_alert_bp' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'The opt-out rate, in basis points of delivered messages, at or above which the per-tenant sending dashboard raises an alert. ⚠️ IT CARRIES THE SAME SIGNIFICANCE FLOOR AS THE COMPLAINT RATE SINCE 2026-08-22 (7660): without one, a new tenant\'s first STOP out of two deliveries reads as 5,000bp and the card goes red on a healthy list (T137 §3.2, §3 rail 2). 200 = 2%. ⚠️ Deliberately NOT the complaint threshold and deliberately not wired to the trip: somebody unsubscribing from a list they joined is exercising a right the product is required to offer, and counting ordinary opt-outs as complaints would trip the containment on a healthy list and teach an operator to raise the threshold until it never fires (511, in a kill switch). ⚠️ Seeded at 0 — no alert threshold set, on 2409\'s reasoning. ⛔ It raises an alert; it stops nothing.',
            ],

            /*
             * The carrier rate schedule — what a message costs US (R9,
             * decisions 2546, 2547). `App\Services\Billing\MessageRates` is the
             * one reader; `MessageCostLedger` records what it is given and
             * deliberately knows no rate at all.
             *
             * ⛔ **INTERNAL, NEVER RETAIL.** What the tenant is charged does not
             * turn on these figures at all — it is one credit for the text and
             * one for the media (9182, confirmed 9193, reversing R9's "one
             * credit per send"). R9's own words about this book are
             * "never shown as retail, never hardcoded", and 9182 leaves it
             * untouched in terms: "the two books stay two books".
             *
             * ⚠️ **MILLICENTS — THOUSANDTHS OF A CENT — BECAUSE A CARRIER SMS
             * COSTS LESS THAN A CENT AND `Money` CANNOT SAY SO.** Infobip's own
             * `MessagePrice::$pricePerMessage` is a float, which is a vendor
             * telling you the unit is finer than a cent. 750 is $0.0075. The
             * conversion to the ledger's integer cents happens once, on the
             * whole message, in `MessageRates`.
             *
             * ⛔ **ALL FIVE SEED TO ZERO AND ZERO MEANS UNSET, NOT FREE.**
             * 2119's precedent exactly: Infobip's US rate is per-account and is
             * in no artefact this lane could read, and `CLAUDE.md` records four
             * separate burns from writing a plausible vendor figure from
             * memory. **The mechanism is the deliverable; the number is the
             * owner's.** ⚠️ Until they are set the internal cost book stays
             * EMPTY — no send is refused or delayed over it, because a
             * bookkeeping gap must never stop a message, but nothing is
             * recorded either.
             */
            'messaging.carrier_cost_outbound_sms_millicents' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'What one outbound SMS **segment** costs us at the carrier, in thousandths of a cent (750 = $0.0075). ⛔ Internal cost, never a retail price — what the tenant pays does not turn on this figure, and a text with no media on it is one credit at any length (9182). ⚠️ Per SEGMENT, and the only rate here that is: a three-segment message costs three of these. Seeded 0, which means unset rather than free — Infobip prices per account and this figure is the owner\'s to enter from their own rate card. While it is 0 no cost row is written for an outbound SMS and nothing else changes.',
            ],

            'messaging.carrier_cost_outbound_mms_millicents' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'What one outbound MMS costs us at the carrier, in thousandths of a cent. ⚠️ Per MESSAGE, not per segment — MMS carries a media fee rather than a segment count, which is why `message_cost_entries.segments` is nullable. ⛔ Internal cost, never retail, and this figure never reaches a tenant. ⚠️ This description read "R9 prices an SMS, an MMS, or the SMS+MMS pair as exactly one credit each" until 9182 (confirmed 9193) reversed it: the tenant is charged one credit for the text and one for the media, so a message that reaches this rate cost them TWO credits — a number unrelated to this one, in the other book. Seeded 0 = unset; see the outbound SMS key.',
            ],

            'messaging.carrier_cost_inbound_sms_millicents' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'What one billable inbound SMS costs us, in thousandths of a cent. ⚠️ This one has a cost and debits NO credit, deliberately — charging a tenant because their customer replied is the wrong product, and on the AI two-way conversation path it would be a charge driven entirely by somebody else\'s behaviour and bounded by nothing. It is here so the margin book sees it. Seeded 0 = unset. ✅ `InboundMessages` writes the row (decision 4804, closing the seam 2549 named) — this description said nothing did until 2026-08-18.',
            ],

            'messaging.carrier_bills_inbound_sms_per_segment' => [
                'seed' => false,
                'group' => 'Messaging',
                'description' => 'Does our carrier contract charge a multi-part inbound SMS once per PART, or once per message? ⛔ Not a feature switch — a term of an Infobip agreement, in the same class as the rates beside it and as the billable-failure list (decision 4805). Infobip\'s inbound webhook reports `smsCount` ("the number of parts the message content was split into") beside a price described as "Price per one SMS", and states no rule joining the two, so reading it either way is a guess. Seeded FALSE, which books one rate per message: short rather than wrong. ⚠️ The part count is recorded on every inbound SMS cost row regardless, so setting this correctly later is a recomputation and not a fresh start.',
            ],

            'messaging.carrier_cost_inbound_mms_millicents' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'What one billable inbound MMS costs us, in thousandths of a cent, for the same reason as the inbound SMS key beside it. Seeded 0 = unset.',
            ],

            'messaging.carrier_cost_undelivered_fee_millicents' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'What a carrier charges us for a message that was never delivered, in thousandths of a cent. ⚠️ Its own kind and its own row rather than an edit to the send\'s row: the fee arrives with the delivery receipt, long after the send was priced, and rewriting a cost row when the receipt lands would make the ledger a mutable estimate rather than a record. Seeded 0 = unset. ✅ The delivery-receipt writer named in decision 2549 EXISTS as of 4805 — but it also needs the key below, because a rate alone does not say WHICH failures are billed.',
            ],

            /*
             * ⛔ **THE SECOND WITHHELD FIGURE ON THIS PATH, AND IT IS NOT A
             * PRICE** (4805). A rate says what an undelivered message costs; it
             * does not say **which** undelivered messages a carrier bills for,
             * and `MessageCostKind::UndeliveredFee`'s own docblock says the set
             * is partial in as many words: *"a carrier still bills for SOME of
             * these."*
             *
             * ⛔ **SO BOOKING EVERY FAILURE WOULD OVERSTATE AND BOOKING NONE
             * WOULD UNDERSTATE, AND BOTH RENDER A PLAUSIBLE, COHERENT, WRONG
             * MARGIN.** `REJECTED` is the case that decides it: a message the
             * platform refused before submission is ordinarily not billed, while
             * `UNDELIVERABLE` and `EXPIRED` were submitted and ordinarily are.
             * **"Ordinarily" is exactly the plausible-from-memory figure
             * `MessageRates`' docblock exists to refuse** (255, 277, 684, 1349),
             * and it is a term of an Infobip contract this platform holds no copy
             * of. Seeding the likely-looking subset would have been that mistake
             * with an extra step.
             *
             * ⚠️ **SEEDED EMPTY = WITHHELD, WHICH IS THE FAIL-CLOSED STATE
             * HERE** (3317's rule). Empty books nothing, exactly as a zero rate
             * books nothing, and no receipt is delayed or dropped over it.
             */
            'messaging.carrier_billable_undelivered_groups' => [
                'seed' => '',
                'group' => 'Messaging',
                'description' => 'Which carrier failure statuses actually cost us a fee — a comma-separated list of Infobip status GROUP names, from `UNDELIVERABLE`, `REJECTED`, `EXPIRED`. ⛔ Seeded EMPTY, which means WITHHELD rather than "none are billable": until it is set, no undelivered-fee row is written at all and nothing else about delivery receipts changes. ⚠️ **It is deliberately not defaulted, and the reason is not caution for its own sake**: a message REJECTED before submission is ordinarily not billed while an UNDELIVERABLE or EXPIRED one ordinarily is — and "ordinarily" is a term of your Infobip contract, not a fact this application may guess. Guessing it wrong does not fail loudly; it renders a plausible, coherent, wrong margin on the one book whose entire job is margin. ⚠️ Read it off your own rate card or invoice, exactly as the millicent rates beside it. ⚠️ Unrecognised names are ignored rather than throwing — a carrier adding a status must never break a webhook.',
            ],

            /*
             * ⚠️ **`provider_cost`, NOT `carrier_cost`, AND THE WORD IS DOING
             * WORK** (3732). Amazon SES is not a carrier and an email is not a
             * message a carrier ever sees, so filing this under the heading an
             * operator reconciles against an Infobip statement would put two
             * vendors' invoices in one bucket. Everything else is the five keys
             * above: millicents, seeded zero, and Ops-editable per 3415.
             */
            'messaging.provider_cost_outbound_email_millicents' => [
                'seed' => 0,
                'group' => 'Messaging',
                'description' => 'What ONE outbound platform email costs US at the transport, in thousandths of a cent (10 = $0.0001 = $0.10 per 1,000). ⛔ Internal cost, never a retail price — the tenant is charged one email unit, metered at $20 per 1,000 (3299), and this figure never reaches them. ⚠️ Seeded 0 = UNSET, not free, and while it is 0 no cost row is written for an email and nothing else changes. ⚠️ **The rate was read and not remembered, and it is still the owner\'s to enter**: https://aws.amazon.com/ses/pricing/ (read 2026-08-14) quotes $0.10 per 1,000 à la carte with no plan, and three plan tiers above it — Essentials $0.16, Pro $0.22, Enterprise $0.23 per 1,000 in their first band. Which applies is a fact about an SES account this platform does not yet hold, and they differ by more than twofold. ⛔ Attachment data is billed separately ($0.12/GB) and is NOT counted here.',
            ],

            'messaging.carrier_cost_currency' => [
                'seed' => 'USD',
                'group' => 'Messaging',
                'description' => 'The currency the CARRIER bills us in, ISO 4217. ⚠️ Not the currency a tenant pays in, and the two are allowed to differ — multi-currency is in scope (2058) and `message_cost_entries` carries its own currency column beside its minor units for exactly this reason. Changing it does not convert rows already written; it labels new ones.',
            ],

            /*
             * Number health scoring — doc `51` §1, §4.3, row 4 slice 6 phase 2.
             *
             * ⚠️ **EIGHT KEYS, AND EACH IS A CONSTANT IN THE SCORE FORMULA
             * RATHER THAN A CONTROL AN OPERATOR IS EXPECTED TO TUNE.** I44:
             * "every threshold, weight, curve, price and bucket mapping in
             * this document lives in the Defaults Registry / seeded tables; a
             * hardcoded literal fails CI." `NumberHealthService` is the one
             * reader of every key below — a chokepoint lint in `MessagingTest`
             * confines the key *strings* to that one file (plus this manifest,
             * which must declare them, and the lint's own test), on decision
             * 1633's reasoning: a numeric literal like `0.45` or `80` is far
             * too generic to grep for on its own, so what is confined is the
             * name nothing else may write, which is strictly checkable.
             *
             * ⚠️ **THE FORMULA'S SHAPE IS CODE; ONLY ITS CONSTANTS ARE HERE.**
             * Doc 51 §4.3's 70%/30% 7-day/24-hour blend and its 5% reply-rate
             * cap (`min(reply_rate/0.05, 1)`) are not registry keys — they are
             * not among the eight this slice was scoped to add, and treating
             * I44 as licence to invent more registry surface than was asked
             * for is its own mistake. If a future slice needs to tune either,
             * that is a registry key added with its own reader and its own
             * decision, not an extension of this one.
             */
            'numbers.health_weight_delivered' => [
                'seed' => 0.45,
                'group' => 'Messaging',
                'description' => 'Score weight — delivered rate (doc 51 §1, §4.3). The four numbers.health_weight_* keys sum to 1.0; NumberHealthService is the only reader.',
            ],

            'numbers.health_weight_filtered' => [
                'seed' => 0.25,
                'group' => 'Messaging',
                'description' => 'Score weight — carrier filter/failure rate, inverse (doc 51 §1, §4.3). Applied as w × (1 − filtered rate), so a higher filtered rate always lowers the score.',
            ],

            'numbers.health_weight_stop' => [
                'seed' => 0.20,
                'group' => 'Messaging',
                'description' => 'Score weight — STOP rate, inverse (doc 51 §1, §4.3). Applied as w × (1 − stop_norm), where stop_norm is the STOP rate normalised against numbers.quarantine_stop_pct.',
            ],

            'numbers.health_weight_engagement' => [
                'seed' => 0.10,
                'group' => 'Messaging',
                'description' => 'Score weight — inbound reply rate, positive (doc 51 §1, §4.3). A number that gets replies is doing its job; capped in the formula so a handful of replies cannot outweigh delivery and filtering.',
            ],

            /*
             * ⚠️ **NO READER OF THIS KEY IN THIS SLICE, AND THAT IS SAID
             * RATHER THAN HIDDEN.** Doc 51 §5.1 defines Degraded as "score <
             * degraded_below_score" — a state comparison — and moving a
             * number into that state is phase 3/4, deliberately not built
             * here (BUILD-PLAN §2.10.3 row 6b–6d). The key is seeded now
             * because I44 wants every constant in the document registered
             * when the document is built against, not only when its reader
             * arrives — the same reasoning `reviews.default_invite_threshold`
             * already applies one section up.
             */
            'numbers.degraded_below_score' => [
                'seed' => 80,
                'group' => 'Messaging',
                'description' => 'Score under which a number is Degraded (doc 51 §5.1). No reader yet — the Degraded state transition is phase 3/4 of row 4 slice 6. ⚠️ THAT SENTENCE IS NOW PINNED RATHER THAN REMEMBERED (8690): MessagingTest\'s health-key chokepoint carries this key in its $awaitingReader list, so the build reddens on the day NumberHealthService reads it in code and tells whoever built the reader to correct this line.',
            ],

            /*
             * ⚠️ **ONE KEY, TWO READERS, AND THEY READ DIFFERENT WINDOWS.**
             * §4.3's small-sample hold ("numbers with < min_sends_quarantine
             * sends in-window hold their last score") is applied to the
             * SEVEN-DAY total; §5.2's failure-rate trigger applies the same
             * figure to the ROLLING 24 HOURS. Both readers are
             * NumberHealthService, and 1642 predicted the second one — it
             * arrived on 2026-08-14 (AG6).
             */
            'numbers.min_sends_quarantine' => [
                'seed' => 25,
                'group' => 'Messaging',
                'description' => 'Minimum sends in the scoring window before a score is trusted enough to move (doc 51 §4.3, §5.2). Below this, NumberHealthService holds the number\'s last published score rather than recomputing one from a small sample.',
            ],

            /*
             * ✅ **THIS KEY GAINED ITS READER ON 2026-08-14 (AG6).** It was
             * seeded at 1642 with none, because doc 51 §5.2's STOP-rate
             * quarantine trigger was phase 3; phase 3 is built, and
             * NumberHealthService::evaluateQuarantine() is the reader.
             */
            'numbers.min_sends_stop' => [
                'seed' => 30,
                'group' => 'Messaging',
                'description' => 'Minimum DELIVERED messages, over today and yesterday, before the STOP-rate auto-quarantine trigger can fire (doc 51 §5.2). Read by NumberHealthService::evaluateQuarantine(); deliberately larger than numbers.min_sends_quarantine, because a STOP rate divides by delivered messages and needs more traffic before it means anything. ⚠️ IT IS A FLOOR AND NOT THE WHOLE GATE (3986): the trigger also derives its own minimum from numbers.quarantine_stop_pct, so that two ordinary opt-outs can never quarantine a number whatever either figure is set to — at the seeded 3.0% that derived minimum is 67 and this 30 is the smaller of the two. Raising this above the derived figure is what makes it bind.',
            ],

            /*
             * ⛔ **THE KEY WHOSE ABSENCE WAS HALF OF WHY PHASE 3 WAS DEFERRED.**
             * Doc 51 §1 states it (`numbers.quarantine_failure_pct | float |
             * 20.0`) and §5.2 makes it the first auto-quarantine trigger; slice
             * 6b seeded its seven siblings and not this one, because it had no
             * reader and its trigger could not have fired anyway — the seed of
             * `dlr_error_buckets` had no `filtered` row, so the rate it
             * compares against was permanently zero (1650). Both halves are
             * closed in the same slice, which is the only order that makes
             * either of them real.
             */
            'numbers.quarantine_failure_pct' => [
                'seed' => 20.0,
                'group' => 'Messaging',
                'description' => 'Carrier filter/failure rate (%) over 24h that auto-quarantines a number, at ≥ numbers.min_sends_quarantine sends (doc 51 §1, §5.2). The rate divides by DECIDED messages — delivered plus failed — never by rows sent, so messages still awaiting a receipt cannot push a number over it. ⚠️ Raising this weakens the containment; lowering it can stop the shared platform number, and with it every text on the platform.',
            ],

            'numbers.quarantine_stop_pct' => [
                'seed' => 3.0,
                'group' => 'Messaging',
                'description' => 'Per-number STOP rate (%) that normalises the STOP term of the score and auto-quarantines a number (doc 51 §1, §4.3, §5.2). ⚠️ THE QUARANTINE TRIGGER MEASURES IT OVER TODAY AND YESTERDAY, not over a rolling 24 hours (3985): an opt-out is counted when it ARRIVES and a message when it was SENT, and STOPs lag their sends by hours — so a rolling window divides yesterday\'s opt-outs by today\'s send and reads an ordinary day as a spike. The score\'s stop_norm = min(stop_rate / quarantine_stop_pct, 1) still uses the rolling 24 hours, because a soft weighting is not a containment. ⚠️ NO SINGLE SETTING CAN MAKE ONE OR TWO OPT-OUTS QUARANTINE A NUMBER: the sample floor is derived from this percentage (3986), so lowering it raises the delivered count required rather than making the trigger hair-trigger.',
            ],

            /*
             * ⛔ **NINETY, NOT DOC 51 §5.5's THIRTY, AND THE OWNER MOVED IT
             * DELIBERATELY** (2686). The doc's own table reads
             * *"`numbers.release_park_days` | int | 30"*. The owner's ruling
             * sets ninety, on the reason the doc gives for having the window at
             * all: *"parked long enough that a recycled number never answers
             * yesterday's customer."*
             *
             * ⛔ **AND THE PARK EXISTS FOR REASSIGNMENT, NOT FOR TIDINESS.**
             * Consent records are per tenant and do not follow a number, so a
             * customer who texts the old number after a business closes must
             * never reach a different business — the Reassigned Numbers problem
             * in miniature, and the one this platform is least able to detect,
             * because the second tenant's HELP reply would name a business the
             * texter has never heard of and their STOP would land on the wrong
             * customer list.
             *
             * ⚠️ **STOP AND HELP ARE ANSWERED THROUGHOUT THE PARK**, at platform
             * level — `TenantNumbers::isPlatformNumber()` counts a parked number
             * as ours precisely so the carrier-mandated reply still goes out
             * while the number belongs to nobody.
             */
            'numbers.release_park_days' => [
                'seed' => 90,
                'group' => 'Messaging',
                'description' => 'How long a number sits parked (state `retired`) after its tenant leaves, before `numbers:return-parked` walks it back into the assignable pool (decision 2686). ⛔ Ninety rather than doc 51 §5.5\'s thirty, by the owner\'s ruling: consent records are per tenant and do not follow a number, so a customer texting the old number after a business closes must be cold before anybody else holds it. ⚠️ Throughout the park the number belongs to nobody, cannot be assigned, cannot send, and still answers STOP and HELP at platform level. ⚠️ Doc 51 releases a parked number back to Infobip instead; this platform returns it to its own pool, which is why the window is the longer one.',
            ],

            /*
             * ⛔ **THE CLOCK THE CONTAINMENT WAS MISSING** (3796, 3988). Doc
             * 51 §1 states it (`numbers.quarantine_cooldown_days | int | 7`)
             * and §5.3 makes it the rest period before a quarantined number may
             * re-enter rotation. Nothing counted it until 2026-08-20: the only
             * way out of an automatic quarantine was an Ops command, so an
             * eager trigger converted itself into a disabled one, one release
             * at a time. `numbers:recover-rested` is the reader.
             *
             * ⚠️ **A NON-POSITIVE VALUE RELEASES NOTHING, IN BOTH THE COMMAND
             * AND THE SERVICE.** A re-arm loosens a containment, so an unset or
             * mis-edited figure has to fail towards *leaving the number
             * resting* — `numbers.release_park_days`' own rule, one section up,
             * and `storage:prune`'s (4941).
             */
            'numbers.quarantine_cooldown_days' => [
                'seed' => 7,
                'group' => 'Messaging',
                'description' => 'How long a number rests in `quarantined` before `numbers:recover-rested` walks it to `recovering` (doc 51 §1, §5.3). ⚠️ Measured from the moment it was quarantined, not from its last send. ⚠️ Zero or negative is read as unset and releases nothing at all, because a re-arm loosens a containment and has to fail towards leaving the number resting. ⚠️ Doc 51 also holds this recovery while a pool health alarm is active for the tenant; that alarm (§7) is not built, and the hold is deliberately not written — see decision 6424.',
            ],

            /*
             * ⚠️ **SEEDED AT DOC 51 §1'S FIGURE AND READ FOR SOMETHING DOC 51
             * DOES NOT ASK IT FOR, WHICH IS SAID OUT LOUD RATHER THAN HIDDEN**
             * (6426). §5.3 re-enters a recovering number at warmup step
             * `recovery_reentry_step` (step 2 = 50/day) and has it climb the
             * curve. **There is no curve.** `numbers.warmup_curve` is unseeded,
             * `phone_numbers` has no `warmup_day` column, and nothing anywhere
             * in `app/` clamps a daily send volume — so a key read as a cap
             * would be a control that reads as a brake and is a no-op (272).
             *
             * What it is read for instead is the one thing the step genuinely
             * determines without the caps: **how many days of climbing are
             * left**, which is how long the number stays in `recovering` before
             * `numbers:recover-rested` walks it to `active`. Higher step =
             * further along = shorter recovery, which is exactly the direction
             * doc 51 gives it. The curve's *length* is a constant in
             * `NumberRecovery` with its own argument, not a second registry key,
             * because seeding a list whose four cap figures nothing enforces is
             * the decoration this comment exists to refuse.
             */
            'numbers.recovery_reentry_step' => [
                'seed' => 2,
                'group' => 'Messaging',
                'description' => 'Which step of doc 51 §5.3\'s warmup curve a recovering number re-enters at (doc 51 §1: 20 / 50 / 100 / 150 / cap, re-entering at step 2 = 50 a day). ⛔ The daily caps themselves are not enforced anywhere — no warmup machinery is built, and a recovering number sends at full capacity. What this figure controls today is how long a number stays in `recovering` before returning to `active`: one day per remaining step, so 2 gives three days and 4 gives one. ⚠️ That window matters because the auto-quarantine trigger deliberately never re-evaluates a `recovering` number, so it is time in which nothing can stop the number automatically — a higher step is the shorter, tighter setting.',
            ],

            /*
             * The reactivation campaign engine — T137 `SL-2`, lane L3.
             *
             * ⚠️ **THE ≤159 LAW IS NOT IN HERE, AND ITS ABSENCE IS THE
             * DECISION.** `38` Part 2 says every threshold and window is a
             * seeded value, and this one is deliberately not: 159 septets is
             * what a GSM-7 segment physically holds minus the law's own unit of
             * headroom, so a registry row that could be edited to 300 would not
             * *relax* a policy — it would make every send silently two segments
             * while the meter that proved it single still read green. It lives
             * in `SmsEncoding::segmentBudget()`, where moving it costs a code
             * review. The same goes for the UCS-2 figure beside it.
             */
            'campaigns.dormancy_days' => [
                'seed' => 90,
                'group' => 'Messaging',
                'description' => 'How long a contact must go without an INBOUND signal before the reactivation audience counts them dormant (S4, seed 90). ⚠️ Our own outbound sends never reset this clock — a campaign that reset it would keep re-qualifying the people it had just texted. Measured from the newest inbound signal on record and, for a contact who has never produced one, from when they entered the book.',
            ],

            'campaigns.batch_size' => [
                'seed' => 100,
                'group' => 'Messaging',
                'description' => 'How many recipients one RunCampaignJob pass sends before re-queueing itself. Small on purpose: the channel switch, the tenant suspension, the tenant pause AND `SendingGuard` — which carries `messaging.global_halt`, this tenant\'s `SendingPause` and the automatic complaint-rate trip — are re-read for every recipient inside a pass, and the batch bound is what keeps a single pass short enough that a stuck one is visible rather than a job holding a worker for an hour. ⚠️ The guard is the fourth and it was missing when this description was written: the runner read `TenantPause`, which the automatic trip does not write, so a campaign whose complaint rate crossed the threshold would have run to the end of its list.',
            ],

            'messaging.marketing_touch_window_hours' => [
                'seed' => 24,
                'group' => 'Messaging',
                'description' => 'The send-collision arbiter\'s window (S1): at most one MARKETING touch reaches a contact on a channel in this many hours, across every campaign, invite and recovery path at once. Service-class messages — missed-call text-backs and replies — are never held by it (the T69 law). Priority when two collide: review invite, then recovery, then reactivation, then campaign.',
            ],

            'campaigns.reply_window_hours' => [
                'seed' => 72,
                'group' => 'Messaging',
                'description' => 'How long after a send an inbound text may still be read as a reply to it (R20, P20). ⚠️ It bounds ATTRIBUTION, never receipt: a reply outside the window still reaches the owner, it simply opens without campaign context — an unlinked reply is a reply. ⛔ Widening it does not so much find more replies as find more AMBIGUOUS ones: two open sends to one contact resolve to nothing at all, because guessing the recent one files a customer\'s words against somebody else\'s campaign. It also bounds the work — the resolver hashes every contact we sent to inside this window, so this figure is what keeps a carrier webhook off an unbounded scan.',
            ],

            /*
             * ✅ **A FONT IS SHIPPED NOW, AND THIS COMMENT SAID THE OPPOSITE
             * UNTIL 2026-08-16** (4365–4368). It argued that a TTF is a binary
             * asset with its own licence and that adding one would mean *"a
             * directory nobody agreed to holding a file whose terms nobody has
             * read"*. Both halves are answered rather than waived: the terms are
             * the SIL Open Font License 1.1, the licence text is committed
             * beside the file at `resources/fonts/OFL.txt`, and Public Sans is
             * already one of this product's three declared typefaces.
             *
             * ⛔ **WHAT THE OLD SEED ACTUALLY DID WAS TURN THE FEATURE OFF
             * EVERYWHERE.** `/usr/share/fonts/…/DejaVuSans-Bold.ttf` is not on
             * the cPanel production box, so every personalised-overlay MMS took
             * `CampaignMedia`'s degrade-to-SMS branch, logged a warning nobody
             * reads, and the whole picture pipeline was inert — with a green
             * suite over it, because the tests probe for a system font and skip
             * the render when there is none. A default that is absent on the one
             * machine that matters is a switched-off feature wearing a seeded
             * key's clothes.
             *
             * ⚠️ **THE SEED IS RELATIVE AND `CampaignMedia::fontPath()` RESOLVES
             * IT AGAINST `base_path()`.** An absolute seed would be wrong on
             * every box but the one it was written on. An operator who wants a
             * different face still writes an absolute path and it still wins —
             * so this is a better default rather than a narrower control.
             */
            'campaigns.overlay_font_path' => [
                'seed' => 'resources/fonts/PublicSans-Bold.ttf',
                'group' => 'Messaging',
                'description' => 'The TrueType font the personalised MMS overlay draws a customer\'s name with. Ships with the application — Public Sans Bold, under the SIL Open Font License 1.1, whose text is committed beside it. A relative path is resolved against the application root; an absolute path is used as written, which is how a box points at a different face. If the file is not readable, campaigns still send as plain SMS and a warning is logged.',
            ],

            'campaigns.media_url_ttl_hours' => [
                'seed' => 72,
                'group' => 'Messaging',
                'description' => 'How long a personalised MMS image stays fetchable after the send that needs it. The URL is signed and unguessable, and the picture has the recipient\'s first name rendered into it — so the window is the one that has to cover a carrier\'s own fetch and retry, and nothing beyond it.',
            ],

            /*
             * The rating at or above which a customer is invited, out of the box.
             *
             * ⚠️ **4, WHICH IS THE OWNER MOVING THEIR OWN DECISION 110.** That
             * decision set it at 5 and CLAUDE.md says not to re-open the
             * argument; 1142 is the one party who may, and 1186 confirmed both
             * the figure and the tenant's range. Do not "correct" it back, and
             * read 110 before proposing any other number — both positions are
             * written up in `DECISIONS.md` §Review invitation.
             *
             * ⚠️ **A REGISTRY KEY RATHER THAN A CONSTANT, because 1142's own
             * words are "it is a settings row, not a deploy"** — and because
             * `38` Part 2 names "threshold" in the list of things that may not
             * be a literal (505). It is the *platform* default only: it decides
             * what a new location is seeded with and what the two screens
             * pre-select, and moving it in Ops changes nothing for a tenant who
             * has already chosen. That is the honest scope of the key and is
             * why the description says so.
             *
             * ⚠️ **IT PAIRS WITH `autopilot_settings.triage_threshold`, WHICH
             * DEFAULTS TO 3, AND THE PAIR IS THE POINT.** 4 and 3 mean "invite
             * 4★ and above, triage 3★ and below" — the owner's 1140 model with
             * no rating falling between the two. The old pair was 5 and 3, so a
             * 4★ customer was neither invited nor triaged; that gap is what
             * this key closes. Anything that moves this must move the other in
             * the same breath (1186), which is why `ReviewGating` is the only
             * writer of both and takes one number.
             */
            'reviews.default_invite_threshold' => [
                'seed' => 4,
                'group' => 'Reviews',
                'description' => 'The rating at or above which a customer is invited to leave a public review, out of the box (decisions 1142 and 1186, moving the owner\'s own 110 from 5). Platform default only: it seeds a new location and pre-selects the two screens, and a tenant who has set their own is unaffected. Its pair is autopilot_settings.triage_threshold, which is this minus one.',
            ],

            /*
             * The invite follow-up delay — T176 §3's P14, seeded at its stated 3
             * days.
             *
             * ⛔ **A PLATFORM DEFAULT AND NOT A TENANT SETTING, WHICH IS A
             * DEPARTURE FROM THE BRIEF'S WORDING AND IS ARGUED RATHER THAN
             * ASSUMED** (4031). T176 §3 says *"at tenant-set delay (seed 3d)"*.
             * `CLAUDE.md`'s standing rule is that a tenant-facing toggle is a
             * future support ticket, and **the owner has overruled it exactly
             * once** — 1143, for the invite threshold, and only after 311 had
             * removed the last tenant-writable threshold and 523 had refused a
             * picker. *"Do not read the exception as the rule — the next toggle
             * needs its own ruling."* This is the next toggle and it has no
             * ruling, so it ships where every other undecided knob ships.
             * `SendCollisionArbiter::windowHours()` is the precedent, in as many
             * words, for a figure `S14` also stages as owner-adjustable.
             *
             * ⚠️ **THE TWO ARE NOT THE SAME KIND OF SETTING, WHICH IS WHY 1143
             * DOES NOT CARRY.** The invite threshold decides *whether a customer
             * is asked at all*, and the owner ruled it the tenant's because a
             * business's standard for "happy enough to ask" is theirs. A delay
             * decides *how long we wait*, which nobody is asking to own, and
             * every hour of it is an hour the platform is deciding to hold a
             * message anyway. Making it settable would also give a tenant a
             * number that changes how many messages leave our shared 10DLC
             * brand, which 2101 puts on us and not on them.
             *
             * ⚠️ **A CEILING RIDES BESIDE IT AND IS DELIBERATELY NOT A KEY** —
             * `SendInviteReminders::MAX_REMINDER_AGE_DAYS`, on
             * `ReviewRouter::MAX_DEFERRAL_DAYS`' precedent. It bounds the sweep's
             * candidate window rather than expressing a policy, and a second
             * editable figure here would let an operator set a delay longer than
             * the window that finds the candidates — a feature that silently
             * stops working with both screens reading correctly.
             */
            'reviews.invite_reminder_delay_days' => [
                'seed' => 3,
                'group' => 'Reviews',
                'description' => 'How many days after a review invite an unanswered one gets its single follow-up (T176 P14). Platform-wide: the brief says "tenant-set" and this ships as an Ops default instead, because CLAUDE.md\'s no-tenant-toggle rule has been overruled exactly once (1143, the invite threshold) and the next toggle needs its own ruling. ⚠️ Raising it past SendInviteReminders::MAX_REMINDER_AGE_DAYS stops every reminder, because the sweep would no longer find the invite: that ceiling bounds the candidate window and is a constant on purpose.',
            ],

            /*
             * Review-loss and pause detection over a location's own Google
             * numbers — `App\Services\Visibility\ReviewLossDetection`, wave 38
             * lane D (`GOAIEZ-MASTER-PLAN.md:8293`).
             *
             * ⚠️ **SEVEN KEYS, EACH A DEFENSIBLE THRESHOLD RATHER THAN A
             * CONSTANT**, on `38` Part 2's rule that a cap or threshold does
             * not belong at a call site. Each one's own accepted false
             * positive is argued on the constant that names it in
             * `ReviewLossDetection`, not restated here.
             */
            ReviewLossDetection::MIN_ABSOLUTE_DROP_KEY => [
                'seed' => 3,
                'group' => 'Reviews',
                'description' => 'A day-over-day Google review-count drop of at least this many is treated as material, whatever the percentage rule below says (wave 38 lane D). Floored so a single self-deleted review, or a small Google spam sweep, does not fire an alert about nothing having gone wrong.',
            ],

            ReviewLossDetection::MIN_RELATIVE_DROP_PERCENT_KEY => [
                'seed' => 5,
                'group' => 'Reviews',
                'description' => 'A day-over-day Google review-count drop of at least this percentage of the prior count is treated as material, whatever the absolute floor above says (wave 38 lane D). The larger of the two rules is what fires, so a business with hundreds of reviews needs a proportionally larger drop before this alerts on routine churn.',
            ],

            ReviewLossDetection::MAX_COMPARISON_GAP_DAYS_KEY => [
                'seed' => 3,
                'group' => 'Reviews',
                'description' => 'Two Google review-count readings are only compared as "day over day" when they are at most this many days apart (wave 38 lane D). A wider gap — a stalled sync, a spent budget — is skipped rather than compared, because the count moving across many unread days is not the same fact as a single day\'s drop.',
            ],

            ReviewLossDetection::PAUSE_MIN_REVIEW_COUNT_KEY => [
                'seed' => 10,
                'group' => 'Reviews',
                'description' => 'Pause detection only evaluates a location once its known Google review count reaches this floor (wave 38 lane D). Below it, a flat count is the ordinary state of a new or small listing rather than a signal — the single largest false-positive population this removes.',
            ],

            ReviewLossDetection::PAUSE_FLAT_DAYS_KEY => [
                'seed' => 45,
                'group' => 'Reviews',
                'description' => 'How many calendar days a Google review count must have read identically, across only successfully-read days, before this platform treats it as a candidate pause rather than an ordinary quiet stretch (wave 38 lane D). Deliberately long: this platform has no way to ask Google whether a listing\'s reviews are actually paused, so time is the only corroborating signal available, and a real but slow, honest business can still read the same way for a while.',
            ],

            ReviewLossDetection::PAUSE_MIN_FLAT_READS_KEY => [
                'seed' => 30,
                'group' => 'Reviews',
                'description' => 'A candidate pause must also be corroborated by at least this many distinct successful reads, not merely span the day count above (wave 38 lane D) — guards against a thin population of reads spread across a wide calendar range, most days unread, reading as a well-observed pause.',
            ],

            ReviewLossDetection::AUTO_DISABLE_ON_PAUSE_KEY => [
                'seed' => false,
                'group' => 'Reviews',
                'description' => 'Whether a detected Google review-count pause may disable the Google destination for public review requests (App\\Services\\Destinations\\DestinationSettings::disable(), wave 38 lane D). ⛔ Seeded false on purpose: DestinationSettings::disable() has no other caller in app/ and no screen re-enables a destination by hand today, so an automatic mutation here has no self-service undo. While false, a pause is still detected and still narrated on the tenant\'s activity feed; only the destination toggle is skipped. Turning this on is the owner\'s call once the alert has been watched fire for real.',
            ],

            /*
             * The fix-then-ask check-in feature switch — T546 §37.3(1), wave 38
             * lane C (10590–10609).
             *
             * ⛔ **SEEDED `false`, AND THE REASON IS NOT A VENDOR READINESS GAP —
             * IT IS `CLAUDE.md`'s CONFIRM RULE, WHICH THIS SWITCH IS A
             * SUBSTITUTE FOR BECAUSE CONFIRM ITSELF IS UNBUILT AND PINNED
             * (8570).** `CLAUDE.md` §Critical rules reserves CONFIRM for exactly
             * three things and names "the first send of a new campaign type" as
             * one of them; asking a previously-unhappy customer, days after the
             * fact, whether their complaint was fixed — and, on "yes", asking
             * them for the public review the platform withheld at the time — is
             * a campaign type nothing in this codebase has sent before. Because
             * there is no CONFIRM mechanism to gate the first send behind, the
             * conservative substitute is a switch that starts off and stays off
             * until an operator makes the deliberate choice this feature needs a
             * person to make once, in place of the per-send confirmation CONFIRM
             * would otherwise supply.
             *
             * ⚠️ **NOTHING ELSE ABOUT READINESS GATES THIS ONE.** Unlike
             * `review_invite.email_enabled`/`review_invite.sms_enabled`, this key
             * does not wait on a vendor: the check-in still rides
             * `sms.enabled`, `SendingGuard`'s containment and
             * `PlatformMailer::customerMailRefusal()` — the ordinary transport
             * gates every send in this product answers to — so turning those on
             * does not turn this on. This is a second, independent decision.
             *
             * WHAT IS LOST WHILE IT IS OFF: nothing this product has ever done.
             * A resolved recovery conversation simply stays resolved; the
             * customer's own rating stands, uncontested, exactly as `29` §12.1
             * requires — every rating is captured and kept, and none is ever
             * deleted, suppressed or hidden, whether or not this ever asks
             * again.
             */
            'reviews.fix_then_ask_enabled' => [
                'seed' => false,
                'group' => 'Reviews',
                'description' => 'Whether a resolved recovery conversation is followed up with a "did we get that sorted?" check-in, and — on a confirmed yes — offered the public review its original rating did not clear (T546 §37.3(1)). Off by default: this is a new kind of message this platform has never sent, CLAUDE.md reserves CONFIRM for the first send of a new campaign type, and CONFIRM itself is unbuilt (8570) — this switch is the deliberate substitute an operator must choose to flip.',
            ],

            /*
             * The fix-then-ask check-in delay — T546 §37.3(1)'s own stated
             * default, wave 38 lane C (10590–10609).
             *
             * ⚠️ **A PLATFORM DEFAULT AND NOT A TENANT SETTING, ON
             * `reviews.invite_reminder_delay_days`'s OWN ARGUMENT, ABOVE.** The
             * plan's own §37.3(1) settings block reads "fix_then_ask_delay 7
             * days, range 1–30" as a per-tenant figure — the identical shape the
             * argument two keys up already answered for the reminder delay:
             * `CLAUDE.md`'s no-tenant-toggle rule has been overruled exactly
             * once, for the invite threshold, because a business's own standard
             * for "happy enough to ask" is genuinely theirs to set. How many
             * days this platform waits before checking in is not that kind of
             * decision — nobody is asking to own it, and every day of it is a
             * day the platform is choosing to hold a message on the tenant's
             * behalf. So it ships as an Ops default, and the tenant-range
             * question the plan poses is written up rather than built — see
             * `DECISIONS.md` 10590–10609.
             *
             * 7, the plan's own figure, because nothing in this codebase has
             * measured a better one and inventing a number here would be the
             * spec's own gap dressed as a decision.
             */
            'reviews.fix_then_ask_delay_days' => [
                'seed' => 7,
                'group' => 'Reviews',
                'description' => 'How many days after a recovery conversation is marked resolved the fix-then-ask check-in is sent (T546 §37.3(1)). Platform-wide, on reviews.invite_reminder_delay_days\'s own argument: CLAUDE.md\'s no-tenant-toggle rule has been overruled exactly once, for the invite threshold, and a send delay is not that kind of decision. 7 is the plan\'s own stated default.',
            ],

            /*
             * The tenant CRM's follow-ups (`44` §2, built as `BUILD-PLAN`
             * §2.9.3 slice 2). Both figures are `44` §1's own table — the
             * first two rows of that table this codebase reads, which is rule
             * 1's condition for seeding them at all.
             */
            'crm.tasks_enabled' => [
                'seed' => true,
                'group' => 'CRM',
                'description' => 'Whether owners can create and see follow-ups (`44` §2). On by default — the feature has no vendor, no spend and no send path, so rule 3\'s conservative value is the working one.',
            ],

            /*
             * T176 §2.4's "quote disclaimer line (seeded default)", and R13's
             * *"give the tenant's price or range + the tenant's disclaimer
             * line"*. `PriceBook` is the only reader.
             *
             * ⚠️ **READ AT READ TIME RATHER THAN COPIED INTO EVERY TENANT'S
             * ROW**, which is `reviews.default_invite_threshold`'s shape (1420)
             * and the reason that key's description says what it does: this is
             * the *platform* default only. A business that has written its own
             * line has it stored on `assistant_briefs` where Ops cannot move it;
             * a business that has not follows this key when the wording
             * improves. Copying the seed in at provisioning would freeze today's
             * sentence into every account ever opened.
             *
             * ⛔ **IT IS NEVER BLANK AND THERE IS NO "NO DISCLAIMER" STATE.**
             * R13 requires the line with every quote, and an emptied box on the
             * owner's screen means "yours is fine" rather than "say nothing" —
             * `PriceBook::setDisclaimer()` writes NULL for that, which is what
             * brings a tenant back to this value.
             */
            'assistant.quote_disclaimer' => [
                'seed' => 'Final price confirmed on site.',
                'group' => 'Assistant',
                'description' => 'The line the assistant says with every price it quotes (T176 §2.4, R13). The PLATFORM DEFAULT only — a business that has written its own has a stored line on assistant_briefs that this key cannot move. ⛔ There is no state in which a quote goes out with no line at all: R13 requires one, so an owner who empties their box goes back to this wording rather than to silence. ⚠️ Keep it short — it rides in a text message beside the figure, inside the composer\'s character budget.',
            ],

            /*
             * Rail 3's turn cap — T176 §2.3: *"registry-keyed, default 12 agent
             * turns per thread"*. The brief names both the key and the figure,
             * so this is the document's own number rather than a guess.
             *
             * ⚠️ **A PLATFORM DEFAULT AND NOT A TENANT SETTING** — 4031's
             * reasoning, which carries here more easily than it did there: this
             * decides how long the platform lets a model loop before handing the
             * thread to a person, and every extra turn is our AI spend and our
             * traffic on the shared 10DLC brand (2101).
             *
             * ⚠️ **THE THREAD CARRIES THE CAP IT STARTED UNDER**, so moving this
             * key moves the cap for the *next* stretch rather than re-judging
             * threads already running — `conversations.agent_turn_cap` is where
             * that is stored, and {@see App\Services\Agent\ThreadState}'s
             * docblock is where the argument lives. The load-bearing test is
             * that moving the seed moves the refusal, not that 12 is right.
             *
             * ⛔ **CLAMPED AT 1 BY THE READER, NEVER HERE.** A cap of 0 would
             * silence the assistant on every thread the instant it was set,
             * which reads on every screen exactly like a model outage.
             */
            /*
             * ⚠️ THE DELAY IS A KEY AND THE 24-HOUR WINDOW IS NOT — the
             * asymmetry is argued in `AgentNudges::WINDOW_HOURS`. *When* to
             * follow up is a judgement an operator may tune; *whether a
             * two-day-old follow-up is still a follow-up* is a fact about the
             * behaviour, and offering it as a setting would let skill 14 be
             * turned into a cold text from a screen with no context.
             *
             * ⛔ CLAMPED AT BOTH ENDS BY THE READER, NEVER HERE. Below five
             * minutes reads to the recipient as a malfunction; past the window
             * it arms a nudge that is expired before it is due, which is a
             * schedule that can never fire.
             */
            'assistant.nudge_delay_minutes' => [
                'seed' => 240,
                'group' => 'Assistant',
                'description' => 'How long after a booking, payment or document link goes out with no reply the assistant sends its one polite follow-up (T176 §2.2 skill 14). ⛔ Exactly one follow-up per conversation, ever, and none at all after 24 hours — the follow-up rides the recipient-local legal daytime window, so a nudge that comes due at night goes the next morning. ⚠️ Clamped between 5 minutes and just under 24 hours at read time.',
            ],

            'assistant.turn_cap' => [
                'seed' => 12,
                'group' => 'Assistant',
                'description' => 'How many turns the assistant may take on one conversation before it hands the thread to the owner (T176 §2.3 rail 3). ⚠️ A thread carries the cap it started under, so raising this frees the NEXT stretch rather than re-opening threads that already capped — re-arming a capped thread from the Inbox is what gives it a fresh stretch. ⛔ Below 1 is refused at read time: a cap of 0 silences the assistant everywhere at once and looks exactly like a model outage on every screen.',
            ],

            /*
             * ⚠️ THE CAP IS A KEY, NEVER A LITERAL — `38` Part 2 names
             * "threshold" in the list of things that may not be hardcoded
             * (505/511), and the load-bearing test is that moving this seed
             * moves the refusal, not that 200 is right. `44` §1 calls it a
             * hoarding guard; its "plain warning at 80%" is deliberately not
             * built — a warning band is a second threshold with its own
             * wording and no ruling behind it.
             */
            'crm.task_open_max' => [
                'seed' => 200,
                'group' => 'CRM',
                'description' => 'Open follow-ups per business before creating another is refused (`44` §1\'s hoarding guard). The refusal names this key so an operator can find it.',
            ],

            /*
             * ⚠️ SEEDED EMPTY ON PURPOSE, WHICH IS RULE 3 RATHER THAN AN
             * OMISSION. `28` §9.5 requires the suspended-tenant status page to
             * carry "the support path", and this codebase has no support
             * mailbox — decision 482 met the identical problem on the crawler
             * disclosure page and settled it the same way: printing an
             * unmonitored address on the one page whose entire job is to give
             * somebody a way to reach us is worse than printing none, because
             * it looks like an answer. The page renders the contact line only
             * when this is set, and says something true either way.
             *
             * A registry row rather than an env var, because an operator who
             * stands a mailbox up should be able to say so in Ops without a
             * deploy — and because a caller-side default is exactly what
             * `38` Part 2's lint refuses (505).
             */
            /*
             * ⛔ **THIS ROW NOW HAS A CARRIER ON THE OTHER SIDE OF IT, AND 482
             * AND THAT CARRIER DISAGREE** (3271). The comment above is unchanged
             * and its argument still stands; what changed is that the key gained
             * two more readers, and for them an empty value is not merely a
             * missing line on a status page.
             *
             * `ComplianceReplies::platformHelpBody()` and
             * `OptInConfirmations::body()` both render `Contact: {this}.` and
             * both omit the clause when it is empty — 482's rule, applied
             * consistently. **But Infobip lists a customer care contact as
             * MANDATORY in the HELP reply**, and the campaign drafted at Infobip
             * declares both messages with `support@goaiez.com` in them. So while
             * this row is empty, **the live HELP text does not satisfy the
             * campaign we filed.**
             *
             * ⛔ **THE SEED IS DELIBERATELY NOT CHANGED, AND CHANGING IT WOULD
             * NOT HAVE HELPED ANYWAY.** Seeding an address would rebuild 482's
             * original defect — an unmonitored mailbox printed on the one line
             * whose whole job is to give somebody a way to reach us — and
             * `support@goaiez.com`'s monitoring is an open question with the
             * owner. It would also have been theatre: **a seed does not move a
             * database that has already been seeded**, so production's row would
             * still read empty and only a fresh install would differ. One `set()`
             * in Ops is what closes this, on every environment, with an actor
             * recorded. See 3271.
             */
            'support.contact_email' => [
                'seed' => 'support@goaiez.com',
                'group' => 'Support',
                'description' => 'Address shown to a suspended tenant on the status page (`28` §9.5), and the customer care contact printed in the HELP reply and the SMS opt-in confirmation. ✅ SEEDED 2026-08-14 (decision 3413): the owner confirmed the mailbox is answered by a person. It was deliberately EMPTY until then — 482 says every reader omits the line rather than printing an address nobody reads, because a customer replying to an unwatched mailbox gets silence, and that is worse than no address at all. ⛔ THIS CLOSES 3271: Infobip lists the contact as MANDATORY in the HELP reply and the filed campaign names this exact address, so until now the live HELP text did not match the filing. ⚠️ The owner also asked for an AI assistant to answer alongside the person (3414) — that is unbuilt, and it does not change this key: the address is answered either way, which is the only thing this key asserts.',
            ],

            'impersonation.notify_owner' => [
                'seed' => true,
                'group' => 'Support',
                'description' => 'Whether the account owner is emailed after a support act-as session ends (`28` §9.4). On by default. View-only sessions never notify — §9.4 gates those on a "strict mode" this application has no setting for, and inventing one would be a tenant-facing toggle.',
            ],

            /*
             * The First 7-Day Results Path (`28` §3.2, row 5) — a single gate
             * over every day of it, checked at the top of
             * `App\Services\Trust\FirstWeekPath::advance()` before anything
             * about the tenant's week is looked at.
             *
             * ⚠️ **SEEDED `true`, WHICH WAS THE ONLY EXCEPTION AMONG THIS
             * FILE'S OUTBOUND SWITCHES AND IS NOW ONE OF TWO — `owner_digest.enabled`
             * JOINED IT IN WAVE 40 ON THIS PARAGRAPH'S OWN ARGUMENT** (10857,
             * corrected here at 10974). **The reason is specific rather than
             * symmetry, and that is what transferred**: both send only to an
             * account holder about their own account, so neither carries the
             * external blocker the two below do.** `review_invite.email_enabled` and
             * `review_invite.sms_enabled` above seed `false` because bounce
             * handling is unbuilt and the 10DLC campaign was REJECTED (11617) — both
             * genuine, external blockers on a message a *customer* receives.
             * Nothing here has that shape: every message this path sends goes
             * through `PlatformMailer::send()` to the account holder's own
             * address, the same account-relationship authorisation
             * `impersonation.notify_owner` above already uses, needing no
             * consent record and carrying no carrier risk.
             *
             * ⚠️ **THIS IS EMAIL, NOT SMS, AND THAT IS A DECISION RATHER THAN A
             * DEFAULT.** `28` §3.2's table says "SMS" for every win.
             *
             * ⛔ **THE REASON GIVEN HERE WAS FALSIFIED BY WAVE 38 AND IS KEPT
             * DATED RATHER THAN DELETED (4368) — CORRECTED 2026-08-28
             * (10951).** It read: *"Texting the owner would need
             * `PlatformTexter::sendToCustomer()`'s consent permit — its own
             * docblock is explicit that there is no account-holder category on
             * this channel — and there is no column anywhere in this schema
             * that records an owner's phone number in the first place."*
             * **Both halves are now false**: `sendToOwner()` is the
             * account-holder category (10540) and `owner_notify_numbers.e164`
             * is the column. ⚠️ **The narrowing stands on the SCOPE of the
             * consent instead** — `OwnerNotifyDisclosure::TEXT` covers an
             * urgent customer message or something needing a reply, and every
             * §3.2 beat is an onboarding nudge or a celebration (10950,
             * 10951). See `FirstWeekPath`'s own docblock for the full account.
             */
            'first_week_path.enabled' => [
                'seed' => true,
                'group' => 'Trust',
                'description' => 'Whether the First 7-Day Results Path emails the owner at all (`28` §3.2). On by default — every send here is account-holder email through PlatformMailer, needing no consent record. Turn off to stop the whole path without a deploy; days already elapsed are caught up once switched back on.',
            ],

            /*
             * The weekly wins digest's one switch — automation #109's email
             * half, wave 40 lane B (decision 10857).
             *
             * ⛔ **IT EXISTS BECAUSE THERE WAS NOTHING BETWEEN THE SCHEDULER
             * AND THE SEND.** `owners:send-weekly-digest` was armed on a
             * deployed schedule at 06:00 daily with no switch, no kill switch,
             * no pause check and no suspension check anywhere on the path — the
             * only way to stop it was an edit to the crontab on the box or a
             * revert and a deploy. Every comparable sender in this file already
             * has one.
             *
             * ⚠️ **SEEDED `true`, ON `first_week_path.enabled`'s ARGUMENT
             * ABOVE AND FOR THE SAME FAMILY OF MESSAGE.** Every send is
             * account-holder email to an address the account relationship
             * authorises, needing no consent record and carrying no carrier
             * risk — none of the external blockers that make
             * `review_invite.email_enabled` seed `false` applies. ⛔ **And a
             * `false` seed would have turned off a feature the owner shipped,
             * silently**: the schedule would go on running and the command
             * would go on exiting zero, which is the shape 9370 is about. **A
             * lane does not switch off a shipped feature by seeding a manifest
             * row.**
             *
             * ⚠️ **`reviews.fix_then_ask_enabled`'s CONFIRM ARGUMENT DOES NOT
             * REACH THIS ONE**, and the difference is who receives the message.
             * That switch substitutes for CONFIRM on *"the first send of a new
             * campaign type"* — a message to a tenant's own **customer**. This
             * is a report to the account holder about their own account, the
             * category `RenewalReminder` and `TrialReminder` already send with
             * no switch at all.
             *
             * ⚠️ **TURNING IT OFF LOSES NOTHING, AND THAT IS A PROPERTY OF THE
             * SENDER RATHER THAN A HOPE.** The cursor advances only on a
             * delivery (10852), so a week spent switched off is still inside
             * the window when it is switched back on — bounded by
             * `OwnerDigest::MAX_WINDOW_DAYS` (10850), and reported honestly,
             * because the subject line names the window it covered (10851).
             */
            'owner_digest.enabled' => [
                'seed' => true,
                'group' => 'Trust',
                'description' => 'Whether the weekly wins digest is emailed to account holders at all (automation #109, `16` §12). On by default — every send is account-holder email through PlatformMailer, needing no consent record. Turn it off to stop the whole sweep without a deploy; no week is lost, because the cursor moves only on a delivery and the next digest widens to cover the gap.',
            ],

            /*
             * The domain every platform email is sent from (5500).
             *
             * ⛔ **THE SENDING DOMAIN IS `goaieasy.net`, A SEPARATELY REGISTERED
             * DOMAIN, SUPERSEDING 2114's `mail.goaiez.com` — BOTH POSITIONS KEPT
             * AND DATED.** Decision 30's `reports.goaiez.com` moved to
             * `mail.goaiez.com` on 2026-08-11 (2114, email domain architecture), and on 2026-08-19
             * the ruling was that **a subdomain of `goaiez.com` is not separation
             * at all**: mailbox providers track reputation at the organizational
             * domain as well as the exact host, DMARC alignment is organizational
             * by default, and some blocklists list the registered domain rather
             * than the name that sent. A spam complaint about platform mail
             * therefore reaches `goaiez.com` whichever subdomain carried it, and
             * only a separately registered domain gives true separation.
             *
             * ⚠️ **2114 WAS NOT A MISTAKE AND MUST NOT BE READ AS ONE.** It kept
             * decision 30's load-bearing half — never the primary domain — and
             * that half is unchanged, still enforced separately in
             * `PlatformMailer::assertNotPrimaryDomain()`, and now satisfied
             * trivially rather than narrowly. What moved is not which subdomain
             * but whether a subdomain counts.
             *
             * ⚠️ **THE MOVE WAS FREE ONLY BECAUSE NO MAIL HAS EVER BEEN SENT.**
             * Production runs `MAIL_MAILER=log` and has delivered nothing, so
             * there is no earned reputation to abandon and no warm-up to repeat.
             * **After the first customer-facing send this stops being a seed
             * edit**: it becomes a new domain starting at zero reputation,
             * re-warmed over weeks, with SPF, DKIM and DMARC republished and
             * every receiving server's history of the old name discarded. That
             * is the reason this happened now rather than later, and it is the
             * reason the next such ruling will cost more than this one did.
             *
             * ⚠️ **A SEED RATHER THAN A LITERAL, WHICH IS WHAT 2096 ASKED FOR
             * AND IS NOT DECORATION.** This question was open between two
             * answers for a day, and every previous email decision in this
             * repository has been reversed at least once — five times over
             * (712, 1181, 1191, 2068, 2093). An Ops row is what the next
             * reversal costs. It is also the value SPF, DKIM and DMARC are
             * published for, so moving it without moving the DNS is a
             * deliverability failure that is invisible in testing and total in
             * production (1195's shape) — which is why `PlatformMailer` refuses
             * a from address that disagrees with it, rather than trusting
             * MAIL_FROM_ADDRESS alone.
             */
            'mail.sending_domain' => [
                'seed' => 'goaieasy.net',
                'group' => 'Messaging',
                'description' => 'The only domain this platform sends email from (decision 5500), superseding 2114\'s mail.goaiez.com and decision 30\'s reports.goaiez.com. A separately registered domain rather than a subdomain, because reputation, DMARC alignment and blocklisting all work at the organizational domain, so a subdomain of goaiez.com is not separation. SPF, DKIM and DMARC are published here and nowhere else, so a from address on any other domain fails authentication at the receiving server — PlatformMailer refuses one rather than sending it. ⚠️ Never the primary domain: that half of decision 30 is unchanged and separately enforced.',
            ],

            'mail.health.window_days' => [
                'seed' => 30,
                'group' => 'Messaging',
                'description' => 'Window days for complaint bounce summary action.',
            ],

            /*
             * ⛔ **THE 24-HOUR SENDING CEILING IS NO LONGER A KEY, IT IS A KEY
             * PER MAILER** (4603, closing 4456). See
             * {@see self::mailSendingCeilings()}, which is merged into this
             * array at the end of the method — the rows are built from
             * `MailDrivers::MAILERS` so a selectable transport cannot exist
             * without a declared ceiling.
             *
             * The account's 24-hour ceiling (2095, email sending quota architecture).
             *
             * ⚠️ **2,000 IS GOOGLE'S FIGURE FOR A STANDARD WORKSPACE USER, OVER
             * A ROLLING 24-HOUR WINDOW** — `knowledge.workspace.google.com`,
             * *Gmail sending limits in Google Workspace*, read 2026-08-11:
             * limits *"apply over a rolling 24-hour period, not a set time of
             * day"*, and a user who exceeds one *"can't send new messages for up
             * to 24 hours"*. ⚠️ **A trial Workspace account is 500, not 2,000**,
             * which is a different number on the same page and the one a fresh
             * domain is on.
             *
             * ⚠️ **IT IS A REGISTRY ROW BECAUSE IT IS A FACT ABOUT A VENDOR
             * ACCOUNT, AND VENDOR ACCOUNTS CHANGE WITHOUT A DEPLOY.** Slotting
             * SES in behind the seam replaces this ceiling with SES's own, which
             * is a quota request rather than a plan tier — a constant here would
             * make that a code change.
             *
             * ⛔ **R16 MOVED THE TRANSPORT AND THE ROW ABOVE SAYS TO REPLACE
             * THIS WHEN IT DOES — SO SETTING IT IS PART OF ACTIVATING SES, NOT
             * A FOLLOW-UP.** The seed stays 2,000, and ⛔ **THE REASON 4432
             * GAVE FOR THAT DOES NOT HOLD** (4456): it argued Workspace "still
             * carries staff and support mail", and 4454 establishes it carries
             * **no outbound mail at all** once `MAIL_MAILER=smtp` — there is
             * one `mail.default` and `PlatformMailer` routes everything through
             * it. So the two transports are never both sending, the "cap a
             * Workspace deployment" cost is imaginary, and what is actually
             * true is that **one key cannot describe two mailers' limits**.
             * ⚠️ **The seed stays anyway, on a narrower argument**: it is
             * `MAIL_MAILER=log`'s and `gmail`'s correct figure, it is the state
             * of every deployment today, and moving it to 200 would encode
             * SES's *sandbox* quota — wrong the moment production access lands,
             * and wrong in the direction that reads as a real limit. **Keying
             * the ceiling per mailer is the fix and it is a design change, not
             * this one** (4456). Until it is made, SES is a 10× over-ceiling
             * against the sandbox quota and the operator setting the Ops row is
             * the whole of the mitigation. What they must set, verified against
             * AWS on
             * 2026-08-16: a fresh SES account's **sending quota is 200 per 24
             * hours** and its **sending rate is 1 per second**, per Region, and
             * both stay there until production access is granted
             * (`docs.aws.amazon.com/general/latest/gr/ses.html`, §Service
             * quotas). ⚠️ **THIS ROW CANNOT EXPRESS THE SECOND ONE.** A
             * per-second rate is not a 24-hour ceiling, nothing here throttles
             * the queue, and SES answers an over-rate send with a `Throttling`
             * error per message rather than a lockout — so the failure mode
             * differs from Google's in kind, and the queue's retry is what
             * absorbs it today.
             *
             * ✅ **AND THAT IS WHAT 4603 FIXED**: the rows are per mailer now,
             * `gmail` keeps Google's 2,000, and `smtp` carries **no seed at
             * all** — there is no number this codebase can honestly state for a
             * relay it has not been told the identity of.
             */

            /*
             * When the ceiling alert fires — decision 2095's actual mechanism.
             *
             * ⚠️ **A METER IS A THING SOMEBODY LOOKS AT.** Email limit architecture asks for a
             * visible limit with a meter in admin, and 2095 records why that is
             * not sufficient on its own: this failure is silent and total at
             * once — sends stop, the queue drains normally, and every tenant's
             * mail stops landing simultaneously. The alert is what happens
             * without anybody watching.
             *
             * An integer percentage rather than a fraction, because
             * `platform_settings` is jsonb and a hand-edited `0.8` and `.8` are
             * two different parses of one intention.
             */
            'mail.ceiling_alert_percent' => [
                'seed' => 80,
                'group' => 'Messaging',
                'description' => 'The percentage of the 24-hour sending ceiling at which an alert fires, at log level `critical`, at most once an hour (decision 2095). The meter explains the problem; this is what raises it while nobody is looking.',
            ],

            /*
             * The slice of the ceiling customer mail may not touch.
             *
             * ⚠️ **IT PROTECTS THE SIGN-IN LINK FROM THE REVIEW-INVITE BATCH.**
             * Platform mail and customer mail compete for one account's quota,
             * and only one of them locks somebody out of their own account when
             * it fails. Without a reserve, the first busy day ends with an owner
             * unable to sign in to find out why their invites stopped.
             */
            'mail.ceiling_customer_reserve' => [
                'seed' => 200,
                'group' => 'Messaging',
                'description' => 'How many of the 24-hour ceiling\'s messages are held back from customer-facing mail, so a sign-in link still sends on an account a batch has otherwise consumed. Clamped below the ceiling — a reserve at or above it would refuse every customer send for ever.',
            ],

            /*
             * ═══ CREDITS AND ALLOTMENTS (3298–3307, restating 2060–2066) ═══
             *
             * What a top-up COSTS lives here; what a plan GRANTS is a per-plan
             * entitlement and lives in entitlements(). That split is not
             * bookkeeping: a SKU is one price list for everybody, and a monthly
             * allotment is the thing a plan entitles an account to, which is
             * exactly what `plan_entitlements` versions (507).
             *
             * ⚠️ **TWO POOLS, AND THE KEY NAMES ARE WHERE THE SEPARATION LIVES**
             * (3307). `credits.topup.*` is the purchased pool and never expires;
             * `plan.*.credits.monthly_grant.*` is the granted pool and is reset at
             * the period boundary. **A spend draws the monthly pool first and
             * spills into top-up when it is exhausted**, and *that rule gets no
             * key* — it is a ruling, not a number, and an Ops-editable "do
             * monthly credits expire?" would be a support surface offering to
             * contradict the owner. What a reader must never be able to do is
             * find one figure that means both pools at once, which is why every
             * key below names its pool before it names its product.
             *
             * ⛔ **NOTHING IN `app/` READS ANY OF THESE KEYS YET, AND THAT IS A
             * DELIBERATE EXCEPTION TO RULE 1 ABOVE.** Rule 1 says a number goes
             * in only when this codebase reads it, and it is right — 272's shape
             * has sixteen instances in this project. The exception is taken
             * because 3316 records the whole model as scheduled work split across
             * lanes, and the figures are the *input* to the ledger and the
             * metering rather than an output of them. **What it costs is stated
             * rather than hidden**: until the ledger grows a pool and a product
             * dimension and the metering reads the rate, every figure below is a
             * row in a table, and a row in a table is not a ceiling. The owed
             * readers are the credit ledger (the pools) and the email metering
             * (the rate).
             */

            /*
             * SMS top-ups (3301, confirming 2061 unchanged).
             *
             * $50 buys 1,000 (5.00¢ a message) and $250 buys 10,000 (2.50¢) —
             * the manual tier is exactly half the automatic rate, which is the
             * owner's structure on both metered products (3302) and is pinned by
             * a lint rather than left to be noticed.
             *
             * ⚠️ **THIS IS THE POOL SMS BROADCASTING SPENDS, AND THE ONLY ONE**
             * (3309, 3310). A broadcast may never touch the monthly allotment,
             * and it additionally needs the tenant's own 10DLC brand and their
             * own number — three preconditions checked at send time, not at
             * configure time.
             */
            'credits.topup.sms.automatic_price_cents' => [
                'seed' => 5_000,
                'group' => 'Credits',
                'description' => 'What one automatic SMS top-up charges, in integer cents (decision 3301, confirming 2061). Buys credits.topup.sms.automatic_messages, so the two together state the rate; moving one without the other reprices SMS silently.',
            ],
            'credits.topup.sms.automatic_messages' => [
                'seed' => 1_000,
                'group' => 'Credits',
                'description' => 'How many SMS credits one automatic top-up adds to the purchased pool (decision 3301). Purchased credits never expire (3307), which is what makes this pool different from the monthly grant rather than merely larger.',
            ],
            'credits.topup.sms.manual_price_cents' => [
                'seed' => 25_000,
                'group' => 'Credits',
                'description' => 'What the manual SMS package charges, in integer cents (decision 3301). Half the automatic rate per message, deliberately — 2061 verified this pair against a suspected typo and the owner restated both figures unchanged three months later.',
            ],
            'credits.topup.sms.manual_messages' => [
                'seed' => 10_000,
                'group' => 'Credits',
                'description' => 'How many SMS credits the manual package adds to the purchased pool (decision 3301).',
            ],

            /*
             * Email top-ups (3302).
             *
             * $50 buys 2,500 (2.00¢ an email) and $150 buys 15,000 (1.00¢) —
             * again exactly half. ⚠️ **The automatic tier is what independently
             * settles the metered rate below**: two figures written minutes apart
             * agreeing to the cent is what 3299 read as a ruling rather than a
             * transcription slip of 2063's superseded $20/10,000.
             */
            'credits.topup.email.automatic_price_cents' => [
                'seed' => 5_000,
                'group' => 'Credits',
                'description' => 'What one automatic email top-up charges, in integer cents (decision 3302). $50 for 2,500 is $20 per 1,000 exactly, which is the corroboration that settled the metered rate (3299).',
            ],
            'credits.topup.email.automatic_emails' => [
                'seed' => 2_500,
                'group' => 'Credits',
                'description' => 'How many email credits one automatic top-up adds to the purchased pool (decision 3302).',
            ],
            'credits.topup.email.manual_price_cents' => [
                'seed' => 15_000,
                'group' => 'Credits',
                'description' => 'What the manual email package charges, in integer cents (decision 3302). $10 per 1,000 — a real half-price volume break, not a rounding artefact, and not to be "corrected" toward a single rate.',
            ],
            'credits.topup.email.manual_emails' => [
                'seed' => 15_000,
                'group' => 'Credits',
                'description' => 'How many email credits the manual package adds to the purchased pool (decision 3302). ⚠️ 15,000 emails for 15,000 cents is a coincidence of the two figures, not a rate — read the price key for the price.',
            ],

            /*
             * AI top-ups (3303, at 9181's figures).
             *
             * ⚠️ **THE ONLY SKU IN THE MODEL WHOSE DISCOUNT IS EXPRESSED AS
             * GRANTED CREDIT RATHER THAN AS A BETTER UNIT RATE**, which is why it
             * has a `grant_cents` beside its `price_cents` where the metered
             * products have a quantity. $300 paid grants $400 of credit — one
             * movement, not a multiplier applied at spend time; modelling it the
             * other way would give every AI debit a per-tenant exchange rate.
             *
             * ⛔ **THE OWNER MOVED BOTH RUNGS ON 2026-08-24 AND KEPT THE SHAPE**
             * (9181, reversing 3303's $50 → $50 and $200 → $300). The automatic
             * rung is $100 for $100 and the manual load is $300 for $400. ⚠️ **The
             * automatic rung's GRANT was not stated by him and is derived
             * one-for-one** on 3303's own principle — the single figure in that
             * block taken rather than quoted, and flagged to him as an assumption.
             *
             * ⚠️ **THE DIRECTION IS THE THING TO GET RIGHT AND IT DID NOT MOVE.**
             * "$100 free" is only true if the money is the smaller of the pair;
             * reversed, the tenant loses $100 and every screen still renders
             * plausibly. A lint pins it, on the difference rather than on either
             * figure — which is why both rungs could move without touching it.
             */
            'credits.topup.ai.automatic_price_cents' => [
                'seed' => 10_000,
                'group' => 'Credits',
                'description' => 'What one automatic AI credit top-up charges, in integer cents (decision 9181, reversing 3303\'s $50).',
            ],
            'credits.topup.ai.automatic_grant_cents' => [
                'seed' => 10_000,
                'group' => 'Credits',
                'description' => 'How much AI credit one automatic top-up adds to the purchased pool, in integer cents. One-for-one: the discount in this product lives entirely in the manual load (decision 3303, at 9181\'s figure). ⚠️ This is the one figure in 9181 the owner did not state — it is derived one-for-one from the price on 3303\'s own principle, and it was flagged to him as an assumption rather than taken quietly.',
            ],
            'credits.topup.ai.manual_price_cents' => [
                'seed' => 30_000,
                'group' => 'Credits',
                'description' => 'What the manual AI credit load charges, in integer cents (decision 9181, reversing 3303\'s $200). The owner\'s "get $100 free" shape is unchanged and the rung moved: this is the $300 and the grant beside it is the $400.',
            ],
            'credits.topup.ai.manual_grant_cents' => [
                'seed' => 40_000,
                'group' => 'Credits',
                'description' => 'How much AI credit the manual load adds to the purchased pool, in integer cents (decision 9181, reversing 3303\'s $300). This is the $400, and it must stay the larger of the pair — reversed, the tenant pays $400 for $300 and nothing on any screen looks wrong.',
            ],

            /*
             * Automatic top-up (3306, and 2064's CONFIRM).
             *
             * ⚠️ **OPT-IN IS A DEFAULT, WHICH MEANS IT IS A VALUE SOMEWHERE, AND
             * THIS IS WHERE.** An automatic charge is "anything that spends
             * money", so CONFIRM applies to the arrangement (2064, 2106) — the
             * fail-closed seed is therefore off, and a lane that defaulted it on
             * would be charging a card on an arrangement nobody confirmed.
             *
             * ⚠️ **THE CEILING IS THE SECOND TENANT-FACING SETTING IN THE PRODUCT
             * AND IT DOES NOT REOPEN "never add a tenant-facing toggle"** (3306).
             * 1143 took an explicit ruling for the review invite threshold and
             * this is an explicit ruling for this one; a third still needs its
             * own. The shape is 1420's exactly: this key is the *platform*
             * default, and a tenant who has chosen has a stored number Ops cannot
             * move.
             *
             * ⚠️ **$50 IS READ FROM A SMART QUOTE** (3308). The reply wrote `$5”`
             * — a `”` occupying the position of a `0` — against $50 appearing as
             * the automatic increment on all three products. The reading was put
             * back to the person holding the reply and confirmed, and it is a
             * registry row rather than a constant precisely so the cost of being
             * wrong is one Ops edit.
             */
            'credits.auto_topup.enabled_by_default' => [
                'seed' => false,
                'group' => 'Credits',
                'description' => 'INERT — NOTHING READS THIS KEY AND TURNING IT ON DOES NOTHING. Automatic top-up is on for an account when that account has an arrangement row and off when it has none (decision 3306, 3488), and the only writer of that row is AutoTopUps::agree(), which requires a confirmation record naming who agreed, to what figure, in what words. There is deliberately no reader here, because the only thing one could do is arrange recurring charges nobody agreed to; the row is kept so the reasoning stays beside the seed rather than being rediscovered.',
            ],
            'credits.auto_topup.default_ceiling_cents' => [
                'seed' => 5_000,
                'group' => 'Credits',
                'description' => 'The most an account will automatically be charged for top-ups before automatic top-up stops, in integer cents, for a tenant who has not set their own (decision 3306). The platform default only — a tenant who has chosen has a stored ceiling this key cannot move, the same shape as reviews.default_invite_threshold (1420). ⚠️ The window it applies over is the CALENDAR MONTH and the ruling does not say so — taken for consistency with 3440, which put the credit reset on the calendar month rather than the billing period. A ceiling on a different clock from the grant it tops up would be two calendars in one feature.',
            ],

            /*
             * ⚠️ **THE LOW-BALANCE THRESHOLDS ARE OPERATIONAL FIGURES RATHER
             * THAN PRICING RULINGS, AND THE DISTINCTION IS WHY THEY MAY BE
             * SEEDED AT ALL.** The owner set the ceiling and the SKU prices; he
             * said nothing about *when* a balance counts as low. That is the same
             * class of number as `campaigns.batch_size` or
             * `messaging.complaint_trip_min_delivered` — something this system
             * needs in order to operate, chosen by us and admin-editable (3415),
             * rather than a commercial term withheld until he rules (157, 3304).
             *
             * ⛔ **READ AGAINST THE PRODUCT'S TOTAL SPENDABLE BALANCE AND NOT
             * AGAINST THE TOP-UP POOL ALONE.** A spend draws the monthly grant
             * first and spills into top-up (3307), so a top-up pool at zero is
             * the *ordinary* state of an account inside its allowance —
             * triggering on it would charge every tenant on the first of the
             * month. What matters is what the tenant can still spend in total.
             *
             * ⚠️ **EACH IS IN ITS PRODUCT'S OWN LEDGER UNIT, AND THEY DIFFER**:
             * whole sends for SMS and email, HUNDREDTHS OF A CENT for AI (3331).
             * The AI seed of 5,000 is fifty cents of credit, not fifty dollars.
             */
            'credits.auto_topup.threshold.sms' => [
                'seed' => 50,
                'group' => 'Credits',
                'description' => 'The SMS balance at or below which automatic top-up fires, in whole sends, counting the monthly grant and purchased credit together. Operational rather than commercial: roughly a day of sending for an active tenant, which is the point of firing before they run out rather than after.',
            ],
            'credits.auto_topup.threshold.email' => [
                'seed' => 100,
                'group' => 'Credits',
                'description' => 'The email balance at or below which automatic top-up fires, in whole sends, counting the monthly grant and purchased credit together. Higher than the SMS threshold because the monthly email allotment is twice the SMS one (3298) and email sends arrive in bursts.',
            ],
            'credits.auto_topup.threshold.ai' => [
                'seed' => 5_000,
                'group' => 'Credits',
                'description' => 'The AI balance at or below which automatic top-up fires, in HUNDREDTHS OF A CENT — so 5,000 is fifty cents of credit, not fifty dollars, which is the unit trap 3331 records. Counting the monthly grant and purchased credit together.',
            ],

            /*
             * The metered email rate (3299), superseding 2063's $20/10,000 by a
             * factor of ten.
             *
             * ⚠️ **IT IS SEEDED BESIDE THE SKU THAT IMPLIES IT, AND THAT IS
             * DELIBERATE RATHER THAN AN OVERSIGHT OF 754.** Two seeded numbers
             * that must agree is normally the trap — but 3299's whole argument is
             * that these are *two statements by the owner*, made minutes apart,
             * agreeing to the cent, and that the agreement is what promoted the
             * figure from a plausible slip to a ruling. Dropping either one
             * throws away the corroboration. So both are seeded and **a lint
             * fails the build the day they disagree**, which is the protection
             * 754 actually wants and is stronger than deriving one from the
             * other, because a derivation cannot notice a wrong SKU.
             *
             * ⚠️ **IT IS THE RATE, NOT THE ONLY RATE ANYTHING IS SOLD AT.** The
             * manual package is half this by design (3302).
             *
             * ✅ **SENDS THROUGH A TENANT'S OWN CONNECTED GOOGLE OR IMAP MAILBOX
             * ARE UNMETERED — A READING AT 3300, A RULING AT 3438.** It was
             * inferred from a missing comma and kept because the error it risked
             * was undercharging; asked directly, the owner answered *"Own domain
             * free."* ⛔ **"Own domain" is read narrowly** (3439): a custom
             * sending domain riding OUR SES still costs us money and stays
             * metered. Free means the send never touches our transport.
             *
             * It remains deliberately **not** a key here. That was true when the
             * exemption was an inference and it is still true now it is a ruling:
             * the exemption is a property of *which transport carried the send*,
             * not a figure an operator sets, and a seeded toggle would invite
             * somebody to switch metering off wholesale.
             */
            'credits.rate.email_cents_per_thousand' => [
                'seed' => 2_000,
                'group' => 'Credits',
                'description' => 'What 1,000 platform emails are priced at, in integer cents (decision 3299) — $20, superseding decision 2063\'s $20 per 10,000 by a factor of ten. Corroborated independently by the automatic top-up SKU ($50 for 2,500), and a lint holds the two together. ⚠️ Applies to the platform transport; a tenant sending from their own connected mailbox costs us nothing and is unmetered (3300, ruled at 3438; "own domain" read narrowly at 3439 — a custom sending domain on our SES stays metered). ✅ EmailCredits reads this key rather than its own constant since 3415.',
            ],

            'credits.rate.ai_retail_multiple' => [
                'seed' => 8,
                'group' => 'Credits',
                'description' => 'What a tenant is charged per unit of provider cost for AI — the owner\'s "8 to 1 what it cost us" (decision 3304). ⛔ IT WAS A CLASS CONSTANT AND THE OWNER OVERRULED THE ARGUMENT FOR THAT (3416): AiCredits held it out of the registry because "a markup is a pricing ruling from the owner" rather than an operator setting, which is a reason to keep an operator out of it and not a reason to keep the OWNER out of it — and 3415 asks for exactly the opposite. ✅ The property that argument protected survives: a change prices future calls only, because the charge is written onto ai_calls.retail_hundredths_cents at call time and the month-to-date total sums that column instead of recomputing cost × multiple. ⚠️ A value below 1 is refused at the read site and falls back to 8 — an admin-editable markup can be set to 0, and a 0 would make every AI call free while every screen went on reporting a rate. Charging nothing needs its own ruling, not a typo in a settings field.',
            ],

            /*
             * ═══ OPERATOR ALERTING (T176 P23) ═══
             *
             * Who gets paged, how loud, and how often. Every figure below is a
             * threshold on a counter the platform keeps about ITSELF — never
             * about a tenant — and none of them refuses, delays or degrades
             * anything (R25: *"operator alerting P23 (a bell, never a brake)"*).
             *
             * ⚠️ **THE THRESHOLDS SEED ON, WHICH IS THE OPPOSITE OF THE
             * COMPLAINT TRIP, AND THE DIFFERENCE IS WHAT THEY DO.** 2119 seeds
             * `messaging.platform_complaint_trip_bp` at zero because a machine
             * may not halt the platform on a figure nobody chose. Nothing here
             * halts anything, so the same caution would only produce a bell that
             * never rings — 272's shape arriving as a default. **Zero still
             * disables each check individually** (2409's convention), so an
             * operator drowning in one of them can silence exactly that one.
             *
             * ⛔ **THE TWO RECIPIENT KEYS SEED BLANK AND THAT IS A REAL GAP
             * RATHER THAN A SAFE DEFAULT.** An address cannot be invented — the
             * launch ops annex has a blank for the operator to fill in, and a
             * plausible-looking `ops@` would send every alert into a void that
             * looks configured. `ops:watch-platform-health` therefore warns on
             * every run while both are empty, the way `WatchPlatformComplaintRate`
             * announces its own switched-off state.
             */
            'ops.alert_email' => [
                'seed' => '',
                'group' => 'Operations',
                'description' => 'Where platform-health alerts are emailed (T176 P23). ⛔ BLANK MEANS NO EMAIL ALERTS — the alert still reaches the log at level `critical`, and nothing else. This is the OPERATOR\'s address, never a tenant\'s: the mail names our own internals. ⚠️ There is deliberately no `enabled` toggle beside it — a bell with a switch is a bell somebody switches off, and a blank field is already the off state.',
            ],
            'ops.alert_sms' => [
                'seed' => '',
                'group' => 'Operations',
                'description' => 'The operator\'s own mobile number, in E.164, for platform-health alerts (T176 P23, owner-approved "email + SMS"). ⛔ BLANK MEANS NO TEXT ALERTS. ⚠️ THIS IS THE ONE NUMBER THIS PLATFORM MAY TEXT WITHOUT A CONSENT RECORD, and it is safe only because of what it is: the platform paging itself at a number the operator typed in themselves. `PlatformTexter::alertOperator()` reads it here and takes no recipient argument at all, so no caller can point it at a customer — see that method\'s docblock, and `PlatformTexter`\'s own refusal of an unpermitted send to an account holder, which stands unchanged.',
            ],
            'ops.alert_quiet_minutes' => [
                'seed' => 60,
                'group' => 'Operations',
                'description' => 'How long one alert kind and subject stays quiet after firing (T176 P23). ⚠️ THIS IS THE KEY THAT DECIDES WHETHER THE PAGER SURVIVES ITS FIRST INCIDENT: the watch runs every five minutes and a broken thing stays broken, so without this the first outage sends a hundred texts and the second sends none, because the number has been blocked by then (decision 511, wearing a phone). ⚠️ De-duplication is per (kind, subject), so a second provider failing twenty minutes into the first one\'s outage still rings. Clamped at one minute — zero would ring on every sweep. ⛔ AND CAPPED AT 10,080 MINUTES — SEVEN DAYS. This one number is the ONLY mute the whole pager has, so a value in months silences the platform telling you it is broken — including “no queue worker has picked up a job”, which has one subject and therefore rings once and then never. Seven days is already seven times the widest stated use, one alert a day through a multi-day outage. ⚠️ TO QUIETEN ONE NOISY CHECK RATHER THAN ALL OF THEM, set that check\'s own threshold to zero on this screen — that switches it off and leaves every other bell ringing. ⛔ AND THAT IS TRUE OF THE PLATFORM-HEALTH COUNTERS AND OF NOTHING ELSE: three other keys carry a threshold on which zero does something other than switch a check off, and the operator alert board states, row by row, what each one\'s zero actually does. Read it there before typing a zero into any other threshold.',
            ],
            'ops.health_window_minutes' => [
                'seed' => 60,
                'group' => 'Operations',
                'description' => 'How far back every counter check looks (T176 P23) — failed jobs, webhook signature failures, vendor error rates. ⚠️ Widened to whole hours at read time, because the counters are hourly buckets and a window that started mid-hour would either drop the bucket the failures are landing in right now or count part of an hour as all of it. The alert states the window it actually used.',
            ],
            'ops.failed_job_spike' => [
                'seed' => 25,
                'group' => 'Operations',
                'description' => 'How many jobs may land in `failed_jobs` inside the window before the operator is alerted (T176 P23). 0 disables the check. ⚠️ THE FIGURE IS A STARTING POINT AND IS MEANT TO MOVE: one failure is ordinary — a vendor timed out and the retry will get it — and the shape worth waking somebody for is a deploy that broke a payload, an expired credential or a dependency that went down, which produces dozens. An operator who finds this too tight raises it in Ops without a deploy, which is the alternative to them muting the channel. ⛔ AND "WHICH PRODUCES DOZENS" IS TRUE OF A BUSY PATH AND FALSE OF EVERY PATH — MEASURED 2026-08-25 (9375). This is a count of FAILURES, which is a count of TRAFFIC, so what it can reach depends on how much work a path carries. This platform\'s own outbound mail carries a handful of messages an hour — sign-in links, renewal notices, support replies — so a mail transport that has completely stopped produces THREE failed jobs in twenty minutes and never approaches twenty-five. That is not hypothetical: it happened, and the rows sat unread for five days. ⛔ THE SAME ARITHMETIC APPLIES TO EVERY LOW-VOLUME PATH ON THIS PLATFORM AND NO FIGURE HERE FIXES IT — lowering this to three pages somebody every time three unrelated vendor calls time out in an hour, which is decision 511 and gets the pager muted. ✅ WHAT COVERS THE MAIL PATH INSTEAD IS A BELL WITH NO THRESHOLD AT ALL: the platform\'s own outbound mail rings "Email this platform sent was not delivered" on the FIRST permanent failure, at any volume, and repeats at most once a day per mailer. ⚠️ SO READ THIS ROW AS WHAT IT IS — a bell for a SPIKE across the whole platform, which is a real shape and is not the only one. A path whose total volume is below this figure is not watched by it, however completely it has stopped.',
            ],
            'ops.webhook_signature_failure_spike' => [
                'seed' => 10,
                'group' => 'Operations',
                'description' => 'How many webhooks may be rejected for an unverifiable signature, per endpoint, inside the window before the operator is alerted (T176 P23). 0 disables the check. ⚠️ THE COMMONEST CAUSE IS OUR OWN MISSING OR ROTATED SIGNING SECRET, NOT AN ATTACKER, and that failure is silent and total: every verification fails closed, so the endpoint accepts nothing while looking exactly like an endpoint nobody is posting to. Counted per endpoint rather than in aggregate, so the alert names the secret to go and fix. ⛔ AND THE FIGURE IS A COUNT OF THE VENDOR\'S OWN TRAFFIC, WHICH IS WHAT DECIDES WHETHER IT CAN BE REACHED AT ALL — not how badly the secret is broken. The counter cannot increment unless that vendor sends us something, so a rotated secret is caught quickly on an endpoint the vendor posts to often and never at all on one it posts to rarely: at one delivery receipt per outbound text this is reached at ten texts in an hour, and an endpoint carrying a few notifications per subscription event may never reach ten in an hour on any install this platform has. ⛔ AN ENDPOINT FOR A FEATURE THAT IS SWITCHED OFF RECEIVES NOTHING, so its bell cannot ring however wrong its secret is; the same is true of a vendor that has never been given anything to report about. ⚠️ LOWERING IT DOES NOT SWITCH THE CHECK ON FOR THOSE — no figure above zero can, because the quantity counted is theirs and not ours. ⚠️ AND IT SAYS NOTHING ABOUT A KEY WE COULD NOT FETCH: that is a separate bell with no threshold, and the operator alert board says which two endpoints can raise it.',
            ],
            'ops.vendor_error_rate_bp' => [
                'seed' => 3_000,
                'group' => 'Operations',
                'description' => 'The share of calls to one outside service that may fail inside the window before the operator is alerted, in basis points — 3,000 = 30% (T176 P23). 0 disables the check. ⚠️ Basis points rather than a float, for the reason money is integer cents: this is compared against a stored threshold and written onto the alert, and 0.0299999 from a JSON round trip compares wrong against itself. ⚠️ A RATE, NEVER A COUNT: ten failures in ten thousand calls is a healthy Tuesday and ten in twelve is an outage. ⛔ NOTHING ABOVE 10,000 (100%) IS ACCEPTED: a rate can never exceed 100%, so a larger figure is not a tighter threshold but an undocumented off switch — the check stops existing while this row goes on reading as configured. Zero is the documented way to switch it off. ⛔ A model REFUSING is not a failure and is never counted here — that is the safety classifier working, and counting it would put the threshold at the mercy of what customers happen to ask.',
            ],
            'ops.vendor_error_floor' => [
                'seed' => 20,
                'group' => 'Operations',
                'description' => 'How many calls to one outside service must have been made inside the window before its error RATE means anything (T176 P23). 0 disables the vendor check entirely. ⚠️ NOT DECORATION: one failed call out of one is a rate of 100%, and an alert that fires on it is one its reader learns to ignore within a week — which is the state the real outage then arrives into (decision 511). ⛔ AND IT IS A BAR ON CALL VOLUME, SO A TOTAL OUTAGE OF A SERVICE THIS PLATFORM CALLS RARELY CANNOT RING THIS BELL AT ALL — the failing calls are still the whole of the traffic, and there is simply not enough of it. A service called once every fifteen minutes for each unit of work makes four calls an hour per unit against a sixty-minute window, so twenty needs five units of work running at once; below that the outage is complete and this check computes nothing. ⚠️ LOWERING IT DOES NOT SWITCH THE CHECK ON FOR A QUIET SERVICE — it makes the check judge a smaller sample, which is the state decision 511 is about, and at 1 the first failed call of the hour is a 100% error rate. ⚠️ AND IT BOUNDS A SIGNAL WITH A NARROW SET OF WRITERS: the operator alert board states, on the vendor row, whose calls this can actually hear, and a build-failing test keeps that sentence true. Read it before assuming a vendor is covered.',
            ],
            'ops.heartbeat_stale_minutes' => [
                'seed' => 15,
                'group' => 'Operations',
                'description' => 'How old the scheduler\'s or a queue worker\'s last heartbeat may be before the operator is alerted (T176 P23). 0 disables the check. ⛔ THIS IS THE ONE ALERT THAT FIRES ON ABSENCE, and it is why the check does not run in the scheduler: a dead scheduler produces silence, not an error, and a check that only runs while the thing it checks is running cannot report that it stopped. It runs in the web process instead. ⚠️ FIFTEEN RATHER THAN TWO, because both processes are restarted on every deploy and a threshold under a deploy\'s length pages somebody every release. ⚠️ A process that has NEVER beaten is not alerted — a fresh install would otherwise page on its first request.',
            ],
            'fixer.ladder.start_level' => [
                'seed' => 3,
                'group' => 'Operations',
                'description' => 'The level a NEW action type starts at in the Fixer ladder.',
            ],
            'fixer.ladder.auto_level' => [
                'seed' => 3,
                'group' => 'Operations',
                'description' => 'At or above this level a command runs without a tap. Below it, the command waits in One-Tap Approval.',
            ],

            /*
             * §10's Delivery paragraph, read literally rather than guessed:
             * "Canary 1% for 60 min, auto-halt on JS-error regression >0.5%."
             * Three of these four figures are the specification's own numbers,
             * verified against the raw document rather than remembered
             * (CLAUDE.md's "verify against the raw artefact" rule). The fourth,
             * the sample floor, is this lane's own choice and is named as one.
             */
            'pixel.canary_percent_bp' => [
                'seed' => 100,
                'group' => 'Pixel',
                'description' => 'Share of /p.js responses that receive a live canary version, in basis points — §10\'s "Canary 1%" (100 = 1%). Basis points rather than a float, for the reason money is integer cents: 0.01 read back from a JSON round trip does not compare equal to itself. Read by PixelDelivery::choose() on every /p.js request while a canary is live.',
            ],
            'pixel.canary_window_minutes' => [
                'seed' => 60,
                'group' => 'Pixel',
                'description' => '§10\'s "for 60 min" — how long a published canary keeps receiving pixel.canary_percent_bp of traffic before WatchPixelCanary promotes it to Active (no regression found) or the window simply keeps running (a regression halts it earlier, on the next sweep after it trips). Widened at read time to the sweep\'s own cadence, ops.health_window_minutes\'s reasoning: a window that ended between two five-minute sweeps is caught on the sweep after it ends, not left open forever.',
            ],
            'pixel.canary_halt_regression_bp' => [
                'seed' => 50,
                'group' => 'Pixel',
                'description' => '§10\'s "auto-halt on JS-error regression >0.5%" (50 = 0.5 percentage points). WatchPixelCanary compares the canary\'s js_error rate against the Active version\'s own rate over the same window — not a fixed error rate, because a page with one third-party script already throwing errors would trip on its first canary otherwise. The canary is halted when it exceeds the Active rate by more than this many basis points. ⛔ ZERO IS NOT AN OFF SWITCH ON THIS ONE AND IS REFUSED. Crossing this threshold does not ring a bell and carry on — it rolls the release back — so a zero would halt every pixel update that is a single basis point noisier than the last, which is the opposite of quietening it. ⛔ AND NOTHING ABOVE 9,999 IS ACCEPTED: one error rate can differ from another by at most 100 percentage points, so a larger figure is not a looser threshold but an undocumented off switch, with the row still reading as configured. There is deliberately no way to switch the automatic halt off.',
            ],
            'pixel.canary_min_pageviews' => [
                'seed' => 20,
                'group' => 'Pixel',
                'description' => 'Minimum pageviews BOTH the canary and the Active version must have recorded inside the comparison window before a regression may be judged. ⚠️ NOT ONE OF §10\'s OWN FIGURES — this lane\'s own choice, mirroring ops.vendor_error_floor\'s reasoning for the identical value: one js_error out of one pageview is a 100% rate, and a halt that fires on it teaches an operator to raise the threshold until it never fires (decision 511). ⛔ ZERO IS READ AS ONE, AND IT USED TO CRASH THE SWEEP EVERY FIVE MINUTES. A rate needs a denominator: with a floor of zero, a build token with no traffic at all — an ordinary canary in its first minutes — cleared the floor and was then divided by, so pixel:watch-canary threw on every run and promoted, halted and alerted nothing. A floor of one is what "disable the floor" can honestly mean here: judge the regression as soon as there is any traffic. ⚠️ Lowering it does not switch the check off, it makes the check trip on a smaller sample.',
            ],

            /*
             * L3, the de-identified network layer (§5.5, §7.3, §11 row 21).
             *
             * ⚠️ THE ONE FIGURE IN THIS FILE WHOSE SETTING SCREEN CAN ONLY DO
             * HARM IN ONE DIRECTION, so the reader refuses rather than clamps.
             * Rule 3 above says the seed is the fail-closed value; here the seed
             * is also the *floor*, and NetworkBenchmarks::minimumCohort() throws
             * on anything below it rather than silently substituting the floor.
             * A privacy rule showing 5 on an Ops screen while the code applied 8
             * is two sources of truth about the same guarantee, which is worse
             * than either number alone.
             */
            'benchmarks.min_cohort' => [
                'seed' => 8,
                'group' => 'Operations',
                'description' => 'How many distinct tenants must contribute to a network benchmark cohort before it may be published at all — `GOAIEZ_PIXEL_MASTER_BUILD` §7.3\'s `benchmark_min_cohort`, §11 row 21\'s "n=7 cohort suppressed", `29` §12.1\'s k-anonymity gate, and `28` §5.4\'s "cohort ≥8, mirroring the existing benchmark rule". ⛔ EIGHT IS A FLOOR AND NOT A DEFAULT: this may be RAISED and may not be lowered. A value below 8 stops the derivation with an error naming this key rather than being quietly ignored, and the L3 cohort table itself carries the same 8 as a CHECK constraint, so a row below it cannot be stored by any writer. ⚠️ The unit is CONTRIBUTING TENANTS, not sessions, visitors or rows.',
            ],

            /*
             * ── THE LEGAL CANON (CC-4, R53) ─────────────────────────────────
             *
             * Two sentences that are quoted rather than composed. Both are
             * registry rows rather than PHP constants for the reason R53a gives
             * — *"a text edit is a data edit, never a release"* — and because
             * two lanes of the same wave bind to these key names from screens
             * and composers this lane does not own.
             *
             * ⚠️ **THEY ARE NOT `legal_documents` ROWS AND THE DIFFERENCE IS
             * THE FREEZE.** That table exists to make a *published version*
             * immutable, because `consent_records.disclosure_version` points at
             * one exact text forever. These are live strings composed into
             * pages and messages at render time; freezing one would mean a
             * wording fix required a new version of a document nobody is
             * citing. Two artefacts, two lifetimes — the distinction
             * `ReviewInviteSender::compose()` already draws about
             * `ConsentDisclosure`.
             */

            /*
             * The conditional performance guarantee, in the owner's own
             * words.
             *
             * ⛔ **THE BYTES ARE THE PACK'S, VERIFIED AGAINST THE RAW ARTEFACTS
             * RATHER THAN AGAINST A SUMMARY** (`CLAUDE.md`'s standing rule).
             * Eight documents in the 2026-08-18 drop carry this sentence and all
             * eight agree, straight apostrophe included: `D1-OFFER-T186` §5
             * layer 2 (*"guarantee supersedes trial here"*), `PIII-4-HOME-V2-T224`'s
             * guarantee stack (*"guarantee verbatim"*), `PIII-13-COMPARE-V2-T234`,
             * `PIII-7`, `PIII-11`, `P1-PRELAUNCH-PACK-T208`, `D3-COPY-1-T188`
             * and `D3-COPY-2-T189`.
             *
             * ⚠️ **R53's L-1 §9 WRITES IT DIFFERENTLY AND THAT IS AN OPEN
             * QUESTION FOR THE OWNER, NOT A TYPO TO PICK A SIDE OF QUIETLY.**
             * The Terms of Service draft says *"we fix it free and extend your
             * **service**"* where the marketing set says *"extend your
             * **trial**"*, and the difference is substantive: a trial cannot be
             * extended for somebody who is already paying, which is presumably
             * why the contract wording differs. The marketing form is seeded
             * because the surfaces that will render this key are marketing
             * surfaces — CC-2's `/pricing` and `/guarantee` — and eight
             * artefacts beat one. See decision 5170: **this key is not the
             * Terms**, and reconciling the two is counsel's.
             *
             * ⚠️ **NOTHING ELSE IN `app/`, `resources/views` OR `lang/` MAY
             * SPELL IT OUT** — `LegalTest`'s *"the guarantee sentence is
             * written out in exactly one place"* fails the build on a second
             * copy, which is what makes "one source" a property rather than an
             * intention. A page that wants it reads this key.
             */
            'legal.guarantee_sentence' => [
                'seed' => 'If your AI doesn\'t text a missed caller back in under 60 seconds, we fix it free and extend your trial.',
                'group' => 'Legal',
                'description' => 'The conditional performance guarantee, verbatim, as the one source every surface that quotes it must read. ⛔ THIS ROW IS LIVE ON PUBLIC PAGES AND IN A DAILY EMAIL. THE SENTENCE HERE SAID THE OPPOSITE — \'nothing in app/ reads this key yet\' — FROM THE DAY IT SHIPPED UNTIL 2026-08-28 (decision 5169, corrected at 10945): every surface that quotes the guarantee reads it from here at render time, which today means two public marketing pages, a support macro an agent can insert into a reply, and two rungs of the trial-reminder email that goes out every morning. That sentence also warned that what this row must never become is \'a row somebody believes is on a page\' — it was on two, and an operator editing it was being told the opposite of that at the moment of the edit. ⚠️ THE REMEDY IS SERVICE AND NEVER MONEY: "Satisfaction Guarantee(d)" and "Money-Back Guarantee" are forbidden phrases (D1-OFFER-T186\'s copy guard) because they legally promise a refund this business does not offer. ⛔ R53\'s Terms of Service draft §9 words the same promise as "extend your service" rather than "extend your trial"; the two have not been reconciled and that is counsel\'s to settle (decision 5170). ⛔ AND NOTHING IN THIS APPLICATION CAN DO WHAT THE SEEDED SENTENCE PROMISES: the no-card trial is registration date plus the platform-wide trial length, with no per-business override anywhere in the schema, so the promised extension is unkeepable by construction rather than merely unbuilt (decision 9401, re-derived 10946). A mechanism or a wording is owed and both are the owner\'s — do not settle it by editing this row. Editing this row changes every future render and nothing already published.',
            ],

            /*
             * The footer every review ask carries.
             *
             * ⚠️ **THIS IS THE HOUSE STRING, NOT A NEW ONE.** It is byte-equal
             * to the literal `ReviewInviteSender::compose()` has always
             * appended; CC-4's job was to give it one name so CC-5 composes
             * from it instead of baking a second wording into a template.
             * Decision 5172 records the mapping.
             *
             * ⛔ **`MissedCallTextBack::compose()` DELIBERATELY DOES NOT READ
             * IT, AND ITS DOCBLOCK IS WHY**: *"a wording an owner can edit is a
             * wording an owner can edit the disclosure out of."* That objection
             * is answered here rather than overruled —
             * `DefaultsRegistry::set()` refuses a value that drops either
             * obligation — but answering it for the review-ask path is this
             * lane's brief and moving a settled missed-call composer is not.
             *
             * ⚠️ **SO THE TWO NOW HOLD THE SAME WORDS BY TWO MECHANISMS, AND
             * THAT IS A NEW DIVERGENCE RATHER THAN AN OLD ONE.** 3182 recorded
             * a cross-composer disagreement about *wording* — the review-invite
             * footer was lane-conditional and the missed-call one was not — and
             * 3191 closed it by making both unconditional. This is a
             * disagreement about *where the words live*, and it is what
             * `LegalTest`'s "every composer that discloses the platform says
             * what the footer canon says" exists to stop drifting. 3182 is the
             * precedent for writing a cross-composer difference down rather
             * than resolving it from a lane that does not own both files.
             *
             * ⚠️ **TWO OBLIGATIONS RIDE IN ONE SENTENCE AND BOTH ARE
             * ENFORCED.** *"Sent via GO AI EZ"* is 3191's unconditional lane
             * disclosure — every reachable sending number is on the GOAIEZ
             * 10DLC brand, so the sentence is never false — and *"Reply STOP"*
             * is the carrier-required opt-out instruction. A counsel swap may
             * reword the rest and may not remove either.
             */
            'legal.sms_ask_footer' => [
                'seed' => 'Sent via GO AI EZ. Reply STOP to opt out.',
                'group' => 'Legal',
                'description' => 'The footer composed onto every review-ask text at send time (R53 L-3/L-7, CC-4/CC-5). ⛔ IT MAY BE REWORDED AND MAY NOT BE EMPTIED: `DefaultsRegistry::set()` refuses a value that drops the "Sent via GO AI EZ" lane disclosure (decision 3191) or the "STOP" opt-out instruction, because a footer an operator can blank is a compliance disclosure an operator can delete. ⚠️ Every character here comes out of the same 159-unit budget the invite already overruns for a long business name, and nothing truncates it — a truncated disclosure is a compliance failure that reports as a successful send (1570).',
            ],

            /*
             * The signed-out site's capability flags — CC-2 §2.3, decision 5192.
             *
             * ⛔ **EVERY ONE SEEDS `false`, AND THE SEED IS A STATEMENT ABOUT THE
             * PRODUCT RATHER THAN A CAUTION.** `/features` renders a section per
             * capability and `/compare` renders a row per capability, both off
             * `App\Enums\MarketingCapability` — so flipping one of these does not
             * enable a feature, it makes a **public claim** that the feature
             * exists. That is the opposite ordering from every other flag in this
             * file, and it is why the seeds are all off: the marketing page must
             * never be the first thing to announce a capability.
             *
             * ⚠️ **THERE IS NO FLAG FOR THE THREE LIVE CAPABILITIES**, and the
             * absence is deliberate. The missed-call text-back, the review ask and
             * the front desk are what the product does today; a flag that has
             * never been off is a switch nobody has tested, and turning one of
             * those three off is not a marketing decision.
             */
            'features.commerce' => [
                'seed' => false,
                'group' => 'Marketing',
                'description' => 'Whether the signed-out site says we sell, book and gift-card. ⛔ THIS IS A CLAIM SWITCH, NOT A FEATURE SWITCH: it renders /features\' commerce section and turns /compare\'s "Online store included" row from "Not yet" to "Yes". Setting it while the storefront is unbuilt publishes a false statement on the page whose whole subject is honesty about what the alternatives do.',
            ],
            'features.campaigns' => [
                'seed' => false,
                'group' => 'Marketing',
                'description' => 'Whether the signed-out site says the twelve ready-made campaign packs are included. ⛔ A CLAIM SWITCH — see `features.commerce`. It also drives /compare\'s "Ready-made campaigns included" row.',
            ],
            'features.inbox' => [
                'seed' => false,
                'group' => 'Marketing',
                'description' => 'Whether the signed-out site says every text, email and call from one person lands in one thread. ⛔ A CLAIM SWITCH — see `features.commerce`.',
            ],
            'features.websites' => [
                'seed' => false,
                'group' => 'Marketing',
                'description' => 'Whether the signed-out site says a site is built from a conversation. ⛔ A CLAIM SWITCH — see `features.commerce`.',
            ],
            'sites.build.recrawl_after_hours' => [
                'seed' => 24,
                'group' => 'Sites',
                'description' => 'Re-run crawls again only if this many hours have passed.',
            ],
            'sites.build.max_pages_publish' => [
                'seed' => 12,
                'group' => 'Sites',
                'description' => 'Maximum number of pages to publish during build.',
            ],
            'sites.crawl.max_pages' => [
                'seed' => 25,
                'group' => 'Sites',
                'description' => 'Maximum number of pages followed on the same host breadth-first when fetching a tenant site.',
            ],
            'sites.images.max_per_site' => [
                'seed' => 60,
                'group' => 'Sites',
                'description' => 'Maximum number of unique images to store from a single site\'s inventory (D2).',
            ],
            'sites.images.max_bytes' => [
                'seed' => 2000000,
                'group' => 'Sites',
                'description' => 'Maximum size of an individual image fetched for inventory storage, in bytes. Must not exceed the FetchGateway\'s own 2 MB ceiling.',
            ],
            'features.boost_score' => [
                'seed' => false,
                'group' => 'Marketing',
                'description' => 'Whether the signed-out site describes the Boost Score. ⛔ A CLAIM SWITCH — see `features.commerce`. ⚠️ The claim it makes is itself conditional ("no data, no number"), so flipping it commits us to a screen that withholds the score rather than inventing one.',
            ],

            /*
             * CC-3's industry engine, gated from CC-2's side — decision 5193.
             *
             * ⚠️ **TWO SURFACES IN THIS SLICE READ IT AND NEITHER OWNS IT.** The
             * home page's hundred-kinds block and the `/industries` hub shell are
             * both dark until this is on; the hundred rows, the landers and the
             * sitemap are CC-3's. The key is declared here because a page that
             * asked for an undeclared key would raise rather than render, and
             * this lane's pages must render today.
             *
             * ⚠️ **TWO SWITCHES GUARD THESE PAGES AND THEY ARE NOT THE SAME
             * SWITCH** (CC-3 §4, decision 5221). This one decides whether
             * `/industries` and its hundred children answer at all;
             * `industry_pages.index_mode` decides whether a page that answers
             * may be **indexed**, and feeds both the `noindex` tag and
             * `sitemap-industries.xml` from one column. **On with `index_mode`
             * still false is the intended intermediate state** — the pages are
             * readable and unlisted — and it is what makes the indexability
             * moment a separate, dated act rather than a side effect of a
             * deploy.
             *
             * ⛔ **CC-2 AND CC-3 EACH DECLARED THIS KEY ON THEIR OWN BRANCH AND
             * THE MERGE KEPT ONE.** A duplicate PHP array key is not an error —
             * the later definition silently wins — so the two would have
             * disagreed about the `group` an operator finds it under with
             * nothing failing. This definition is CC-2's, its `group` is
             * `Marketing` beside the five sibling capability flags, and CC-3's
             * two-switch argument is folded in above rather than lost with its
             * copy.
             */
            'features.industry_pages' => [
                'seed' => false,
                'group' => 'Marketing',
                'description' => 'Whether the industry pages are published. Off, `/industries` is not reachable and the home page\'s "A hundred kinds of local business" block does not render — a link to a hub with nothing in it is worse than no link (261). On, the hub lists the `industry_pages` rows CC-3 seeds. ⚠️ THE FLIP IS THE PUBLICATION: it is what puts a hundred URLs in front of a crawler, so it belongs with the sitemap submission rather than ahead of it.',
            ],

            /*
             * The six demo-door keywords — CC-2 §2.7, decision 5194.
             *
             * ⚠️ **OURS TO CHOOSE, WHICH IS WHY THEY CARRY SEEDS WHERE THE NUMBER
             * THEY ARE TEXTED TO DOES NOT.** A keyword is a word we pick; the
             * number is a fact about a line somebody has to provision. See
             * `demo.number` in {@see self::declaredWithoutSeed()} for the half of
             * this pair that no seed could honestly hold.
             *
             * ⛔ **NOTHING ANSWERS THESE YET.** `InboundKeyword` parses STOP, HELP
             * and START and files everything else as `None`, so a text saying
             * `TRADES` reaches the assistant as ordinary conversation rather than
             * as a demo request. That is survivable only because no page prints a
             * keyword until `demo.number` is set, and setting it is the act that
             * makes the promise.
             */
            'demo.keyword.trades' => [
                'seed' => 'TRADES',
                'group' => 'Marketing',
                'description' => 'The word a visitor texts to see the trades demo answer. Uppercase because that is how a keyword is printed and how a handset user types one; matching is the inbound parser\'s job and is case-insensitive there.',
            ],
            'demo.keyword.care' => [
                'seed' => 'CARE',
                'group' => 'Marketing',
                'description' => 'The word a visitor texts to see the care demo answer — dentists, clinics, vets, therapists.',
            ],
            'demo.keyword.auto' => [
                'seed' => 'AUTO',
                'group' => 'Marketing',
                'description' => 'The word a visitor texts to see the auto demo answer — shops, body, tyres, detailing.',
            ],
            'demo.keyword.food' => [
                'seed' => 'FOOD',
                'group' => 'Marketing',
                'description' => 'The word a visitor texts to see the food demo answer — restaurants, cafés, caterers, bakeries.',
            ],
            'demo.keyword.medspa' => [
                'seed' => 'MEDSPA',
                'group' => 'Marketing',
                'description' => 'The word a visitor texts to see the medspa demo answer — aesthetics, wellness, salons and spas.',
            ],
            'demo.keyword.office' => [
                'seed' => 'OFFICE',
                'group' => 'Marketing',
                'description' => 'The word a visitor texts to see the office demo answer — law, accounting, agencies, insurance.',
            ],

            /*
             * The publish gate — doc `16` §15.3, `29` §2 rule 29.
             *
             * ⚠️ **PLATFORM POLICY, NOT A TENANT SETTING, AND THE DISTINCTION IS
             * `CLAUDE.md`'s STANDING RULE RATHER THAN A PREFERENCE.** A tenant
             * who could move these could move them to zero, and the thing they
             * would be turning off is the protection against Google's March 2026
             * update — decision 44: *"cut traffic 50–80% on sites that skipped
             * this"*. The owner has overruled the no-toggle rule exactly once
             * (1143, the invite threshold) and said in terms that the exception
             * is not the rule.
             *
             * ⚠️ **THE THREE FIGURES ARE THE DOCUMENTS' AND NOT THIS FILE'S.**
             * 60 and 2 are `16` §15.3 verbatim; 8 is `29` §9.1's *"target
             * reading level 8"* and doc `33`'s *"readability grade ≤8 for
             * consumer topics"*. ⛔ **`28` §7.2's ≤6 IS A DIFFERENT RULE ABOUT A
             * DIFFERENT THING** — the simple-language gate over owner-facing
             * report copy — and seeding it here would silently hold every
             * customer-facing page to a standard written for a monthly summary.
             *
             * ⛔ **THERE IS NO DEMAND-VOLUME KEY AND ITS ABSENCE IS DELIBERATE**
             * (5571). `16` §15.3 asks the page to answer *"a question with
             * demonstrated real demand"* and never says how much. Seeding a
             * floor would invent a product judgement nobody has made, and an
             * invented number in this file is worse than one in prose because
             * this file executes (204). The gate asks for evidence to exist and
             * to carry a count above zero, which is the distinction the document
             * actually draws.
             */
            'content.quality.min_uniqueness_pct' => [
                'seed' => 60,
                'group' => 'Content',
                'description' => 'How much of a growth page must be new writing, measured by shingle comparison against every other page this tenant has (doc `16` §15.3: "≥60% content unique vs. every other page on the site"). Compared with `>=`, so 60 passes. ⚠️ The comparison is tenant-wide rather than per-site, which is stricter than `16`\'s wording and is `BUILD-PLAN` §2.11.3\'s: the failure `16` §15.1 warns about is the city×service matrix across locations, which a per-location comparison cannot see.',
            ],
            'content.quality.min_first_party_data_points' => [
                'seed' => 2,
                'group' => 'Content',
                'description' => 'How many pieces of genuine first-party data a growth page must carry (doc `16` §15.3: "contains ≥2 pieces of genuine first-party data" — a real review quote, service detail, photo, price or local fact). ⛔ The gate counts data points it can actually find in the copy, never the number a caller declared: counting declarations would make the check a self-assessment a generator passes by attaching labels.',
            ],
            /*
             * The actuation switch `BUILD-PLAN` §2.11.3 assumed and nothing
             * created (5652, 5665).
             *
             * ⛔ **ITS DESCRIPTION CARRIED TWO FALSE CLAUSES UNTIL 2026-08-20,
             * AND IT IS THE SENTENCE AN OPERATOR READS WHILE AUTHORISING WRITES
             * TO OTHER PEOPLE'S WEBSITES** (6127(c), 6184). It said *"Off until
             * BUILD-PLAN §2.11.3 slice H — measure and auto-rollback — is
             * merged"* (H merged the same day, 5800–5819) and *"the only adapter
             * that exists reports itself unwritable, so turning this on today
             * changes nothing"* — while `WordPressAdapter` existed and had been
             * **bound in production** for part of that day (5913). So the row
             * reassured its reader, in the reassuring direction, at the moment of
             * the press.
             *
             * ⛔ **THE FIX IS NOT A BETTER SENTENCE — IT IS A SENTENCE THAT SAYS
             * ONLY WHAT THIS REPOSITORY KNOWS** (6121: *a seed is not a
             * deployment*). Which adapter is bound comes from an environment
             * variable read at config-cache time; **no string in this file can
             * see it, whatever it says.** `RegistryTest`'s *"no registry
             * description tells an operator what their deployment has
             * configured"* fails the build on any description naming an
             * environment variable, and the deployment fact is stated by
             * `Admin\PlatformSettings`, which runs inside the deployment and
             * asks the container through `Publishing::adapterReachesAWebsite()`.
             *
             * ⚠️ **THE OLD SECOND CLAUSE WAS ALSO THE WEAKER OF TWO GUARDS AND
             * THAT PART WAS TRUE WHEN WRITTEN.** `Publishing::canWriteToSite()`
             * asks the registry, the tier and the adapter's own `health()`, and
             * a driver that reads nothing cannot produce a snapshot, so
             * `SiteChanges::open()` refuses a change set on it (5528). **What
             * changed is that "the only adapter" stopped being true**, and the
             * guard that survives is asked per location at write time rather
             * than asserted here.
             *
             * ⚠️ **IT DOES NOT GATE THE ADVISORY.** T4 transmits nothing, and
             * switching off the rung that changes no website would leave the
             * `handoff()` half of rule 44 untestable on the only deployment
             * that exists.
             */
            'actuation.enabled' => [
                'seed' => false,
                'group' => 'Content',
                'description' => 'Whether this platform may write to a tenant\'s own website — pages published and fixes applied on their site, on its own, without anybody here pressing anything again. ⛔ This alone answers nothing: a website adapter must also be connected on this deployment, and every write is refused unless that adapter reports the site reachable. ⚠️ Which adapter a deployment has connected is a setting on the machine, not in this database, so nothing written here can tell you — the confirmation shown when you turn this on states what this deployment actually has. ⚠️ It does not switch off the advisory hand-off, which transmits nothing to any website and needs no revert. Turning it off takes one press.',
            ],
            'actuation.faq.max_items' => [
                'seed' => FaqBlock::MAX_ITEMS,
                'group' => 'Content',
                'description' => 'The maximum number of items allowed in a FAQ block.',
            ],
            'actuation.faq.max_answer_chars' => [
                'seed' => FaqBlock::MAX_ANSWER,
                'group' => 'Content',
                'description' => 'The maximum number of characters allowed in a FAQ answer.',
            ],
            'actuation.alt_text.max_chars' => [
                'seed' => AltText::MAX_TEXT,
                'group' => 'Content',
                'description' => 'The maximum number of characters allowed in alt text.',
            ],
            'actuation.internal_link.max_text_chars' => [
                'seed' => InternalLink::MAX_TEXT,
                'group' => 'Content',
                'description' => 'The maximum number of characters allowed in an internal link text.',
            ],
            'actuation.meta.max_content_chars' => [
                'seed' => MetaUpsert::MAX_CONTENT,
                'group' => 'Content',
                'description' => 'The maximum number of characters allowed in a meta tag content attribute.',
            ],
            'speed.min_hours_between_fixes' => [
                'seed' => SpeedFixes::MINIMUM_HOURS_BETWEEN_FIXES,
                'group' => 'Content',
                'description' => 'The minimum number of hours that must elapse before a subsequent speed fix can be applied.',
            ],
            'speed.baseline_days' => [
                'seed' => SpeedDecider::BASELINE_DAYS,
                'group' => 'Content',
                'description' => 'The number of days to measure performance before applying a speed fix.',
            ],
            'sites.measure.window_starts_days' => [
                'seed' => SiteMeasurements::MEASURED_WINDOW_STARTS_DAYS,
                'group' => 'Content',
                'description' => 'The number of days after a site change when the measurement window begins.',
            ],
            'sites.measure.window_ends_days' => [
                'seed' => SiteMeasurements::MEASURED_WINDOW_ENDS_DAYS,
                'group' => 'Content',
                'description' => 'The number of days after a site change when the measurement window ends.',
            ],
            'sites.measure.baseline_days' => [
                'seed' => SiteMeasurements::BASELINE_DAYS,
                'group' => 'Content',
                'description' => 'The number of days to measure performance prior to a site change for comparison.',
            ],
            'sites.revert.attempt_ceiling' => [
                'seed' => SiteMeasurements::REVERT_ATTEMPT_CEILING,
                'group' => 'Content',
                'description' => 'The maximum number of times to attempt reverting a site change before giving up.',
            ],
            'sites.undo.in_progress_minutes' => [
                'seed' => SiteChanges::UNDO_IN_PROGRESS_MINUTES,
                'group' => 'Content',
                'description' => 'The maximum time in minutes allowed for an undo operation before it is considered stuck or failed.',
            ],
            'wordpress.timeout_seconds' => [
                'seed' => WordPressRestClient::TIMEOUT_SECONDS,
                'group' => 'Content',
                'description' => 'The timeout in seconds for requests made to a WordPress site via the REST API.',
            ],

            /*
             * Doc `16` §15.3's volume caps — *"deliberately conservative"* —
             * and `29` §2 rule 30, which carries the reason: *"Google's March
             * 2026 update cut traffic 50–80% on sites that ignored this."*
             *
             * ⚠️ **THEY SEED AS WRITTEN RATHER THAN BEING WITHHELD**
             * (`BUILD-PLAN` §2.11.6 row 7). Every other unset figure in this
             * file is unset because it is the owner's to decide; these are
             * somebody else's risk model, so refusing to quote one would refuse
             * to quote Google. Ops may move them **downward**.
             *
             * ⛔ **TWO OF THE FOUR ENFORCE NOTHING, AND THEIR DESCRIPTIONS SAY
             * SO** (5667, 5668). `App\Services\Content\PublishingVolume::UNENFORCED`
             * is the list, a lint reads it, and `CLAUDE.md`'s own warning is why
             * both are named rather than left to be discovered: *"a seeded
             * figure with no reader is a row in a table, not a ceiling."*
             */
            'content.volume.max_new_pages_per_month' => [
                'seed' => 4,
                'group' => 'Content',
                'description' => 'How many new pages one location may publish in a calendar month (doc `16` §15.3: "New pages: ≤ 4 per month per location (NOT per day)"; `29` §2 rule 30). ⚠️ Counted from when a page went live, never from when it was drafted — the cap is about what appeared on the website. Blog posts have their own line and do not consume this one.',
            ],
            'content.volume.max_new_posts_per_month' => [
                'seed' => 4,
                'group' => 'Content',
                'description' => 'How many blog posts one location may publish in a calendar month (doc `16` §15.3: "Blog posts: ≤ 4 per month"). ⚠️ A separate cap from the page one on purpose: four posts must not be able to spend a tenant\'s whole service-page allowance, which is the opposite of "refresh beats publish".',
            ],
            'content.volume.max_quarterly_growth_pct' => [
                'seed' => 20,
                'group' => 'Content',
                'description' => 'The most a tenant\'s site may grow in a quarter, as a share of the pages it already has (doc `16` §15.3: "Total site growth: ≤ 20% pages per quarter"). ⛔ NOT ENFORCED TODAY, AND THIS ROW IS A PARKED FIGURE RATHER THAN A LIVE CEILING: nothing in this application knows how many pages a tenant\'s website has, so the share has no denominator. Using our own published count instead would refuse the first page every tenant ever publishes — twenty per cent of zero. It becomes enforceable when a page inventory exists.',
            ],
            'content.volume.min_refresh_to_new_ratio' => [
                'seed' => 2,
                'group' => 'Content',
                'description' => 'How many refreshes of existing pages should accompany each new one (doc `16` §15.3: "Refresh:reNew ratio target ≥ 2:1 — refresh beats publish"; `29` §2 rule 30). ⛔ NOT ENFORCED TODAY, AND THIS ROW IS A PARKED FIGURE RATHER THAN A LIVE CEILING: nothing refreshes a published page yet, so the ratio has no numerator and every tenant would sit at 0:1 for ever. The refresh half arrives with the Stage 5 content engine.',
            ],

            'content.quality.max_reading_grade' => [
                'seed' => 8,
                'group' => 'Content',
                'description' => 'The highest Flesch–Kincaid grade level a growth page may read at (`29` §9.1 "target reading level 8"; doc `33` "readability grade ≤8 for consumer topics"). ⚠️ Lower is better, so this is an upper bound and the column it is compared against — content_quality_checks.readability_score — holds a grade rather than a score. ⛔ Not `28` §7.2\'s ≤6, which is the simple-language gate over owner-facing report copy and a different rule about different text.',
            ],

            /*
             * `28` §4.2's *"the allowlist ships in code, is admin-extendable in
             * the Ops Console"*, second half.
             *
             * ⚠️ **IT ADDS TO `ScriptDeferral::SHIPPED` AND NEVER REPLACES IT.**
             * A key that replaced the shipped list would let an operator switch
             * the whole fix off with an empty edit, silently and totally — the
             * unset SES sending ceiling's failure mode with its sign flipped
             * (4604).
             *
             * ⛔ **AND IT CANNOT ALLOW A PAYMENT OR BOOKING SCRIPT, WHICH IS A
             * PROPERTY OF THE ORDER THE TWO LISTS ARE ASKED IN** rather than of
             * this description. §4.2 says *never defer* those, and a rule an Ops
             * row can switch off is not one; `ScriptDeferral::mayDefer()` asks
             * the refusal list **after** the allowlist, so a host added here
             * still meets it.
             *
             * Seeds empty: every host we are prepared to vouch for is in code,
             * and an operator adding one is answering for that host.
             *
             * ⛔ **AND NOTHING APPLIES IT YET, WHICH THE DESCRIPTION NOW SAYS
             * FIRST RATHER THAN NOT AT ALL** (8780-8809). This row had exactly
             * one reader — `ScriptDeferral::allowed()` — and `ScriptDeferral`
             * has **no caller anywhere in `app/`**: `SpeedFixes::apply()` takes
             * a fix's content from its caller and its own docblock names that
             * caller as **F2, the plugin**, which is unbuilt. So an operator
             * could add a host, save it, and change nothing on any website,
             * with the row reading as configured throughout — 272's shape with
             * an Ops screen in front of it, and `sending_health_windows`'
             * *"a set threshold on a dead counter is a decoration"* one layer up.
             *
             * ⚠️ **THE ROW IS KEPT RATHER THAN REMOVED**, because `28` §4.2 asks
             * for it by name — *"the allowlist ships in code, is admin-extendable
             * in the Ops Console"* — and the reader that consumes it is written,
             * tested and correct. What was wrong was a present-tense sentence on
             * the one surface an operator reads.
             *
             * ⛔ **THE SENTENCE IS PINNED TO ITS CONDITION**, in
             * `tests/Feature/Actuation/ScriptDeferralTest.php`: the day anything
             * calls `mayDefer()` or `deferrable()`, that test goes red and names
             * this sentence as the thing to correct. A caveat with no failing
             * state is the one artefact in this repository that can go quietly
             * false (8531).
             *
             * ⚠️ **AND THE PLUGIN'S COPY IS THE *REFUSAL* LIST, NOT THIS ONE.**
             * `plugins/wordpress/includes/class-goaiez-speed.php` mirrors
             * `FORBIDDEN_HOSTS` and is pinned by `PluginSourceTest`, so reading
             * *"the guard is implemented in the plugin"* as *"the allowlist is
             * wired"* is the mistake this paragraph exists to stop: the plugin
             * defers whatever handles arrive in its `deferred_scripts` payload,
             * and nothing in `app/` builds that payload.
             */
            'speed.script_deferral_extra_hosts' => [
                'seed' => [],
                'group' => 'Content',
                'description' => '⛔ Nothing applies this list yet, so an edit here changes no website today. Extra third-party script hosts the speed layer may defer, beyond the list that ships in code (`28` §4.2). ⚠️ Exact hostnames, one per entry — never a suffix, because a suffix match also accepts a lookalike somebody else registered. ⛔ This can only widen what may be deferred: payment, checkout and booking scripts are refused after this list is read, so adding one here does not defer it. ⚠️ A general-purpose CDN is not a safe entry — allowlisting one allowlists everything anybody serves through it.',
            ],

            'affiliate.rate_monthly_bp' => [
                'seed' => 4000,
                'group' => 'Affiliate',
                'description' => 'The affiliate\'s share of every monthly payment, in basis points (P-009 2026-09-05).',
            ],
            'affiliate.rate_annual_bp' => [
                'seed' => 4000,
                'group' => 'Affiliate',
                'description' => 'The affiliate\'s share of an annual plan payment, in basis points (P-009 2026-09-05).',
            ],
            'affiliate.cookie_days' => [
                'seed' => 90,
                'group' => 'Affiliate',
                'description' => 'How many days an affiliate link remembers who sent a visitor (R245 2026-09-05).',
            ],
            'affiliate.minimum_payout_cents' => [
                'seed' => 5000,
                'group' => 'Affiliate',
                'description' => 'The balance an affiliate must reach before a payout goes out, in integer cents (R245 2026-09-05).',
            ],
            'agency.usage_discount_bp' => [
                'seed' => 4000,
                'group' => 'Agency',
                'description' => 'The agency discount off retail, in basis points (P-008 2026-09-05).',
            ],
            'agency.voice_discount_bp' => [
                'seed' => 2500,
                'group' => 'Agency',
                'description' => 'The agency discount off voice, in basis points (P-008 2026-09-05).',
            ],
            'sites.draft.about_max_chars' => [
                'seed' => 1200,
                'group' => 'Sites',
                'description' => 'Maximum length of the drafted about text block, extracted from the inventory.',
            ],

            'sites.draft.reviews_max' => [
                'seed' => 6,
                'group' => 'Sites',
                'description' => 'Maximum number of displayable reviews included in the drafted reviews strip block.',
            ],

            'sites.draft.reviews_min_rating' => [
                'seed' => 4,
                'group' => 'Sites',
                'description' => 'Minimum rating required for a review to be included in the drafted reviews strip block.',
            ],
            'signals.decay.half_life_days' => [
                'seed' => 14,
                'group' => 'Signals',
                'description' => 'The default half-life in days for signal decay models (C1).',
            ],
            'signals.decay.rate_pct' => [
                'seed' => 5.0,
                'group' => 'Signals',
                'description' => 'The default decay rate percentage for signal decay models (C1).',
            ],
            'ai.eval.pass_threshold_pct' => [
                'seed' => 90,
                'group' => 'Ai',
                'description' => 'Threshold percentage for an AI evaluation to pass (C2b).',
            ],
            'ai.eval.max_cases_per_set' => [
                'seed' => 50,
                'group' => 'Ai',
                'description' => 'Maximum number of cases kept in a golden set before the oldest is dropped (C2b).',
            ],
            'ops.alerts.push_budget_per_kind' => [
                'seed' => OperatorAlerts::PUSH_BUDGET_PER_KIND,
                'group' => 'Operations',
                'description' => 'Operator alerts push budget per kind.',
            ],
            'ops.alerts.push_budget_hours' => [
                'seed' => OperatorAlerts::PUSH_BUDGET_HOURS,
                'group' => 'Operations',
                'description' => 'Operator alerts push budget hours.',
            ],
            'ops.alerts.mail_path_repeat_hours' => [
                'seed' => OperatorAlerts::MAIL_PATH_REPEAT_HOURS,
                'group' => 'Operations',
                'description' => 'Operator alerts mail path repeat hours.',
            ],
            'ops.alerts.summary_limit' => [
                'seed' => OperatorAlerts::SUMMARY_LIMIT,
                'group' => 'Operations',
                'description' => 'Operator alerts summary limit.',
            ],
            'ops.alerts.min_opening' => [
                'seed' => OperatorAlerts::MIN_OPENING,
                'group' => 'Operations',
                'description' => 'Operator alerts minimum opening.',
            ],
            'ops.alerts.retention_days' => [
                'seed' => OperatorAlerts::RETENTION_DAYS,
                'group' => 'Operations',
                'description' => 'Operator alerts retention days.',
            ],
            'warehouse.bot_threshold' => [
                'seed' => L1Derivation::BOT_THRESHOLD,
                'group' => 'Pixel',
                'description' => 'Warehouse bot threshold.',
            ],
            'warehouse.attribution_window_days' => [
                'seed' => Replayer::ATTRIBUTION_WINDOW_DAYS,
                'group' => 'Pixel',
                'description' => 'Warehouse attribution window days.',
            ],
            'warehouse.sightings_fresh_days' => [
                'seed' => PixelSightings::FRESH_DAYS,
                'group' => 'Pixel',
                'description' => 'Warehouse sightings fresh days.',
            ],
            'warehouse.l2_retention_days' => [
                'seed' => WarehouseRetention::L2_RETENTION_DAYS,
                'group' => 'Pixel',
                'description' => 'Warehouse L2 retention days.',
            ],
            'billing.reconciliation.minimum_age_minutes' => [
                'seed' => PurchaseReconciliation::MINIMUM_AGE_MINUTES,
                'group' => 'Billing',
                'description' => 'Minimum age in minutes for purchase reconciliation.',
            ],
            'billing.reconciliation.lookback_days' => [
                'seed' => PurchaseReconciliation::LOOKBACK_DAYS,
                'group' => 'Billing',
                'description' => 'Lookback days for purchase reconciliation.',
            ],
            'billing.reconciliation.default_batch' => [
                'seed' => PurchaseReconciliation::DEFAULT_BATCH,
                'group' => 'Billing',
                'description' => 'Default batch size for purchase reconciliation.',
            ],
            'pixel.rejects.retention_days' => [
                'seed' => IngestRejects::RETENTION_DAYS,
                'group' => 'Pixel',
                'description' => 'Retention days for ingest rejects.',
            ],
            'pixel.rejects.recent_window_hours' => [
                'seed' => IngestRejects::RECENT_WINDOW_HOURS,
                'group' => 'Pixel',
                'description' => 'Recent window in hours for ingest rejects.',
            ],
            'pixel.rejects.tenant_window_hours' => [
                'seed' => IngestRejects::TENANT_WINDOW_HOURS,
                'group' => 'Pixel',
                'description' => 'Tenant window in hours for ingest rejects.',
            ],
            'pixel.rejects.origins_per_hour' => [
                'seed' => IngestRejects::ORIGINS_PER_HOUR,
                'group' => 'Pixel',
                'description' => 'Origins per hour for ingest rejects.',
            ],
            'ops.health.credential_repeat_days' => [
                'seed' => PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS,
                'group' => 'Operations',
                'description' => 'Credential repeat days for platform health checks.',
            ],
            'ops.runs.failed_run_repeat_hours' => [
                'seed' => ScheduledRunMeter::FAILED_RUN_REPEAT_HOURS,
                'group' => 'Operations',
                'description' => 'Failed run repeat hours for scheduled run meter.',
            ],
            'support.data_requests.statutory_due_days' => [
                'seed' => DataRequests::STATUTORY_DUE_DAYS,
                'group' => 'Operations',
                'description' => 'The statutory period. Lowering it is fine, raising it is a legal question.',
            ],
            'gbp.zernio.free_tier_credit_cents' => [
                'seed' => ZernioSpend::FREE_TIER_CREDIT_CENTS,
                'group' => 'Google Business Profile',
                'description' => 'Free tier credit in cents for Zernio spend.',
            ],
            'notifications.quiet_hours.start' => [
                'seed' => 21,
                'group' => 'Notifications',
                'description' => 'The hour the quiet window starts.',
            ],
            'notifications.quiet_hours.end' => [
                'seed' => 8,
                'group' => 'Notifications',
                'description' => 'The hour the quiet window ends.',
            ],
            'notifications.holds.window_days' => [
                'seed' => 7,
                'group' => 'Notifications',
                'description' => 'How many days of holds the screen shows.',
            ],
            'brand.default_accent' => [
                'seed' => '#0284c7',
                'group' => 'Brand',
                'description' => 'The default accent colour for brand cards.',
            ],
            'brand.card.max_logo_kb' => [
                'seed' => 512,
                'group' => 'Brand',
                'description' => 'Maximum allowed size for the tenant brand logo in KB.',
            ],
            'brand.card.badge_max_chars' => [
                'seed' => 40,
                'group' => 'Brand',
                'description' => 'Maximum characters allowed in the badge text.',
            ],
        ];

        return array_merge($settings, self::mailSendingCeilings());
    }

    /**
     * One 24-hour sending ceiling per mailer — 4456's owed fix, built at 4603.
     *
     * ⛔ **THERE WAS ONE KEY FOR TWO TRANSPORTS AND THEIR LIMITS DIFFER BY TEN
     * TIMES.** Google's Workspace figure is 2,000 per user per rolling 24 hours;
     * a fresh SES account's is 200 per 24 hours until production access is
     * granted (`docs.aws.amazon.com/general/latest/gr/ses.html`, §Service
     * quotas, read 2026-08-16). So `MAIL_MAILER=smtp` — the whole of R16's
     * activation — put the platform 10× over SES's quota with the registry
     * reading as configured throughout, and 4456 could only report it because
     * re-keying is a design change and a fix wave is not where those belong.
     *
     * ⛔ **`smtp` IS DECLARED WITHOUT A SEED AND THAT IS THE POINT OF THE
     * EXERCISE** (4604). Every candidate figure is a false statement: 2,000 is
     * Google's and belongs to a different vendor; 200 is SES's *sandbox* quota
     * and is wrong the moment production access lands, in the direction that
     * reads as a decided limit; and the mailer need not be SES at all —
     * `MailDrivers`' own docblock records that two `smtp` mailers can point at
     * two relays. **So it is stated by the operator or nothing sends**, which is
     * `mail.postal_address`'s shape exactly (4019): declared without a seed
     * rather than withheld, because withheld is checked *before* the stored row
     * and could therefore never be supplied through Ops — and here typing the
     * number **is** the operator supplying it, which is what 4432 already said
     * activating SES requires.
     *
     * ⚠️ **THE KEYS ARE BUILT FROM `MailDrivers::MAILERS`**, so a mailer this
     * application can be pointed at cannot exist without a ceiling row, and a
     * ceiling row cannot exist for a mailer nobody can select.
     *
     * @return array<string, array{seed: mixed, group: string, description: string}>
     */
    private static function mailSendingCeilings(): array
    {
        $descriptions = [
            'gmail' => 'How many emails the Workspace sending account may send in a rolling 24 hours. 2,000 is Google\'s standard figure per user (`knowledge.workspace.google.com`, Gmail sending limits in Google Workspace, read 2026-08-11); ⚠️ a trial Workspace account is 500, which is a different number on the same page and the one a fresh domain is on. Exceeding it stops that user accepting mail for up to 24 hours, so this refuses the send instead. ⚠️ The window is rolling, not a calendar day.',
            'log' => 'The ceiling applied while mail is being written to storage/logs rather than sent. ⚠️ NO VENDOR LIMIT EXISTS ON THIS TRANSPORT — nothing is handed to anybody — so this figure is not a fact about a provider; it keeps the meter, the reserve and the alert exercisable on the transport `.env.example` ships, which is the state of every deployment that has not activated SES.',
            'array' => 'The ceiling applied on the in-memory transport the test suite runs on. ⚠️ NO VENDOR LIMIT EXISTS ON THIS TRANSPORT, for `log`\'s reason.',
        ];

        $seeds = [
            'gmail' => 2000,
            'log' => 2000,
            'array' => 2000,
        ];

        $rows = [];

        foreach (MailDrivers::MAILERS as $mailer) {
            // ⚠️ **A MAILER WITH NO SEED IS SKIPPED HERE AND DECLARED IN
            // {@see self::declaredWithoutSeed()} INSTEAD**, which is where the
            // Ops editor groups it under *"Set by an operator only"* with the
            // reason it is empty. `MailTest`'s *"every mailer this application
            // can be pointed at has a declared sending ceiling"* is what stops
            // a mailer falling between the two lists.
            //
            // ⚠️ **`$descriptions` IS INDEXED WITHOUT A FALLBACK ON PURPOSE.** A
            // `??` here was dead code and Larastan said so: the two arrays are
            // keyed alike. A default sentence would be worse than the error it
            // silenced, because it would let a seeded mailer reach an operator's
            // screen described by prose nobody wrote.
            if (! isset($seeds[$mailer], $descriptions[$mailer])) {
                continue;
            }

            $rows[MailQuota::ceilingKeyFor($mailer)] = [
                'seed' => $seeds[$mailer],
                'group' => 'Messaging',
                'description' => $descriptions[$mailer],
            ];
        }

        return $rows;
    }

    /**
     * Per-plan seeds → `plan_entitlements`, keyed by plan then by key.
     *
     * ⚠️ **EVERY FIGURE HERE COMES FROM `CLAUDE.md`'s COMMERCIAL-MODEL TABLE AND
     * NOWHERE ELSE**, and `ArchitectureTest` re-derives them from that table on
     * every run. The cents are `17999`, `9999`, `99700`, `49900` — the owner's
     * figures of 2026-08-11 (decisions 2053, 2054), superseding 146's `$199.99`
     * monthly and its `$497` annual add-on, which had itself corrected decision
     * 96's `$499.99`. `$997` and `$499` are the owner's own figures and are *not*
     * 5× their monthly lines; do not "correct" them toward one.
     *
     * ⚠️ **THE ANNUAL ADD-ON MOVING BACK TO `$499` LOOKS LIKE 146 BEING UNDONE
     * BY ACCIDENT AND IS NOT.** It is corroborated by the owner's own instalment
     * figure: $166.33 × 3 = $498.99, which rounds to $499 and cannot be reached
     * from $497. See 2054, which also records that the two dollars were flagged
     * for confirmation rather than assumed.
     *
     * ⚠️ **THE THREE-INSTALMENT ANNUAL OPTION IS NOT SEEDED HERE** (2055). It is
     * derived from these annual figures, with the remainder on the final payment
     * — 33233 / 33233 / 33234 — because two seeded numbers that must sum to a
     * third is decision 754's trap, and 3 × 33233 is a cent short of 99700.
     *
     * ⚠️ **A SEED IS NOT A REPRICE.** `DefaultsRegistry` keeps an operator-set
     * value over a seed, so an installation that already holds `19999` keeps
     * holding it until somebody changes it in Ops. This governs new installs.
     *
     * ⚠️ **`Plan::Limited` HAS NO ROWS HERE ON PURPOSE.** See {@see
     * self::withheld()}.
     *
     * ⚠️ **THE MONTHLY CREDIT ALLOTMENT IS SEEDED ON `Plan::Base` ONLY, AND THE
     * ABSENCE ON THE OTHER TWO IS THE SAME REFUSAL AS `Plan::Limited`'s** (3321).
     * 3298 gives one list of figures and does not say what a Free or a Limited
     * account is granted. Seeding `0` for Free would read as a ruling that Free
     * grants nothing, which nobody has made — the ladder itself is an open
     * conversation (2067) — and seeding 500 would give the free tier the paid
     * tier's allotment. So there are no rows, `entitlement()` raises for those
     * plans, and the raise is the correct answer until somebody rules.
     *
     * ⚠️ **A SECOND CLAUDE.md TABLE IS NOW A FIXTURE TOO.** The credits and
     * allotment table in that file's commercial-model section is parsed and
     * compared against these figures and the `credits.*` settings, the same way
     * the price table always has been.
     *
     * ⛔ **THE FOUR `cost_cap.*` SEEDS THAT LIVED HERE ARE GONE** (decision 3364,
     * on 3293 and 3295). The owner deleted the per-tenant dollar cost cap
     * outright — *"delete caps follow credit amounts"* — which overrides `29` §2
     * rule 43, one of the 48, and supersedes decisions 150, 151, 152 and 156's
     * $2/month. **All four were correct values with no reader anywhere in
     * `app/`** (3106), so removing them changes no behaviour: there was never a
     * ceiling here to open. What replaces them is the credit balance, which is
     * the ceiling a tenant can see, query and be told about. ⚠️ **What survives
     * of rule 43 is the discipline and not the figure** (3294): graceful
     * degradation, never hard-fail, never bill by surprise. ⚠️ **The family's
     * fifth member is withheld rather than seeded and stays where it is** — see
     * {@see self::withheld()} and 3365. ⛔ **Removing a seeded key does not move a
     * database that has already been seeded** (3271, restated at 3295) — an
     * installation carrying these rows keeps them until somebody deletes them,
     * and that is a production step rather than a consequence of this file.
     *
     * @return array<string, array<string, array{seed: mixed, description: string}>>
     */
    public static function entitlements(): array
    {
        return [
            Plan::Free->value => [
                'price.monthly_cents' => [
                    'seed' => 0,
                    'description' => 'Free is $0 (decisions 154–156).',
                ],
                'price.annual_cents' => [
                    'seed' => 0,
                    'description' => 'Free is $0 (decisions 154–156).',
                ],
            ],

            /*
             * Plan::Limited is absent. Not an oversight — see withheld().
             */

            Plan::Base->value => [
                /*
                 * ═══ THE MONTHLY ALLOTMENT (3298, confirming 2060) ═══
                 *
                 * ⚠️ **A PLAN ENTITLEMENT RATHER THAN A PLATFORM SETTING, AND
                 * THE REASON IS NOT TIDINESS.** What a plan grants is precisely
                 * what `plan_entitlements` versions, so a tenant who signed up
                 * under a 500-message allotment keeps it when the platform
                 * default moves — 507's grandfathering, applied to credits
                 * instead of to a price. A platform setting could not express
                 * that at all.
                 *
                 * ⚠️ **GRANTED PER ACCOUNT, NOT PER LOCATION.** A tenant with
                 * six locations gets 500 SMS, not 3,000. The owner's list says
                 * "per account" and the additional-location line is a price, not
                 * a multiplier on the grant.
                 *
                 * ⚠️ **RESET AT THE PERIOD BOUNDARY — WHICH IS `billing.cycle_days`
                 * AND IS NOT A CALENDAR MONTH.** The owner wrote "reset each
                 * month" and decision 147 bills every 30 days, and those differ:
                 * 12.17 billing periods a year against 12 calendar months. The
                 * reading taken is the billing period, because a grant is what
                 * the charge buys and the two should not drift apart — but it is
                 * a reading, flagged rather than settled (3330).
                 *
                 * ⛔ **THE AI HALF IS WITHHELD, NOT ABSENT.** See withheld().
                 */
                'credits.monthly_grant.sms' => [
                    'seed' => 500,
                    'description' => 'SMS credits granted at each period boundary (decision 3298, confirming 2060). One shared balance across review invites, missed-call text-back and the chat bot (2066) — per-feature quotas are the larger support surface. ⛔ SMS broadcasting may never spend this pool and must use purchased credit (3309); it also needs the tenant\'s own 10DLC brand and number (3310). Granted credits expire at the boundary; purchased ones do not, and a spend draws this pool first (3307).',
                ],
                'credits.monthly_grant.emails' => [
                    'seed' => 1_000,
                    'description' => 'Email credits granted at each period boundary (decision 3298). New in the owner\'s 2026-08-13 reply, and stated twice over: "$20 for the account" of email credit at $20 per 1,000 is the same sentence as 1,000 emails. Granted credits expire at the boundary; purchased ones do not, and a spend draws this pool first (3307).',
                ],
                'credits.monthly_grant.ai_cents' => [
                    'seed' => 5_000,
                    'description' => 'AI credit granted at each period boundary, in integer cents of RETAIL credit — $50 (decision 9180, reversing 3412\'s $30). ⛔ THE FIGURE MOVED AND THE DENOMINATION DID NOT, AND THEY ARE TWO SEPARATE ANSWERS FROM THE SAME OWNER: 9180 sets the amount, 3412 settled what a dollar of it MEANS, and 9180 explicitly leaves the 8:1 markup alone — so this is $50 the tenant can spend at our sell rate, costing us about $6.25. ⚠️ 3412\'S ARGUMENT IS KEPT RATHER THAN REWRITTEN, because it is what stops the denomination being re-opened by the next reader of a larger number: the key was WITHHELD FOR A DAY rather than guessed, the owner had set "$30" and priced AI credit "8 to 1 what it cost us", those two sentences did not say which currency the $30 was in, and asked directly he answered "$3.75 of real spend". The alternative — provider spend — would have been about $240 of credit given away monthly on a $179.99 plan, a factor of eight on the largest variable cost in the product, settled by asking rather than by inferring (255, 277, 684, 1349). ⛔ THE SAME FACTOR APPLIES UNCHANGED TO $50 AND THE STAKE IS NOW LARGER, NOT SMALLER. ⚠️ It is cents because that is what a customer is charged (`18` §Money handling); ai_calls.retail_hundredths_cents is HUNDREDTHS of a cent because a single call costs a fraction of one, and 3331 records that reading one straight into the other under-grants by a hundred with the balance looking plausible throughout. ✅ THIS POOL IS NOW SPENT AND IT IS NOW THE CEILING: credit_ledger gained a product dimension (3419), AiSpend::record() debits it per call through AiCredits::debitForCall() (3424), and AiSpend::allows() refuses on it (3608) — so this figure bounds a tenant who HAS been granted it. ⚠️ A tenant who has never been granted any AI credit is NOT refused by it (3609): a balance of zero means "exhausted" and "never granted" alike, and 3421 records that the monthly grant had never granted anything to anybody, so gating on an unfunded zero would have stopped all AI for every tenant at once. ⛔ WHICH IS WHY ai.monthly_cap_per_tenant IS STILL HERE AND NOT GONE (3820): today every account is unfunded from registration until it confirms a Google listing and survives a daily reset, so this key is the ceiling for accounts that have a balance and that one is the ceiling for accounts that do not.',
                ],

                /*
                 * ⚠️ **A SUBSCRIPTION LINE, NOT A CREDIT** (3305), which is the
                 * only reason it needs saying: the owner's reply lists $10 a
                 * month for an extra number in among the credit figures, and it
                 * belongs to neither pool. It sits here beside the additional
                 * location price because that is the shape it actually has — a
                 * recurring per-unit add-on to the plan — and deliberately not
                 * under any `credits.` key, where a reader would eventually try
                 * to spend it.
                 */
                'additional_phone_number.monthly_cents' => [
                    'seed' => 1_000,
                    'description' => '$10/month per additional phone number (decision 3305, confirming 2062). A recurring subscription line billed with the plan — never a credit, never drawn from either pool.',
                ],

                'price.monthly_cents' => [
                    'seed' => 17_999,
                    'description' => '$179.99/month, one location included (decision 2053, superseding 146).',
                ],
                'price.annual_cents' => [
                    'seed' => 99_700,
                    'description' => '$997/year (decisions 95, 146). The owner\'s figure, not 5× the monthly line.',
                ],
                'additional_location.monthly_cents' => [
                    'seed' => 9_999,
                    'description' => '$99.99/month per additional location (decisions 96, 146).',
                ],
                'additional_location.annual_cents' => [
                    'seed' => 49_900,
                    'description' => '$499/year per additional location (decision 2054, superseding 146\'s $497). Corroborated by the owner\'s instalment figure of $166.33 x 3.',
                ],
            ],
        ];
    }

    /**
     * Keys the registry recognises and deliberately does not seed.
     *
     * Distinct from {@see self::withheld()}: nothing is undecided here. These
     * are keys whose *absence* is the correct runtime state, and they are
     * declared so that `DefaultsRegistry` can tell "an operator has not set this"
     * apart from "somebody typed the key wrong", which is the difference between
     * a fallback and a silent no-op.
     *
     * ⚠️ **`mail.postal_address` IS THE ONE MEMBER WHOSE ABSENCE IS *NOT*
     * CORRECT, AND IT IS HERE ANYWAY — SEE ITS OWN NOTE** (T176 P21, decision
     * 4019). Read that before adding a second key of that shape.
     *
     * @return array<string, string> key => why it carries no seed
     */
    public static function declaredWithoutSeed(): array
    {
        $keys = [
            'public_audit.daily_spend_ceiling_cents' => 'Derived, not configured: the dollar ceiling follows from the audit budget times what an audit costs. An audit budget and a dollar ceiling that disagree is a trap — whichever is looser is the real policy and nobody would know which. Seeding a number here would create exactly that disagreement the first time either moves. It exists as a key so an operator can override the derivation on the day a field mask changes and it stops matching reality.',

            /*
             * GO AI EZ's own physical mailing address — CAN-SPAM
             * §7704(a)(5)(A)(iii), which requires *"a valid physical postal
             * address of the sender"* on every commercial email (T176 P21).
             *
             * ⛔ **NO SEED, BECAUSE EVERY POSSIBLE SEED IS A FALSE STATEMENT.**
             * `CLAUDE.md`'s rule for this file is that the seed is the
             * fail-closed value and the conservative one; for an address there
             * is no conservative value. A placeholder would be a lie printed
             * inside the very disclosure it is there to satisfy, and — worse —
             * it would look answered, so nobody would look again.
             *
             * ⚠️ **AND UNLIKE THE OTHER MEMBERS OF THIS LIST, ITS ABSENCE IS
             * NOT THE CORRECT RUNTIME STATE.** The two keys above are
             * deliberately empty for ever; this one is empty until the owner
             * supplies it, and while it is empty **no commercial email can be
             * sent at all** — `CanSpamFooters::postalAddress()` throws
             * `MailNotDeliverable::noPostalAddress()` and
             * `PlatformMailer::sendToCustomer()` asks before it queues
             * anything, so the caller's transaction rolls back rather than
             * leaving a row that says `Queued` for ever.
             *
             * ⚠️ **`withheld()` WAS THE OBVIOUS HOME AND IT WAS REFUSED, WITH
             * THE REASON RECORDED** (decision 4019). Withheld is checked
             * *before* the stored row on purpose — a figure somebody typed in
             * is exactly the guess the manifest exists to refuse — so a
             * withheld key cannot be supplied by an Ops edit at all, only by
             * moving it in this file. That is right for a price, where a typed
             * number is indistinguishable from a decision. It is wrong for an
             * address, which nobody can guess or derive: typing it *is* the
             * owner supplying it, and there is nothing for a reviewer to
             * second-guess. What withheld buys — a refusal that names the
             * decision — is kept, and it is kept at the send where it can
             * actually stop a message.
             */
            /*
             * The operator attestation that gates call recording (4505).
             *
             * ⛔ **NO SEED, AND ITS ABSENCE *IS* THE CORRECT RESTING STATE —
             * WHICH IS THE OPPOSITE OF THE KEY BELOW.** A seeded attestation
             * would be this application asserting, on nobody's behalf, that a
             * clip nobody uploaded is playing to callers who have not heard it.
             * That is not a conservative default; it is the false statement the
             * whole mechanism exists to prevent, pre-signed.
             *
             * ⚠️ **AND IT IS NOT AN OPS FIGURE**, though it lives in an
             * Ops-editable store. `voice:announcement-attestation record` is the
             * writer — it shows an operator the published wording, composes the
             * clip through `VoiceGreeting` and refuses a superseded version —
             * and a blob typed into this row by hand that does not reconstruct
             * reads as *no attestation at all* rather than as a valid one.
             */
            'voice.recording_announcement_attestation' => 'The operator attestation that the recording announcement clip is configured as the first thing every caller hears, before they can speak (decision 4505, `29` §2 and 2104). ⛔ No seed: an attestation nobody made is the false statement this exists to prevent. While it is empty, `voice.enabled` cannot be turned on and no recording is stored — which is the correct resting state. Written by `php artisan voice:announcement-attestation record` and never by hand; who attested what, and when, is in `registry_changes`.',

            /*
             * The `smtp` mailer's 24-hour sending ceiling — 4456's owed fix
             * (4603), and the one member of the family with no seed (4604).
             *
             * ⛔ **ITS ABSENCE IS NOT A CORRECT RESTING STATE, WHICH IT SHARES
             * WITH `mail.postal_address` BELOW AND WITH NOTHING ELSE HERE.**
             * While it is empty the `smtp` mailer sends **nothing** —
             * `MailQuota::ceiling()` answers null, `hasHeadroom()` is false, and
             * `PlatformMailer` refuses with a message naming this key. That is
             * the fail-closed direction on a limit: the alternative is sending
             * ten times what a sandboxed SES account will accept.
             *
             * ⚠️ **`withheld()` WAS THE OBVIOUS HOME AND IS WRONG FOR THE SAME
             * REASON IT IS WRONG FOR AN ADDRESS** (4019). Withheld is checked
             * *before* the stored row, so a withheld key cannot be supplied
             * through Ops at all — only by editing this file. For a price that
             * is right, because a typed number is indistinguishable from a
             * decision. For an account quota AWS granted last Tuesday, typing it
             * **is** the operator supplying it, and 4432 already says setting it
             * is part of activating SES.
             */
            MailQuota::ceilingKeyFor('smtp') => 'How many emails the `smtp` sending account may send in a rolling 24 hours (decisions 4456, 4603, 4604). ⛔ No seed, and while it is empty this mailer sends nothing at all. SES is reached as plain smtp (1191, R16) and its quota is an account fact this codebase cannot know: a fresh account is 200 per 24 hours, with a sending rate of 1 per second, until AWS grants production access — after which it is whatever was granted (`docs.aws.amazon.com/general/latest/gr/ses.html`, §Service quotas, read 2026-08-16). Borrowing Google\'s 2,000 was a 10× over-ceiling, and seeding 200 would encode the sandbox figure as though somebody had decided it. Set this to the account\'s granted 24-hour quota in the same change as the SES credentials. ⚠️ It cannot express the per-second rate at all: nothing here throttles the queue, and SES answers an over-rate send with a per-message Throttling error rather than Google\'s 24-hour lockout.',

            'mail.postal_address' => 'GO AI EZ\'s own physical mailing address, printed in the footer of every commercial email (CAN-SPAM §7704(a)(5)(A)(iii), decision 4019, T176 P21). ⛔ No seed and no default: for an address there is no conservative value, and a placeholder would be a false statement inside the disclosure it satisfies. ⚠️ Unlike the other keys in this group, its absence is NOT a correct resting state — while it is empty every commercial send is refused rather than going out without it. Set it to the full mailing address, one line per line.',

            /*
             * The number the six demo doors tell a stranger to text — CC-2 §2.7,
             * decision 5195.
             *
             * ⛔ **NO SEED, FOR `mail.postal_address`'s REASON EXACTLY** (4019): a
             * phone number cannot be guessed or derived, so every candidate seed
             * is a false statement printed on a public page — and worse, one that
             * *looks* answered, so nobody looks again. The owner's own close-out
             * carries `[DEMO-NUMBER]` as an open item; this is that item, in the
             * one place a reader will meet it.
             *
             * ⚠️ **`withheld()` IS THE WRONG HOME AND THAT IS THE SAME ARGUMENT
             * AGAIN.** Withheld is checked *before* the stored row, so a withheld
             * key can never be supplied through Ops. For a price that is right,
             * because a typed number is indistinguishable from a decision. For a
             * line somebody provisions, typing it **is** the owner supplying it.
             *
             * ⚠️ **ITS ABSENCE *IS* THE CORRECT RESTING STATE**, unlike the two
             * keys above it. Empty, the doors still render — the family, the
             * sample chrome, the honesty footer — and the one block that needs a
             * number does not. A door that says "text us" with no number is the
             * placeholder this rule exists to refuse.
             *
             * ⛔ **AND SETTING IT IS WHAT MAKES THE PROMISE.** Nothing routes a
             * demo keyword to anything: `InboundKeyword` files every word but
             * STOP, HELP and START as `None`. So the row is also the switch that
             * starts telling members of the public to text a line, and the
             * responder has to exist by then.
             */
            'demo.number' => 'The phone number printed on the six /demo/{family} doors, in the form a person reads it (decision 5195, CC-2 §2.7). ⛔ No seed: a number cannot be guessed, and a placeholder on a public page is a false statement that looks answered. ⚠️ While it is empty the doors render without their "text this word to this number" block — deliberately, and not as a degraded state. ⛔ SETTING IT IS A PROMISE THAT SOMETHING ANSWERS: nothing in the application routes `demo.keyword.*` to a demo tenant yet, so the responder must be live before this row is. The keywords themselves are seeded under `demo.keyword.*`.',

            /*
             * The written guarantee sentence lived here, withheld, until the W33
             * composition — CC-2 §2.5, CC-4 §2, decisions 5196 and 5283.
             *
             * ⛔ **BOTH READINGS ARE KEPT AND DATED, BECAUSE EACH WAS RIGHT ABOUT
             * ITS OWN TREE.** CC-2 declared it here with the argument *"no seed,
             * because this codebase may not draft a guarantee — a plausible
             * sentence would be a promise nobody made, published, in the owner's
             * name"*, which is the price-withholding rule applied to a legal
             * instrument, and it was correct while the words did not exist.
             *
             * ✅ **THE WORDS EXIST AND NOTHING DRAFTED THEM.** CC-4 transcribed
             * the written guarantee sentence and verified it against **eight** artefacts in the
             * 2026-08-18 drop rather than against a summary, so the premise the
             * withholding rested on is satisfied rather than overruled. The row
             * is seeded in `settings()` above, and it is the **same key**: every
             * reader, every byte-match test and the one-source lint are unmoved.
             *
             * ⛔ **A KEY IN BOTH LISTS IS A CONTRADICTION THE MANIFEST CANNOT
             * EXPRESS, AND THAT IS WHY THIS ENTRY IS GONE RATHER THAN COMMENTED
             * OUT.** For one merge it was seeded *and* declared unseedable —
             * `MarketingTest` caught it, which is the whole reason that lint
             * asserts both halves rather than only the one it cares about.
             *
             * ⚠️ **WHAT IS STILL OPEN IS THE WORDING, NOT THE SEEDING** (5170):
             * the marketing set says *"extend your **trial**"* and R53's Terms §9
             * says *"extend your **service**"*. That is counsel's to settle, and
             * settling it is a text edit to a registry row rather than a release.
             */
        ];

        /*
         * The per-second sending rate, one row per mailer (4648).
         *
         * ⛔ **EVERY MAILER IS HERE AND NOT ONE OF THEM HAS A SEED, WHICH IS THE
         * OPPOSITE OF THE CEILING FAMILY ABOVE AND IS DELIBERATE.** 4432 named
         * this limit and did not build it: a per-second rate is not a 24-hour
         * ceiling and the family above cannot express one. There is
         * no figure this codebase can honestly state for any mailer — SES's
         * `1 per second` is the **sandbox** rate and is wrong the moment
         * production access lands, an `smtp` mailer need not be SES at all,
         * Google publishes no per-second figure for Workspace, and `log` and
         * `array` hand nothing to anybody.
         *
         * ⚠️ **AND THE EMPTY STATE IS *OPEN* HERE WHERE IT IS *CLOSED* ON THE
         * CEILING** (4604 against 4649). An unstated ceiling refuses every send,
         * because exceeding a 24-hour quota costs a lockout of up to a day. An
         * unstated rate paces nothing, because AWS answers an over-rate send
         * with a per-message `454 Throttling failure: Maximum sending rate
         * exceeded` — transient by definition, and already absorbed by the
         * queue's retry. Refusing all mail to avoid a recoverable per-message
         * error is the hard-fail rule 43's surviving half forbids.
         *
         * ⚠️ **SO THESE ROWS ARE INERT UNTIL AN OPERATOR TYPES A NUMBER**, which
         * is 272's shape and is answered the way 4604 answered it for the
         * ceiling: by making the row **visible** rather than remembered.
         * `Admin\MailSending` shows it beside the ceiling, and `.env.example`'s
         * SES block names it in the activation sequence.
         */
        foreach (MailDrivers::MAILERS as $mailer) {
            $keys[MailSendRate::rateKeyFor($mailer)] = 'How many emails a second the `'.$mailer.'` sending account may be handed (decisions 4432, 4648). ⛔ No seed on any mailer, and while it is empty nothing is paced — which is this application\'s behaviour today and is the correct open state, because an over-rate send is answered with a transient per-message error the queue retry absorbs and refusing mail to avoid one would cost more than the error. ⚠️ Unlike the 24-hour sending ceiling, an empty row here does NOT stop sending. A fresh SES account is granted 1 per second until production access lands, after which it is whatever was granted (`docs.aws.amazon.com/general/latest/gr/ses.html`, §Service quotas, read 2026-08-17); Google publishes no per-second figure for Workspace. ⚠️ Setting it paces the queue: a send that would exceed the rate waits up to 5 seconds and is then handed over regardless, because SES tolerates short bursts and states that the rate it accepts may itself be lower than the one granted.';
        }

        /*
         * The model serving each AI task. `CLAUDE.md` requires an
         * "admin-editable model router" and AiTask::defaultModel() already holds
         * the fallback, priced in AiModel. Seeding a model id here would make
         * two sources of truth for it — and the enum is the one that carries the
         * price the monthly cap is checked against, so the enum has to win.
         */
        foreach (AiTask::cases() as $task) {
            $keys[$task->settingKey()] = 'The model router\'s per-task override (decision 280). Unset means AiTask::defaultModel() applies, which is where the priced default belongs — AiModel carries the prices the monthly cap is checked against, so a seed here would be a second source of truth for a figure that decides a bill.';
        }

        /*
         * How long each kind of stored object is kept, in days — decisions
         * 4940–4945, answering 4768.
         *
         * ⛔ **NO SEED ON ANY OF THEM, AND THE EMPTY STATE IS *OPEN* WHERE THE
         * MAIL CEILING'S IS *CLOSED* — WHICH IS THE MOST IMPORTANT SENTENCE IN
         * THIS BLOCK** (4942). An unstated mail ceiling refuses every send,
         * because the conservative direction on a *limit* is to send nothing.
         * The conservative direction on a *deletion schedule* is the exact
         * reverse: an unstated period must delete **nothing**. A pruner reading
         * a missing period as zero would delete every object of that kind on its
         * first scheduled run — irreversible destruction of other people's
         * photographs, voicemails and documents, executed by a cron entry,
         * against a green suite. `StorageRetention::periodFor()` answers `null`
         * here and `storage:prune` treats `null` as *skip this kind entirely*;
         * `tests/Feature/Storage/StorageRetentionTest.php` drives that case red
         * by mutation rather than trusting it.
         *
         * ⛔ **A PERIOD IS THE OWNER'S RULING AND MAY NOT BE GUESSED — BUT
         * `withheld()` IS THE WRONG HOME, FOR 4019's AND 4604's REASON.**
         * Withheld is checked *before* the stored row, so a withheld key cannot
         * be supplied through Ops at all, only by editing this file. That is
         * right for a price, where a typed number is indistinguishable from a
         * decision. It is wrong here for a subtler reason than it was for an
         * address: a retention period will be ruled on once per kind, possibly
         * in stages, and the ruling has to be actionable the day it is made
         * rather than after a deploy. What withheld buys — a refusal that names
         * the gap — is kept, and it is kept where it can be acted on:
         * `storage:prune` prints every unset key on every run, so the gap is
         * visible rather than remembered.
         *
         * ⚠️ **ONE KEY PER KIND, NOT ONE KEY** (4943). Voicemail audio, a
         * customer's inbound photograph, a document the owner uploaded and a
         * rendered campaign image are four different arguments; 4761 already
         * found the five kinds unalike enough that a single *ceiling* was wrong
         * on four of them, and a single *period* would force the shortest
         * defensible answer onto every kind or the longest onto all of them.
         *
         * ⚠️ **`Export` IS ABSENT AND THAT IS THE RULING, NOT AN OMISSION**
         * (4941) — see {@see StoredObjectKind::retentionKey()}. Its seven days
         * are published in the Terms and fixed in `ExportBuilder`; an Ops row
         * could only shorten a promise somebody was already given.
         */
        foreach (StoredObjectKind::cases() as $kind) {
            $key = $kind->retentionKey();

            if ($key === null) {
                continue;
            }

            $keys[$key] = 'How many days '.lcfirst($kind->label()).' are kept before the object is deleted (decisions 4768, 4940–4945). ⛔ No seed, and AN EMPTY ROW DELETES NOTHING — a missing period is not zero days, and `storage:prune` skips this kind entirely until a number is here. ⚠️ The number is the owner\'s ruling and may not be guessed by an operator or by this file: the drafted privacy policy commits to a period for pre-signup audits and to nothing about this kind, and `docs/LEGAL-DRAFTS-V1.md` lists "retention periods beyond the two the code enforces" as counsel\'s to settle. ⚠️ Setting it starts deletion on the next scheduled run and applies to objects already older than the period, so the first run after a period is set is the largest one this application will ever make. ⚠️ It does not delete the row: the record that the object existed survives, its size column goes null, and the footprint falls with it.';
        }

        /*
         * ⛔ **`automation_runs`' OWN PERIOD, ~219 ROWS PER SINGLE-LOCATION
         * TENANT PER DAY FROM THE CLOCK ALONE — decisions 10184, 10185, 10260.**
         * `storage.retention_days.*`'s precedent for that reason. The owner
         * has never stated how long this platform keeps a record of what it
         * did, and this file may not guess one: the number decides how much of
         * `Account\ReplyQueue`, Home and the Google reviews screen's history
         * remains readable, which is a decision about the product's own
         * accountability record, not a disk-space setting.
         *
         * ⛔ **NO SEED, AND AN EMPTY ROW DELETES NOTHING** — `intOr()`'s `0`
         * fallback, read the same way `StorageRetention::periodFor()` reads it:
         * a `0` or unset key means "keep everything", never "delete
         * everything", because the conservative direction on a deletion
         * schedule is to delete less rather than more.
         * `App\Services\Automation\AutomationRunRetention::retentionDays()`
         * answers null and `automation:prune-runs` opens no query at all —
         * `tests/Feature/Automation/AutomationRunRetentionTest.php` drives that
         * arm red by mutation rather than trusting it.
         *
         * ⚠️ **ONE KEY, NOT ONE PER AUTOMATION.** `automation_runs` carries 142
         * different automations under one schema and there is no reader that
         * needs a shorter window for one kind and a longer one for another —
         * unlike `storage.retention_days.*`, where four unlike kinds of
         * evidence (a photograph, a document, a voicemail, an ad image) argued
         * for four different periods. A single figure is what an operator
         * actually has an opinion about here: how long this platform's own
         * accountability record stays queryable.
         *
         * ⚠️ **SETTING IT DOES NOT MAKE `NeverRead`/`NeverChecked` GO STALE.**
         * `AutomationRunRetention::prune()` keeps the newest terminal run per
         * (`business_id`, `location_id`, `automation_key`) for ever, by the
         * survivor rule 9934 and wave 34 (10184, 10185) already argued —
         * `VisibilitySyncHistory`'s three readers ask for exactly that row, and
         * `AutomationRunHorizonTest` fails the build if that stops being true.
         */
        $keys['automation.retention_days'] = 'How many days a finished automation-run row is kept before it is deleted (decisions 10184, 10185, 10260). ⛔ No seed, and AN EMPTY ROW DELETES NOTHING — a missing period is not zero days, and `automation:prune-runs` opens no query at all until a number is here. ⚠️ The number is the owner\'s ruling and may not be guessed: this table is the only exhaustive record of what every automation did, and setting a period trades that record for less disk. ⚠️ The newest terminal run of every (location, automation) pair is kept regardless of this figure — the survivor rule that keeps "we have never read your Google listing" honest once a period is finally set.';

        /*
         * ⛔ **`fetch_attempts` — DECISION 10189(f), CARRIED FORWARD RATHER
         * THAN OVERTURNED — decisions 10184, 10189, 10260–10269.** Wave 34
         * called this table the easy case: every reader is windowed
         * (`subMinute`, `subDay`, `subDays(7)`, the `coolingDown` scope), it
         * carries no tenant column and no personal data by construction — a
         * row is a fetch-source key, a SHA-256 of a URL, a tier, an outcome
         * and a timestamp — and nothing depends on a row's absence the way
         * `VisibilitySyncHistory` depends on `automation_runs`' presence. ⛔
         * **AND WAVE 34 STILL LEFT THE PERIOD UNSET RATHER THAN PICKING ONE**,
         * on the same ground as `automation_runs`: an outbound-fetch audit
         * trail beyond what any reader needs is a real (if small) capability
         * — answering "did we scrape Yelp on Tuesday" a week later — and this
         * file may not decide how long that capability lasts on the owner's
         * behalf, even though nothing here is personal data. `PrunePlatformMailSends`
         * chose 30 unilaterally for a table that also carries no PII and no
         * tenant; the difference is that table also carries no legitimate
         * externally-facing question anybody could ask about it, where "do you
         * scrape this site" is exactly the audit question this table's own
         * creating migration says a refusal row exists to answer.
         */
        $keys['fetch.attempts_retention_days'] = 'How many days a fetch_attempts row (a source key, a URL hash, a tier, an outcome — never the URL itself) is kept before it is deleted (decisions 10189(f), 10260–10269). ⛔ No seed, and AN EMPTY ROW DELETES NOTHING — a missing period is not zero days, and `fetch:prune-attempts` opens no query at all until a number is here. ⚠️ No reader looks back further than seven days, so any period an operator sets beyond that changes nothing any cool-down, rate budget or block-rate panel can see — the only thing it changes is how long an audit question like "did we scrape this site last week" stays answerable.';

        /*
         * ⛔ **`google_rating_snapshots`' OWN PERIOD — wave 38 lane D, on
         * `automation.retention_days`'s exact ground.** This table is the
         * only history `App\Services\Visibility\ReviewLossDetection` has: how
         * long this platform keeps a record of a tenant's own Google review
         * count is a decision about the product's accountability record for
         * itself, not a disk-space setting, and this file may not guess one.
         *
         * ⛔ **NO SEED, AND AN EMPTY ROW DELETES NOTHING** — `intOr()`'s `0`
         * fallback, `AutomationRunRetention::retentionDays()`'s identical
         * reading: an unset key means "keep everything", never "delete
         * everything".
         * `App\Services\Visibility\GoogleRatingSnapshotRetention::retentionDays()`
         * answers null and `review-loss:prune-snapshots` opens no query at
         * all.
         *
         * ⚠️ **AND UNLIKE ITS SIBLINGS ABOVE, A STATED PERIOD IS ALSO
         * FLOOR-CLAMPED**, never taken as written: it cannot be set shorter
         * than `review_loss.pause_flat_days` plus a seven-day buffer, because
         * pause detection reads back that much of this table's own history on
         * every evaluation, and a shorter period would silently make it
         * permanently unreachable.
         */
        /*
         * ⛔ **THE OWNER CHANNEL'S OWN PERIOD — wave 40 lane A (10834), and
         * the first key in this group whose subject is A PERSON'S OWN FREE
         * TEXT.** `owner_replies.body` is what a business's account holder
         * typed back to us, and until this key existed it was kept for ever:
         * fourteen `Prune*` commands and not one of them touched the table,
         * while that table's own creating migration cited *"a retention
         * policy to apply to it"* as part of its justification for existing.
         *
         * ⛔ **NO SEED, AND AN EMPTY ROW DELETES NOTHING** — `intOr()`'s `0`
         * fallback and `owner-channel:prune` opening no query at all, exactly
         * as its three siblings above do. ⚠️ **The reason is
         * `storage.retention_days.*`'s STATED one rather than habit**: the
         * drafted privacy policy commits to a period for pre-signup audits
         * and to nothing about this kind, and `docs/LEGAL-DRAFTS-V1.md` lists
         * retention periods beyond the two the code enforces as counsel's to
         * settle. A plausible figure invented in this file would look exactly
         * like a decision the moment it was read back out of the database.
         *
         * ⚠️ **ONE KEY FOR TWO TABLES, DELIBERATELY.** `owner_notifications`
         * exists so a stored reply has something it can be an answer to, so
         * sweeping one half and keeping the other leaves records that read as
         * a reply to nothing. One number is also one decision for the owner
         * rather than two, and a second knob nobody sets is a second
         * permanent no-op.
         */
        $keys['owner_channel.retention_days'] = 'How many days an owner-channel row — what the account holder texted back (`owner_replies`) and the record that we texted them (`owner_notifications`) — is kept before it is deleted (wave 40 lane A, decision 10834). ⛔ No seed, and AN EMPTY ROW DELETES NOTHING — a missing period is not zero days, and `owner-channel:prune` opens no query at all until a number is here. ⚠️ This is the only key in this group whose subject is a named person\'s own free text, and the period is still the owner\'s and counsel\'s rather than this file\'s, for `storage.retention_days.*`\'s stated reason. ⚠️ One number covers both tables: they are one conversation, and keeping half of it leaves a record that reads as a reply to nothing. ⚠️ A row whose `created_at` is null is kept regardless — a row we cannot date is one we cannot prove is past the period.';

        $keys['review_loss.snapshot_retention_days'] = 'How many days a google_rating_snapshots row is kept before it is deleted (wave 38 lane D). ⛔ No seed, and AN EMPTY ROW DELETES NOTHING — a missing period is not zero days, and `review-loss:prune-snapshots` opens no query at all until a number is here. ⚠️ A stated period is floor-clamped to review_loss.pause_flat_days plus seven days: shorter than that would silently make pause detection unreachable, because it reads back that much of this table\'s own history on every evaluation.';

        /*
         * ⛔ **THE TWO SENTENCES COUNSEL OWNS — L-3's REVIEW-INVITE FOOTER AND
         * THE WRITTEN GUARANTEE** (decision 5251, CC-5 §3 and §2).
         *
         * `mail.postal_address`'s shape (4019) and its reason, doubled: an
         * unstated legal sentence has no conservative value, only an invented
         * one, and an invented one is worse than a refusal because it looks
         * answered. So both fail closed — `LegalCanon` refuses, the composer
         * that needed the sentence refuses, and nothing is sent.
         *
         * ⚠️ **THE SLOT IS DECLARED HERE AND THE WORDS ARE NOT SET HERE.**
         * `DefaultsRegistry` cannot read a key this manifest has never heard
         * of, so a lane binding to a key name needs the declaration to exist
         * before the read can even fail honestly. What is declared is that the
         * key is real and carries no seed; the wording is counsel's, supplied
         * by an operator or by CC-4's own seed.
         *
         * ⚠️ **GUARDED SO THAT CC-4's DECLARATION WINS.** CC-5 and CC-4 are
         * parallel lanes and both name these keys. If CC-4 has landed — as a
         * seeded setting or as its own entry here — that entry is left exactly
         * as it is and this block adds nothing. A key declared twice would
         * otherwise reach `grouped()` twice and redden
         * `DefaultsRegistryTest`'s *"groups every declared key for the settings
         * editor"*, which is the right way for a merge collision to surface and
         * the wrong way to discover it.
         *
         * ⚠️ **THIS QUOTED *"every declared key reaches the screen"* UNTIL
         * 2026-08-25 AND NO TEST OF THAT NAME HAS EVER EXISTED** (9780). The
         * claim is real and held across **two** files, which is why the
         * invented name was plausible: the grouping is the case above, and the
         * rendering is `PlatformSettingsAdminTest`'s *"the screen shows every
         * declared key, its default, and whether it moved"*. **The mechanism
         * this paragraph describes is the first one**, so that is the one
         * quoted; the second is named because a reader following the old words
         * was looking for it.
         */
        $settings = self::settings();

        foreach (LegalCanon::declarations() as $key => $why) {
            if (array_key_exists($key, $keys) || array_key_exists($key, $settings)) {
                continue;
            }

            $keys[$key] = $why;
        }

        return $keys;
    }

    /**
     * Numbers the owner has not set, which may not be guessed.
     *
     * ⚠️ **THIS IS THE POINT OF THE WHOLE FILE.** `CLAUDE.md`: *"One number is
     * still unset and may not be guessed … It fails closed"* — and the count is
     * {@see self::withheld()}'s to state rather than any sentence's, which is
     * what `CLAUDE.md` itself now says. A seed manifest is the
     * worst possible place for a plausible figure, because a number read back
     * out of the database has lost every trace of having been invented — it
     * looks exactly like a decision. So these are named, and asking for one
     * raises an exception quoting the decision that left it open.
     *
     * @return array<string, string> registry path => why it is withheld
     */
    public static function withheld(): array
    {
        /*
         * This list is empty (run 84, ruling R245).
         * A key enters this list only with a ruling that names what is missing.
         */
        return [];
    }
}
