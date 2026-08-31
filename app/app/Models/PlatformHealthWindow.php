<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformHealthSignal;
use App\Services\Ops\PlatformHealth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One hour of one platform-health signal — P23's counter.
 *
 * ⚠️ **{@see PlatformHealth} IS THE WRITER AND THE READER**, and the increments
 * do not go through this model at all: Eloquent has no upsert-with-increment, so
 * the writes are raw `INSERT … ON CONFLICT DO UPDATE`, exactly as
 * `SendingHealth` does it and for the same reason — `$row->failures++;
 * $row->save()` across two workers in the same hour loses one of the two, and a
 * counter that reads low is a bell that does not ring.
 *
 * Untenanted and un-RLS'd. The creating migration carries the argument, and the
 * allowlist entry in `TenancyTest` repeats it where a reviewer of the tenant
 * boundary will find it.
 *
 * @property int $id
 * @property PlatformHealthSignal $signal
 * @property string $source
 * @property CarbonImmutable $window_start
 * @property int $total
 * @property int $failures
 * @property int|null $max_duration_ms
 * @property CarbonImmutable|null $last_at
 */
final class PlatformHealthWindow extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'signal',
        'source',
        'window_start',
        'total',
        'failures',
        'max_duration_ms',
        'last_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signal' => PlatformHealthSignal::class,
            'window_start' => 'immutable_datetime',
            'total' => 'integer',
            'failures' => 'integer',
            'max_duration_ms' => 'integer',
            'last_at' => 'immutable_datetime',
        ];
    }
}
