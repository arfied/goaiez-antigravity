<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacesSku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One metered Google Places request (row 2 slice B).
 *
 * NOT tenant-owned, and on the TenancyTest allowlist for the same reason
 * PublicAudit is: the free audit runs before any tenant exists, so most rows
 * here have no business to belong to. `business_id` is nullable and unscoped —
 * it exists so that row 3's in-tenant calls can be attributed to a cost cap,
 * and a null means the call was platform spend on the public path.
 *
 * That nullability is exactly why this cannot be a BelongsToTenant model: a
 * global scope would hide every public-audit row from the query that enforces
 * the daily budget, and RLS would refuse them outright.
 *
 * APPEND-ONLY IN PRACTICE. Nothing updates a metered call; a ledger that can be
 * edited is not evidence of anything.
 *
 * @property-read int $id
 * @property PlacesSku $sku
 * @property ?string $place_id
 * @property bool $served_from_cache
 * @property int $unit_cents_per_thousand
 * @property ?int $business_id
 * @property string $purpose
 * @property Carbon $called_at
 */
final class PlacesApiCall extends Model
{
    /**
     * Written once, never touched again — there is no updated_at to maintain.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Calls that actually cost money — billable requests that left the building.
     *
     * @param  Builder<PlacesApiCall>  $query
     */
    public function scopeBillable(Builder $query): void
    {
        $query->where('served_from_cache', false)
            ->where('unit_cents_per_thousand', '>', 0);
    }

    /**
     * Calls made on a given day.
     *
     * @param  Builder<PlacesApiCall>  $query
     */
    public function scopeOnDay(Builder $query, Carbon $day): void
    {
        $query->whereBetween('called_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sku' => PlacesSku::class,
            'served_from_cache' => 'boolean',
            'unit_cents_per_thousand' => 'integer',
            'business_id' => 'integer',
            'called_at' => 'datetime',
        ];
    }
}
