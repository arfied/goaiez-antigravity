<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BillingTerm;

/**
 * The offers this application ships with — founder pricing rates, and the one
 * file in which a founder figure may be written (decisions 2090, 2091, 2092).
 *
 * ## ⛔ WHY THIS IS NOT `DefaultsManifest`
 *
 * 2090: *"a founder rate is the same plan sold on different terms"*.
 * `DefaultsManifest::entitlements()` is keyed by `Plan`, so a founder price
 * seeded there would be `Plan::Base`'s price — which silently reprices **retail**
 * for every tenant who signs up after the window closes, and `RegistryTest`
 * compares that manifest against `CLAUDE.md`'s commercial table on every run, so
 * the two would have to be edited together and the retail schedule would be gone.
 * The founder rates are an **offer**; offers live in `plan_offers` rows, and this
 * is the seed those rows are loaded from by `offers:sync`.
 *
 * ## ⚠️ IT IS ON THE PRICE LINT's PERMITTED LIST, ON `DefaultsManifest`'s TERMS
 *
 * `RegistryTest`'s *"no plan price is written as a literal outside the seed
 * manifest"* names two files: the manifest, and the test that lists the forbidden
 * figures in order to forbid them. This is a third, and the rule is unchanged —
 * it is the same job, for the figures the manifest deliberately may not hold. The
 * list grew by one file whose entire purpose is to be the single place a founder
 * price is written; it did not grow a hole.
 *
 * ## ⚠️ THE ANNUAL IS TWO PAYMENTS HERE AND THREE IN `CLAUDE.md`, AND BOTH ARE RIGHT
 *
 * This is the difference T176's own estimate-confirm predicted somebody would
 * "correct". `CLAUDE.md`'s retail annual is $997 in **three** instalments
 * (33233 / 33233 / 33234); the founder annual is $499.99 in **two**
 * (25000 + 24999 as the owner writes them). They are different products. 2754
 * said the count belongs on the offer rather than beside `PlanCharges::PAYMENTS`,
 * and `plan_offers.instalment_payments` is where it went. **Neither figure is a
 * correction of the other, and a build-failing test in `RegistryTest` says so**,
 * so an edit that harmonises them reddens rather than quietly repricing a year.
 *
 * ## ⚠️ THE PER-PAYMENT CENTS ARE NOT HERE, AND MAY NEVER BE
 *
 * 2055's rule, restated by 2092: derive the split from the annual figure, never
 * seed it beside it. The two founder rows prove the rule between them — the add-on
 * (49900) divides evenly into two and the base (49999) does not — so a seeded pair
 * would be right for one row and a cent wrong for the other. `Instalments::split()`
 * is the only thing that produces them.
 *
 * ⚠️ **AND THE OWNER'S ORDERING IS NOT WHAT IS CHARGED.** The founder pricing notes write
 * "$250.00 + $249.99"; `Instalments` puts the remainder **last** on `CLAUDE.md`'s
 * rule, so the founder annual is billed $249.99 then $250.00. 2741 records that
 * remainder-last is *the only ordering Authorize.Net can express* — a fact about
 * the vendor, not a vote on 2135 — so this is not a choice this slice made. The
 * total is identical to the cent.
 */
final class PlanOfferCatalog
{
    /**
     * Founder offer.
     *
     * A constant rather than a literal at each row, because the key is what
     * `plan_offers.key` is unique on and what `offers:sync` matches an existing
     * row by — two spellings would seed a second offer rather than update the
     * first, and both would then be live at once.
     */
    public const string FOUNDER = 'founder';

    /**
     * When the founder window opened.
     *
     * ⚠️ **A FIXED INSTANT RATHER THAN "NOW AT SEED TIME", BECAUSE `offers:sync`
     * RUNS ON EVERY DEPLOY.** A window that opened whenever the command last ran
     * would move every time somebody deployed, and the one question this column
     * answers — *was this offer live when that subscription was sold* — would get
     * a different answer next week. Soft launch is what the date names.
     */
    public const string FOUNDER_OPENS_AT = '2026-08-16T00:00:00+00:00';

    /**
     * When the founder window ends — R18's *"dated, announced flip"*, dated at last.
     *
     * ⛔ **THE OWNER ENDED IT ON 2026-08-25: *"founder pricing is no more we just
     * have soft lauch which is bsic package then everything else"***
     * (`docs/incoming-2026-08-25/OWNER-RULING.md`, part 3). This constant is the
     * instant that sentence names, and nothing else. R18 wanted the flip dated
     * rather than tied to the ambiguous word *"launch"*; the ruling is the date.
     *
     * ⚠️ **THE ROWS ARE CLOSED RATHER THAN DELETED, AND THE PRICES STAY.** A
     * `plan_offers` row is the record of what was on the table while people were
     * buying — `PlanOffers::close()` says so about the
     * table and this catalogue is where that table is stated. Deleting the two
     * entries would leave a deployed database quoting $99.99 with nothing in this
     * repository able to say what it was quoting or refuse a changed figure, and
     * would empty three tests into vacuity at once. **Closing states the same
     * ruling and keeps the record.**
     *
     * ⚠️ **A PAST INSTANT IS THE ANNOUNCED ONE, NOT THE OBSERVED ONE.** If the
     * deploy carrying this is late, the window really went on being quoted after
     * midnight on the 25th and the row will say it ended then. That is deliberate:
     * the row records the flip that was announced, and `registry_changes` carries
     * the instant the close was actually written, so a reader can reconstruct both.
     * Writing `max(this, now)` instead would make the seeded date depend on when
     * somebody shipped, which is exactly what {@see self::FOUNDER_OPENS_AT}
     * refuses one constant above.
     */
    public const string FOUNDER_CLOSES_AT = '2026-08-25T00:00:00+00:00';

