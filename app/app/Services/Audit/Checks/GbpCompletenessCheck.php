<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Contracts\AuditCheck;
use App\Enums\AuditCheckKey;
use App\Services\Audit\AuditContext;
use App\Services\Audit\CheckResult;
use App\Services\Audit\Finding;
use App\Services\Places\PlaceSummary;

/**
 * `29` §6.2's first check: "GBP completeness via Places Details (hours present,
 * categories, photos count, description)".
 *
 * ALL FOUR FIELDS ARE ALREADY PAID FOR. Place Details bills Atmosphere for this
 * audit whatever happens, because reply-rate needs `reviews` (PlacesSku). Hours,
 * categories, photos and description sit at Pro and Enterprise, strictly below
 * that ceiling — so this check is free in the sense that matters: removing it
 * would not save a cent. Worth stating because the reverse is not true, and a
 * later check that wants one field above the ceiling would move the whole bill.
 *
 * THE THRESHOLDS ARE OURS, NOT GOOGLE'S. Google publishes no completeness score
 * and we must not imply it does. Each finding says what is missing and what it
 * costs, never "Google penalises this" — that is the same rule as never claiming
 * guaranteed rankings (`29` §2), applied to a claim about somebody else's
 * algorithm.
 */
final class GbpCompletenessCheck implements AuditCheck
{
    /**
     * Below this, photos read as neglect rather than absence.
     *
     * Ours, and deliberately low: the finding is meant to be actionable in an
     * afternoon, not a content project.
     */
    private const int THIN_PHOTO_COUNT = 5;

    public function key(): AuditCheckKey
    {
        return AuditCheckKey::GbpCompleteness;
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

        $findings = [];

        $hoursFinding = $this->hoursFinding($place);

        if ($hoursFinding instanceof Finding) {
            $findings[] = $hoursFinding;
        }

        $findings[] = $place->primaryType !== null
            ? Finding::healthy(
                $this->key(),
                'gbp.category_present',
                'Your listing has a primary category, which is how Google decides what searches to show you in.',
                ['primary_type' => $place->primaryType],
            )
            : Finding::critical(
                $this->key(),
                'gbp.category_missing',
                'Your listing has no primary category, so Google has little idea which searches to show you in.',
            );

        $photosFinding = $this->photosFinding($place);

        if ($photosFinding instanceof Finding) {
            $findings[] = $photosFinding;
        }

        $findings[] = $place->hasDescription()
            ? Finding::healthy(
                $this->key(),
                'gbp.description_present',
                'Your listing has a description.',
            )
            : Finding::attention(
                $this->key(),
                'gbp.description_missing',
                'Your listing has no description, so the first thing people read about you is whatever the reviews say.',
            );

        return CheckResult::ran($this->key(), $findings);
    }

    /**
     * ⛔ **`$place->hasOpeningHours` USED TO BE READ DIRECTLY, AND A `false` FROM
     * A MASK THAT NEVER ASKED FOR `regularOpeningHours` READS IDENTICALLY TO A
     * `false` FROM A LISTING THAT GENUINELY HAS NONE.** {@see PlaceSummary::knownHasOpeningHours()}
     * tells the two apart using the mask already carried on the object — no
     * second vendor call. A missing finding is the right answer for the "never
     * asked" arm rather than a hedged one (decision 229, `ReviewStatsCheck`'s
     * same rule): "we cannot say" is not itself a finding, and the three checks
     * around this one keep reporting normally.
     */
    private function hoursFinding(PlaceSummary $place): ?Finding
    {
        $hasHours = $place->knownHasOpeningHours();

        if ($hasHours === null) {
            return null;
        }

        return $hasHours
            ? Finding::healthy(
                $this->key(),
                'gbp.hours_present',
                'Your opening hours are on your Google listing.',
            )
            : Finding::critical(
                $this->key(),
                'gbp.hours_missing',
                'Your Google listing has no opening hours, so people searching after closing time cannot tell whether you are open.',
            );
    }

    /**
     * The same shape as {@see self::hoursFinding()}, for `$place->photoCount`.
     */
    private function photosFinding(PlaceSummary $place): ?Finding
    {
        $count = $place->knownPhotoCount();

        if ($count === null) {
            return null;
        }

        return match (true) {
            $count === 0 => Finding::critical(
                $this->key(),
                'gbp.photos_none',
                'Your listing has no photos. Listings with photos get noticeably more clicks than those without.',
                ['photo_count' => 0],
            ),
            $count < self::THIN_PHOTO_COUNT => Finding::attention(
                $this->key(),
                'gbp.photos_thin',
                sprintf(
                    'Your listing has %d photo%s. A handful more of the inside, the outside and your work gives people a reason to choose you.',
                    $count,
                    $count === 1 ? '' : 's',
                ),
                ['photo_count' => $count],
            ),
            default => Finding::healthy(
                $this->key(),
                'gbp.photos_present',
                sprintf('Your listing has %d photos.', $count),
                ['photo_count' => $count],
            ),
        };
    }
}
