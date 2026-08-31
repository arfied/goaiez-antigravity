<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\FirstWeekWinType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One tenant's First 7-Day Results Path (`28` §3.2, `DATA-MODEL` §Trust &
 * early results).
 *
 * ⚠️ **DERIVED, NOT AUTHORED — `ProofNumber`'s precedent, applied here.**
 * `App\Services\Trust\FirstWeekPath` is the only thing in `app/` allowed to
 * write this row; an `ArchitectureTest` lint in `OutboundTest.php` holds that.
 * Every column but the primary key and the tenant key is guarded for the same
 * reason `ProofNumber` guards its three counts: a mass-assigned `step` or
 * `win_type` is an authored claim about what happened this week wearing a
 * derived column's clothes.
 *
 * Tenant-scoped like everything it reads, with RLS beneath the global scope —
 * and `business_id UNIQUE` at the database, because there is one first week per
 * tenant, ever.
 *
 * ⚠️ **NO `HasFactory`, AND THAT IS THE POINT (1886).** A factory shipped with
 * this model, had no caller, and could not have had a working one: `$guarded`
 * lists every column, so a factory's ordinary mass assignment would have dropped
 * all of them and inserted a row with a null `started_at`. `FirstWeekPath::
 * begin()` is the only way a row is created — that is the same chokepoint an
 * `OutboundTest.php` lint already enforces, and a factory is the standard way
 * around it. A test that needs a run in a particular state calls `begin()` and
 * then `forceFill()`s, which is what every test in `tests/Feature/Trust` does.
 *
 * ⚠️ **`DATA-MODEL`'s `state JSONB` IS NOT ON THIS TABLE** — dropped for having
 * no writer and no reader, with its absence pinned by a test. The migration's
 * own docblock carries the reasoning.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $step
 * @property ?FirstWeekWinType $win_type
 * @property ?Carbon $win_at
 * @property Carbon $started_at
 * @property ?Carbon $completed_at
 */
final class FirstWeekRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * `DATA-MODEL`'s column list has no `created_at`/`updated_at` — `started_at`
     * and `completed_at` already carry the lifecycle this table needs, and a
     * `computed_at`-style third timestamp would be a fourth clock on a row that
     * only ever has two meaningful moments.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'step',
        'win_type',
        'win_at',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'win_type' => FirstWeekWinType::class,
            'win_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
