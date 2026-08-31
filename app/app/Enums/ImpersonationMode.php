<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two ways a support agent can be inside a tenant's account (`28` §9.4).
 *
 * A `string` column cast to this enum, never a database enum — `DATA-MODEL`
 * writes the column as `mode imp_mode`, and CLAUDE.md's rule overrides it for
 * the reasons recorded there.
 *
 * The distinction is diagnosis versus repair, and it is worth stating because
 * the two failure modes are not symmetrical. View-only exists so that "I cannot
 * reproduce what you are seeing" stops being an answer; its whole value is that
 * the render is *identical* to the owner's, which is why the acting identity is
 * the owner rather than the agent. Act-as exists to fix something, costs a typed
 * reason and a ticket, and every write it makes is announced to the owner in
 * their own feed.
 */
enum ImpersonationMode: string
{
    /** Diagnose. GET-only, and the database refuses a write regardless. */
    case View = 'view';

    /** Fix. Writes allowed except the blocklist, every one of them attributed. */
    case Act = 'act';

    /**
     * How long a fresh session of this mode lasts.
     *
     * `28` §9.4: 60 minutes viewing, 30 minutes acting. The stronger mode gets
     * the shorter clock, which is the whole shape of the feature in one line.
     *
     * ⚠️ These are minutes, not a `platform_settings` row, and that is
     * deliberate rather than an oversight. `28`'s env block offers
     * `IMPERSONATION_TTL_VIEW` and `IMPERSONATION_TTL_ACT`; making them
     * operator-editable means the ceiling on how long we can be inside a
     * customer's account is a number an operator can raise, quietly, from
     * inside the console the ceiling exists to bound. Decision 502's shape —
     * some values are withheld from the registry on purpose.
     */
    public function ttlMinutes(): int
    {
        return match ($this) {
            self::View => 60,
            self::Act => 30,
        };
    }

    /**
     * Whether a request in this mode may write at all.
     *
     * The blocklist is a second question asked only of the modes that get here.
     */
    public function permitsWrites(): bool
    {
        return $this === self::Act;
    }

    /** What the banner calls this mode, in the owner's language rather than ours. */
    public function label(): string
    {
        return match ($this) {
            self::View => 'Viewing only',
            self::Act => 'Making changes',
        };
    }
}
