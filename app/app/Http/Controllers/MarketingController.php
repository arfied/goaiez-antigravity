<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DemoFamily;
use App\Enums\MarketingCapability;
use App\Enums\Plan;
use App\Enums\TermsAcceptanceMethod;
use App\Exceptions\AmbiguousPlanOffer;
use App\Exceptions\WithheldRegistryValue;
use App\Models\PublicAudit;
use App\Services\Billing\PlanCharges;
use App\Services\Config\DefaultsRegistry;
use App\Services\Legal\SignupTerms;
use App\Support\Money;
use App\Support\PlanPricing;
use App\Support\PlatformCredentials;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The signed-out marketing surface (`29` §6.1, §6.2).
 *
 * Deliberately not Livewire, and this is the one place in the application where
 * that is true. Every other screen is a Livewire component because it has
 * server-held state worth a round trip; these pages have none — the audit talks
 * to the public JSON API of slice F, which was built for exactly this — and
 * `29` §11.2 row 1 gates the home on LCP under 1.5 seconds. Shipping a
 * component runtime to a page with no component is the cheapest way to lose
 * that budget (decision 259).
 */
final class MarketingController extends Controller
{
    /**
     * The home page. The audit is the hero (`29` §6.1).
     */
    public function home(DefaultsRegistry $registry, PlanCharges $charges): View
    {
        ['rates' => $rates] = $this->quotedRates($charges);

        // ⚠️ **TWO KEYS IN ONE QUERY, AND THAT IS ROW 1's BUDGET RATHER THAN
        // TIDINESS** (decision 5213). CC-2 gave this page a second
        // platform-setting read, and `DefaultsRegistryTest`'s query lint moves its
        // number only by the query a feature *genuinely* adds — two keys on one
        // tiny table do not. `values()` applies the identical withheld and
        // declared checks `value()` does, key by key.
        $settings = $registry->values(['billing.trial_days', 'features.industry_pages']);

        return view('marketing.home', [
            // Read rather than typed into the template. Doc `38` Part 2's
            // registry lint refuses a price literal in a view, and this page was
            // the only place in the codebase carrying one (decision 512). A
            // handful of small indexed reads against tiny tables; the LCP element
            // is the `<h1>` above the fold and nothing here blocks it.
            //
            // ⛔ **THROUGH `PlanCharges` AND NOT THE REGISTRY DIRECTLY SINCE T176
            // P1**, which is 512's defect one door along. `PlanCharges` is what
            // knows about the founder offer; a page reading
            // `entitlementCents(Plan::Base, …)` would quote the retail schedule
            // while every checkout on the site charged the founder rate — the
            // page that sells the plan printing a price nobody pays, with nothing
            // anywhere to notice, because a template is not where anyone looks
            // when an offer opens.
            //
            // ⚠️ **ONE CALL FOR ALL FOUR FIGURES, AND THAT IS ROW 1's LCP QUERY
            // BUDGET RATHER THAN TIDINESS** (4337). `PlanCharges` reads the
            // registry per key by design — it is the charging path and 510 keeps
            // it uncached — so four separate calls put eight extra queries on the
            // page the gate is measured against. `displayRates()` batches inside
            // one call and keeps nothing, and it resolves the live offer through
            // the same memo a checkout uses, so this page cannot quote a price
            // different from the one about to be charged.
            ...array_map(
                static fn (Money $amount): string => PlanPricing::format($amount),
                $rates,
            ),

            // Free has no offer and never will: an offer prices a plan somebody
            // pays for. It stays a plain registry read, and `entitlementCents()`
            // raises rather than printing `$0` for an unset figure (502).
            //
            // ⚠️ **THE CURRENCY COMES OFF A RATE ABOVE RATHER THAN FROM A SECOND
            // LOOKUP** (4337). `PlanPricing` was here and read `billing.currency`
            // for itself, which was the twelfth query on a page budgeted for ten —
            // one figure's worth of duplication paid for on the page carrying row
            // 1's LCP gate. Every `Money` in `$rates` already carries it.
            'free' => PlanPricing::format(Money::of(
                $registry->entitlementCents(Plan::Free, 'price.monthly_cents'),
                $rates['monthly']->currency,
            )),
            // ⚠️ Cast rather than re-read: jsonb hands back an int for a seeded
            // `14` and a string for a row somebody edited by hand, and a trial
            // length printed as `"14"` would be indistinguishable on the page.
            'trialDays' => (int) $settings['billing.trial_days'],

            // CC-2 §2.1's hundred-kinds block, behind CC-3's own flag. Dark, the
            // block does not render and neither does its link — `29` §6.1's nav
            // rule (261) reaches a call to action just as much as a menu item,
            // and a hundred-kinds promise linking to a hub with nothing in it is
            // worse than no promise.
            //
            // ⚠️ **`=== true` RATHER THAN A CAST**, which is {@see self::flag()}'s
            // rule: a row hand-edited to the string `"false"` is truthy, and
            // turning this on publishes a claim.
            'industryPages' => $settings['features.industry_pages'] === true,

            // ⛔ **READ HERE RATHER THAN IN THE TEMPLATE, AND THROUGH THE SEAM
            // RATHER THAN `config()`** (9146). The view carried
            // `config('credentials.turnstile_site_key')` inline — the only
            // `config('credentials.*)` call site left in the tree, and outside
            // `app/`, which is the whole subject set of `CredentialsTest`'s lint
            // against exactly this. So this key read the `.env` seed for ever and
            // an operator rotating it in Ops changed nothing, silently, with the
            // build green.
            //
            // ⚠️ **`has()` BEFORE `get()`, AND `''` WHEN IT IS ABSENT.** This is
            // a page a stranger loads. `get()` raises on an unset credential, so
            // reading it straight would trade an unrotatable key for a 500 on the
            // marketing home — the defect this slice exists to close, reintroduced
            // by closing it. The empty string is what the template rendered
            // before, so the widget's own degradation (`CredentialManifest`: the
            // form loses its challenge and the 3/hr/IP limit becomes the only
            // abuse control) is unchanged.
            'turnstileSiteKey' => PlatformCredentials::has('turnstile_site_key')
                ? PlatformCredentials::get('turnstile_site_key')
                : '',
        ]);
    }

