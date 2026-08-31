<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\ProofNumberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One tenant's three proof numbers for one period (`28` §3.3).
 *
 * ⚠️ **DERIVED, NOT AUTHORED.** Nothing here is a fact in its own right — every
 * column is a count of rows that live somewhere else, and `ProofNumbers::
 * recompute()` is the only thing in `app/` allowed to write it. That is what
 * keeps §3.3's *"no estimates, no modeled numbers, ever"* true: the moment a
 * second writer sets one of these by hand, the number stops being a count and
 * becomes an assertion, and nothing downstream can tell the difference.
 *
 * Tenant-scoped like everything it counts, with RLS beneath the global scope.
 */
final class ProofNumber extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ProofNumberFactory> */
    use HasFactory;

    /** The all-time bucket's period value. */
    public const string ALL = 'all';

    /**
     * `business_id` is filled from ambient tenancy by the trait, and the three
     * counts are guarded because a mass-assigned proof number is exactly the
     * authored figure this model exists not to hold.
     */
    protected $guarded = ['id', 'business_id', 'google_reviews', 'leads', 'recovered'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'google_reviews' => 'integer',
            'leads' => 'integer',
            'recovered' => 'integer',
            'computed_at' => 'immutable_datetime',
        ];
    }
}
