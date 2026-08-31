<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ImpersonationCapability;
use RuntimeException;

/**
 * Thrown when support may not do the thing they are attempting.
 *
 * Two shapes, one exception, on purpose. `because()` refuses to *open* a
 * session — the wrong role, a thin reason, a missing ticket, somebody else
 * already inside. `capability()` refuses one *action* inside an open session,
 * from `28` §9.4's blocklist. They are the same class because the audience is
 * the same person at the same terminal and the useful thing in both cases is a
 * sentence they can act on, not a taxonomy.
 *
 * ⚠️ **The message is written for an agent, never for a customer.** These are
 * only ever raised on a staff path — either the console, or a tenant screen
 * being driven by staff — so they name the rule and say what to do instead.
 * A refusal an agent cannot act on becomes a ticket to us about our own tool.
 */
final class ImpersonationRefused extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?ImpersonationCapability $capability = null,
    ) {
        parent::__construct($message);
    }

    public static function because(string $message): self
    {
        return new self($message);
    }

    public static function capability(ImpersonationCapability $capability): self
    {
        return new self($capability->refusal(), $capability);
    }
}
