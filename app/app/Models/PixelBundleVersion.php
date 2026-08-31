<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PixelBundleStatus;
use Database\Factories\PixelBundleVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One published pixel bundle — `GOAIEZ_PIXEL_MASTER_BUILD.md` §10's Delivery
 * paragraph. See the creating migration for the platform-scope argument and
 * `PixelDelivery` for the only reader and writer.
 *
 * @property int $id
 * @property string $sha
 * @property string $build_token
 * @property string $contents
 * @property int $byte_size
 * @property int $gzip_byte_size
 * @property PixelBundleStatus $status
 * @property Carbon $published_at
 * @property ?Carbon $canary_started_at
 * @property ?Carbon $promoted_at
 * @property ?Carbon $halted_at
 * @property ?string $halt_reason
 * @property string $actor
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PixelBundleVersion extends Model
{
    /** @use HasFactory<PixelBundleVersionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PixelBundleStatus::class,
            'byte_size' => 'integer',
            'gzip_byte_size' => 'integer',
            'published_at' => 'datetime',
            'canary_started_at' => 'datetime',
            'promoted_at' => 'datetime',
            'halted_at' => 'datetime',
        ];
    }

    /**
     * Never deleted, `PlanOffer`'s reasoning: a superseded or halted row is the
     * only record of what `/p.js` served, and to whom, while it was live —
     * `pixel_delivery_samples` rows name it by `build_token` for as long as it
     * exists to be joined against.
     */
    protected static function booted(): void
    {
        self::deleting(function (): never {
            throw new LogicException(
                'A pixel bundle version is retired or rolled back, never deleted — it is the '
                .'only record of what /p.js served while it was live.'
            );
        });
    }
}
