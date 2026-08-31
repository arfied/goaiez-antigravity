<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A tenant-owned operation was handed a business that is not the one in context.
 *
 * The gap this closes is narrow and specific. Passing a `Business` around reads
 * as self-scoping — you cannot load another tenant's business, because the global
 * scope hides it and row-level security blocks the raw query underneath. But an
 * *unsaved* model is not loaded at all:
 *
 *     $b = new Business;
 *     $b->id = $someoneElsesId;
 *
 * If a service took that id and established it as the tenant, the argument would
 * have become the authorization. So services take the business as documentation
 * of intent and take the *tenant* from context, then assert the two agree. The
 * caller has to have genuinely resolved the tenant, and RLS is still underneath.
 *
 * Never a fallback. A mismatch is a bug in the caller, not a condition to
 * recover from.
 */
final class TenantMismatch extends RuntimeException
{
    public function __construct(int $expected, int|string|null $given)
    {
        parent::__construct(sprintf(
            'Tenant in context is %d but the operation was given business %s. '
            .'Establish the correct tenant with Tenancy::set() rather than '
            .'passing a business across the boundary.',
            $expected,
            $given === null ? 'null' : (string) $given,
        ));
    }
}
