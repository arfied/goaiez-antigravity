<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one tenant's Business Associate Agreement stands.
 *
 * `29` §2 rule 24 holds a PHI tenant's form values at `schema_only` "until the
 * BAA is executed". That phrase had no state anywhere in this codebase:
 * `LegalDocumentType::Baa` versions the *template* — counsel drafts and
 * publishes it exactly like the other twelve documents (decision 420) — and
 * nothing recorded that an agreement had been executed with a given business.
 * So the condition rule 24 hangs on was unanswerable, and `29` §12.2's
 * prelaunch gate ("healthcare-counsel review of the BAA template before the
 * first PHI tenant") had nothing enforcing it.
 *
 * ⚠️ THIS IS THE TENANT-FACING AGREEMENT AND SAYS NOTHING ABOUT OUR VENDORS.
 * An executed BAA here makes *us* the tenant's Business Associate. It does not
 * make Anthropic or OpenAI ours — `docs/SUBPROCESSOR-INVENTORY.md` records that
 * no BAA exists with either — so `Executed` must never be read as permission to
 * put patient text on the wire. `tests/Feature/PhiAnalysisGateTest.php` pins
 * that as a tripwire, because wiring the two together is the helpful-looking
 * change somebody will otherwise make.
 *
 * A `string` column cast to this enum, never a database enum — CLAUDE.md
 * §Critical rules and the convention test that enforces it.
 */
enum BaaStatus: string
{
    /**
     * Opened, unsigned. The state every PHI tenant starts in, and the only one
     * `BaaRecords::open()` writes.
     */
    case Pending = 'pending';

    /**
     * Signed by both sides, against one exact published version of the
     * template. `29` §2 rule 24's condition, and the only case that satisfies
     * it.
     */
    case Executed = 'executed';

    /**
     * Terminated. Read exactly like `Pending` by every gate — an agreement that
     * has ended is not one that is in force.
     */
    case Revoked = 'revoked';

    /**
     * Outcome language (`22`, `29` §5.7): what is true of the agreement, never
     * what the row holds.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not signed yet',
            self::Executed => 'Signed by both sides',
            self::Revoked => 'Ended',
        };
    }

    /**
     * Whether rule 24's "until the BAA is executed" is satisfied.
     *
     * ⚠️ SPELLED AS A METHOD SO A CALLER CANNOT WRITE `!== Pending`, which is
     * the plausible shorthand and treats a revoked agreement as live.
     */
    public function isInForce(): bool
    {
        return $this === self::Executed;
    }
}
