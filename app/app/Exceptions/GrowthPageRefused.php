<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Content\GrowthPages;
use App\Services\Content\QualityOutcome;
use RuntimeException;

/**
 * Something was asked of a growth page that this platform has promised not to do.
 *
 * ⚠️ **A REFUSAL HERE IS A PROGRAMMING ERROR, NOT A CONDITION TO RECOVER FROM**
 * — `SiteChangeRefused`'s rule. A page failing the quality gate is an ordinary
 * outcome and is a return value ({@see QualityOutcome});
 * these are *"you asked us to publish something we already refused"*, and
 * catching one to proceed is the promise being broken one indirection away.
 */
final class GrowthPageRefused extends RuntimeException
{
    public static function withoutSlug(): self
    {
        return new self(
            'Refusing to draft a growth page with no slug. The slug is the path the page will live at on '
            .'the tenant\'s own site, and a blank one collides with every other blank one under the unique '
            .'index — so the second candidate of the day would fail on a database error rather than on the '
            .'thing that is actually wrong with it.',
        );
    }

    /**
     * ⚠️ **ITS OWN FACTORY BECAUSE THE PIPELINE WORKS IN IDS** (5661). Every
     * caller outside `GrowthPages` hands over an `int`, so *"that page is not
     * this tenant's, or no longer exists"* is a case the row-carrying methods
     * never had — and `find()` returning null there would otherwise become a
     * TypeError three lines later, naming the wrong thing.
     */
    public static function missing(int $id): self
    {
        return new self(sprintf(
            'Growth page [%d] is not this tenant\'s, or no longer exists. The global scope and row-level '
            .'security both answer that question, and neither distinguishes "deleted" from "somebody '
            .'else\'s" on purpose — telling them apart would confirm the existence of another tenant\'s row.',
            $id,
        ));
    }

    public static function alreadyPublished(int $id): self
    {
        return new self(sprintf(
            'Growth page [%d] is already published. Re-gating or re-holding it would change what this '
            .'application believes about a page that is already on somebody\'s website, without changing the '
            .'website; taking a published page down is a site change and goes through SiteChanges.',
            $id,
        ));
    }

    public static function withoutClearingTheGate(int $id): self
    {
        return new self(sprintf(
            'Refusing to put a lapsing hold on growth page [%d], which has not cleared the quality gate. '
            .'`29` requires AUTO-WITH-HOLD to proceed on silence, so a release time on a page the gate '
            .'refused publishes exactly the page that was refused — and the two rows are indistinguishable '
            .'afterwards. Gate it first (%s); a page held for a person carries no release time at all.',
            $id,
            GrowthPages::class.'::gate()',
        ));
    }

    public static function withAPastHold(int $id): self
    {
        return new self(sprintf(
            'Refusing to hold growth page [%d] until a moment that has already passed. AUTO-WITH-HOLD '
            .'proceeds on silence after a window in which the owner could have spoken; a window that closed '
            .'before it opened is not a hold, it is a publication with a hold\'s paperwork.',
            $id,
        ));
    }
}
