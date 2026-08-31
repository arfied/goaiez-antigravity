<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Contracts\AuditCheck;
use App\Enums\AuditCheckKey;
use App\Services\Audit\AuditContext;
use App\Services\Audit\CheckResult;
use App\Services\Audit\Finding;
use App\Services\Audit\PageText;

/**
 * `29` §6.2's third check: "NAP quick-scan: site fetch + top-4 directory
 * fetches, phone/address diff".
 *
 * THE DIRECTORY HALF IS NOT BUILT, AND THAT IS SLICE D'S ANSWER RATHER THAN AN
 * OMISSION. The "top-4 directories" for a US local business are Google, Yelp and
 * Facebook plus one more — and all three of those are seeded `guided_only` in
 * `fetch_sources`, permanently ineligible for any fetch tier (`40` §6.2,
 * decision 217's migration). The gateway would refuse every one of those
 * requests, correctly. So this check scans the business's own website, which is
 * the one source we may fetch and also the one the owner can actually fix. When
 * the directory comparison arrives it will come through the guided-fix packet
 * path `40` designs for exactly this, not through a fetch.
 *
 * IT REPORTS ABSENCE, NEVER DISAGREEMENT. The check says "the phone number on
 * your Google listing does not appear on your website" — it never says which one
 * is correct, because it cannot know. A confident "your website has the wrong
 * number" that turns out to be a second, deliberate line is the kind of finding
 * that makes an owner distrust every other finding on the page.
 */
final class NapQuickScanCheck implements AuditCheck
{
    public function key(): AuditCheckKey
    {
        return AuditCheckKey::NapQuickScan;
    }

    public function run(AuditContext $context): CheckResult
    {
        $place = $context->place;

        if ($place === null) {
            return CheckResult::unavailable(
                $this->key(),
                $context->placeUnavailableReason ?? 'no_place',
            );
        }

        $reason = $context->siteUnavailableReason();

        if ($reason !== null) {
            // No page means no comparison. Unlike site basics, there is no
            // finding to salvage here — "we could not read your website" is not
            // evidence about whether your details match it.
            return CheckResult::unavailable($this->key(), $reason);
        }

        $body = $context->siteBody() ?? '';
        $text = PageText::visible($body);
        $findings = [];

        $phoneFinding = $this->phoneFinding($text, $place->nationalPhoneNumber);

        if ($phoneFinding instanceof Finding) {
            $findings[] = $phoneFinding;
        }

        $addressFinding = $this->addressFinding($text, $place->formattedAddress);

        if ($addressFinding instanceof Finding) {
            $findings[] = $addressFinding;
        }

        if ($findings === []) {
            // The listing carried neither a phone nor an address to compare
            // against. Nothing was checked, so nothing is claimed.
            return CheckResult::unavailable($this->key(), 'nothing_to_compare');
        }

        return CheckResult::ran($this->key(), $findings);
    }

    private function phoneFinding(string $text, ?string $listingPhone): ?Finding
    {
        if ($listingPhone === null) {
            return null;
        }

        $wanted = PageText::phoneDigits($listingPhone);

        if ($wanted === null) {
            return null;
        }

        return PageText::containsPhone($text, $wanted)
            ? Finding::healthy(
                $this->key(),
                'nap.phone_matches',
                'The phone number on your Google listing also appears on your website.',
            )
            : Finding::critical(
                $this->key(),
                'nap.phone_absent',
                'The phone number on your Google listing does not appear anywhere on your website. Search engines read that as two different businesses.',
            );
    }

    /**
     * Address comparison, deliberately anchored on two tokens rather than the
     * whole string.
     *
     * "123 Main St, Springfield, IL 62704" is written a dozen ways in a footer —
     * "Main Street", no comma, the state spelled out, the ZIP on its own line.
     * Matching the formatted string would report a mismatch on nearly every real
     * website. The street number and the postcode are the two parts that do not
     * get restyled, and requiring both is what keeps a bare "123" in a price
     * from counting as a match.
     */
    private function addressFinding(string $text, ?string $formattedAddress): ?Finding
    {
        if ($formattedAddress === null) {
            return null;
        }

        $streetNumber = PageText::leadingStreetNumber($formattedAddress);
        $postcode = PageText::postcode($formattedAddress);

        if ($streetNumber === null || $postcode === null) {
            // An address we cannot decompose is one we cannot compare without
            // guessing. Silence beats a fabricated finding.
            return null;
        }

        $hasNumber = PageText::containsToken($text, $streetNumber);
        $hasPostcode = PageText::containsToken($text, $postcode);

        if ($hasNumber && $hasPostcode) {
            return Finding::healthy(
                $this->key(),
                'nap.address_matches',
                'The address on your Google listing also appears on your website.',
            );
        }

        return Finding::attention(
            $this->key(),
            'nap.address_absent',
            'The address on your Google listing does not appear in full on your website. Matching them exactly is one of the cheapest things you can do for local search.',
            ['street_number_found' => $hasNumber, 'postcode_found' => $hasPostcode],
        );
    }
}
