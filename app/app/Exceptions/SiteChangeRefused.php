<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Actuation\AdapterOutcome;
use RuntimeException;

/**
 * A change to somebody else's website was asked for and refused.
 *
 * ⛔ **THE FIRST OF THESE IS RULE 32 AND IS THE REASON THE TYPE EXISTS.** `29`
 * §2: *every site change snapshots its prior state and is reversible.* A change
 * set carrying an empty `before_snapshot` is an edit nobody can undo, and the
 * only safe moment to say so is before it is written — afterwards the page is
 * already different and there is nothing left to restore it from.
 *
 * ⚠️ **A REFUSAL HERE IS A PROGRAMMING ERROR, NOT A CONDITION TO RECOVER FROM.**
 * The site being unreachable is {@see AdapterOutcome}'s
 * business and is a return value. This is *"you asked us to do something we
 * have promised never to do"*, and catching it to proceed is the promise being
 * broken one indirection away.
 */
final class SiteChangeRefused extends RuntimeException
{
    public static function withoutSnapshot(string $url): self
    {
        return new self(sprintf(
            'Refusing to open a change set for [%s] with an empty before_snapshot. `29` rule 32 requires '
            .'every site change to snapshot its prior state and be reversible, and a change with nothing to '
            .'restore from is neither. Read the page through CmsAdapter::snapshot() first — and if the '
            .'adapter returned nothing, that is the answer: this deployment cannot reverse a write to that '
            .'site, so it must not make one.',
            $url,
        ));
    }

    public static function withoutChange(string $url): self
    {
        return new self(sprintf(
            'Refusing to open a change set for [%s] with an empty after_snapshot. A change set that writes '
            .'nothing is not a change, and recording one would put a row on the owner\'s "what we changed" '
            .'log describing an edit that never happened.',
            $url,
        ));
    }

    /**
     * ⛔ **THE INVERSE OF *"THIS PAGE DID NOT EXIST"* IS NOT A FIELD MAP** (5771).
     * A creation's `before` is a recorded absence, so swapping the two sides
     * would ask an adapter to write `_page_state: absent` onto a stranger's page
     * as though it were content. Undoing a creation is an **unpublish**, through
     * `CmsAdapter::unpublishPage()`, and `SiteChanges::revert()` routes it there.
     */
    public static function creationCannotBeInverted(string $url): self
    {
        return new self(sprintf(
            'Refusing to invert the change set that created [%s]. A creation has no prior field values to '
            .'swap back — its before_snapshot records that nothing was published at that address — so an '
            .'inverted write would hand a snapshot marker to a website as page content. Undo a creation '
            .'with CmsAdapter::unpublishPage(): this platform takes a page it created off the public site '
            .'and never deletes anything on a customer\'s website.',
            $url,
        ));
    }

    public static function alreadyApplied(int $id): self
    {
        return new self(sprintf(
            'Site change [%d] has already been applied. Applying twice would stamp a second applied_at over '
            .'the first and leave the site holding one write with two records of it; open a new change set '
            .'instead, so the prior state it captures is the one actually on the page.',
            $id,
        ));
    }

    public static function notApplied(int $id): self
    {
        return new self(sprintf(
            'Site change [%d] was never applied, so there is nothing on the site to undo. Reverting it would '
            .'write a rollback into the owner\'s log for a change they never received.',
            $id,
        ));
    }

    public static function alreadyRolledBack(int $id): self
    {
        return new self(sprintf(
            'Site change [%d] has already been rolled back. A second revert would overwrite who undid it, '
            .'and telling an owner undo apart from an automatic one is the whole reason that column exists.',
            $id,
        ));
    }

    public static function withoutReason(int $id): self
    {
        return new self(sprintf(
            'Refusing to roll back site change [%d] without a reason. The reason is what slice J shows the '
            .'owner in plain words and what slice H quarantines an automation on; an empty one leaves both '
            .'surfaces saying only that something was undone.',
            $id,
        ));
    }
}
