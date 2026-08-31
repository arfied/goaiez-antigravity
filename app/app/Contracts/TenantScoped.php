<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * A model the tenant boundary applies to.
 *
 * Implemented by whichever of the two tenancy traits a model carries —
 * [[BelongsToTenant]] for ordinary tenant-owned models, [[IsTenantRoot]] for
 * Business. Both supply tenantKeyName(); the interface exists so TenantScope can
 * ask for it without reaching into an untyped Model.
 *
 * Declare it explicitly on the model as well as using the trait. A convention
 * test asserts the pair, because a trait alone is invisible to the type system
 * and the interface alone has no behaviour.
 */
interface TenantScoped
{
    /**
     * The column carrying the tenant.
     *
     * `business_id` for ordinary tenant-owned models; the primary key for the
     * tenant root, whose row *is* the tenant.
     */
    public function tenantKeyName(): string;
}
