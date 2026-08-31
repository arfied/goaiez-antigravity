<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Contracts\TenantScoped;
use App\Models\Business;
use App\Scopes\TenantScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every model owned by a business.
 *
 * Two things, together: the query scope, and filling the key on create. Both are
 * needed — a scope without the auto-fill produces rows with a null tenant that
 * the scope then hides from everyone, which reads as data loss.
 *
 * A model using this trait is also asserted to have row-level security enabled
 * and forced on its table, by a convention test that fails the build. That test
 * discovers tables *through* this trait, so a tenant-owned model that skips it
 * loses both layers at once and nothing notices.
 *
 * For the tenant root itself — Business, which is scoped on its own primary key
 * rather than a foreign key, and cannot fill that key from context because it
 * does not exist yet — use [[IsTenantRoot]] instead.
 *
 * Specification: CLAUDE.md §Critical rules, .claude/skills/multi-tenancy.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (! $model instanceof TenantScoped) {
                return;
            }

            $key = $model->tenantKeyName();

            // ??= rather than an unconditional assignment: a caller inside
            // Tenancy::actingAs() may legitimately set the key itself, and
            // overwriting it there would silently move the row.
            $model->{$key} ??= Tenancy::idOrFail();
        });
    }

    /**
     * The column carrying the tenant. `DATA-MODEL.md` uses `business_id`
     * throughout — never `tenant_id`.
     */
    public function tenantKeyName(): string
    {
        return 'business_id';
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
