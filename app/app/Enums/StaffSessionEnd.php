<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an internal session was ended (`28` §9.1: *"session lifetime 12h, idle
 * timeout 30m"*).
 *
 * Two cases rather than one boolean, and the reason is the message. A staff
 * member told *"signed out after 30 minutes of inactivity"* when the twelve-hour
 * ceiling actually fired will sign back in, work for a minute, and be signed out
 * again with the same wrong explanation — the sentence they were given names a
 * cause they can do something about, and it is not the one that happened. The
 * ceiling is the opposite: nothing they do prevents it, and saying so is what
 * stops it reading as a bug.
 *
 * Not a database column, so no migration and no `Rule::enum()` — this is a
 * transient reason carried from the middleware to the sign-in screen. It is an
 * enum rather than two strings so that adding a third way a session can end
 * cannot be done without deciding what the person is told.
 */
enum StaffSessionEnd: string
{
    /** The twelve-hour ceiling, measured from the sign-in that opened it. */
    case Absolute = 'absolute';

    /** Thirty minutes with no request on it. */
    case Idle = 'idle';

    /**
     * A session that re-authenticated itself from a remember-me cookie.
     *
     * ⚠️ **This case is the reason the other two are not decoration**, and it
     * exists as a runtime refusal rather than as a rule about call sites. See
     * `App\Support\Auth\StaffSession::remember()`.
     */
    case Remembered = 'remembered';

    /**
     * What the person is told on the sign-in screen.
     *
     * Outcome language (`22`): each names what happened to them, never the
     * mechanism that did it. None of the three mentions a middleware, a cookie
     * or a session, because none of those is a thing the reader controls.
     */
    public function message(): string
    {
        return match ($this) {
            self::Absolute => 'Your staff sign-in has reached its 12-hour limit. Sign in again to continue.',
            self::Idle => 'You were signed out after 30 minutes of inactivity. Sign in again to continue.',
            self::Remembered => 'Staff accounts sign in fresh each time. Sign in again to continue.',
        };
    }
}
