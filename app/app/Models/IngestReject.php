<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\PixelRefusal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.7's dead-letter queue, as an hourly rollup —
 * see the creating migration for why this is a bucket with a count rather than
 * a row per rejected request, and `App\Services\Pixel\IngestRejects` for the
 * one writer.
 *
 * ⚠️ **READ-MOSTLY FROM HERE**, on `PixelMonthlyUsage`'s precedent. The
 * increment is an atomic `INSERT … ON CONFLICT` written by
 * `App\Services\Pixel\IngestRejects` directly, never an Eloquent
 * `increment()` — two concurrent requests both reading, both adding one and
 * both saving is decision 350's shape.
 *
 * @property-read int $id
 * @property int $business_id
 * @property PixelRefusal $reason
 * @property ?string $origin
 * @property Carbon $hour
 * @property int $rejects
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class IngestReject extends Model implements TenantScoped
{
    use BelongsToTenant;

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
            'reason' => PixelRefusal::class,
            'hour' => 'datetime',
            'rejects' => 'integer',
        ];
    }
}
