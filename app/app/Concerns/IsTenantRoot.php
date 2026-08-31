<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Scopes\TenantScope;

/**
 * Applied to the tenant root — Business, and nothing else.
 *
 * The root is tenant-owned data like any other table: acting as business A, you
 * must not read business B's row. But it differs from [[BelongsToTenant]] in two
 * ways that make sharing one trait worse than having two:
 *
 *   1. It is scoped on its own primary key. There is no `business_id` column on
 *      `businesses`; the row *is* the tenant.
 *   2. It cannot fill that key on create. A business is created during signup,
 *      before any tenant exists, and the id is assigned by the database. A
 *      creating hook here would throw on the one operation that must work
 *      without a tenant in context.
 *
 * So: the same scope, no auto-fill. Row-level security still applies to the
 * table, and the convention tests discover it through this trait exactly as they
 * discover ordinary tenant-owned tables through the other one.
 *
 * Creating a business therefore happens outside tenancy, and reading one back
 * immediately afterwards requires establishing it — see Tenancy::actingAs().
 */
trait IsTenantRoot
{
    protected static function bootIsTenantRoot(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * The root is scoped on its own key.
     */
    public function tenantKeyName(): string
    {
        return $this->getKeyName();
    }
}
