<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Contracts\TenantScoped;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LogicException;

/**
 * Constrains every query on a tenant-owned model to the tenant in context.
 *
 * The application half of the boundary. PostgreSQL row-level security sits
 * beneath it, but this scope is what makes the application *correct* rather than
 * merely rescued — RLS catches a query that forgot to filter, never one that
 * filtered by the wrong tenant.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! $model instanceof TenantScoped) {
            // Reachable only by applying this scope to a model that uses neither
            // tenancy trait, which would silently filter on a column it has no
            // opinion about. Loud is the only safe option on this path.
            throw new LogicException(sprintf(
                '%s is scoped by TenantScope but does not implement %s. Add the '
                .'interface alongside the tenancy trait.',
                $model::class,
                TenantScoped::class,
            ));
        }

        // Qualifying the column is not cosmetic: an unqualified business_id in a
        // query joining another tenant-owned table is ambiguous, and PostgreSQL
        // says so at runtime rather than at review.
        $builder->where(
            $model->qualifyColumn($model->tenantKeyName()),
            Tenancy::idOrFail(),
        );
    }
}
