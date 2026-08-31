<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Platform index from a Zernio account id to a location.
 *
 * ⚠️ Not tenant-scoped — see the creating migration. GbpConnections is the only
 * writer; the webhook path is the only reader outside that service.
 *
 * ⚠️ **AND IT OUTLIVES THE TENANT, WHICH IS WHY `revocation_owed_at` IS HERE**
 * (4880). When a business is destroyed under `28` §9.5, its rows in
 * `gbp_connections` go with it and this row does not — no foreign key, for the
 * same reason `zernio_account_days` has none (4730). That makes this the only
 * surviving record of an account Zernio still holds `business.manage` on, and
 * the column is what says the revocation has not happened yet. Null means *not
 * owed*, never *already revoked*: a revoked account has no row at all.
 *
 * @property-read int $id
 * @property string $account_ref
 * @property int $business_id
 * @property int $location_id
 * @property ?Carbon $revocation_owed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class GbpAccountBinding extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revocation_owed_at' => 'immutable_datetime',
        ];
    }
}
