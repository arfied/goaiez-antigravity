<?php

declare(strict_types=1);

namespace App\Support\Campaigns;

use App\Services\Campaigns\CampaignPacks;
use App\Services\Messaging\Composer\ComposedText;

/**
 * One message of a pack, as a tenant sees it before they turn the pack on.
 *
 * T296 §B2's preview rule, in full: *"preview shows the ACTUAL messages,
 * {Name} and {Link} slots visible — 'what you see is what sends.'"* Two halves
 * that are two different strings, so this carries both:
 *
 *  - {@see self::$template} is the authored body with its slots showing, which
 *    is the half the rule names. A tenant reading it can see where their
 *    customer's name lands.
 *  - {@see self::$composed} is what a handset receives — the business prefix,
 *    the body with a probe name and the real short link substituted, the
 *    "Sent via GO AI EZ" disclosure and the opt-out sentence — measured and
 *    proved to fit a single segment.
 *
 * ⛔ **THE SECOND IS NOT DECORATION AND IS THE REASON THIS TYPE EXISTS.** A
 * preview of the template alone would show a tenant a message shorter than the
 * one that sends, missing the two sentences carriers require, and would say
 * nothing about whether it fits. `Campaigns::confirm()` already refuses a
 * template that cannot compose; a preview that could not see the same refusal
 * would be showing somebody a campaign they are about to be told they cannot
 * run.
 *
 * ⚠️ **IT IS A PROBE AND NOT A PROOF**, on `Campaigns`' own wording: a long
 * first name can still push a real send over the budget, and that recipient is
 * refused individually. What this catches is the template mistake, which is the
 * one a person standing in front of the screen can fix.
 *
 * @see CampaignPacks::preview()
 */
final readonly class PackPreview
{
    public function __construct(
        public int $dayOffset,
        public string $template,
        public ComposedText $composed,
    ) {}
}
