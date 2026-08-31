<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an internal account may not be created or moved as asked.
 *
 * ⚠️ **Every refusal here is a lockout risk in one direction or an escalation
 * in the other**, which is why they are refusals rather than validation
 * messages: `StaffDirectory` is the only writer of `users.role` in the whole
 * application (740), so a change it permits is a change nothing else can undo
 * from inside the application. The last `super_admin` demoting themselves is not
 * a mistake somebody fixes on the next screen; it is a database edit on a live
 * system.
 *
 * ⚠️ **The message is written for an operator, never for a customer** —
 * `ImpersonationRefused`' rule, for its reason. These are only ever raised on a
 * staff path, so they name the rule and say what to do instead.
 */
final class StaffChangeRefused extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $message): self
    {
        return new self($message);
    }
}