    /**
     * Pricing (`29` §6.1, CC-2 §2.2).
     *
     * ⚠️ **EVERY FIGURE ON THIS PAGE COMES OUT OF THIS METHOD AND NONE IS TYPED
     * IN THE TEMPLATE**, which is the binding rule for the whole surface and the
     * one a lint enforces: `MarketingTest`'s *"no marketing view prints a money
     * amount or a percentage of its own"* fails the build on a literal in any
     * blade under this surface, and `RegistryTest`'s price lint fails on the four
     * plan prices anywhere at all. 512 is why both exist — this page's ancestor
     * printed all four as literals.
     */
    public function pricing(DefaultsRegistry $registry, PlanCharges $charges): View
    {
        ['rates' => $rates, 'offerLive' => $offerLive] = $this->quotedRates($charges);

        $currency = $rates['monthly']->currency;

        // What a year on the annual term saves against twelve monthly payments,
        // derived from the two figures already in hand rather than seeded beside
        // them — 2055's rule for the instalments, which is the same rule: two
        // stored numbers that must agree with a third is 754's trap, and this one
        // would go stale the first time either price moved.
        $saving = $rates['monthly']->times(12)->minus($rates['annual']);

        return view('marketing.pricing', [
            ...array_map(
                static fn (Money $amount): string => PlanPricing::format($amount),
                $rates,
            ),

            'free' => PlanPricing::format(Money::of(
                $registry->entitlementCents(Plan::Free, 'price.monthly_cents'),
                $currency,
            )),

            // Withheld rather than shown as zero or as "nothing saved": a
            // non-positive saving means somebody has priced the annual term above
            // twelve months of the monthly one, and the honest page says nothing
            // rather than inviting a reader to work out that the sentence is
            // false.
            'annualSaving' => $saving->isNegative() || $saving->isZero()
                ? null
                : PlanPricing::format($saving),

            'trialDays' => $registry->int('billing.trial_days'),

            // ⛔ **THE FOUNDER-HISTORY LINE IS PAST TENSE AND MUST NOT RENDER
            // WHILE THE WINDOW IS OPEN** (decision 5198). CC-2 §2.2 supplies it
            // VERBATIM — *"Founder pricing existed, it closed exactly when we
            // said it would…"* — and it is written for the day after R18's flip.
            // Rendered today, beside a page quoting the founder rate, it is a
            // page saying an offer has closed while selling it. So the copy is
            // byte-exact and its condition is that no offer is live.
            //
            // ⚠️ **AN AMBIGUOUS OFFER COUNTS AS LIVE.** `quotedRates()` falls back
            // to the retail schedule when two offers collide, and the one thing
            // that collision proves is that offers exist — so the line stays
            // hidden, which is the direction that cannot publish a false claim.
            'founderWindowClosed' => ! $offerLive,

            // One source for the guarantee, shared with `/guarantee`, and null
            // until somebody sets it. See {@see self::settledString()}.
            'guarantee' => $this->settledString($registry, 'legal.guarantee_sentence'),
        ]);
    }

