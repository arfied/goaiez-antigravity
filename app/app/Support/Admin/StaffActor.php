<?php

declare(strict_types=1);

namespace App\Support\Admin;

/**
 * How an internal staff member is named in an audit trail.
 *
 * ## The two labels mean different things, and one screen had them backwards
 *
 * This codebase writes two actor vocabularies, and the difference is **whose
 * account was acted on**, never which screen did it:
 *
 *   `user:{id}`     somebody acting inside their *own* account. Sixteen files
 *                   emit it — the owner's screens, the setup wizard, and a staff
 *                   member working on a business they themselves own.
 *                   `AuditExplorer`'s docblock states this in as many words, and
 *                   it is why such a person does not appear in the staff index.
 *
 *   `support:{id}`  internal staff acting on an account that is **not theirs**.
 *                   `28` §9.4 fixes this format for an internal write.
 *
 * ⚠️ **`Support\Accounts` filed its *read* entry under `user:{id}`, and that was
 * not merely inconsistent — it was the wrong one of the two.** The action is
 * literally named `business.viewed_by_staff`: internal staff opening a customer's
 * account, which is the definition of the second label. One agent, on one screen,
 * appeared under `user:` for looking and `support:` for acting, and the `user:`
 * half asserted the thing that vocabulary exists to deny.
 *
 * ## ⚠️ The rows already written cannot be repaired, and that is why this class
 * answers a plural question
 *
 * `audit_log` is append-only forever (`29` §2 rule 42). Every
 * `business.viewed_by_staff` row filed before this class existed carries
 * `user:{id}` and **always will** — they are permanently mislabelled, and no
 * migration may touch them, because rewriting an audit trail to make it tidier
 * is precisely what an append-only trail exists to prevent.
 *
 * So converging the writer does not converge the data. Any reader asking *"which
 * rows are this agent"* has to accept both labels regardless of how long ago the
 * writer was fixed, which is what {@see self::labelsFor()} is for. **A reader
 * that only knows the new label silently under-reports history**, and an audit
 * search that quietly returns less than the truth is worse than one that fails.
 */
final class StaffActor
{
    /** Internal staff acting on an account that is not their own. */
    public const INTERNAL_PREFIX = 'support:';

    /** Anybody acting inside their own account. */
    public const OWN_PREFIX = 'user:';

    /**
     * The label for internal staff acting on somebody else's account.
     *
     * The null case is `support` rather than `support:0`. A missing id means no
     * authenticated user, which on these screens cannot happen through the door
     * — every one is behind `auth` — so a synthetic id would invent a person,
     * while the bare word records that the act happened and that we could not
     * say who. `28` §9.4 is about attributing staff acts, and a fabricated
     * attribution is worse for that purpose than an honest blank.
     *
     * ⚠️ `int|string|null` because that is what `Auth::id()` returns by
     * contract, not to be accommodating. Laravel's authenticatable key may be a
     * string, and the alternative — narrowing to `?int` and casting at the call
     * site — moves an assumption about the key type into every screen that
     * writes an audit row. `AccountAudit` and `AdminForm` already cast at the
     * call site; this is the same fact, admitted once.
     */
    public static function internal(int|string|null $id): string
    {
        return $id === null ? 'support' : self::INTERNAL_PREFIX.$id;
    }

    /**
     * Every actor label under which this agent's entries may be filed.
     *
     * ⚠️ **BOTH, ALWAYS, AND NOT AS A TRANSITION MEASURE.** It is tempting to
     * read this as a migration shim that can be deleted once old rows age out.
     * They do not age out: `audit_log` has no retention policy and is
     * append-only, so a 2026 read filed under `user:{id}` is still there and
     * still mislabelled whenever this is next read. **Deleting the `user:` entry
     * from this list at any future date silently drops those rows from the
     * answer.**
     *
     * @return list<string>
     */
    public static function labelsFor(int|string $id): array
    {
        return [self::INTERNAL_PREFIX.$id, self::OWN_PREFIX.$id];
    }

    /**
     * Whether this label names internal staff acting on somebody else's account.
     *
     * ⚠️ **A POSITIVE TEST, AND THE NEGATIVE ONE IS NOT EQUIVALENT.**
     * `Livewire\Account\Settings` asks the inverse — *"does `paused_by` not start
     * with `user:`"* — to tell an owner that support stopped their account
     * (decision 825). That reads `businesses.paused_by`, not this, and it is
     * **deliberately not changed here**: its fail-open direction is what stops
     * 825's ticket, and re-pointing an owner-facing message is a different change
     * with a different blast radius than fixing an audit label.
     *
     * The difference bites on the sentinels. `TenantPause` writes the bare word
     * `owner` when there is no authenticated id, and the negative test reads that
     * as support — telling an owner that we paused their account when they did.
     * Anything reasoning about an *audit* actor should ask this instead.
     */
    public static function isInternal(string $actor): bool
    {
        return str_starts_with($actor, self::INTERNAL_PREFIX);
    }
}
