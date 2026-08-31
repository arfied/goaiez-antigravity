<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-owned data is touched with no tenant in context.
 *
 * This is the fail-closed half of the boundary. The alternative — returning null
 * and letting the query run unscoped — is a cross-tenant read, which `29` §2
 * rule 28 makes a Blocker rather than a bug.
 *
 * Seeing this in a job or a console command usually means the tenant was never
 * established for that entry point: Context propagates from a request, but a
 * scheduled command starts with nothing. Set it explicitly rather than relaxing
 * the caller.
 */
final class TenantNotResolved extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No tenant in context. Tenant-owned data cannot be queried until a '
            .'business is established via Tenancy::set(). If this is a job or a '
            .'console command, set the tenant explicitly — Context propagates '
            .'from a request, but not from the scheduler.'
        );
    }
}
