<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlacesSku;
use App\Services\Places\GooglePlacesClient;
use App\Support\PlacesFieldTiers;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * `places:verify-pricing` — the command three artefacts already cited.
 *
 * ⛔ **IT DID NOT EXIST UNTIL 2026-08-29, AND IT WAS NAMED AS THE ANSWER TO THE
 * ONE QUESTION THE SUITE CANNOT ANSWER.** `PlacesSku`'s docblock said it
 * "exists to make re-checking a command rather than a research project";
 * `PlacesSpendTest` said the cost-model test "can only fail when code drifts
 * from the figures, never when the figures drift from Google … which is what
 * `places:verify-pricing` and PlacesSku::VERIFIED_ON are for"; and decision 256
 * — the row that deliberately kept a test it had just proved could not detect a
 * wrong price — ends on the same sentence. **A green suite could not see that
 * the thing all three of them deferred to was never written.**
 *
 * ⛔ **IT DOES NOT CALL GOOGLE AND MUST NOT.** Every Places endpoint is metered,
 * so a verifier that verified by requesting would spend money to ask what money
 * costs, and Google publishes prices on a documentation page rather than an API.
 * What it does instead is lay out **everything a person needs to compare**, in
 * the order the published table lists it: family, tier, SKU code, price, free
 * allowance — and then every field mask this application actually sends, the SKU
 * derived for it, and the tier of every field in it.
 *
 * ⚠️ **SO ITS OUTPUT IS AN INPUT TO A HUMAN CHECK, NOT A VERDICT.** It says so
 * on every run, in the same breath as the dates, because a command called
 * `verify-pricing` that exits 0 is the most obvious thing in this tree to
 * mistake for a verification. What it *can* fail on by itself is narrower and
 * real: a mask this application sends containing a field the tier table cannot
 * price, which is the state where the ledger would be recording a guess.
 */
final class VerifyPlacesPricing extends Command
{
    protected $signature = 'places:verify-pricing
        {--max-age-days=90 : Warn when the recorded fetch dates are older than this}';

    protected $description = 'Print the Places SKU table, the masks we send, and what to re-read on Google to check them';

    public function handle(): int
    {
        $this->line('⚠️  This command does NOT call Google. It prints what to compare against these pages:');

        foreach (PlacesFieldTiers::SOURCES as $name => $url) {
            $this->line(sprintf('    %-18s %s', $name, $url));
        }

        $this->newLine();
        $this->dates();
        $this->newLine();
        $this->priceBook();
        $this->newLine();

        return $this->masks();
    }

    /**
     * The two dates, which are two dates on purpose.
     *
     * Prices and tier membership move independently at Google, so one date
     * covering both is a date that is half true — and the half that is stale is
     * the half nobody re-read.
     */
    private function dates(): void
    {
        $maxAge = max(1, (int) $this->option('max-age-days'));

        foreach ([
            'Prices (PlacesSku::VERIFIED_ON)' => PlacesSku::VERIFIED_ON,
            'Field-mask tiers (PlacesFieldTiers::FETCHED_ON)' => PlacesFieldTiers::FETCHED_ON,
        ] as $label => $date) {
            $age = (int) Carbon::parse($date)->diffInDays(Carbon::now());
            $line = sprintf('%-48s %s  (%d days old)', $label, $date, $age);

            $age > $maxAge ? $this->warn('⚠️  '.$line) : $this->line('    '.$line);
        }
    }

    /**
     * Every SKU, keyed the way Google's own list is keyed.
     *
     * ⚠️ **THE SKU CODE IS PRINTED BESIDE THE PRICE DELIBERATELY.** One of them
     * was wrong for a month in a docblock — `635D-A9DD-C520`, Google's free
     * *Text Search Essentials (IDs Only)*, sat on a case charging $2.32/1,000 —
     * and a code that is only ever read in prose is checked by whoever happens
     * to read the prose.
     */
    private function priceBook(): void
    {
        $rows = [];

        foreach (PlacesSku::cases() as $sku) {
            $allowance = $sku->freeRequestsPerMonth();
            $tier = $sku->tier();

            // ⛔ A CASE NO MASK CAN REACH IS NAMED HERE RATHER THAN LEFT TO
            // READ AS A PRICE LIST ENTRY. Asked of the derivation itself rather
            // than of a hand-kept list: if `(family, tier)` does not come back
            // to this case, nothing in this application can produce it, and the
            // row is history rather than a price. `TextSearchEssentials` is the
            // one — Google sells no such SKU (2026-08-29).
            $reachable = $tier !== null
                && PlacesSku::fromFamilyAndTier($sku->family(), $tier) === $sku;

            $rows[] = [
                $sku->family()->label(),
                $tier === null
                    ? '— (not chosen by a field mask)'
                    : $tier->value.($reachable ? '' : '  ⛔ NOT SOLD BY GOOGLE'),
                $sku->skuCode() ?? '—',
                $sku->isFree() ? 'free' : '$'.number_format($sku->centsPerThousand() / 100, 2).'/1k',
                $allowance === PHP_INT_MAX ? 'unlimited' : number_format($allowance).'/mo',
                $sku->value,
            ];
        }

        $this->table(['Family', 'Tier', 'Google SKU code', 'Price', 'Free', 'Ledger value'], $rows);
    }

    /**
     * The masks this application sends, and what each one prices to.
     *
     * ⛔ **TAKEN FROM THE CLIENT'S OWN CONSTANTS, NEVER RETYPED HERE.** A
     * verification tool holding its own copy of the thing it verifies is 8460's
     * shape, and this command exists because of a comment that was its own
     * authority.
     */
    private function masks(): int
    {
        $unpriced = 0;

        foreach (GooglePlacesClient::pricedMasks() as $method => $priced) {
            $sku = $priced->sku;

            $this->line(sprintf(
                '%s()  →  %s  (%s)',
                $method,
                $sku->value,
                $sku->isFree() ? 'free' : '$'.number_format($sku->centsPerThousand() / 100, 2).'/1k',
            ));

            if (in_array($priced->family, PlacesFieldTiers::familiesWithoutFieldMaskTiers(), true)) {
                $this->line('    (this family bills one SKU per request; its mask does not choose a price)');
                $this->newLine();

                continue;
            }

            foreach ($priced->fields() as $field) {
                $tier = PlacesFieldTiers::tierFor($priced->family, $field);

                if ($tier === null) {
                    $unpriced++;
                    $this->error(sprintf('    %-26s NOT PRICED by the tier table', $field));

                    continue;
                }

                $this->line(sprintf('    %-26s %s', $field, $tier->value));
            }

            $this->newLine();
        }

        if ($unpriced > 0) {
            $this->error(
                "{$unpriced} field(s) in the masks above have no tier. Re-read the pages listed at the "
                .'top and add each one at the tier Google lists it under — the ledger is recording a '
                .'guess until you do.'
            );

            return self::FAILURE;
        }

        $this->line('Every field in every mask this application sends has a tier in the table.');
        $this->line('⚠️  That is a statement about the TABLE, not about Google. Compare the two by hand.');

        return self::SUCCESS;
    }
}
