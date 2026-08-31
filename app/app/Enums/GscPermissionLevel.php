<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a connected Google account may do with one Search Console property.
 *
 * ⚠️ **GOOGLE DOCUMENTS TWO DIFFERENT SPELLINGS OF THESE VALUES AND THIS ENUM
 * ACCEPTS BOTH.** The REST reference page for the Sites resource
 * (https://developers.google.com/webmaster-tools/v1/sites, read 2026-08-05)
 * lists `siteOwner`, `siteFullUser`, `siteRestrictedUser`, `siteUnverifiedUser`.
 * The live discovery document
 * (https://searchconsole.googleapis.com/$discovery/rest?version=v1, revision
 * **20260804**) lists the same enum as `SITE_OWNER`, `SITE_FULL_USER`,
 * `SITE_RESTRICTED_USER`, `SITE_UNVERIFIED_USER`, plus a
 * `SITE_PERMISSION_LEVEL_UNSPECIFIED` the reference page does not mention at
 * all.
 *
 * An implementation written from either page alone is a coin flip, and the
 * failure is silent in the worst direction: an unmatched value becomes "unknown",
 * which this codebase would then treat as "cannot read", and a tenant who has
 * connected correctly is told their property is unavailable forever. Decision
 * 684's shape — `current_period_end` moved to `items.data[]` and the plausible
 * line yielded null with no error — a third time after 277 and 255.
 *
 * So {@see from()} normalises rather than matching, and
 * `SITE_PERMISSION_LEVEL_UNSPECIFIED` maps to {@see self::Unknown} deliberately
 * rather than being dropped: a level Google declines to state is not the same
 * fact as a level we failed to parse, but the consequence is identical and
 * pretending otherwise would invent a distinction the wire does not carry.
 *
 * The backing value is the reference page's lowerCamel form, because that is
 * what `webmasters/v3` actually puts on the wire today and a stored value should
 * look like what arrived.
 */
enum GscPermissionLevel: string
{
    case Owner = 'siteOwner';
    case FullUser = 'siteFullUser';
    case RestrictedUser = 'siteRestrictedUser';
    case UnverifiedUser = 'siteUnverifiedUser';

    /**
     * Neither documented spelling matched, or Google declined to state one.
     *
     * A real case rather than a null, for decision 1084's reason applied one
     * level down: `?GscPermissionLevel` puts "Google said nothing" and "we could
     * not read what Google said" into the same absence, and the second is a bug
     * report while the first is not.
     */
    case Unknown = 'unknown';

    /**
     * Read whichever spelling arrived.
     */
    public static function fromWire(mixed $value): self
    {
        if (! is_string($value) || $value === '') {
            return self::Unknown;
        }

        return match (strtoupper(str_replace('_', '', $value))) {
            'SITEOWNER' => self::Owner,
            'SITEFULLUSER' => self::FullUser,
            'SITERESTRICTEDUSER' => self::RestrictedUser,
            'SITEUNVERIFIEDUSER' => self::UnverifiedUser,
            default => self::Unknown,
        };
    }

    /**
     * Whether this level can return Search Analytics data at all.
     *
     * ⚠️ **A RESTRICTED USER CAN, AND THE INSTINCT IS THAT THEY CANNOT.** Google's
     * own permissions page (https://support.google.com/webmasters/answer/7687615,
     * read 2026-08-05) gives a restricted user "simple view rights on most data"
     * and its feature table grants them the Performance report. Excluding them
     * would refuse a perfectly readable property and send the owner to fix a
     * permission that is already sufficient.
     *
     * An unverified user is the one that genuinely cannot: the property appears
     * in `sites.list` because verification was started, and every read against it
     * fails. Storing that choice would leave the owner looking at "no data yet"
     * forever, which decision 1084 forbids in as many words — an absence rendered
     * as a measurement.
     *
     * `Unknown` returns false, which is the fail-closed direction: an unreadable
     * answer produces a refusal the owner can act on, never a silent connection
     * that never reports anything.
     */
    public function canReadPerformance(): bool
    {
        return match ($this) {
            self::Owner, self::FullUser, self::RestrictedUser => true,
            self::UnverifiedUser, self::Unknown => false,
        };
    }

    /**
     * Outcome language for the one case an owner has to do something about.
     */
    public function refusalReason(): ?string
    {
        return match ($this) {
            self::UnverifiedUser => 'property_not_verified',
            self::Unknown => 'permission_level_unreadable',
            default => null,
        };
    }
}
