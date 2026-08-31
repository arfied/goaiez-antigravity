<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\ChangeSet;
use App\Services\Actuation\LogCmsAdapter;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteSnapshot;

/**
 * What happened when an adapter went to read a page — decision 5770.
 *
 * ⛔ **THIS EXISTS BECAUSE "THERE IS NO PAGE HERE" AND "WE COULD NOT SEE THE
 * SITE" WERE THE SAME ANSWER, AND THE FIRST IS THE ONE A CREATION NEEDS.**
 * Decision 5753 found that a growth page is a page that does not exist yet:
 * {@see SiteSnapshot} carried an array of fields and
 * nothing else, so an absent page, an unreachable host and a driver that reads
 * nothing all returned `[]` — and {@see SiteChanges::open()}
 * refuses `[]` by construction, which is rule 32 and stays.
 *
 * ⛔ **THE PERMISSIVE COLLAPSE IS THE WHOLE HAZARD AND IT ONLY RUNS ONE WAY.**
 * Reading {@see self::Unreadable} as {@see self::Absent} means creating a page
 * on a site we could not see — on top of the page that is already there, or on a
 * site that is merely down for an hour. Reading `Absent` as `Unreadable` costs a
 * publication that gets retried. So every arm that widens this enum's meaning is
 * driven by a test, and both directions are asserted separately.
 *
 * ⚠️ **A CREATION'S PRIOR STATE IS NOT NOTHING — IT IS THIS.** *"No page at this
 * URL when we looked"* is a real, recordable fact, and it is what
 * {@see ChangeSet::creating()} writes into
 * `before_snapshot` so that rule 32's guard is satisfied by a *reading* rather
 * than relaxed by an exception.
 */
enum SiteSnapshotState: string
{
    /**
     * We read the page and these are its fields.
     */
    case Read = 'read';

    /**
     * ⚠️ **WE LOOKED, AND NOTHING IS PUBLISHED AT THAT ADDRESS.** The site
     * answered; the collections came back; no published page claims this URL.
     * That is an observation, not a failure, and it is the only state a creation
     * may proceed from.
     */
    case Absent = 'absent';

    /**
     * ⛔ **WE COULD NOT SEE THE SITE.** A timeout, a 5xx, a credential revoked
     * inside WordPress, an ambiguous slug, a REST root that has stopped
     * answering. Nothing may be written or created on this answer.
     */
    case Unreadable = 'unreadable';

    /**
     * ⛔ **NOTHING LOOKED.** {@see LogCmsAdapter} returns this: a driver that
     * transmits nothing has read nothing, which is decision 5528's argument and
     * is deliberately **not** {@see self::Absent}. A log driver that said *"there
     * is no page there"* would have become able to open change sets — and to
     * create pages — on the one deployment that exists.
     */
    case Unread = 'unread';

    /**
     * Whether a change set may be opened on the strength of this reading.
     *
     * ⚠️ **TWO STATES, AND `Absent` IS THE ONE THAT IS NEW.** `Read` opens an
     * edit; `Absent` opens a creation. The other two open nothing.
     */
    public function permitsAChangeSet(): bool
    {
        return $this === self::Read || $this === self::Absent;
    }
}
