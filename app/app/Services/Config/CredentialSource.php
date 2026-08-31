<?php

declare(strict_types=1);

namespace App\Services\Config;

/**
 * Where a credential's value is coming from (`38` Part 1's health board: "shows
 * exactly which key is absent — never a stack trace, never a silent stall").
 *
 * ⚠️ THREE STATES RATHER THAN TWO, AND THE MIDDLE ONE IS THE POINT. D-149 makes
 * `.env` a bootstrap seed, so a key can be working perfectly while the store
 * knows nothing about it. A board with one green light and one red light would
 * show that key as simply *working*, which hides the two facts an operator
 * actually needs: it cannot be rotated from Ops, and clearing it here does not
 * stop the old value being used — the seed underneath keeps answering.
 *
 * Not in `app/Enums`: this is a computed answer about the state of the system,
 * never a column, and putting it there would invite somebody to persist it.
 */
enum CredentialSource: string
{
    /** The encrypted store holds it. This is the state D-149 wants every key in. */
    case Store = 'store';

    /**
     * No stored row; `config/credentials.php` has a value, which is `.env`.
     *
     * Works, and is the correct state on a fresh install — but it is not
     * rotatable from Ops, is not audited, and is not encrypted at rest anywhere
     * but in the file's own permissions.
     */
    case EnvironmentSeed = 'environment_seed';

    /** Nothing anywhere. The key's module degrades; see the manifest's `degradation`. */
    case Absent = 'absent';

    /**
     * How this reads on the board.
     *
     * Outcome language (`22`): what an operator can conclude, not what the enum
     * is called.
     */
    public function label(): string
    {
        return match ($this) {
            self::Store => 'Managed here',
            self::EnvironmentSeed => 'From the environment file',
            self::Absent => 'Not configured',
        };
    }

    /** Whether the vendor has something to authenticate with. */
    public function isConfigured(): bool
    {
        return $this !== self::Absent;
    }
}