    /**
     * Every offer row this application seeds.
     *
     * ⛔ **`closes_at` WAS NULL AND THAT WAS FAIL-OPEN ON A PRICE**, which this
     * codebase otherwise refuses. R18 ends the founder window at *"the SEO + sites
     * flip event — a dated, announced flip, never at the ambiguous word
     * 'launch'"*, and until 2026-08-25 nobody had dated that event: a guessed date
     * would have repriced every tenant who signed up the day after it, and
     * withholding the figure (502's pattern) meant no founder price at all, which
     * was the thing P1 existed to deliver. ✅ **The owner dated it, and both rows
     * now carry {@see self::FOUNDER_CLOSES_AT}.**
     *
     * ⛔ **AND A CLOSING DATE STATED HERE ONLY REACHED A DATABASE THAT DID NOT YET
     * HAVE THE ROW.** `offers:sync` creates what is missing and skips what exists,
     * so until 9268 the sentence *"closing it is one row's edit through
     * `PlanOffers::close()` and nothing here will ever remind anybody"* was the
     * whole truth: every deployed install went on quoting the founder rate however
     * this file was edited. `PlanOffers::sync()` now
     * carries a stated close onto a row whose `closes_at` is **null**, and onto no
     * other, so the deploy is what ends the window and nobody has to remember a
     * command. ⚠️ **It is still not retroactive**: until that deploy runs, or
     * `php artisan offers:close founder --at=…` is run by hand, a running install
     * quotes the founder rate whatever this file says.
     *
     * @return list<array{
     *     key: string,
     *     term: BillingTerm,
     *     price_cents: int,
     *     additional_location_cents: int,
     *     price_currency: string,
     *     instalment_payments: ?int,
     *     opens_at: string,
     *     closes_at: ?string,
     * }>
     */
    public static function offers(): array
    {
        return [
            [
                'key' => self::FOUNDER,
                'term' => BillingTerm::Monthly,

                // R10: "plan $99.99/mo". ⚠️ The same figure as the *retail*
                // add-on rate, which is a coincidence of the owner's two lists
                // rather than a derivation — do not read one from the other.
                'price_cents' => 9999,

                // R10: "Extra location $99.99/mo" — identical to retail's add-on
                // monthly. It is stated here anyway rather than left to fall
                // through to the registry, because an offer that priced only its
                // base plan would quote a founder base beside a retail add-on and
                // call the sum a founder price.
                'additional_location_cents' => 9999,
                'price_currency' => 'USD',

                // Monthly is not sold in instalments on any offer: 2055 gives the
                // instalment option to the annual price and to nothing else, and
                // `PlanSelection` cannot even spell the other combination.
                'instalment_payments' => null,
                'opens_at' => self::FOUNDER_OPENS_AT,

                // ⛔ THE OWNER'S RULING OF 2026-08-25, AS THE COLUMN THAT ENDS A
                // WINDOW RATHER THAN AS A DELETED ROW. See FOUNDER_CLOSES_AT.
                'closes_at' => self::FOUNDER_CLOSES_AT,
            ],
            [
                'key' => self::FOUNDER,
                'term' => BillingTerm::Annual,

                // R10: "$499.99 annual in 2 payments". ⚠️ 2091's rider fires on
                // this line: `$499.99` and `49999` were classified **superseded**
                // in `RegistryTest` — forbidden even in tests, with the message
                // "decision 146 corrected this to $497" — and they move to the
                // live group in the same commit that seeds them, or the lint
                // fails the build on the one figure it exists to protect while
                // citing a correction that no longer describes anything.
                'price_cents' => 49999,

                // R10: "Extra location … $499.00 annual". The retail annual add-on
                // to the cent (49900), and stated for the row above's reason.
                'additional_location_cents' => 49900,
                'price_currency' => 'USD',

                // ⛔ TWO, NOT `PlanCharges::PAYMENTS`. See the class docblock.
                'instalment_payments' => 2,
                'opens_at' => self::FOUNDER_OPENS_AT,

                // ⛔ CLOSED WITH ITS MONTHLY SIBLING AND AT THE SAME INSTANT.
                // `PlanOffers::close()` closes a key's rows together or not at
                // all, for the reason a half-closed key is worse than either
                // state: one term quoted at the founder rate and the other at
                // retail is a price table nobody chose.
                'closes_at' => self::FOUNDER_CLOSES_AT,
            ],
        ];
    }
}