    /**
     * Features (`29` §6.1's `/what-it-does`, CC-2 §2.3).
     */
    public function features(DefaultsRegistry $registry): View
    {
        return view('marketing.features', [
            'capabilities' => $this->capabilities($registry),
        ]);
    }

    /**
     * The honest comparison table (`29` §6.1's `/compare/seo-company`, CC-2 §2.4).
     *
     * ⚠️ **OUR COLUMN IS THE SAME SOURCE `/features` RENDERS FROM**, which is the
     * point of {@see MarketingCapability} existing at all: a comparison table with
     * its own idea of what we ship would claim a store on the page whose whole
     * subject is not overclaiming.
     */
    public function compare(DefaultsRegistry $registry): View
    {
        return view('marketing.compare', [
            'capabilities' => $this->capabilities($registry),
        ]);
    }

    /**
     * The guarantee (CC-2 §2.5).
     */
    public function guarantee(DefaultsRegistry $registry): View
    {
        return view('marketing.guarantee', [
            'guarantee' => $this->settledString($registry, 'legal.guarantee_sentence'),
        ]);
    }

    /**
     * The twenty questions (CC-2 §2.6).
     *
     * ⚠️ **IT READS THE CAPABILITY FLAGS FOR THE SAME REASON `/compare` DOES.**
     * Two of the twenty answers describe things that ship dark — gift cards and
     * the Boost Score — and a page answering "yes, gift cards" while `/features`
     * has no commerce section is precisely the drift {@see MarketingCapability}
     * exists to make impossible. An answer about a dark capability is not
     * rendered.
     */
    public function faq(DefaultsRegistry $registry, PlanCharges $charges): View
    {
        ['rates' => $rates] = $this->quotedRates($charges);

        return view('marketing.faq', [
            'monthly' => PlanPricing::format($rates['monthly']),
            'trialDays' => $registry->int('billing.trial_days'),
            'industryPages' => $this->flag($registry, 'features.industry_pages'),
            'capabilities' => $this->capabilities($registry),
        ]);
    }

