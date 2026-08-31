<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of the three rooms of the contact list is being shown (`34` §1.1).
 *
 * ⚠️ **A TYPE RATHER THAN TWO BOOLEANS, AND THAT IS THE POINT.** `page()` took
 * `bool $archived` while archive was the only alternative view; delete makes a
 * third, and two booleans for three mutually exclusive states has a fourth
 * combination that means nothing — `archived: true, deleted: true` would read
 * as a room, get a filter written for it, and quietly return the empty set. The
 * enum has no such value to pass.
 *
 * The three are exclusive as *views*, never as *states*: a contact can be both
 * archived and deleted at once, which is exactly why restoring one returns them
 * to the other rather than to the list (1541).
 */
enum ContactView: string
{
    /** The default room: not archived, not deleted, not merged away. */
    case Active = 'active';

    /**
     * The owner's tidying (`44` §8, decision 1327). Deliberately excludes a
     * contact who was archived and then deleted — they are in the later room,
     * and listing them in both would offer two restores that mean different
     * things.
     */
    case Archived = 'archived';

    /**
     * D-205's tombstone (1540): deleted, and still inside the seven days the
     * owner can undo it in. ⚠️ **The window is part of this view's definition,
     * not a decoration on it** — a contact leaves this room at day seven while
     * the row stays exactly where it was, which is what makes the door's
     * "renders only while occupied" rule (1502) do real work here.
     */
    case RecentlyDeleted = 'recently_deleted';
}
