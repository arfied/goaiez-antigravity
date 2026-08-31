<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingTerm;

/**
 * The auto-renewal wording, and the versions that name it (2980–2999).
 *
 * California's Automatic Renewal Law requires the renewal terms to be presented
 * "in a clear and conspicuous manner" and **acknowledged separately** from the
 * purchase itself. `AutoRenewalAcknowledgements` stores the version a tenant
 * saw; this is where the version and the words live together, so the two cannot
 * drift apart. `tests/Feature/AutoRenewalAcknowledgementTest.php`'s *"each
 * version is pinned to its exact rendered wording"* pins each version to its
 * exact rendered string, so editing the wording without bumping the version
 * fails the build — `ConsentDisclosure`'s rule, one domain over.
 * ⛔ **THIS CITED an `AutoRenewalDisclosureTest` AND NO FILE OF THAT NAME HAS
 * EVER EXISTED — CORRECTED 2026-08-25 (9662).** The claim itself was true the
 * whole time, which is what makes the shape expensive: the sentence reads as a
 * settled fact and the grep behind it comes back empty.
 *
 * ⚠️ **NOT IN `ConsentDisclosure`, AND THAT IS A JUDGEMENT RATHER THAN AN
 * OVERSIGHT.** That class lives in `App\Services\Feedback`, reads its strings
 * from the `feedback.*` translation namespace, and its `versionFor()` takes an
 * `OutreachChannel` because its two versions are permissions to contact
 * somebody. This is neither: it is shown to the **account holder**, on a
 * billing page, and permits no contact of any kind. `PHI_ANALYSIS_VERSION`
 * already had to be documented as "deliberately not reachable from
 * `versionFor()`"; a third unreachable constant behind a channel-keyed method
 * is where that pattern stops paying.
 *
 * ⚠️ **THE STRINGS ARE HERE RATHER THAN IN A TRANSLATION FILE**, unlike the
 * feedback disclosures. Those are read by a tenant's customers on a public page
 * that will be localised; this one is read by the account holder on a checkout
 * screen whose every other word is hard-coded English Blade, and a lone
 * `lang/en/billing.php` holding two strings would be a translation namespace
 * invented for a page that has no translated siblings.
 *
 * ## Why there are two versions
 *
 * ⛔ **AN INSTALMENT PLAN DOES NOT RENEW, SO THE AUTO-RENEWAL WORDING WOULD BE
 * A FALSE STATEMENT ON IT.** Decision 2749 is explicit: three ARB payments a
 * cycle apart complete inside the first quarter, the vendor's schedule ends,
 * and nothing renews it — "a paid-in-full annual subscription renews at the
 * vendor, an instalment one cannot". Telling that buyer "this renews
 * automatically until you cancel" would be exactly the misrepresentation the
 * statute exists to prevent, made *in the disclosure written to comply with
 * it*. So the non-renewing purchase gets its own words and its own version, and
 * the stored version is what tells the two populations apart afterwards.
 */
final class AutoRenewalDisclosure
{
    /**
     * The wording for a purchase that renews by itself.
     *
     * ⚠️ Bump the trailing number when the words change; never reuse a version
     * for different text. Rows point at these strings by name, and grouping
     * every tenant who agreed to a given statement is the one thing an
     * acknowledgment record is for.
     *
     * ⚠️ **`.2` BECAUSE THE `.1` WORDING NAMED A SCREEN THAT DOES NOT EXIST.**
     * Both texts said *"from the Billing page in your account"*, and `/billing`
     * is the **signup** page: it has no `OwnerNav` entry, nothing in `app/`
     * links to it, and it carries no cancel control. A disclosure that tells
     * somebody where to cancel has to name somewhere they can get to, so it
     * names *Your plan*, which is where the mechanism actually is. Bumped rather
     * than edited in place because the rule below is the rule: never reuse a
     * version for different text. **No row carried `.1` — nothing in `app/`
     * called this class at all — so nobody's evidence changed.**
     */
    public const string RENEWING_VERSION = 'auto-renewal-2026-08-12.2';

    /**
     * The wording for a fixed-term purchase that ends when it is paid.
     */
    public const string NON_RENEWING_VERSION = 'no-auto-renewal-2026-08-12.2';

    /**
     * The version that goes with this purchase.
     */
    public static function versionFor(bool $renews): string
    {
        return $renews ? self::RENEWING_VERSION : self::NON_RENEWING_VERSION;
    }

    /**
     * The words that go with this purchase.
     *
     * @param  string  $price  Already formatted by `PlanPricing::format()`. ⚠️
     *                         Never a literal and never assembled here: the
     *                         money lint forbids a plan figure outside
     *                         `DefaultsManifest`, and a second formatter is the
     *                         second source of truth 512 closed.
     */
    public static function textFor(bool $renews, string $price, BillingTerm $term): string
    {
        return $renews
            ? self::renewingText($price, $term)
            : self::nonRenewingText();
    }

    /**
     * The label beside the box.
     *
     * ⚠️ **IT SAYS WHAT IS BEING AGREED TO, NOT "I AGREE".** A box labelled "I
     * agree" beside a paragraph is an agreement to whatever the paragraph turns
     * out to say; a box that restates the substance is an acknowledgment on its
     * own, which is what "distinct" means here.
     */
    public static function labelFor(bool $renews): string
    {
        return $renews
            ? 'I understand this subscription renews automatically until I cancel it.'
            : 'I understand this plan does not renew and that nothing is charged after the last payment.';
    }

    private static function renewingText(string $price, BillingTerm $term): string
    {
        $cadence = $term === BillingTerm::Annual ? 'year' : 'month';

        return 'This subscription renews automatically. '.$price.' is charged every '
            .$cadence.', and the same amount is charged every '.$cadence.' after that, '
            .'until you cancel it. There is no minimum term and no cancellation fee. '
            .'You can cancel at any time from the Your plan page in your account, and '
            .'cancelling stops every future charge.';
    }

    private static function nonRenewingText(): string
    {
        return 'This plan covers one year, paid in the instalments shown on this page, '
            .'and it does not renew. Those payments are every payment: nothing is '
            .'charged after the last one and nothing starts again by itself. You can '
            .'cancel at any time from the Your plan page in your account, and cancelling '
            .'stops any payment that has not been taken yet.';
    }
}
