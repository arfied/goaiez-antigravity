<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FetchOutcome;
use App\Enums\FetchTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One fetch the gateway made, or refused to make (`40` §6.3).
 *
 * NOT tenant-owned — these are our outbound requests under our policy, and row
 * 2's are made before any tenant exists. On the TenancyTest allowlist
 * alongside FetchSource.
 *
 * @property-read int $id
 * @property string $source_key
 * @property string $url_hash
 * @property FetchTier $tier
 * @property FetchOutcome $outcome
 * @property ?int $http_status
 * @property ?Carbon $cooldown_until
 * @property ?int $cost_micros
 * @property Carbon $created_at
 */
final class FetchAttempt extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Attempts against a source still inside a cool-down window.
     *
     * @param  Builder<FetchAttempt>  $query
     */
    public function scopeCoolingDown(Builder $query): void
    {
        $query->whereNotNull('cooldown_until')
            ->where('cooldown_until', '>', Carbon::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => FetchTier::class,
            'outcome' => FetchOutcome::class,
            'http_status' => 'integer',
            'cooldown_until' => 'datetime',
            'cost_micros' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
