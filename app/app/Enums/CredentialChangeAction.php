<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to a credential (`38` Part 1: "Rotation and every reveal-less
 * set is audit-logged").
 *
 * ⚠️ THERE IS NO `Read` CASE, AND THAT IS DELIBERATE. A credential is read on
 * every vendor call, so logging reads would make this the largest table in the
 * schema within a week and would bury the handful of rows a year that actually
 * matter. `platform_credentials.last_used_at` carries that fact instead, at the
 * resolution the question has.
 *
 * A `string` column cast to this enum, never a database enum (CLAUDE.md).
 */
enum CredentialChangeAction: string
{
    /** A key that had no row now has one. */
    case Set = 'set';

    /**
     * A key that had a row now has a different value.
     *
     * Distinct from `Set` even though the write is the same shape, because the
     * two answer different questions: `Set` is an install step and `Rotated` is
     * a security event. A board showing them as one thing cannot tell an
     * operator whether a key has ever been changed since the day it was pasted.
     */
    case Rotated = 'rotated';

    /**
     * The row is gone and the credential falls back to `.env` — or to nothing.
     *
     * `38` Part 1's degradation rule applies from this moment: "missing
     * credential = the dependent module degrades to its specced failure
     * behavior and the health board shows exactly which key is absent".
     */
    case Cleared = 'cleared';

    /** The value did not move; which account it opens was corrected. */
    case EnvironmentChanged = 'environment_changed';

    /**
     * How this reads in the history list.
     *
     * Outcome language (`22`) — what happened to the key, never the verb the
     * table stores.
     */
    public function label(): string
    {
        return match ($this) {
            self::Set => 'Set for the first time',
            self::Rotated => 'Rotated',
            self::Cleared => 'Cleared',
            self::EnvironmentChanged => 'Environment corrected',
        };
    }
}