    /**
     * One of the six family demo doors (CC-2 §2.7).
     *
     * ⚠️ **THE FAMILY IS A BACKED ENUM AND THAT IS THE WHOLE VALIDATION.** Laravel
     * resolves the parameter through `DemoFamily::tryFrom()` and answers 404 for
     * anything else, so there is no seventh door and no list to keep in step with
     * the enum.
     *
     * ⛔ **THE KEYWORD IS WITHHELD WITH THE NUMBER RATHER THAN BESIDE IT.** The
     * word on its own is an instruction nobody can follow; printing "text TRADES"
     * with no number is the placeholder `demo.number` carries no seed to avoid.
     * So both come out of one read and the block renders on both or neither.
     */
    public function demo(DefaultsRegistry $registry, DemoFamily $family): View
    {
        $number = $this->settledString($registry, 'demo.number');
        $keyword = $this->settledString($registry, $family->keywordKey());

        return view('marketing.demo', [
            'family' => $family,
            'demoNumber' => $number === null || $keyword === null ? null : $number,
            'demoKeyword' => $number === null || $keyword === null ? null : $keyword,
        ]);
    }

    /**
     * Customer stories (CC-2 §2.8).
     *
     * ⛔ **THERE IS NOTHING TO LIST AND THAT IS NOT A BUG.** `29` §6.1 requires
     * proof to be real platform aggregates and decision 260 kept the reference
     * site's three invented testimonials off the home page; there are no
     * customers and therefore no studies. The page is the empty state CC-2 §2.8
     * writes for exactly this, and it stays that way until something publishes a
     * signed study — see decision 5199 for why no table was invented to count.
     */
    public function customers(): View
    {
        return view('marketing.customers');
    }

    /**
     * The affiliate programme (CC-2 §2.9, P-009).
     *
     * ✅ **EVERY RATE ON THIS PAGE IS SEEDED AND THE DEAL BLOCK RENDERS (P-009, R245)**.
     * The deal block renders only when all four figures are set, because a
     * commission with a rate and no payout floor is as unfinished a promise as one
     * with neither.
     */
    public function affiliates(DefaultsRegistry $registry): View
    {
        $rateMonthly = $this->settledInt($registry, 'affiliate.rate_monthly_bp');
        $rateAnnual = $this->settledInt($registry, 'affiliate.rate_annual_bp');
        $cookieDays = $this->settledInt($registry, 'affiliate.cookie_days');
        $payout = $this->settledInt($registry, 'affiliate.minimum_payout_cents');

        $settled = $rateMonthly !== null
            && $rateAnnual !== null
            && $cookieDays !== null
            && $payout !== null;

        return view('marketing.affiliates', [
            'deal' => $settled ? [
                'rateMonthly' => self::percent($rateMonthly),
                'rateAnnual' => self::percent($rateAnnual),
                'cookieDays' => $cookieDays,
                'minimumPayout' => PlanPricing::format(
                    Money::of($payout, $this->currency($registry)),
                ),
            ] : null,
        ]);
    }

    /**
     * The agency programme (CC-2 §2.10, P-008).
     */
    public function agencies(DefaultsRegistry $registry): View
    {
        $usageDiscount = $this->settledInt($registry, 'agency.usage_discount_bp');
        $voiceDiscount = $this->settledInt($registry, 'agency.voice_discount_bp');

        return view('marketing.agencies', [
            'usageDiscount' => $usageDiscount === null ? null : self::percent($usageDiscount),
            'voiceDiscount' => $voiceDiscount === null ? null : self::percent($voiceDiscount),
        ]);
    }

    /**
     * Signup (`29` §6.1's `/start`).
     *
     * ⚠️ **A CONTROLLER ACTION ONLY BECAUSE THE PAGE HAS TO READ THE TRIAL
     * LENGTH** (decision 691). It was a `Route::view` and promised "Seven days
     * free" in two places — a page title and a line above the form — while
     * `billing.trial_days` has been **14** since the owner reversed it (544).
     * The home page was fixed to read the key when CFG1 landed (518); this one
     * was not, because a `Route::view` has nothing to read one with, and the
     * registry lint could not see it either — 511 states that a price or a term
     * written in words is one of the three things it deliberately does not
     * cover. So the page that takes the promise was the one still making the old
     * one.
     */
    public function start(DefaultsRegistry $registry, SignupTerms $terms): View
    {
        return view('marketing.start', [
            'trialDays' => $registry->int('billing.trial_days'),

            // ⚠️ **THE BOX AND ITS THREE LINKS** (T176 P22). The wording comes
            // off `TermsAcceptanceMethod` rather than being typed here, because
            // the same string is stored in the acceptance's proof blob and two
            // copies are two things that drift.
            'termsLabel' => TermsAcceptanceMethod::Checkbox->notice(),

            // Empty when nothing is published, which is also when the form
            // itself is withheld — `CreateNewUser` refuses that registration,
            // so offering a form that cannot succeed would be a page lying
            // about what it can do.
            'termsLinks' => $terms->links(),
        ]);
    }

