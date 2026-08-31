<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\GbpConnectionStatus;
use App\Enums\GbpProvider;
use Database\Factories\GbpConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One location's Google Business connection.
 *
 * ⚠️ **`App\Services\Gbp\GbpConnections` is the only thing in `app/` that may
 * read or write this**, held there by a lint. The chokepoint is not ceremony:
 * every row here decides which third party a tenant's listing is reached
 * through, and a second writer is how a location ends up connected to an account
 * ref nobody checked the provenance of.
 *
 * It keeps its global scope. Decision 401 settled the shape of that choice —
 * `Tenancy::idOrFail()` *throws* rather than filtering to nothing, so a scoped
 * model fails loudly on any path that forgot a tenant, while an unscoped one
 * quietly returns whatever the policy permits. Nothing public reads a connection,
 * so there is no reason to be the exception.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property GbpProvider $provider
 * @property GbpConnectionStatus $status
 * @property ?string $provider_profile_ref
 * @property ?string $account_ref
 * @property ?string $external_label
 * @property ?Carbon $connected_at
 * @property ?Carbon $disconnected_at
 * @property ?Carbon $last_checked_at
 * @property ?string $last_error
 * @property ?string $sync_cursor
 * @property ?Carbon $last_synced_at
 */
final class GbpConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<GbpConnectionFactory> */
    use HasFactory;

    /**
     * `business_id` stays guarded — it is the tenant key, and mass-assigning it
     * from request input is how a row crosses the boundary. `account_ref` is
     * guarded for a second reason: it arrives on a **redirect from a third
     * party**, so the one column that decides whose reviews we read is never
     * fillable from an array the request had a hand in.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'account_ref'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GbpProvider::class,
            'status' => GbpConnectionStatus::class,
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Whether this connection can be read through right now.
     *
     * Both halves are required and neither implies the other: a `connected` row
     * always carries an account ref (the database says so), and a row carrying
     * one may still have been disconnected since.
     */
    public function isUsable(): bool
    {
        return $this->status === GbpConnectionStatus::Connected
            && $this->account_ref !== null;
    }
}