    /**
     * A shareable audit result (`29` §6.1's `/audit/{token}`).
     *
     * Server-rendered rather than fetched, because this is the page somebody
     * arrives at from a link somebody else sent them: the result is almost
     * always already complete, so rendering it into the initial HTML makes the
     * content its own LCP element instead of waiting on a round trip. When the
     * audit is still running the same partial ships with `data-pending="true"`
     * and the page's poller takes over — identical markup either way, because
     * it is the identical partial.
     */
    public function audit(string $token): View
    {
        return view('marketing.audit', [
            'audit' => $this->liveAudit($token),
        ]);
    }

    /**
     * The result partial on its own, as an HTML fragment.
     *
     * THE REASON THIS ROUTE EXISTS (decision 258). The home page streams
     * findings as they are computed, and the obvious way to do that is to poll
     * the JSON API and build the markup in JavaScript. That would mean two
     * renderers for one result, and the second one would get the gauge wrong:
     * decision 236 makes the arc and the printed number structurally unable to
     * disagree, via `pathLength="100"`, and a JS reimplementation re-opens
     * exactly the failure nobody would check — a plausible-looking dial reading
     * the wrong value.
     *
     * So the client renders nothing. It asks for this, swaps it in, and reads
     * `data-pending` off the root to decide whether to ask again.
     *
     * The JSON API is untouched and still the right thing for a programmatic
     * caller; this is the same data shaped for the one consumer that wants
     * markup.
     */
    public function result(string $token): Response
    {
        $html = view('marketing.partials.result', [
            'audit' => $this->liveAudit($token),
        ])->render();

        // Private, because a result is about one business and the token is the
        // only thing protecting it. No shared cache should hold this.
        return response($html)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Today's four display rates, and whether an offer is behind them.
     *
     * ⚠️ **EXTRACTED FROM `home()` RATHER THAN COPIED OUT OF IT.** Three pages now
     * quote a plan price and each needs the identical fallback; a second copy of
     * the `catch` is a second place for the two to disagree about what a collision
     * means, on the one subject where disagreeing costs revenue.
     *
     * @return array{rates: array{monthly: Money, annual: Money, locationMonthly: Money, locationAnnual: Money}, offerLive: bool}
     */
    private function quotedRates(PlanCharges $charges): array
    {
        try {
            return ['rates' => $charges->displayRates(), 'offerLive' => $charges->offerIsLive()];
        } catch (AmbiguousPlanOffer $collision) {
            // ⛔ **THE PUBLIC PAGES DO NOT 500 BECAUSE AN OPERATOR OPENED TWO
            // OFFERS ON ONE TERM (4342).** 4323 refuses to rank two live offers
            // and that refusal is right *at quote time*, where there is no honest
            // answer. Here there is one — the retail schedule, which is what these
            // pages printed before `plan_offers` existed — and rule 43's surviving
            // half asks for graceful degradation rather than a hard fail (3294).
            // The alternative was every visitor and every checkout meeting an
            // exception from the second a deploy wrote the row.
            //
            // ⚠️ **LOGGED AT `error` BECAUSE NOTHING ELSE WILL SAY IT.** The page
            // renders correctly and cheaply on the wrong prices; without this
            // line the only symptom is revenue.
            Log::error('two plan offers are live on one term; a marketing page fell back to retail', [
                'reason' => $collision->getMessage(),
            ]);

            // ⚠️ **`offerLive` IS TRUE ON THIS ARM EVEN THOUGH THE RATES ARE
            // RETAIL**, and the two are not in tension. The figures fall back
            // because there is no honest ranking; the flag answers a different
            // question — *is a founder window open* — and a collision is proof
            // that at least two are. Its only reader hides a past-tense sentence,
            // so the safe answer is the one that hides it.
            return ['rates' => $charges->retailDisplayRates(), 'offerLive' => true];
        }
    }

    /**
     * Whether a registry flag is on.
     *
     * Compared against `true` rather than cast, so a row hand-edited to the string
     * `"false"` reads as off. Every one of these flags publishes a public claim
     * when it is on (see {@see MarketingCapability}), and a truthy string is not
     * somebody deciding to make one.
     */
    private function flag(DefaultsRegistry $registry, string $key): bool
    {
        return $registry->value($key) === true;
    }

    /**
     * Every capability, against whether the site may say we do it.
     *
     * @return array<string, bool>
     */
    private function capabilities(DefaultsRegistry $registry): array
    {
        $states = [];

        foreach (MarketingCapability::cases() as $capability) {
            $key = $capability->flagKey();

            $states[$capability->value] = $key === null || $this->flag($registry, $key);
        }

        return $states;
    }

    /**
     * A registry string, or null when nobody has set one.
     *
     * ⛔ **THE `catch` IS THE FEATURE AND IT IS DELIBERATELY NARROW.** A withheld
     * figure raises {@see WithheldRegistryValue} naming the decision that left it
     * open (502), and on a public page the correct response to that is to render
     * nothing — never a placeholder, which is a false statement that looks
     * answered. What is **not** caught is `InvalidArgumentException`: a key the
     * manifest has never heard of is a typo, and swallowing it would turn a
     * misspelled key into a silently missing block that nobody would ever notice.
     */
    private function settledString(DefaultsRegistry $registry, string $key): ?string
    {
        try {
            $value = $registry->value($key);
        } catch (WithheldRegistryValue) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A registry integer, or null when nobody has set one. {@see self::settledString()}.
     */
    private function settledInt(DefaultsRegistry $registry, string $key): ?int
    {
        try {
            $value = $registry->value($key);
        } catch (WithheldRegistryValue) {
            return null;
        }

        return is_int($value) ? $value : null;
    }

    /**
     * The currency every printed amount on this surface is denominated in.
     */
    private function currency(DefaultsRegistry $registry): string
    {
        $currency = $registry->value('billing.currency');

        return is_string($currency) && $currency !== '' ? $currency : 'USD';
    }

    /**
     * Basis points as a percentage a person reads.
     *
     * ⚠️ **INTEGER ARITHMETIC, FOR THE REASON `PlanPricing::format()` GIVES.** A
     * rate is stored in basis points for the same reason money is stored in cents
     * — `0.175` does not survive a jsonb round trip comparing equal to itself —
     * and dividing by a hundred here to print it would put a float back in the one
     * place the storage decision was made to keep one out.
     */
    private static function percent(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $fraction = $basisPoints % 100;

        if ($fraction === 0) {
            return $whole.'%';
        }

        return $whole.'.'.rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0').'%';
    }

    /**
     * One live audit, or a 404 that does not distinguish its reasons.
     *
     * "Never existed", "expired" and "pruned by `audits:prune`" are the same
     * fact from a visitor's side, and telling them apart would confirm that a
     * given token was once real.
     */
    private function liveAudit(string $token): PublicAudit
    {
        $audit = PublicAudit::query()
            ->live()
            ->where('token', $token)
            ->first();

        if (! $audit instanceof PublicAudit) {
            throw new NotFoundHttpException;
        }

        return $audit;
    }
}
